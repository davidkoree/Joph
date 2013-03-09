<?php
class ActionHello {
	public function __construct() {
		Joph_Controller::initAction(__CLASS__);
	}
	
	public function init() {
		echo "Hello, ";
	}
	
	public function execute() {
		echo "Joph!";
	}
}
