<?php

class Module
{
	protected $requires_session = true;
	protected $dependencies = array();
	
	protected $api;
	
	function __construct(API $api)
	{
		$this->api = $api;
		
		// Check if we need a session
		
		if($this->requires_session && !$this->api->auth->_get_session_id())
		{
			$this->api->error('Invalid session or session has expired. Please authenticate.');
		}
		
		// Load any dependencies
		
		if(count($this->dependencies) > 0)
		{
			foreach($this->dependencies as $dependency)
			{
				$this->api->load_module($dependency);
			}
		}
	}
}