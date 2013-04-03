<?php

error_reporting(E_ALL);
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
	$joph->bind('/calendar/<date>/compare/<date>', array(
		'ActionCalendarCompare',
		'ActionFooter',
	));
	$joph->shipout();
} catch (Exception $e) {
	$str = sprintf('[%s:%s]Exception: %s', 
	    pathinfo($e->getFile(), PATHINFO_FILENAME), $e->getLine(), $e->getMessage());
	echo $str;
}
