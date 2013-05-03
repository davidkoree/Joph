<?php
class Joph_Framework {
	const VERSION = '1.0';
	private static $joph = null;
	private function __construct() {}
	
	public static function getInstance() {
		if (!(self::$joph instanceof Joph))
		{
			self::$joph = new Joph();
		}
		return self::$joph;
	}
}

class Joph {
	protected $_tag_map = array();
	protected $_bind_map = array();
	protected $_schema_map = array(
		'<id>'          => '(?P<id>\d+)',
		'<name>'        => '(?P<name>\w+)',
		'<date>'        => '(?P<date>\d{8})',
		'<year>'        => '(?P<year>\d{4})',
		'<month>'       => '(?P<month>\d{2})',
		'<day>'         => '(?P<day>\d{2})',
		'<yyyymmdd>'    => '(?P<yyyymmdd>\d{8})',
	);
	private $_subpattern_idx = array();

	/**
	 * add user-defined schema and corresponding regexp
	 * @param mixed
	 * @throws Joph_Exception
	 */
	public function addSchema() {
		$argnum = func_num_args();
		switch ($argnum) {
			case 1:
				$arr = func_get_arg(0);
				if (!is_array($arr) || empty($arr)) {
					throw new Joph_Exception('fail to add schema, argument is empty');
				}
				break;
			case 2:
				$arr[func_get_arg(0)] = func_get_arg(1);
				break;
			default:
				throw new Joph_Exception('fail to add schema, argument is incorrect');
		}
		foreach ($arr as $schema => $regexp) {
			if (empty($schema) || empty($regexp)) {
				throw new Joph_Exception('fail to add schema, argument is empty string');
			}
			$this->_schema_map[$schema] = "(?P{$schema}{$regexp})";
		}
	}
	
	/**
	 * parse uri pattern into explicit regular expression
	 * @param string $pattern_str
	 * @throws Joph_Exception
	 */
	public function parseUriPattern($pattern_str = '') {
		# sample: '/bookstore/view/<id>'
		# sample: '/calendar/<date>'
		# sample: '/calendar/mark'
		# sample: '/calendar/<date>/compare/<date>'
		
		// check method parameters
		if (!is_string($pattern_str)) {
			throw new Joph_Exception('pattern should be a string');
		}
		
		// execute parse
		$pattern_str = trim($pattern_str);
		if (!empty($pattern_str)) {
			$arr = array(); // pattern, regexp, action
			$arr['pattern'] = $pattern_str;
			$regexp_str = strtr($pattern_str, $this->_schema_map);
			$regexp_str = $this->parseNamedSubPattern($regexp_str);
			$arr['regexp'] = $regexp_str;
			return $arr;
		} else {
			throw new Joph_Exception('fail to parse uri pattern');
		}
		
	}
	
	/**
	 * parse subpatterns that have same schema name in regular expression 
	 * @param string $regexp_str
	 * @throws Joph_Exception
	 */
	public function parseNamedSubPattern($regexp_str = '') {
		// check method parameters
		if (!is_string($regexp_str)) {
			throw new Joph_Exception('regexp should be a string');
		}
		
		//execute parse
		$str = preg_replace('#\(\?P(<[^>]+)#', '\0__[]', $regexp_str, -1, $count);
		
		if ($count == 0) return $regexp_str;
		
		$this->_subpattern_idx = range(0, $count - 1);
		$arr = explode('_[]', $str);
		$result = array_walk($arr, array($this, 'addSubPatternIndex'));
		
		if (false == $result) {
			throw new Joph_Exception('error occurs when parsing subpatterns');
		}
		
		$this->_subpattern_idx = array();
		$str = implode('', $arr);
		
		return $str;
	}
	
	/**
	 * add index for each subpattern, index starts with zero
	 * @param 
	 */
	private function addSubPatternIndex(&$item) {
		$item .= substr($item, -1) == '_' ? array_shift($this->_subpattern_idx) : '';
	}
	
	/**
	 * parse action chain
	 * @param array $action_chain
	 * @throws Joph_Exception
	 */
	public function parseActionChain($action_chain = array()) {
		// check method parameters
		if (!is_array($action_chain)) {
			throw new Joph_Exception('action chain should be an array');
		}
		
		// execute parse
		if (count($action_chain) > 0) {
			$arr = array(); // pattern, regexp, action
			$arr['action'] = array();
			foreach ($action_chain as $item) {
				if (0 === strpos('@', $item)) {
					// process a bundle of tagged actions
					$actions = $this->getActionsByTag($item);
					if (count($actions) > 0) {
						$arr['action'] = array_merge((array)$arr['action'], (array)$actions);
					}
				} else {
					// push normal action
					$arr['action'][] = $item;
				}
			}
			foreach ($arr['action'] as $item) {
				if (!file_exists(ACTION_PATH . '/' . $item . '.class.php')) {
					throw new Joph_Exception('action ' . $item . ' does not exist');
				}
			}
			return $arr;
		} else {
			throw new Joph_Exception('fail to parse action chain');
		}
	}
	
