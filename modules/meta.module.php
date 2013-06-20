<?php

class Meta extends Module
{
	protected $description = 'Get API information';
	protected $requires_session = false;
	
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
				'requires_session' => $module->_requires_session(),
				//'dependencies' => $module->_dependencies()
			);
			
			$module_meta['actions'] = array();
			
			foreach(get_class_methods($module) as $method)
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