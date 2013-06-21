<?php

class Module
{
	protected $description = '';
	protected $requires_authentication = true;
	protected $dependencies = array();
	
	protected $api;
	
	function __construct(API $api, $called = false)
	{
		$this->api = $api;
		
		// Check if we need a session
		
		if($called && $this->requires_authentication && !$this->api->auth->_get_session_id())
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
	
	public function _description()
	{
		return $this->description;
	}
	
	public function _requires_authentication()
	{
		return $this->requires_authentication;
	}
	
	public function _dependencies()
	{
		return $this->dependencies;
	}
}