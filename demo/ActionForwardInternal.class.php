<?php
class ActionForwardInternal extends Joph_Action_Internal {
	public function execute() {
		echo "IN " . __CLASS__ . " ";
		$item = Joph_Controller::getCurrentAction();
		if (false == $item) {
			echo "No action exists, done.<br><br>";
		} else {
			echo "[".$item['idx']."] ".var_export($item['action'], true)."<br><br>";
		}
		var_dump($this->_schema);
		var_dump(Joph_Controller::getSchemaCount());
	}
}
