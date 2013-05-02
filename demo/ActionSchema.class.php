<?php
class ActionSchema extends Joph_Action {
	public function execute() {
		echo "IN " . __CLASS__ . " ";
		$item = Joph_Controller::getCurrentAction();
		if (false == $item) {
			echo "No action exists, done.<br><br>";
		} else {
			echo "[".$item['idx']."] ".$item['action']."<br><br>";
		}
		var_dump($this->_schema);
		var_dump(Joph_Controller::getSchemaCount());
	}

	public function onFinished() {
		switch ($this->_schema['name']) {
			case 'single':
				$this->forward('/action/single/name1');
				break;
			default:
				$this->forward('/action/schema/forward/name2/name3');
				break;
		}
	}
}
