<?php
class ActionHello extends Joph_Action {
	public function __construct() {
		Joph_Controller::initAction($this);
	}
	
	public function initHello() {
		echo "Hello, ";
	}
	
	public function execute() {
		echo "Joph!<br><br>";
	}
}
