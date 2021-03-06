<?php

class User extends Module
{
	protected $description = 'Get user information and send profile messages';
	
	/**
		Description: Gets a User by ID
		Method: GET
		Parameter: id | required | integer | Facepunch User ID
		Return: user | object | User
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
		Description: Gets a User by Username
		Method: GET
		Parameter: username | required | string | Facepunch Username
		Return: user | object | User
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
		Description: Adds a visitor message to a user profile
		Method: POST
		Parameter: id | required | integer | Facepunch User ID
		Parameter: message | required | string | The message to submit
		Return: sent | boolean | Message sent or failed
	**/
	public function message($id, $message)
	{
		$data = array(
			'ajax' => 1,
			'wysiwyg' => 0,
			'fromquickcomment' => 1,
			'do' => 'message',
			'u' => $id,
			'loggedinuser' => $this->api->user_id,
			'parseurl' => 1,
			'message_backup' => $message,
			'message' => $message
		);
		
		$data = array_merge($data, $this->_get_view_security_data($id));
		
		$ret = $this->api->request('visitormessage.php?do=message', $data, 'POST');
		
		return true;
	}
	
	public function _get_view_security_data($id = null)
	{
		$data = array(
			'u' => $id
		);
		
		$ret = $this->api->request('member.php', $data);
		
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
	
	// Total posts
	
	$user['posts_count'] = (int) str_replace(',', '', trim($html->find('dl.stats dd', 2)->plaintext));
	
	return $user;
}