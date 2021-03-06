<?php

class ArtModel {
	public $errno = 0;
	public $errmsg = "";
	private $_db = null;

	public function __construct() {
		$this->_db = new PDO("mysql:host=127.0.0.1;dbname=tjhzs_api;","root","seagull");
		/*
		* 不设置下面这行，PDO会在拼SQL语句时，把int 0 转成 string 0
		*/	
		$this->_db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	}

	// add function start	
	public function add($title, $contents, $author, $cate, $artId = 0) {
		$isEdit = false;
		
		if ($artId != 0 && is_numeric($artId)) {
			/* edit */
			$query = $this->_db->prepare("select count(*) from `art` where `id`= ?");
			$query->execute(array($artId));
			$ret = $query->fetchAll();
			if (!$ret || count($ret) != 1) {
				$this->errno = -2004;
				$this->errmsg = "找不到文章";
				return false;
			}
			$isEdit = true;
		} else {
			/* add */
			/*
			*	检测文章类是否存在
			*	编辑文章就不存在这个情况	
			*/
			echo 'add';
			$query = $this->_db->prepare("select count(*) from `cate` where `id`= ? ");
			$query->execute(array($cate));
			$ret = $query->fetchAll();
			if (!$ret || $ret[0][0] == 0) {
				$this->errno = -2005;
				$this->errmsg = "找不到该文章的分类信息,请先创建 ".$cate." 分类";
				return false;
			}
		}
		
		/*
		* 插入或更新文章内容
		*/
		$data = array($title, $contents, $author, intval($cate));
		if ($isEdit) {
			$query = $this->_db->prepare("update `art` set `title`=?, `contents`=?, `author`=?, `cate`=? where `id`=?");
			$data[] = $artId;		
		} else {
			$query = $this->_db->prepare("insert into `art` (`title`, `contents`,`author`, `cate`) VALUES (?, ?, ?, ?)");
		}
		
		$ret = $query->execute($data);
		if (!$ret) {
			$this->errno = -2006;
			$this->errmsg = "操作失败，ErrInfo:".end($query->errorInfo());
			return false;
		}

		/* 返回文章最后的ID */
		if ($isEdit) {
			return intval($artId);
		} else {
			return intval($this->_db->lastInsertId());
		}
	}
	// add function end
	
	// del function start
	public function del($artId) {
		$query = $this->_db->prepare("delete from `art` where `id`=?");
		$ret = $query->execute(array( intval($artId) ) );
		if (!$ret) {
			$this->errno = -2002;
			$this->errmsg = "删除失败，ERRINFO=".end($query->errorInfo());
			return false;
		}
		return true;
				
	}
	// del function end

	// status function start
	public function status($artId, $status="offline") {
		$query = $this->_db->prepare("update `art` set `status`=? where `id`=?");
		$ret = $query->execute( array($status, intval($artId) ) );
		if (!$ret) {
			$this->errno = -2008;
			$this->errmsg = "文章状态更新失败 ,ErrorInfo：".end($query->errorInfo());
			return false;
		}
		return true;
	}
	// del function end
	
	// get function start
	public function get($artId) {
		$query = $this->_db->prepare("select `title`, `contents`, `author`, `cate`, `ctime`, `mtime`, `status` from `art` where `id`=?");
		$status = $query->execute( array( intval($artId) ) );
		$ret = $query->fetchAll();

		if (!$status || !$ret) {
			$this->errno = -2009;
			$this->errmsg = "获取文章信息失败，ErrorInfo:".end($query->errorInfo());
			return false;
		}

		$artInfo = $ret[0];
		/*
		* 获取分类信息
		*/
		$query = $this->_db->prepare("select `name` from `cate` where `id`=?");
		$query->execute( array($artInfo['cate']) );
		$ret = $query->fetchAll();
		if (!$ret) {
			$this->errno = -2010;
			$this->errmsg = "获取分类信息失败，ERRORINFO:".end($query->errorInfo());
			return false;		
		}
		$artInfo['cateName'] = $ret[0]['name'];

		$data = array(
			"id" => intval($artId),
			"title" => $artInfo["title"],
			"contents" => $artInfo["contents"],
			"author" => $artInfo["author"],
			"cateName" => $artInfo["cateName"],
			"cateId" => intval($artInfo["cate"]),
			"ctime" => $artInfo["ctime"],
			"mtime" => $artInfo["mtime"],
			"status" => $artInfo["status"]
		);

		return $data;
	}
	// get function end
	
	// list function start
	public function list ($pageNo = 0, $pageSize = 0, $cate = 0, $status = "online") {
		
		$start = $pageNo * $pageSize + ($pageNo == 0? 0 : 1);
		if($cate == 0) {
			$filter = array($status, intval($start), intval($pageSize));
			$query = $this->_db->prepare("select `id` , `title`, `contents`, `author`, `cate`, `ctime`, `mtime`, `status` from `art` where `status`=? order by `ctime` desc limit ?, ?");
		} else {
			$filter = array(intval($cate), $status, intval($start), intval($pageSize));
			$query = $this->_db->prepare("select `id`, `title`, `contents`, `author`, `cate`, `ctime`, `mtime`, `status` from `art` where `cate`=? and `status`=? order by `ctime` desc limit ?, ?");
		}

		$stat = $query->execute($filter);
		$ret = $query->fetchAll();
		
		if (!$ret) {
			$this->errno = -2001;
			$this->errmsg = "获取文章列表失败，ErrInfo: ".end($query->errorInfo());	
			return false;	
		}

		// 存放数据信息
		$data = array();
		$cateInfo = array();
		
		foreach ($ret as $item) {
			/*
			**  获取分类信息
			** 1 首先检查当前数据中的分类信息是否在cateInfo中存在
	 		** 如果存在，那么就不用再去数据库中读取
			*/
			if (isset( $cateInfo[ $item['cate'] ] ) ) {
				$cateName = $cateInfo[$item['cate']];
			} else {
				// 2 如果当前数据中分类信息不存在，那么到数据库中读取
				$query = $this->_db->prepare("select `name` from `cate` where `id`=?");
				$query->execute(array($item['cate']));
				$retCate = $query->fetchAll();

				if (!$retCate) {
					$this->errno = -2010;
					$this->errmsg = "获取分类信息失败，ErrInfo: ".end($query->errnorInfo());
					return false;
				}
				$cateName = $cateInfo[$item['cate']] = $retCate[0]['name'];
			}
			
			/*
			** 正文太长则剪切
			*/
			$contents = mb_strlen($item['contents']) > 30 ? mb_substr($item['contents'], 0 , 30).'......' : $item['contents'];
		
			// 拼接数据信息，存入$data*
			$data[] = array(
				'id' => intval($item['id']),
				'title' => $item['title'],
				'contents' => $contents,
				'author' => $item['author'],
				'cateName' => $cateName,
				'cateId' => intval($item['cate']),
				'ctime' => $item['ctime'],
				'mtime' => $item['mtime'],
				'status' => $item['status']
			);
		} // foreach end
		
		return $data;
	}

	// list function end
}

	
?>
