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
		Parameter: id | required | integer | get | Folder ID
		Return: pms | array | Array of personal messages
	**/
	public function folder($id)
	{
		$data = array(
			'folderid' => $id
		);
		
		$ret = $this->api->request('private.php', $data);
		
		return array('pms' => parse_pms($ret));
	}
	
	/**
		Description: Send a Personal Message to a user
		Parameter: user_id | required | integer | get | Facepunch User ID
		Parameter: message | required | string | post | The message to submit
		Return: sent | boolean | Message sent or failed
	**/
	public function send($user_id)
	{
		// TODO: This
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