<?php

class User extends Module
{
	protected $description = 'Get user information and send profile messages';
	
	/**
		Gets a User by ID
		id | required | integer | get | Facepunch User ID
	**/
	public function id($id)
	{
		$data = array(
			'u' => $id
		);
		
		$ret = $this->api->request('member.php', $data);
		
		return parse_user($ret);
	}
	
	/**
		Gets a User by Username
		username | required | string | get | Facepunch Username
	**/
	public function username($username)
	{
		$data = array(
			'username' => $username
		);
		
		$ret = $this->api->request('member.php', $data);
		
		return parse_user($ret);
	}
	
	/**
		Adds a visitor message to a user profile
		id | required | integer | get | Facepunch User ID
		message | required | string | post | The message to submit
	**/
	public function message($id)
	{
		$message = $_POST['message'];
		
		if(!$message || strlen($message) < 1)
		{
			$this->api->error('Missing message body.');
		}
		
		//$ret = $this->api->request('member.php', $data, 'POST');
		
		return true;
	}
}

function parse_user($str)
{
	$html = is_string($str) ? str_get_html($str) : $str;
	
	$user = array();
	
	$avatar_a = $html->find('a.avatar', 0);
	$username_span = $html->find('span[id=userinfo] span', 0);
	
	// ID
	
	$user['user_id'] = (int) quick_match('u=(\d+)', $avatar_a->href);
	
	// Name
	
	$user['username'] = $username_span->plaintext;
	$user['username_html'] = $username_span->innertext;
	
	// Usertitle
	
	$user['usertitle'] = $html->find('span.usertitle', 0)->innertext;
	
	// Online
	
	$user['online'] = $username_span->hasClass('online');
	
	// Avatar
	
	$user['avatar_url'] = FACEPUNCH_URL . 'image.php?u=' . $user['user_id'];
	
	// Join date
	
	$user['join_date'] = trim($html->find('dl.stats dd', 0)->plaintext);
	
	// Last activity
	
	$user['last_activity'] = trim($html->find('dl.stats dd', 1)->plaintext);
	
	// Last activity
	
	$user['num_posts'] = (int) str_replace(',', '', trim($html->find('dl.stats dd', 2)->plaintext));
	
	return $user;
}