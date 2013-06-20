<?php

class Auth extends Module
{
	protected $requires_session = false;
	
	private $session_id = false;
	
	/**
		Authenticate with Facepunch
		username | required | string | get | Facepunch username
		password | required | string | get | Facepunch password MD5 hash
	**/
	public function login($username, $password)
	{
		$data = array(
			'securitytoken' => 'guest',
			'vb_login_username' => urlencode($username),
			'vb_login_md5password' => urlencode($password),
			'vb_login_md5password_utf' => urlencode($password),
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
		Authenticate with Facepunch
		bb_userid | required | string | get | Facepunch User ID
		bb_password | required | string | get | Facepunch password MD5 hash
		bb_sessionhash | required | string | get | Facepunch session hash
	**/
	public function cookie_login($bb_userid, $bb_password, $bb_sessionhash)
	{
		$cookies = 'bb_user=' . $bb_userid . ';bb_password=' . $bb_password . ';bb_sessionhash=' . $bb_sessionhash;
		
		$data = array(
			'cookies' => $cookies,
			'user_id' => $bb_userid
		);
		
		$session_id = md5(uniqid());
		
		$this->api->cache($session_id, $data);
		
		$this->session_id = $session_id;
		
		if($this->_check_login())
		{
			return array('session_id' => $session_id, 'user_id' => $bb_userid);
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