	/**
	 * 
	 * @param string $tag_name
	 * @return array
	 */
	public function getActionsByTag($tag_name) {
		if (isset($this->_tag_map[$tag_name])) {
			return $this->_tag_map[$tag_name];
		}
		return array();
	}
	
	/**
	 * 
	 * @param string $uri
	 * @return array
	 */
	public function getActionsByURI($uri) {
		$uri = trim($uri);
		$uri = rtrim($uri, '/');
		if (!empty($uri)) {
			foreach ($this->_bind_map as $arr) {
				$regexp = '#^' . rtrim($arr['regexp'], '/') . '$#';
				if (preg_match($regexp, $uri, $schema)) {
					$action_arr = Joph_Controller::parseInternalAction($arr['action'], $schema);
					return $action_arr;
					break;
				}
			}
			return array();
		} else {
			throw new Joph_Exception('URI is empty, get no action');
		}
	}
	
	/**
	 * tag a series of actions for common use
	 * @param string $tag_name
	 * @param array $action_arr
	 * @throws Joph_Exception
	 */
	public function tag($tag_name = '', $action_arr = array()) {
		// check method parameters
		if (!is_string($tag_name)) {
			throw new Joph_Exception('tag name should be a string');
		}
		if (!is_array($action_arr)) {
			throw new Joph_Exception('action queue should be an array');
		}
		//TODO validate action arr, only 'ActionName' is allowed
		
		// execute tag
		if (!empty($tag_name) && count($action_arr) > 0) {
			if (trim($tag_name) == '') {
				throw new Joph_Exception('tag name is empty');
			}
			$this->_tag_map[$tag_name] = $action_arr;
		} else {
			throw new Joph_Exception('fail to create tag for actions');
		}
	}
	
	/**
	 * 
	 * @param string $pattern_str
	 * @param array $action_chain
	 * @throws Joph_Exception
	 */
	public function bind($pattern_str = '', $action_chain = array()) {
		// check method paramters
		if (!is_string($pattern_str)) {
			throw new Joph_Exception('pattern should be a string');
		}
		if ('/' !== substr($pattern_str, 0, 1)) {
			throw new Joph_Exception('pattern should starts with slash');
		}
		if (!is_array($action_chain)) {
			throw new Joph_Exception('action chain should be an array');
		}
		
		// execute bind
		if (!empty($pattern_str) && count($action_chain) > 0) {
			$uri = $this->parseUriPattern($pattern_str);
			$act = $this->parseActionChain($action_chain);
			$map = array_merge((array)$uri, (array)$act);
			$this->_bind_map[] = $map;
		} else {
			throw new Joph_Exception('fail to bind pattern and actions');
		}
	}
	
	public function shipout() {
		$orbit = false;
		$query_pos = strpos($_SERVER['REQUEST_URI'], '?');
		$_SERVER['REQUEST_PATH'] = (false === $query_pos) ? 
			$_SERVER['REQUEST_URI'] : substr($_SERVER['REQUEST_URI'], 0, $query_pos);
		$_SERVER['REQUEST_PATH'] = rtrim($_SERVER['REQUEST_PATH'], '/');
		foreach ($this->_bind_map as $arr) {
			$regexp = '#^' . rtrim($arr['regexp'], '/') . '$#';
			if (preg_match($regexp, $_SERVER['REQUEST_PATH'], $schema)) {
				$orbit = true;
				Joph_Controller::parseClientRequest($schema);
				Joph_Controller::setActions($arr['action']);
				do {
					Joph_Controller::runAction();
				} while (Joph_Controller::hasUnexecutedAction());
				break;
			}
		}
		
		if (false === $orbit) {
			//TODO redirect to error page (404 not found)
		}
	}
}

class Joph_Controller {
	protected static $_field_value  = array();
	protected static $_schema_count = array();
	protected static $_action_stack = array();
	protected static $_action_count = 0;
	protected static $_action_idx   = 0;
	
	public static function getActions() {
		return self::$_action_stack;
	}
	
	public static function setActions($arr = array()) {
		self::$_action_stack = (array)$arr;
		self::$_action_count = count($arr);
	}
	
	public static function setActionIndex($idx) {
		self::$_action_idx = $idx;
	}
	
	public static function getCurrentAction() {
		$idx = self::$_action_idx;
		return isset(self::$_action_stack[$idx]) ?
		    array('action' => self::$_action_stack[$idx], 'idx' => $idx) : null;
	}
	
