<?php
 /*
 ** 邮件控制
 */
class MailController extends Yaf_Controller_Abstract {

	public function indexAction() {
        return $this->listAction();
	}
	
	// 发送邮件
	public function sendAction() {
		if ($this->_checkAdmin()) {
			// 获取参数
			$uid = $this->getRequest()->getPost('uid', false);
			$title = $this->getRequest()->getPost('title', false);
			$contents = $this->getRequest()->getPost('contents', false);

			if (!$uid || !$title || !$contents) {
				echo json_encode(array(
					"errno"=>-3002,
					"errmsg"=>"用户id、邮件标题、内容不能为空"
				));
				return false;
			}

			// 调用Model,执行邮件发送
			$model = new MailModel();

			if ($model->send( intval($uid), trim($title), trim($contents) )) {
					echo json_encode(array(
						"errno"=>0,
						"errmsg"=>"",
					));
			} else {
				echo json_encode(array(
					"errno"=>$model->errno,
					"errmsg"=>$model->errmsg
				));
			}
			return true;
		}
	}

	// 当前用户检测
	private function _isAdmin() {
		return true;
	}

	private function _checkAdmin() {
		if (!$this->_isAdmin()) {
			echo json_encode(array(
				"errno"=>-2000,
				"errmsg"=>"你不能执行该操作"
			));	
			return false;
		} else {
			$submit = $this->getRequest()->getQuery("submit", "0");
			if ($submit != "1") {
				echo json_encode(array(
					"errno"=>-2001,
					"errmsg"=>"提交渠道不正常"
				));
				return false;
			}
		}
		return true;
	}
	
}
