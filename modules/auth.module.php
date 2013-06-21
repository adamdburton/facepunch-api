<?php

class Auth extends Module
{
	protected $description = 'Authenticate';
	protected $requires_authentication = false;
	
	private $session_id = false;
	
	/**
		Description: Authenticate with username and password
		Parameter: username | required | string | get | Facepunch username
		Parameter: password | required | string | get | Facepunch password MD5 hash
		Return: session_id | string | Session ID to use for subsequent requests
		Return: user_id | integer | The logged in user's ID
	**/
	public function login($username, $password)
	{
		$data = array(
			'securitytoken' => 'guest',
			'vb_login_username' => $username,
			'vb_login_md5password' => $password,
			'vb_login_md5password_utf' => $password,
			'vb_login_password_hint' => '',
			'cookieuser' => 1,
			'do' => 'login',
			's' => ''
		);
		
		$ret = $this->api->request('login.php?do=login', $data, 'POST', array(), true);
		
		if(preg_match_all('#Set-Cookie: (.*);#U', $ret, $matches) > 0)
		{
			$cookies = implode(';', $matches[1]);
			$user_id = quick_match('bb_userid=(\d+);', $ret);
			
			$data = array(
				'cookies' => $cookies,
				'user_id' => $user_id
			);
			
			$session_id = md5(uniqid());
			
			$this->api->cache($session_id, $data);
			
			return array('session_id' => $session_id, 'user_id' => $user_id);
		}
		else
		{
			$this->api->error('Invalid username or password.');
		}
	}
	
	/**
		Description: Authenticate with cookies
		Parameter: userid | required | string | get | Facepunch User ID
		Parameter: password | required | string | get | Facepunch password MD5 hash
		Parameter: sessionhash | required | string | get | Facepunch session hash
		Return: session_id | string | Session ID to use for subsequent requests
		Return: user_id | integer | The logged in user's ID
	**/
	public function cookie_login($userid, $password, $sessionhash)
	{
		$cookies = 'bb_user=' . $userid . ';bb_password=' . $password . ';bb_sessionhash=' . $sessionhash;
		
		$data = array(
			'cookies' => $cookies,
			'user_id' => $bb_userid
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
		if($this->api->cache($session_id))
		{
			$this->session_id = $session_id;
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