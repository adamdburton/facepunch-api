<?php

class Meta extends Module
{
	protected $description = 'Get API information';
	protected $requires_authentication = false;
	
	/**
		Description: Get API documentation
		Return: modules | array | Array of API modules and actions documentation
	**/
	function documentation()
	{
		$modules = array();
		
		foreach($this->api->get_modules() as $module_name)
		{
			$module = $this->api->load_module($module_name);
			
			$module_meta = array(
				'name' => $module_name,
				'description' => $module->_description(),
				'requires_authentication' => $module->_requires_authentication(),
			);
			
			$module_meta['actions'] = array();
			
			$methods = get_class_methods($module);
			sort($methods);
			
			foreach($methods as $method)
			{
				if(substr($method, 0, 1) != '_')
				{
					$module_meta['actions'][] = get_function_info($module, $method);
				}
			}
			
			$modules[] = $module_meta;
		}
		
		return array('modules' => $modules);
	}
}