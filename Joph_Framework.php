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
				if (strpos('@', $item) === 0) { // #,@,%,*
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
		foreach ($this->_bind_map as $arr) {
			$regexp = '#^' . $arr['regexp'] . '/?$#';
			if (preg_match($regexp, $_SERVER['REQUEST_PATH'], $schema)) {
				$orbit = true;
				Joph_Controller::parseClientRequest($schema);
				//TODO processing in every single action
				foreach ($arr['action'] as $item) {
					if (!class_exists($item)) {
						throw new Joph_Exception('action class ' . $item . ' not exist');
					}
					$action = new $item(); // initialize action
					$result = $action->execute();
					if (false === $result) {
						throw new Joph_Exception('fail to execute action ' . $item);
					}
				}
				
				break;
			}
		}
		
		if (false === $orbit) {
			//TODO redirect to error page (404 not found)
		}
	}
}

class Joph_Controller {
	protected static $_field_value = array();
	protected static $_schema_count = array();
	
	/**
	 * call each subaction's initial methods 
	 * @param string $class
	 */
	public function initAction($class) {		
		$methods = (array)get_class_methods($class);
		foreach ($methods as $method) {
			if (strpos($method, 'init') === 0) {
				call_user_func("$class::$method");
			}
		}
	}
	
	/**
	 * set user input values as POST/GET, and URI subpattern values as Schema
	 * @param array $schema
	 * @throws Joph_Exception
	 */
	public static function parseClientRequest($schema = array()) {
		// check method parameters
		if (count($schema) === 0) {
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
	
	/**
	 * get real schema name(without numeric key) and corresponding count
	 */
	public function getSchemaCount() {
		return self::$_schema_count;
	}
	
	/**
	 * fetch Schema values
	 * @param string $key
	 */
	public function getSchema($key = '') {
		return self::getFieldValue(__METHOD__, (string)$key);
	}
	
	/**
	 * fetch POST values
	 * @param string $key
	 */
	public function getPost($key = '') {
		return self::getFieldValue(__METHOD__, (string)$key);
	}
	
	/**
	 * fetch GET values
	 * @param string $key
	 */
	public function getQuery($key = '') {
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

	//TODO
	//DRAFT

	//->break() complete without any further Actions (such as footer output)
	//self::$_switch_break = On/Off should also support?
	//->forward(mixed URI/action chains) go ahead to execute another special action(s)
	//->sweep(mixed URI/action chains) inspired by 'front crawl', in other words, call another action(s) and then continue

	//self::onFinished() more logical stuff is round here (e.g. break or forward in case)

	// FLOW SAMPLES:
	// URI ( action1 > action2 > action3(break here) -> *action4 ... )
	// URI ( action1 > action2 > action3(forward to action3 '/some/other/uri/') -> *action4 ... )
	// URI ( action1 > action2 > action4(sweep to action4 '/some/other/uri/') -> action3 ... )
	// Note: action prefix with * should not been executed

	//Response::redirect (if it does really exist) is not included in this DRAFT, it's stuff of Response

}

class Joph_Exception extends Exception {}
