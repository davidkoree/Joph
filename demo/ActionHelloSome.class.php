<?php

class ActionHelloSome extends ActionHello {
	protected $_schema = array();
	public function __construct() {
		Joph_Controller::initAction(__CLASS__);
	}
	
	public function initSchema() {
		$this->_schema = Joph_Controller::getSchema();
	}
	
	public function execute() {
		echo $this->_schema['name'] . "!<br><br>";
	}
}
