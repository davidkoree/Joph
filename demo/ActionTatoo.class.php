<?php
class ActionTatoo extends Joph_Action {
	public function execute() {
		echo "IN " . __CLASS__ . " ";
		$item = Joph_Controller::getCurrentAction();
		if (false == $item) {
			echo "No action exists, done.<br><br>";
		} else {
			echo "[".$item['idx']."] ".$item['action']."<br><br>";
		}
	}
}
