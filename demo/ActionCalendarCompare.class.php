<?php

class ActionCalendarCompare {
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
		$str = sprintf("Compare %s and %s<br><br>", $this->_schema['date'][0], $this->_schema['date'][1]);
		echo $str;
	}
}