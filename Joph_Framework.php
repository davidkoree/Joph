<?php

abstract class Joph_Framework
{
	protected static $_tag_map = array();
	protected static $_bind_map = array();
	protected static $_schema_map = array(
		'<id>'			=> '(?P<id>\d+)',
		'<name>'		=> '(?P<name>\w+)',
		'<date>'		=> '(?P<date>\d{8})',
		'<year>'		=> '(?P<year>\d{4})',
		'<month>'		=> '(?P<month>\d{2})',
		'<day>'			=> '(?P<day>\d{2})',
		'<yyyymmdd>'	=> '(?P<yyyymmdd>\d{8})',
	);
	
	/**
	 * parse uri pattern into explicit regular expression
	 * @param string $pattern_str
	 * @throws Joph_Exception
	 */
	public static function parseUriPattern($pattern_str = '')
	{
		# sample: '/bookstore/view/<id>'
		# sample: '/calendar/<date>'
		# sample: '/calendar/mark'
		
		// check method parameters
		if (!is_string($pattern_str))
		{
			throw new Joph_Exception('pattern should be a string');
		}
		
		// execute parse
		if (!empty($pattern_str))
		{
			$arr = array(
				'pattern' => rtrim($pattern_str, "\t\r\n "),
				'regexp'  => strtr($pattern_str, self::$_schema_map),
			);
			return $arr;
		}
		else
		{
			throw new Joph_Exception('fail to parse uri pattern');
		}
		
	}
	
	/**
	 * parse action chain
	 * @param array $action_chain
	 * @throws Joph_Exception
	 */
	public static function parseActionChain($action_chain = array())
	{
		// check method parameters
		if (!is_array($action_chain))
		{
			throw new Joph_Exception('action chain should be an array');
		}
		
		// execute parse
		if (count($action_chain) > 0)
		{
			$arr = array();
			$arr['action'] = array();
			foreach ($action_chain as $item)
			{
				if (strpos('_', $item) === 0)
				{
					// process a bundle of tagged actions
					$actions = self::getActionsByTag($item);
					if (count($actions) > 0)
					{
						$arr['action'] = array_merge((array)$arr['action'], (array)$actions);
					}
				}
				else
				{
					// push normal action
					$arr['action'][] = $item;
				}
			}
			foreach ($arr['action'] as $item)
			{
				if (!file_exists(ACTION_PATH . '/' . $item . '.class.php'))
				{
					throw new Joph_Exception('action ' . $item . ' does not exist');
				}
			}
			return $arr;
		}
		else
		{
			throw new Joph_Exception('fail to parse action chain');
		}
	}
	
	/**
	 * 
	 * @param string $tag_name
	 * @return array
	 */
	protected static function getActionsByTag($tag_name)
	{
		if (isset(self::$_tag_map[$tag_name]))
		{
			return self::$_tag_map[$tag_name];
		}
		return array();
	}
}

abstract class Joph extends Joph_Framework
{
	/**
	 * tag a series of actions for common use
	 * @param string $tag_name
	 * @param array $action_arr
	 * @throws Joph_Exception
	 */
	public static function tag($tag_name = '', $action_arr = array())
	{
		// check method parameters
		if (!is_string($tag_name))
		{
			throw new Joph_Exception('tag name should be a string');
		}
		if (!is_array($action_arr))
		{
			throw new Joph_Exception('action queue should be an array');
		}
		
		// execute tag
		if (!empty($tag_name) && count($action_arr) > 0)
		{
			if (trim($tag_name) == '')
			{
				throw new Joph_Exception('tag name is empty');
			}
			self::$_tag_map[$tag_name] = $action_arr;
		}
		else
		{
			throw new Joph_Exception('fail to create tag for actions');
		}
	}
	
