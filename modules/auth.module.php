<?php

class Auth extends Module
{
	protected $description = 'Authenticate';
	protected $requires_authentication = false;
	
	private $session_id;
	public $user_id;
	
	/**
		Description: Authenticate with username and password
		Method: POST
		Parameter: username | required | string | Facepunch username
		Parameter: password | required | string | Facepunch password MD5 hash
		Return: session_id | string | Session ID to use for subsequent requests
		Return: user_id | integer | The logged in user's ID
	**/
	public function login($username, $password)
	{
		$ret = $this->api->request('');
		$s = quick_match('s\=(.*?)&', $ret);
		
		if(!$s)
		{
			$this->api->error('Couldn\'t create a valid session.');
		}
		
		$data = array(
			'securitytoken' => 'guest',
			'vb_login_username' => $username,
			'vb_login_password_hint' => 'Password',
			'vb_login_md5password' => $password,
			'vb_login_md5password_utf' => $password,
			'vb_login_password_hint' => '',
			'cookieuser' => 1,
			'do' => 'login',
			's' => $s
		);
		
		$ret = $this->api->request('login.php?do=login&s=' . $s, $data, 'POST', array(), true);
		
		if(preg_match('/Set-Cookie: bb_userid;/U', $ret))
		{
			preg_match_all('/Set-Cookie: (.*);/U', $ret, $matches);
		
			$userid = quick_match('bb_userid=(\d+);', $ret);
			$password = quick_match('bb_password=([a-z0-9]+);', $ret);
			$sessionhash = quick_match('bb_sessionhash=([a-z0-9]+);', $ret);
			
			$cookies = 'bb_userid=' . $userid . ';bb_password=' . $password . ';bb_sessionhash=' . $sessionhash;
			
			$data = array(
				'cookies' => $cookies,
				'user_id' => $userid
			);
			
			$session_id = md5(uniqid());
			
			$this->api->cache($session_id, $data);
			
			return array('session_id' => $session_id, 'user_id' => $userid);
		}
		else
		{
			$this->api->error('Invalid username or password.');
		}
	}
	
	/**
		Description: Authenticate with cookies
		Method: POST
		Parameter: userid | required | string | Facepunch User ID
		Parameter: password | required | string | Facepunch password MD5 hash
		Parameter: sessionhash | required | string | Facepunch session hash
		Return: session_id | string | Session ID to use for subsequent requests
		Return: user_id | integer | The logged in user's ID
	**/
	public function cookie_login($userid, $password, $sessionhash)
	{
		$cookies = 'bb_userid=' . $userid . ';bb_password=' . $password . ';bb_sessionhash=' . $sessionhash;
		
		$data = array(
			'cookies' => $cookies,
			'user_id' => $userid
		);
		
		$session_id = md5(uniqid());
		
		$this->api->cache($session_id, $data);
		
		$this->session_id = $session_id;
		
		if($this->_check_login())
		{
			return array('session_id' => $session_id, 'user_id' => $userid);
		}
		else
		{
			$this->session_id = null;
			$this->api->cache($session_id, null);
			$this->api->error('Invalid cookies.');
		}
	}
	
	public function _check_login()
	{
		$ret = $this->api->request('fp_rules.php');
		
		return strstr($ret, 'navbar_loginform') === false;
	}
	
	public function _set_session_id($session_id)
	{
		if($data = $this->api->cache($session_id))
		{
			$this->session_id = $session_id;
			$this->user_id = $data['user_id'];
			return true;
		}
		
		return false;
	}
	
	public function _get_session_id()
	{
		return $this->session_id ? $this->session_id : false;
	}
	
	public function _is_authorized()
	{
		return $this->get_session_id !== false;
	}
	
	public function _get_cookies()
	{
		if($this->session_id && $session = $this->api->cache($this->session_id))
		{
			return $session['cookies'];
		}
		
		return '';
	}
}