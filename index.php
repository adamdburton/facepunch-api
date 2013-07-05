<?php

include('../burt0n.net/fpbots/functions/pushover.php');

function pushover_errors($code, $message, $file, $line)
{
	switch($code)
	{
		case 1:     $e_type = 'E_ERROR'; break;
		case 2:     $e_type = 'E_WARNING'; break;
		case 4:     $e_type = 'E_PARSE'; break;
		case 8:     $e_type = 'E_NOTICE'; break;
		case 16:    $e_type = 'E_CORE_ERROR'; break;
		case 32:    $e_type = 'E_CORE_WARNING'; break;
		case 64:    $e_type = 'E_COMPILE_ERROR'; break;
		case 128:   $e_type = 'E_COMPILE_WARNING'; break;
		case 256:   $e_type = 'E_USER_ERROR'; break;
		case 512:   $e_type = 'E_USER_WARNING'; break;
		case 1024:  $e_type = 'E_USER_NOTICE'; break;
		case 2048:  $e_type = 'E_STRICT'; break;
		case 4096:  $e_type = 'E_RECOVERABLE_ERROR'; break;
		case 8192:  $e_type = 'E_DEPRECATED'; break;
		case 16384: $e_type = 'E_USER_DEPRECATED'; break;
		case 30719: $e_type = 'E_ALL'; break;
		default:    $e_type = 'E_UNKNOWN'; break;
	}
	
	pushover(sprintf('%s: "%s" (%s line %s)', $e_type, $message, $file, $line));
}

define('DEBUG', true);

if(DEBUG)
{
	//ini_set('display_errors', 1);
	set_error_handler('pushover_errors', E_ALL);
}

$start_time = microtime(true);

include('classes/api.class.php');
include('classes/module.class.php');
include('classes/simple_html_dom.class.php');

// Send universal headers

header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); 
header('Cache-Control: no-store, no-cache, must-revalidate'); 
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

header('Content-type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Here we go!

$api = new API();

?>