	/**
	 * 
	 * @param string $pattern_str
	 * @param array $action_chain
	 * @throws Joph_Exception
	 */
	public static function bind($pattern_str = '', $action_chain = array())
	{
		// check method paramters
		if (!is_string($pattern_str))
		{
			throw new Joph_Exception('pattern should be a string');
		}
		if (!is_array($action_chain))
		{
			throw new Joph_Exception('action chain should be an array');
		}
		
		// execute bind
		if (!empty($pattern_str) && count($action_chain) > 0)
		{
			$arr = self::parseUriPattern($pattern_str);
			$arr = array_merge((array)$arr, (array)self::parseActionChain($action_chain));
			self::$_bind_map[] = $arr;
		}
		else
		{
			throw new Joph_Exception('fail to bind pattern and actions');
		}
	}
	
	public static function shipout()
	{
		$orbit = false;
		foreach (self::$_bind_map as $arr)
		{
			$regexp = '#^' . $arr['regexp'] . '$#';
			if (preg_match($regexp, $_SERVER['REQUEST_URI'], $schema))
			{
				$orbit = true;
				Joph_Action::parseClientRequest($schema);
				//TODO processing in every single action
				foreach ($arr['action'] as $item)
				{
					if (!class_exists($item))
					{
						throw new Joph_Exception('action class ' . $item . ' not exist');
					}
					$action = new $item();
					$result = $action->execute();
					if (false === $result)
					{
						throw new Joph_Exception('fail to execute action ' . $item);
					}
				}
				
				break;
			}
		}
		
		if (false === $orbit)
		{
			//TODO redirect to error page (404 not found)
		}
	}
}

abstract class Joph_Action
{
	protected static $_field_value = array();
	
	/**
	 * for every subaction, execute its initial methods 
	 * @param Joph_Action $obj
	 */
	public function execute(Joph_Action $obj)
	{
		// sample:
		// ActionSub extends Joph_Action
		// execute() {
		//     parent::execute(__CLASS__)
		//     parent::execute($this)
		// }
		$method_arr = (array)get_class_methods($obj);
		foreach ($method_arr as $method_name)
		{
			if (strpos($method_name, 'init') === 0)
			{
				$obj->$method_name();
			}
		}
	}
	
	/**
	 * set user input values as POST/GET, and URI subpattern values as Schema
	 * @param array $schema
	 * @throws Joph_Exception
	 */
	public static function parseClientRequest($schema = array())
	{
		// check method parameters
		if (count($schema) === 0) {
			throw new Joph_Exception('field name should be a string');
		}
			
		$arr = array();
		foreach ($schema as $key => $value)
		{
			// ignore values that indexed with numeric
			if (is_string($key))
			{
				$arr[$key] = $value;
			}
		}
		self::$_field_value['schema'] = $arr;
		self::$_field_value['post'] = $_POST;
		self::$_field_value['get'] = $_GET;
	}
	
	/**
	 * fetch Schema values
	 * @param string $key
	 */
	public function getSchema($key = '')
	{
		return self::getFieldValue(__METHOD__, (string)$key);
	}
	
	/**
	 * fetch POST values
	 * @param string $key
	 */
	public function getPost($key = '')
	{
		return self::getFieldValue(__METHOD__, (string)$key);
	}
	
	/**
	 * fetch GET values
	 * @param string $key
	 */
	public function getQuery($key = '')
	{
		return self::getFieldValue(__METHOD__, (string)$key);
	}
	
	/**
	 * 
	 * @param string $method_name
	 * @param string $key
	 * @return multitype:|NULL
	 */
	protected function getFieldValue($method_name = '', $key = '')
	{
		$field_name = str_replace('get', '', $method_name, 1);
		$field_name = strtolower($field_name);
		if (isset(self::$_field_value[$field_name]))
		{
			if ('' == trim($key))
			{
				return self::$_field_value[$field_name];
			}
			elseif (isset(self::$_field_value[$field_name][$key]))
			{
				return self::$_field_value[$field_name][$key];
			}
		}
		return null;
	}
}
