<?php

class Thread extends Module
{
	protected $description = 'Get and reply to threads';
	protected $dependencies = array('forum');
	
	/**
		Gets a Thread by ID
		id | required | integer | get | Thread ID
		page | optional | integer | get | Page number
	**/
	public function id($id, $page = 1)
	{
		$data = array(
			't' => $id,
			'page' => $page
		);
		
		$ret = $this->api->request('showthread.php', $data);
		
		return parse_thread($ret);
	}
	
	/**
		Reply to a Thread
		id | required | integer | get | Thread ID
		subscribe | optional | integer | get | Whether to subscribe to the thread
		message | required | string | post | The message to submit
	**/
	public function reply($id, $subscribe = false)
	{
		$message = isset($_POST['message']) ? $_POST['meesage'] : false;
		
		if(!$message || strlen($message) < 1)
		{
			$this->api->error('Missing message body.');
		}
		
		$data = array(
			'message' => $message,
			't' => $id,
			'do' => 'postreply',
			'subscribe' => $subscribe ? $subscribe : $this->is_subscribed($id),
			'parseurl' => 1
		);
		
		array_merge($data, $this->_get_security_data($id));
		
		$ret = $this->api->request('newreply.php?do=postreply&t=' . $id, $data, 'POST');
		
		return true;
	}
	
	/**
		Gets whether the user is subscribed to a thread
		id | required | integer | get | Thread ID
	**/
	public function is_subscribed($id)
	{
		$subscribed_threads = $this->subscribed('all');
		
		foreach($subscribed_threads as $thread)
		{
			if($thread['thread_id'] == $id)
			{
				return true;
			}
		}
		
		return false;
	}
	
	/**
		Gets read threads
	**/
	public function read()
	{
		$ret = $this->api->request('fp_read.php');
		
		return parse_threads($ret, false);
	}
	
	/**
		Gets popular threads
	**/
	public function popular()
	{
		$ret = $this->api->request('fp_popular.php');
		
		return parse_threads($ret, false);
	}
	
	/**
		Gets subscribed threads
		folder_id | optional | integer | get | Subscriptions Folder ID
	**/
	public function subscribed($folder_id = 0)
	{
		$data = array(
			'folderid' => $folder_id
		);
		
		$ret = $this->api->request('subscription.php');
		
		return parse_threads($ret, false);
	}
	
	public function _get_security_data($thread_id)
	{
		$data = array(
			't' => $id
		);
		
		$ret = $this->api->request('showthread.php', $data);
		
		$posthash = quick_match('\"posthash\":\"([0-9a-z])+\"', $ret);
		$poststarttime = quick_match('\"poststarttime\":\"([0-9])+\"', $ret);
		$securitytoken = quick_match('SECURITYTOKEN = \"([0-9a-z\-]+)\";', $ret);
		
		if(!$posthash || !$poststarttime || !$securitytoken)
		{
			$this->api->error('Security data not found.');
		}
		
		return array(
			'posthash' => $posthash,
			'poststarttime' => $poststarttime,
			'securitytoken' => $securitytoken
		);
	}
}

function parse_thread($str)
{
	$html = is_string($str) ? str_get_html($str) : $str;
	
	$thread = array();
	
	// Title
	
	$thread['title'] = trim($html->find('div#lastelement span', 0)->plaintext);
	
	// Forum
	
	$last_navbit = $html->find('span.navbit a', -1);
	
	$thread['forum'] = array(
		'forum_id' => (int) quick_match('f=(\d+)', $last_navbit->href),
		'name' => $last_navbit->plaintext
	);
	
	// Pages
	
	$pagination = $html->find('div.pagination_top', 0);
	
	if($pagination)
	{
		$thread['pages'] = array(
			'current' => (int) $pagination->find('span.selected a', 0)->plaintext,
			'total' => (int) quick_match('page=(\d+)', $pagination->find('span.first_last a', -1)->href)
		);
	}
	else
	{
		$thread['num_pages'] = array(
			'current' => 1,
			'total' => 1
		);
	}
	
	// Posts
	
	$thread['posts'] = parse_posts($html);
	
	return $thread;
}

function parse_posts($str)
{
	$html = is_string($str) ? str_get_html($str) : $str;
	
	$posts = array();
	
	foreach($html->find('li.postbitlegacy') as $post_row)
	{
		if($post_row->hasClass('postbitdeleted')) { continue; }
		
		$post = array();
		
		// ID
		
		$post['post_id'] = (int) quick_match('post_(\d+)', $post_row->id);
		
		// Status
		
		$post['yourpost'] = $post_row->hasClass('yourpost');
		$post['new'] = $post_row->hasClass('postbitnew');
		
		// Date
		
		$post['date'] = trim($post_row->find('span.postdate span.date', 0)->plaintext);
		
		// User
		
		$username_a = $post_row->find('a.username', 0);
		$user_id = (int) quick_match('u=(\d+)', $username_a->href);
		$userstats = $post_row->find('div[id=userstats]', 0)->plaintext;
		$join_date = quick_match('(\w+\s\d+)', $userstats);
		$post_count = (int) str_replace(',', '', quick_match('([0-9,]+) Posts', $userstats));
		
		$post['user'] = array(
			'user_id' => $user_id,
			'username' => $username_a->plaintext,
			'username_html' => $username_a->innertext,
			'usertitle' => trim($post_row->find('span.usertitle', 0)->innertext),
			'online' => $username_a->hasClass('online'),
			'avatar_url' => FACEPUNCH_URL . 'image.php?u=' . $user_id,
			'join_date' => $join_date,
			'num_posts' => $post_count
		);
		
		// Post Info
		
		$postlinking = $post_row->find('span.postlinking', 0);
		
		$os = $postlinking->children(0);
		$browser = $postlinking->children(1);
		
		if($os)
		{
			$post['os'] = quick_match('browser\/(.+)\.png', $os);
		}
		
		if($browser)
		{
			$post['browser'] = quick_match('browser\/(.+)\.png', $browser);
		}
		
		// Content
		
		$post['content'] = trim($post_row->find('blockquote', 0)->innertext);
		
		// Ratings
		
		$posts[] = $post;
	}
	
	return $posts;
}