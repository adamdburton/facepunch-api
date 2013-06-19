<?php

date_default_timezone_set('Europe/London'); // Facepunch is on GMT time

define('FACEPUNCH_URL', 'http://www.facepunch.com/'); // It's changed before!
define('USERAGENT', 'FPAPI');
define('DEBUG', true);

class API
{
	private $api_version = 'v1';
	
	private $module, $action, $parameters;
	
	private $cache;
	
	function __construct()
	{
		// Set up the cache
		
		if(class_exists('Memcache'))
		{
			$this->cache = new Memcache();
		
			if(!$this->cache->connect('localhost', 11211))
			{
				$this->error('Memcache unavailable.');
			}
		}
		else
		{
			require_once('simplecache.class.php');
			
			$this->cache = new SimpleCache('./cache');
		}
		
		// Split the request
		
		$parts = explode('/', $_SERVER['QUERY_STRING']);
		
		// Shift off the first value if it's blank or index.php
		
		if(isset($parts[0]) && ($parts[0] == '' || $parts[0] == 'index.php'))
		{
			array_shift($parts);
		}
		
		// Pop off the last value if it's blank
		
		if(count($parts) > 0 && $parts[count($parts) - 1] == '')
		{
			array_pop($parts);
		}
		
		// Load the auth module
		
		$this->load_module('auth');
		
		// Set (possible) session_id
		
		if(count($parts) > 1 && $this->auth->_set_session_id($parts[count($parts) - 1]))
		{
			array_pop($parts);
		}
		
		// Check version
		
		if(!isset($parts[0]))
		{
			$this->error('API version parameter missing.');
		}

		if($parts[0] != $this->api_version)
		{
			$this->error('Incorrect API version.');
		}
		
		// Check module
		
		if(!isset($parts[1]) || $parts[1] == '')
		{
			$this->error('Module parameter missing.');
		}
		
		if(!$this->module_exists($parts[1]))
		{
			$this->error('Unknown module: ' . $parts[1] . '.');
		}
		
		// Module ok, let's load it
		
		$this->load_module($parts[1], true);
		
		// Check action
		
		if(isset($parts[2]) && is_numeric($parts[2]))
		{
			// Missing action but parts[2] is numeric (an id), so push it in
			array_insert($parts, 2, 'id');
		}
		
		if(!isset($parts[2]) || $parts[2] == '')
		{
			$this->error('Action parameter missing.');
		}
		
		if(!$this->module_action_exists($parts[1], $parts[2]))
		{
			$this->error('Unknown action: ' . $parts[2] . '.');
		}
		
		// Move the module and action off the start of the array
		
		$version = array_shift($parts);
		$module = array_shift($parts);
		$action = array_shift($parts);
		$params = $parts;
		
		$this->module = $module;
		$this->action = $action;
		$this->parameters = $params;
		
		// Check param counts
		
		$info = get_function_info($module, $action);
		
		$req_args = count($info['required_arguments']);
		
		if(count($params) < $req_args)
		{
			$missing_arg_names = array_map(function($v) { return $v['name']; }, $info['required_arguments']);
			
			$missing_params = array_slice($info['required_arguments'], count($params));
			$this->error('Missing ' . hr_implode($missing_arg_names) . ' parameter' . (count($missing_arg_names) == 1 ? '' : 's') . '.');
		}
		
		// Let's go!
		
		$data = call_user_func_array(array($this->$module, $this->action), $this->parameters);
		
		// We only get here if we dont error, so we should have some data
		
		$this->success($data);
	}
	
	function success($data = array())
	{
		$json = array(
			'status' => 'success',
			'success' => $data
		);
		
		$json = $this->debug($json);
		
		die(json_encode($json));
	}

	function error($string = 'Internal API error.')
	{
		// If we're in debug mode, throw an exception to get the stack trace
		if(DEBUG)
		{
			try
			{
				throw new Exception($string);
			}
			catch(Exception $e)
			{
				$error = $e->getMessage();
				$stack_trace = $e->getTraceAsString();
				
				$json = array(
					'status' => 'error',
					'error' => $error,
					'stack_trace' => $stack_trace
				);
				
				$json = $this->debug($json);
				
				die(json_encode($json));
			}
		}
		else
		{
			$json = array(
				'status' => 'error',
				'error' => $string,
			);
			
			$json = $this->debug($json);
			
			die(json_encode($json));
		}
	}
	
	function debug($json)
	{
		global $start_time;
		
		if(DEBUG)
		{
			$json['stats'] = array(
				'time_taken' => number_format(microtime(true) - $start_time, 10),
				'memory_usage' => bcdiv(memory_get_peak_usage(), 1048576, 2) . ' MB'
			);
		
			$json['system'] = array(
				'module' => $this->module,
				'action' => $this->action,
				'parameters' => $this->parameters
			);
		}
		
		return $json;
	}
	
	function get_modules()
	{
		$modules = array();
		
		foreach(glob('./modules/*.module.php') as $file)
		{
			$modules[] = basename($file, ".module.php");
		}
		
		return $modules;
	}
	
