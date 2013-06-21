<?php

class General extends Module
{
	protected $description = 'Miscellaneous actions';
	protected $requires_authentication = true;
	
	/**
		Description: Gets any PM notifications
		Return: notifications | array | Array of notifications
	**/
	public function notifications()
	{
		$ret = $this->api->request('fp_rules.php');
		
		return array('notifications' => parse_pms_notifications($ret));
	}
}

function parse_pms_notifications($str)
{
	$html = is_string($str) ? str_get_html($str) : $str;
	
	$notifications = array();
	
	$notifications_div = $html->find('div.notifications', 0);
	
	if($notifications_div)
	{
		foreach($notifications_div->find('a') as $a)
		{
			$notifications[] = $a->title;
		}
	}
	
	return $notifications;
}