	public static function hasUnexecutedAction() {
		return (self::$_action_count > self::$_action_idx);
	}
	
	/**
	 * call each subaction's initial methods 
	 * @param string $object
	 */
	public static function initAction($object) {
		$methods = (array)get_class_methods($object);
		foreach ($methods as $method) {
			if (0 === strpos($method, 'init')) {
				call_user_func(array($object, $method));
			}
		}
	}
	
	public static function resetActionChain($method_name, $current, $next = '') {
		$next = self::convertActionChain($next);
		if (strpos($method_name, '::') !== false) {
			list(, $method_name) = explode('::', $method_name, 2);
		}
		$actions = self::getActions();
		$idx = array_search($current, $actions);
		if ($idx !== NULL && $idx !== false && intval($idx) >= 0) {
			switch ($method_name) {
				case 'halt':
					$actions = array_slice($actions, 0, $idx);
					break;
				case 'sweep':
					if (empty($next)) {
						throw new Joph_Exception('fail to convert action chain');
					}
					array_splice($actions, $idx + 1, 0, $next);
					break;
				case 'forward':
					if (empty($next)) {
						throw new Joph_Exception('fail to convert action chain');
					}
					array_splice($actions, $idx + 1, count($actions), $next);
					break;
				default:
					throw new Joph_Exception("unknown action method '$method_name'");
					break;
			}
			self::setActions($actions);
			self::setActionIndex(++$idx);
			return true;
		} else {
			throw new Joph_Exception("can not $method_name action '$next'");
		}
	}
	
	public static function convertActionChain($entity) {
		$prefix = substr($entity, 0, 1);
		switch ($prefix) {
			case '/': // URI
				$joph = Joph_Framework::getInstance();
				$action = $joph->getActionsByURI($entity);
				break;
			case '@': // tag
				$joph = Joph_Framework::getInstance();
				$action = $joph->getActionsByTag($entity);
				break;
			case 'A': // Action Name
				$action = $entity;
				break;
			default:
				$action = array();
				break;
		}
		return $action;
	}
	
	public static function runAction() {
		$item = Joph_Controller::getCurrentAction();
		if (false == $item) { //maybe NULL
			return false;
		}
		$sub_action = $item['action'];
		$idx = $item['idx'];
		if (is_string($sub_action) && !class_exists($sub_action)) {
			throw new Joph_Exception('action class ' . $sub_action . ' not exist');
		}
		if (is_string($sub_action)) {
			$action = new $sub_action(); // initialize action
			$action->execute();
			Joph_Controller::setActionIndex(++$idx);
			$action->onFinished();
		} elseif ($sub_action instanceof Joph_Action_Internal) {
			$sub_action->execute();
			Joph_Controller::setActionIndex(++$idx);
		}
		return true;
	}
	
	/**
	 * set user input values as POST/GET, and URI subpattern values as Schema
	 * @param array $schema
	 * @throws Joph_Exception
	 */
	public static function parseClientRequest($schema = array()) {
		// check method parameters
		if (0 === count($schema)) {
			throw new Joph_Exception('field name should be a string');
		}
			
		$arr = array();
		foreach ($schema as $key => $value) {
			// ignore numeric keys
			if (is_string($key)) {
				$arr[$key] = $value;
				$len = strpos($key, '_');
				if ($len > 0) {
					$keyname = substr($key, 0, $len);
					if (!isset(self::$_schema_count[$keyname])) {
						self::$_schema_count[$keyname] = 0;
					}
					self::$_schema_count[$keyname]++;
				}
			}
		}
		self::$_field_value['schema'] = $arr;
		
		self::$_field_value['post'] = $_POST;
		self::$_field_value['get'] = $_GET;
	}
	
	public static function parseInternalAction($actions = array(), $schema = array()) {
		// check method parameters
		if (0 === count($schema)) {
			throw new Joph_Exception('field name should be a string');
		}
		$schema_arr = array();
		$schema_count = array();
		foreach ($schema as $key => $value) {
			$len = strpos($key, '_');
			if ($len > 0) {
				$keyname = substr($key, 0, $len);
				$schema_arr[$keyname][] = $value;
				if (isset($schema_count[$keyname])) $schema_count[$keyname]++;
				else $schema_count[$keyname] = 1;
			}
		}
		foreach ($schema_arr as $keyname => $value) {
			if (1 === $schema_count[$keyname]) {
				unset($schema_arr[$keyname]);
				$schema_arr[$keyname] = $value[0];
			}
		}
		$action_arr = array();
		foreach ($actions as $sub_action) {
			if (!class_exists($sub_action)) {
				throw new Joph_Exception('action class ' . $sub_action . ' not exist');
			}
			$action = new $sub_action();
			if (!is_a($action, 'Joph_Action_Internal')) {
				throw new Joph_Exception('action class' . $action . ' is not property');
			}
			$action->setSchema($schema_arr);
			$action_arr[] = $action;
		}
		//TODO prettify structure of single-dimension array
		return $action_arr;
	}
	