	function module_exists($module)
	{
		return file_exists('./modules/' . $module . '.module.php');
	}
	
	function module_action_exists($module, $action)
	{
		return substr($action, 0, 1) != '_' && is_callable(array($module, $action));
	}
	
	function load_module($module, $call = false)
	{
		if($this->module_exists($module))
		{
			if(!isset($this->$module))
			{
				include_once('modules/' . $module . '.module.php');
				$this->$module = new $module($this, $call);
			}
		}
		else
		{
			$this->error('Missing module: ' . $module);
		}
		
		return $this->$module;
	}
	
	function request($url, $data = array(), $method = 'GET', $headers = array(), $returnheaders = false)
	{
		$headers['User-Agent'] = USERAGENT;
		$headers['X-Forwarded-For'] = $_SERVER["REMOTE_ADDR"];
		
		$url = substr($url, 0, 1) == '/' ? substr($url, 1) : $url; // Strip off any slash from the beginning
		
		$req = request(FACEPUNCH_URL . $url, $data, $method, $headers, $returnheaders, $this->auth->_get_cookies());
		
		// Check if there was an error with the request
		
		if(!$req)
		{
			$this->error('Service unavailable.');
		}
		
		// Check if we were shown an error message from Facepunch
		
		if(strstr($req, 'standard_error') && !strstr($req, '<p class="blockrow restore">'))
		{
			$html = str_get_html($req);
			$error = $html->find('div.standard_error', 0);
			$error = str_replace(' If you followed a valid link, please notify the administrator', '', $error->find('div.blockrow', 0)->plaintext);
			
			$this->error($error);
		}
		
		return $req;
	}
	
	function cache($key, $value = null)
	{
		if($this->cache)
		{
			$val = $this->cache->get($key);
		
			if(!$val && isset($value) || $val && isset($value))
			{
				// Insert or update
				$this->cache->set($key, $value);
	
				return true;
			}
			elseif(!isset($value) && !$key)
			{
				// Not set
				return false;
			}

			return $val;
		}
		
		return false;
	}
}

function get_function_info($object, $function)
{
	$result = array();
	
	$reflection = new ReflectionMethod($object, $function);
	
	$comment = substr($reflection->getDocComment(), 4, -4);
	//$comment = str_replace("/** ", "", str_replace(" */", "", $reflection->getDocComment()));
	
	$opt_arg_names = array();
	$req_arg_names = array();
	
	foreach($reflection->getParameters() as $param)
	{
		$name = $param->getName();
		
		if($param->isOptional())
		{
			$default = (is_bool($param->getDefaultValue()) ? ($param->getDefaultValue() ? 'true' : 'false') : $param->getDefaultValue());
			$opt_arg_names[] = array('name' => $name, 'default' => $default);
		}
		else
		{
			$req_arg_names[] = array('name' => $name);
		}
	}
	
	$result['name'] = $function;
	$result['required_arguments'] = $req_arg_names;
	$result['optional_arguments'] = $opt_arg_names;
	$result['comment'] = $comment;
	
	return $result;
}

function request($url, $fields = array(), $method = 'GET', $headers = array(), $returnheaders = false, $cookies = '')
{
	if($method == 'GET' && count($fields) > 0)
	{
		$url .= '?' . http_build_query($fields);
	}
	
	$c = curl_init($url);
	
	curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
	
	if($returnheaders)
	{
		curl_setopt($c, CURLOPT_HEADER, true);
	}
	
	curl_setopt($c, CURLOPT_COOKIE, $cookies);
	
	if($method == 'POST')
	{
		$f = '';
		
		foreach($fields as $param => $value)
		{
			$f .= $param . '=' . urlencode($value) . '&';
		}
		
		curl_setopt($c, CURLOPT_POST, true);
		curl_setopt($c, CURLOPT_POSTFIELDS, $f);
		
		$headers['Content-Type'] = 'application/x-www-form-urlencoded';
	}
	
	if(count($headers) > 0)
	{
		curl_setopt($c, CURLOPT_HTTPHEADER, headers_to_curl_headers($headers));
	}
	
	$out = curl_exec($c);
	
	curl_close($c);
	
	return $out;
}

function headers_to_curl_headers($array)
{
	$out = array();
	
	foreach($array as $key => $value)
	{
		$out[] = "$key: $value";
	}
	
	return $out;
}

function quick_match($for, $in)
{
	preg_match('/' . $for . '/is', $in, $m);
	
	if(count($m) < 1)
	{
		return false;
	}
	
	return $m[1];
}

function array_insert(&$array, $index, $value)
{
	$len = count($array);
	
	for($i = $len; $i > $index; $i--)
	{
		$array[$i] = $array[$i - 1];
	}
	
	$array[$index] = $value;
}

function hr_implode($array)
{
	if(count($array) > 2)
	{
		$last = array_pop($array);
		
		return "'" . implode("', '", $array) . "' and '" . $last . "'";
	}
	elseif(count($array) < 2)
	{
		return array_shift($array);
	}
	else
	{
		return "'" . implode("' and '", $array) . "'";
	}
}