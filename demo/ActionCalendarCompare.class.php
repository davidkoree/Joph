<?php
class ActionCalendarCompare extends Joph_Action {
	public function execute() {
		$str = sprintf("Compare %s and %s<br><br>", $this->_schema['date'][0], $this->_schema['date'][1]);
		echo $str;
	}
}
