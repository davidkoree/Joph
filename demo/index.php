<?php

error_reporting(E_ALL);
define('ACTION_PATH', dirname(__FILE__));
set_include_path(get_include_path() . PATH_SEPARATOR . ACTION_PATH);
//TODO use spl_autoload_register instead
//TODO Joph_Config as main dynamic smart wrapper
function __autoload($class) {
	if (0 === strpos($class, 'Action')) {
		require_once ACTION_PATH . '/' . $class . '.class.php';
	}
}

require_once 'Joph_Framework.php';

try {
	$joph = Joph_Framework::getInstance();

	//TODO add support on user-defined schemas
	$joph->addSchema('<title>', '[a-zA-Z_-]+');
	$joph->addSchema(array(
		'<Mon>' => 'Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec',
		'<Day>' => 'Sun|Mon|Tue|Wed|Thu|Fri|Sat',
	));
	$joph->bind('/article/<title>/date/<year>/<Mon>-<Day>', array(
		'ActionArticle',
	));

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
	$joph->bind('/action/chain', array(
		'ActionChain1',
		'ActionChain2',
		'ActionChain3',
	));
	$joph->bind('/action/halt/head', array(
		'ActionHaltHere',
		'ActionChain1',
		'ActionChain2',
	));
	$joph->bind('/action/halt/middle', array(
		'ActionChain1',
		'ActionHaltHere',
		'ActionChain2',
	));
	$joph->bind('/action/halt/tail', array(
		'ActionChain1',
		'ActionChain2',
		'ActionHaltHere',
	));
	$joph->bind('/action/sweep/head', array(
		'ActionSweepHere',
		'ActionChain1',
		'ActionChain2',
	));
	$joph->bind('/action/sweep/middle', array(
		'ActionChain1',
		'ActionSweepHere',
		'ActionChain2',
	));
	$joph->bind('/action/sweep/tail', array(
		'ActionChain1',
		'ActionChain2',
		'ActionSweepHere',
	));
	$joph->bind('/action/forward/head', array(
		'ActionForwardHere',
		'ActionChain1',
		'ActionChain2',
	));
	$joph->bind('/action/forward/middle', array(
		'ActionChain1',
		'ActionForwardHere',
		'ActionChain2',
	));
	$joph->bind('/action/forward/tail', array(
		'ActionChain1',
		'ActionChain2',
		'ActionForwardHere',
	));
	
	$joph->tag('@tag1', array('ActionTatoo', 'ActionShow'));
	$joph->bind('/action/sweep/tatoo', array(
		'ActionNormSweep',
		'ActionNorm',
	));
	$joph->bind('/action/forward/tatoo', array(
		'ActionNormForward',
		'ActionNorm',
	));
	$joph->bind('/action/uri/tatoo', array(
		'ActionNormUri',
		'ActionNorm',
	));

	$joph->bind('/action/schema/<name>', array(
		'ActionSchema',
	));
	$joph->bind('/action/schema/forward/<name>/<name>', array(
	//$joph->bind('/action/schema/forward/name', array(
		'ActionForwardInternal',
	));
	$joph->bind('/action/single/<name>', array(
		'ActionSingleInternal',
	));

	$joph->shipout();
} catch (Exception $e) {
	$str = sprintf('[%s:%s]Exception: %s', 
	    pathinfo($e->getFile(), PATHINFO_FILENAME), $e->getLine(), $e->getMessage());
	echo $str;
}
