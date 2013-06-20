<?php

define('DEBUG', true);

if(DEBUG)
{
	ini_set('display_errors', 1);
}

$start_time = microtime(true);

include('classes/api.class.php');
include('classes/module.class.php');
include('classes/simple_html_dom.class.php');

// Send universal headers

header('Content-type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Here we go!

$api = new API();

?>