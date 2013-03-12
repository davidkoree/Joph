<?php

class ActionHelloSome extends ActionHello {
	protected $_schema = array();
	public function __construct() {
		Joph_Controller::initAction(__CLASS__);
	}
	
	public function initSchema() {
		$schema = array();
		$tmp = Joph_Controller::getSchema();
		foreach ($tmp as $key => $value) {
			if (strpos($key, '_') > 0) {
				$key = preg_replace('/_\d+$/', '', $key);
				$schema[$key][] = $value;
			}
		}
		$this->_schema = $schema;
	}
	
	public function execute() {
		echo $this->_schema['name'][0] . "!<br><br>";
	}
}