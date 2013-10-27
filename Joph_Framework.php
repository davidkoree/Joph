<?php
class Joph_Framework {
	const VERSION = '1.0';
	private static $joph = null;
	private function __construct() {}
	
	public static function getInstance() {
		if (!(self::$joph instanceof Joph)) {
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
		if (!is_string($pattern_str) || '' == trim($pattern_str)) {
			throw new Joph_Exception('pattern should be a string');
		}
		$arr = array(); // pattern, regexp, action
		$arr['pattern'] = $pattern_str;
		$regexp_str = strtr($pattern_str, $this->_schema_map);
		$regexp_str = $this->parseNamedSubPattern($regexp_str);
		$arr['regexp'] = $regexp_str;
		return $arr;
	}
	
	/**
	 * parse subpatterns that have same schema name in regular expression 
	 * @param string $regexp_str
	 * @throws Joph_Exception
	 */
	public function parseNamedSubPattern($regexp_str = '') {
		if (!is_string($regexp_str) || '' == trim($regexp_str)) {
			throw new Joph_Exception('regexp should be a string');
		}
		$str = preg_replace('#\(\?P(<[^>]+)#', '\0__[]', $regexp_str, -1, $count);
		if (0 == $count) {
			return $regexp_str;
		}
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
		if (!is_array($action_chain) || empty($action_chain)) {
			throw new Joph_Exception('action chain should be an array');
		}
		$arr = array(); // pattern, regexp, action
		$arr['action'] = array();
		foreach ($action_chain as $item) {
			if (0 === strpos('@', $item)) {
				$actions = $this->getActionsByTag($item);
				if (count($actions) > 0) {
					$arr['action'] = array_merge((array)$arr['action'], (array)$actions);
				}
			} else {
				$arr['action'][] = $item;
			}
		}
		foreach ($arr['action'] as $item) {
			if (!file_exists(ACTION_PATH . '/' . $item . '.class.php')) {
				throw new Joph_Exception('action ' . $item . ' does not exist');
			}
		}
		return $arr;
	}
	
	/**
	 * @param string $tag_name
	 * @return array
	 */
	public function getActionsByTag($tag_name) {
		return isset($this->_tag_map[$tag_name]) ? $this->_tag_map[$tag_name] : array();
	}
	
	/**
	 * @param string $uri
	 * @return array
	 */
	public function getActionsByURI($uri) {
		$uri = trim($uri);
		$uri = rtrim($uri, '/');
		if (empty($uri)) {
			throw new Joph_Exception('URI is empty, get no action');
		}
		foreach ($this->_bind_map as $arr) {
			$regexp = '#^' . rtrim($arr['regexp'], '/') . '$#';
			if (preg_match($regexp, $uri)) {
				return $arr['action'];
				break;
			}
		}
		return array();
	}
	
	/**
	 * @param string $uri
	 * @return array
	 */
	public function getSchemasByURI($uri) {
		$uri = trim($uri);
		$uri = rtrim($uri, '/');
		if (empty($uri)) {
			throw new Joph_Exception('URI is empty, get no schema');
		}
		foreach ($this->_bind_map as $arr) {
			$regexp = '#^' . rtrim($arr['regexp'], '/') . '$#';
			if (preg_match($regexp, $uri, $schema)) {
				$schema_arr = array();
				$schema_count = array();
				foreach ($schema as $key => $value) {
					// ignore numeric keys
					if (is_string($key)) {
						$len = strpos($key, '_');
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
				return $schema_arr;
				break;
			}
		}
		return array();
	}
	
	/**
	 * tag a series of actions for common use
	 * @param string $tag_name
	 * @param array $action_arr
	 * @throws Joph_Exception
	 */
	public function tag($tag_name = '', $action_arr = array()) {
		if (!is_string($tag_name) || '' == trim($tag_name)) {
			throw new Joph_Exception('tag name should be a string');
		}
		if (!is_array($action_arr) || empty($action_arr)) {
			throw new Joph_Exception('action queue should be an array');
		}
		//TODO validate action arr, only 'ActionName' is allowed
		$this->_tag_map[$tag_name] = $action_arr;
	}
	
	/**
	 * @param string $pattern_str
	 * @param array $action_chain
	 * @throws Joph_Exception
	 */
	public function bind($pattern_str = '', $action_chain = array()) {
		if (!is_string($pattern_str) || '' == trim($pattern_str)) {
			throw new Joph_Exception('pattern should be a string');
		}
		if (!is_array($action_chain) || empty($action_chain)) {
			throw new Joph_Exception('action chain should be an array');
		}
		if ('/' !== substr($pattern_str, 0, 1)) {
			throw new Joph_Exception('pattern should starts with slash');
		}
		$uri = $this->parseUriPattern($pattern_str);
		$act = $this->parseActionChain($action_chain);
		$map = array_merge((array)$uri, (array)$act);
		$this->_bind_map[] = $map;
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
	
	public static function resetActionChain($method_name, $current, $macro = '') {
		$sub_actions = self::convertActionChain($macro);
		if ('/' === substr($macro, 0, 1)) {
			$joph = Joph_Framework::getInstance();
			$schema = $joph->getSchemasByURI($macro);
			$action_arr = array();
			foreach ($sub_actions as $sub_action) {
				if (!class_exists($sub_action)) {
					throw new Joph_Exception('action class ' . $sub_action . ' not exist');
				}
				if (is_subclass_of($sub_action, 'Joph_Action_Internal')) {
					$action = new $sub_action();
					$action->setSchema($schema);
					$action_arr[] = $action;
				} elseif (is_subclass_of($sub_action, 'Joph_Action')) {
					$action_arr[] = $sub_action;
					//TODO reset top level schema values?
				} else {
					throw new Joph_Exception('action class ' . $sub_action . ' is not property');
				}
			}
			$sub_actions = $action_arr;
		}
		
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
					if (empty($sub_actions)) {
						throw new Joph_Exception('fail to convert action chain');
					}
					array_splice($actions, $idx + 1, 0, $sub_actions);
					break;
				case 'forward':
					if (empty($sub_actions)) {
						throw new Joph_Exception('fail to convert action chain');
					}
					array_splice($actions, $idx + 1, count($actions), $sub_actions);
					break;
				default:
					throw new Joph_Exception('unknown action method ' . $method_name);
					break;
			}
			self::setActions($actions);
			self::setActionIndex(++$idx);
			return true;
		} else {
			throw new Joph_Exception('can not ' . $method_name . ' action ' . $sub_actions);
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
				$action = array($entity);
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
		if (is_string($sub_action) && is_subclass_of($sub_action, 'Joph_Action')) {
			$action = new $sub_action(); // initialize action
			$action->execute();
			Joph_Controller::setActionIndex(++$idx);
			$action->onFinished();
		} elseif ($sub_action instanceof Joph_Action_Internal) {
			$sub_action->execute();
			Joph_Controller::setActionIndex(++$idx);
		} else {
			throw new Joph_Exception('incorrect action class ' . $sub_action);
		}
		return true;
	}
	
	/**
	 * set user input values as POST/GET, and URI subpattern values as Schema
	 * @param array $schema
	 * @throws Joph_Exception
	 */
	public static function parseClientRequest($schema = array()) {
		$schema_arr = array();
		$schema_count = array();
		foreach ($schema as $key => $value) {
			// ignore numeric keys
			if (is_string($key)) {
				$len = strpos($key, '_');
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
		self::$_field_value['schema'] = $schema_arr;
		
		self::$_field_value['post'] = $_POST;
		self::$_field_value['get'] = $_GET;
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
	protected static function getFieldValue($method_name = '', $key = '') {
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
		$this->_schema = Joph_Controller::getSchema();
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
	
	public function forward($macro) {
		$sub_action = get_called_class();
		Joph_Controller::resetActionChain(__METHOD__, $sub_action, $macro);
	}
	
	public function sweep($macro) {
		$sub_action = get_called_class();
		Joph_Controller::resetActionChain(__METHOD__, $sub_action, $macro);
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

class Joph_Config {
	private $__config = array();
	private $__reserved = array();

	public function setAutoload($pathes = array()) {
		foreach ($pathes as $prefix => $path) {
			$prefix = ucfirst(strtolower($prefix));
			$this->__reserved[$prefix] = $path;
		}
	}

	public function registAutoload() {
		spl_autoload_register(array($this, 'autoload'));
	}

	private function autoload($class) {
		$regex = '/(' . implode('|', array_keys($this->__reserved)) . ')/';
		if (preg_match($regex, $class, $matches)) {
			$prefix = $matches[1];
			$path = $this->__reserved[$prefix];
			require_once $path . '/' . $class . '.class.php';
		}
	}

	public function set($key, $value) {
		$this->__config[$key] = $value;
	}

	public function __get($key) {
		if (array_key_exists($this->__config, $key)) {
			return $this->__config[$key];
		}
		return null;
	}

	public function __set($key, $name) {
		throw Exception('Setting properties directly on Joph_Config is not allowed');
	}
}
