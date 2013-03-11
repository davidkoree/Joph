<?php

define('ACTION_PATH', dirname(__FILE__));
set_include_path(get_include_path() . PATH_SEPARATOR . ACTION_PATH);
function __autoload($class) {
	if (0 === strpos($class, 'Action')) {
		require_once ACTION_PATH . '/' . $class . '.class.php';
	}
}

require_once 'Joph_Framework.php';

try {
	$joph = Joph_Framework::getInstance();
	$joph->bind('/hello', array(
		'ActionHello',
		'ActionFooter',
	));
	$joph->bind('/hello/<name>', array(
		'ActionHelloSome',
		'ActionFooter',
	));
	$joph->shipout();
} catch (Exception $e) {
	echo '['
	. pathinfo($e->getFile(), PATHINFO_FILENAME) . ':'
	. $e->getLine() . ']' 
	. ' Exception: ' . $e->getMessage();
}
