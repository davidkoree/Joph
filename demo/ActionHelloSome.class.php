<?php

class ActionHelloSome extends ActionHello {
	public function execute() {
		$name = sprintf("%s%s%s",
		    isset($this->_get['bold']) ? '<strong>' : '',
		    $this->_schema['name'],
		    isset($this->_get['bold']) ? '</strong>' : ''
		);
		echo "$name!<br><br>";
	}
}
