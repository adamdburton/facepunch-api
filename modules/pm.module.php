<?php

class PM extends Module
{
	protected $description = 'Get and send PMs';
	protected $dependencies = array('thread');
	
	/**
		Description: Gets a Personal Message by ID
		Parameter: id | required | integer | get | PM ID
		Return: pm | object | personal message
	**/
	public function id($id)
	{
		$data = array(
			'do' => 'showpm',
			'pmid' => $id,
		);
		
		$ret = $this->api->request('private.php', $data);
		
		return array('pm' => parse_pm($ret));
	}
	
	/**
		Description: Deletes a Personal Message by ID
		Parameter: id | required | integer | get | PM ID
		Return: deleted | boolean | Personal Message deleted or failed
	**/
	public function delete($id)
	{
		$data = array(
			'do' => 'managepm',
			'dowhat' => 'delete',
			'pmid' => $id,
			'folderid' => 0,
		);
		
		$data['pm[' . $id . ']'] = true;
		
		$data = array_merge($data, $this->_get_view_security_data($id));
		
		$ret = $this->api->request('private.php', $data, 'POST');
		
		return array('deleted' => true);
	}
	
	/**
		Description: Gets Personal Messages folders
		Return: folders | array | Array of folders
	**/
	public function folders()
	{
		$ret = $this->api->request('private.php');
		
		return array('folders' => parse_pm_folders($ret, false));
	}
	
	/**
		Description: Gets Personal Messages from a folder
		Parameter: id | optional | integer | get | Folder ID
		Return: pms | array | Array of personal messages
	**/
	public function folder($id = 0)
	{
		$data = array(
			'folderid' => $id
		);
		
		$ret = $this->api->request('private.php', $data);
		
		return array('pms' => parse_pms($ret));
	}
	
	/**
		Description: Gets Personal Message Icons
		Return: icons | array | Array of icons
	**/
	public function icons()
	{
		$ret = $this->api->request('private.php?do=newpm');
		
		return array('icons' => parse_pm_icons($ret));
	}
	
	/**
		Description: Send a Personal Message to a user
		Parameter: recipients | required | string | get | Semi-colon seperated list of Facepunch Username
		Parameter: subject | required | string | get | The message subject
		Parameter: icon | required | integer | get | The message icon ID
		Parameter: message | required | string | post | The message to send
		Return: sent | boolean | Message sent or failed
	**/
	public function send($recipients, $subject, $icon)
	{
		$message = $_POST['message'];
		
		$data = array(
			'do' => 'insertpm',
			'recipients' => $recipients,
			'title' => $subject,
			'message' => $message,
			'iconid' => $icon,
			'parseurl' => 1,
			'savecopy' => 1,
			'sbutton' => 'Submit%20Message'
		);
		
		$data = array_merge($data, $this->_get_new_security_data());
		
		$ret = $this->api->request('private.php?do=insertpm&pmid=', $data, 'POST');
		
		return array('sent' => true);
	}
	
	public function _get_view_security_data($id = null)
	{
		$data = array(
			'do' => 'showpm',
			'pmid' => $id,
		);
		
		$ret = $this->api->request('private.php', $data);
		
		$securitytoken = quick_match('SECURITYTOKEN = \"([0-9a-z\-]+)\";', $ret);
		
		if(!$securitytoken)
		{
			$this->api->error('Security data not found.');
		}
		
		return array(
			'securitytoken' => $securitytoken
		);
	}
	
	public function _get_new_security_data()
	{
		$data = array(
			'do' => 'newpm'
		);
		
		$ret = $this->api->request('private.php', $data);
		
		$securitytoken = quick_match('SECURITYTOKEN = \"([0-9a-z\-]+)\";', $ret);
		
		if(!$securitytoken)
		{
			$this->api->error('Security data not found.');
		}
		
		return array(
			'securitytoken' => $securitytoken
		);
	}
}

function parse_pm($str)
{
	$html = is_string($str) ? str_get_html($str) : $str;
	
	$pm = array();
	
	$pm['title'] = trim($html->find('div#lastelement span', 0)->plaintext);
	
	$posts = parse_posts($str);
	
	$pm['post'] = $posts[0];
	
	return $pm;
}

function parse_pm_folders($str)
{
	$html = is_string($str) ? str_get_html($str) : $str;
	
	$folders = array();
	
	foreach($html->find('div.block', 3)->find('a.usercp_folder-left') as $folder_link)
	{
		$folder = array();
		
		// ID
		
		$folder['folder_id'] = (int) quick_match('folderid=([0-9\-]+)', $folder_link->href);
		
		// Name
		
		$folder['name'] = $folder_link->plaintext;
		
		$folders[] = $folder;
	}
	
	return $folders;
}

function parse_pms($str)
{
	$html = is_string($str) ? str_get_html($str) : $str;
	
	$pms = array();
	
	foreach($html->find('li.pmbit') as $pm_row)
	{
		$pm = array();
		
		// ID
		
		$pm['pm_id'] = (int) quick_match('pm_(\d+)', $pm_row->id);
		
		// Title
		
		$pm['title'] = $pm_row->find('span a', 0)->plaintext;
		
		// Status
		
		$pm['status'] = quick_match('images\/statusicon\/pm\_(.*)\.png', $pm_row->find('img.threadicon', 0)->src);
		
		// Date
		
		$pm['date'] = trim($pm_row->find('label', 0)->plaintext);
		
		// Sender(s)
		
		$pm['senders'] = array();
		
		foreach($pm_row->find('ol.commalist li a') as $sender_link)
		{
			$sender = array();
			
			// ID
			
			$sender['user_id'] = (int) quick_match('u=(\d+)', $sender_link->href);
			
			// Username
			
			$sender['username'] = $sender_link->plaintext;
			
			// Avatar
			
			$sender['avatar_url'] = FACEPUNCH_URL . 'image.php?u=' . $sender['user_id'];
			
			$pm['senders'][] = $sender;
		}
		
		$pms[] = $pm;
	}
	
	return $pms;
}

function parse_pm_icons($str)
{
	$html = is_string($str) ? str_get_html($str) : $str;
	
	$icons = array();
	
	$posticons_img = $html->find('div.posticons', 0)->find('img');
	
  foreach($posticons_img as $icon_img)
  {
		$id = (int) quick_match('pi\_(\d+)', $icon_img->id);
		$image_url = FACEPUNCH_URL . ltrim($icon_img->src, '/');
		$title = $icon_img->alt;
		
		$icons[] = array(
			'id' => $id,
			'image_url' => $image_url,
			'title' => $title
		);
  }
	
	return $icons;
}