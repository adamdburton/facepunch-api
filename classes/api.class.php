<?php

date_default_timezone_set('Europe/London'); // Facepunch is on GMT time

define('FACEPUNCH_URL', 'http://www.facepunch.com/'); // It's changed before!
define('USERAGENT', 'FPAPI');

class API
{
	private $api_version = 'v1';
	
	private $module, $action, $parameters;
	private $cachestore;
	
	private $response_time = 0;
	
	function __construct()
	{
		// Set up the cache
		
		if(class_exists('Memcache'))
		{
			$this->cachestore = new Memcache();
			
			if(!$this->cachestore->connect('localhost', 11211))
			{
				$this->error('Memcache unavailable.');
			}
		}
		else
		{
			require_once('simplecache.class.php');
			
			$this->cachestore = new SimpleCache('./cache');
		}
		
		// Split the request
		
		$parts = explode('/', urldecode($_SERVER['QUERY_STRING']));
		
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
		
		// Check param counts
		
		$info = get_function_info($module, $action);
		
		$req_args = count($info['required_parameters']);
		
		// Push in POST data matching the required_fields
		
		foreach($info['required_parameters'] as $required_param)
		{
			if(isset($_POST[$required_param]))
			{
				$params[] = $_POST[$required_param];
			}
		}
		
		if(count($params) < $req_args)
		{
			$missing_param_names = array_map(function($v) { return $v['name']; }, $info['required_parameters']);
			
			$missing_params = array_slice($info['required_parameters'], count($params));
			$this->error('Missing ' . hr_implode($missing_param_names) . ' parameter' . plural(count($missing_param_names)) . '.');
		}
		
		$this->module = $module;
		$this->action = $action;
		$this->parameters = $params;
		
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
			$time_taken = microtime(true) - $start_time;
			$response_time = $this->response_time;
			$processing_time = $time_taken - $response_time;
			
			$json['stats'] = array(
				'time_taken' => number_format($time_taken, 10),
				'response_time' => number_format($response_time, 10),
				'processing_time' => number_format($processing_time, 10),
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
			$modules[] = basename($file, '.module.php');
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
		$headers['X-Forwarded-For'] = $_SERVER['REMOTE_ADDR'];
		
		$url = ltrim($url, '/'); // Strip off any slash from the beginning
		
		$t = microtime(true);
		$req = request(FACEPUNCH_URL . $url, $data, $method, $headers, $returnheaders, $this->auth->_get_cookies());
		$this->response_time += microtime(true) - $t;
		
		// Check if there was an error with the request
		
		if($req === false)
		{
			$this->error('Service unavailable: ' . $url);
		}
		
		// Check if we were shown an error message from Facepunch
		
		if(strstr($req, 'standard_error') && !strstr($req, '<p class="blockrow restore">'))
		{
			$html = str_get_html($req);
			$error = $html->find('div.standard_error', 0)->find('div.blockrow', 0)->plaintext;
			
			// Invalid forum/thread error
			$error = str_replace(' If you followed a valid link, please notify the administrator', '', $error);
			
			// Login error
			$error = str_replace(' Forgotten your password? Click here!', '', $error);
			
			$this->error(trim($error));
		}
		
		if(strstr($req, 'errorblock') && strstr($req, 'ul class="blockrow"') && !strstr($req, '<p class="blockrow restore">'))
		{
			$html = str_get_html($req);
			$error = $html->find('div.errorblock ul.blockrow', 0);
			
			$this->error(trim($error->plaintext));
		}
		
		return $req;
	}
	
	function cache($key, $value = null)
	{
		if($this->cachestore)
		{
			$val = $this->cachestore->get($key);
		
			if(!$val && isset($value) || $val && isset($value))
			{
				// Insert or update
				$this->cachestore->set($key, $value);
	
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
	$reflection = new ReflectionMethod($object, $function);
	
	$comment = trim(substr($reflection->getDocComment(), 4, -4));
	
	$description = quick_match('Description\: ([A-Za-z0-9., ]+)', $comment);
	$return = quick_match('Return\: (.+)', $comment);
	
	$parameters = array();
	
	$req_param_names = array();
	$opt_param_names = array();
	
	if(preg_match_all('/Parameter\: ([a-z_]+) \| (required|optional) \| (string|integer|array|object|boolean) \| (get|post) \| ([A-Za-z0-9., ]+)/', $comment, $matches, PREG_SET_ORDER))
	{
		foreach($matches as $match)
		{
			$parameters[] = array(
				'name' => $match[1],
				'required' => $match[2] == 'required' ? true : false,
				'type' => $match[3],
				'method' => $match[4],
				'description' => $match[5]
			);
			
			if($match[2] == 'required')
			{
				$req_param_names[] = $match[1];
			}
			else
			{
				$opt_param_names[] = $match[1];
			}
		}
	}
	
	$returns = array();
	
	if(preg_match_all('/Return\: ([a-z_]+) \| (string|integer|array|object|boolean) \| ([A-Za-z0-9., ]+)/', $comment, $matches, PREG_SET_ORDER))
	{
		foreach($matches as $match)
		{
			$returns[] = array(
				'name' => $match[1],
				'type' => $match[2],
				'description' => $match[3]
			);
		}
	}
	
	$return = array(
		'name' => $function,
		'description' => $description,
		'parameters' => $parameters,
		'required_parameters' => $req_param_names,
		'optional_parameters' => $opt_param_names,
		'returns' => $returns
	);
	
	return $return;
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

function plural($var)
{
	return $var == 1 ? '' : 's';
}