	/**
	 * get real schema name(without numeric key) and corresponding count
	 */
	public static function getSchemaCount() {
		return self::$_schema_count;
	}
	
	/**
	 * fetch Schema values
	 * @param string $key
	 */
	public static function getSchema($key = '') {
		return self::getFieldValue(__METHOD__, (string)$key);
	}
	
	/**
	 * fetch POST values
	 * @param string $key
	 */
	public static function getPost($key = '') {
		return self::getFieldValue(__METHOD__, (string)$key);
	}
	
	/**
	 * fetch GET values
	 * @param string $key
	 */
	public static function getQuery($key = '') {
		return self::getFieldValue(__METHOD__, (string)$key);
	}
	
	/**
	 * 
	 * @param string $method_name
	 * @param string $key
	 * @return multitype:|NULL
	 */
	protected function getFieldValue($method_name = '', $key = '') {
		if (strpos($method_name, '::') !== false) {
			list(, $method_name) = explode('::', $method_name, 2);
		}
		$field_name = str_replace('get', '', $method_name);
		$field_name = strtolower($field_name);
		if ('query' == $field_name) $field_name = 'get';
		if (isset(self::$_field_value[$field_name])) {
			if ('' == trim($key)) {
				return self::$_field_value[$field_name];
			} elseif (!empty(self::$_field_value[$field_name][$key])) {
				return self::$_field_value[$field_name][$key];
			}
		}
		return null;
	}
}

class Joph_Action {

	//TODO DRAFT

	//->halt() complete without any further Actions (such as footer output)
	//self::$_switch_break = On/Off should also support?
	//->forward(mixed URI/action tag) go ahead to execute another special action(s)
	//->sweep(mixed URI/action tag) inspired by 'front crawl', in other words, call another action(s) and then continue

	//self::onFinished() more logical stuff is round here (e.g. halt, sweep or forward in case)

	// FLOW SAMPLES:
	// URI ( action1 > action2 > action3(break here) -> *action4 ... )
	// URI ( action1 > action2 > action3(forward to action3 '/some/other/uri/|@action_tag') -> *action4 ... )
	// URI ( action1 > action2 > action4(sweep to action4 '/some/other/uri/|@action_tag') -> action3 ... )
	// Note: action prefix with * should not been executed

	//Response::redirect (if it does really exist) is not included in this DRAFT, it's stuff of Response
	
	//->setVar($name, $value) and ->getVar($name) support? vars can be access within actions

	protected $_schema = array();
	protected $_get    = array();
	protected $_post   = array();
	
	public function __construct() {
		Joph_Controller::initAction($this);
	}
	
	public function __call($name, $arg) {
		if ('onFinished' == $name) {
			return;
		}
	}
	
	/**
	 * build schema data
	 */
	public function initSchema() {
		$schema = array();
		$tmp = Joph_Controller::getSchema();
		$schema_count = Joph_Controller::getSchemaCount();
		foreach ($tmp as $key => $value) {
			if (strpos($key, '_') > 0) {
				$key = preg_replace('/_\d+$/', '', $key);
				if (1 === $schema_count[$key]) {
					$schema[$key] = $value;
				} else {
					$schema[$key][] = $value;
				}
			}
		}
		$this->_schema = $schema;
	}
	
	public function initQuery() {
		$this->_get = Joph_Controller::getQuery();
	}
	
	public function initPost() {
		$this->_post = Joph_Controller::getPost();
	}
	
	public function halt() {
		$sub_action = get_called_class();
		Joph_Controller::resetActionChain(__METHOD__, $sub_action);
	}
	
	public function forward($action) {
		$sub_action = get_called_class();
		Joph_Controller::resetActionChain(__METHOD__, $sub_action, $action);
	}
	
	public function sweep($action) {
		$sub_action = get_called_class();
		Joph_Controller::resetActionChain(__METHOD__, $sub_action, $action);
	}
}

class Joph_Action_Internal {
	protected $_schema = array();
	protected $_get    = array();
	protected $_post   = array();
	
	public function __construct() {
		$this->_get  = $_GET;
		$this->_post = $_POST;
	}
	
	/**
	 * build schema data
	 */
	public function setSchema($schema) {
		$this->_schema = $schema;
	}
}

class Joph_Exception extends Exception {}
