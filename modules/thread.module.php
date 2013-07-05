<?php

class Thread extends Module
{
	protected $description = 'Get and reply to threads';
	protected $dependencies = array('forum');
	
	/**
		Description: Gets a Thread by ID
		Parameter: id | required | integer | get | Thread ID
		Parameter: page | optional | integer | get | Page number
		Return: thread | object | Thread with array of posts
	**/
	public function id($id, $page = 1)
	{
		$data = array(
			't' => $id,
			'page' => $page
		);
		
		$ret = $this->api->request('showthread.php', $data);
		
		return array('thread' => parse_thread($ret));
	}
	
	/**
		Description: Gets the first page of a Thread by ID with unread posts
		Parameter: id | required | integer | get | Thread ID
		Return: thread | object | Thread with array of posts
	**/
	public function unread($id)
	{
		$data = array(
			't' => $id,
			'goto' => 'newpost'
		);
		
		$ret = $this->api->request('showthread.php', $data);
		
		return array('thread' => parse_thread($ret));
	}
	
	/**
		Description: Reply to a Thread
		Parameter: id | required | integer | get | Thread ID
		Parameter: subscribe | optional | integer | get | Whether to subscribe to the thread
		Parameter: message | required | string | post | The message to submit
		Return: sent | boolean | Reply sent or failed
	**/
	public function reply($id, $subscribe = false, $message)
	{
		$data = array(
			'message' => $message,
			't' => $id,
			'do' => 'postreply',
			'subscribe' => $subscribe ? $subscribe : $this->is_subscribed($id),
			'parseurl' => 1
		);
		
		$data = array_merge($data, $this->_get_security_data($id));
		
		$ret = $this->api->request('newreply.php?do=postreply&t=' . $id, $data, 'POST');
		
		return array('sent' => true);
	}
	
	/**
		Description: Gets whether the user is subscribed to a thread
		Parameter: id | required | integer | get | Thread ID
		Return: subscribed | boolean | User subscribed or not
	**/
	public function is_subscribed($id)
	{
		$subscribed_threads = $this->subscribed('all');
		
		foreach($subscribed_threads as $thread)
		{
			if($thread['thread_id'] == $id)
			{
				return array('subscribed' => true);
			}
		}
		
		return array('subscribed' => false);
	}
	
	/**
		Description: Gets read threads
		Return: threads | array | Array of threads
	**/
	public function read()
	{
		$ret = $this->api->request('fp_read.php');
		
		$threads = parse_forum($ret, false);
		
		foreach($threads['threads'] as $index => $thread)
		{
			if(!isset($thread['num_new_posts']))
			{
				unset($threads['threads'][$index]);
			}
		}
		
		return $threads;
	}
	
	/**
		Description: Gets popular threads
		Return: threads | array | Array of threads
	**/
	public function popular()
	{
		$ret = $this->api->request('fp_popular.php');
		
		return parse_forum($ret, false);
	}
	
	/**
		Description: Gets subscribed threads
		Parameter: folder_id | optional | integer | get | Subscriptions Folder ID
		Return: threads | array | Array of threads
	**/
	public function subscribed($folder_id = 0)
	{
		$data = array(
			'folderid' => $folder_id
		);
		
		$ret = $this->api->request('subscription.php');
		
		return array('threads' => parse_forum($ret, false));
	}
	
	/**
		Description: Gets thread icons
		Parameter: forum_id | required | integer | get | Forum ID
		Return: icons | array | Array of icons
	**/
	public function icons($forum_id)
	{
		$ret = $this->api->request('newthread.php?do=newthread&f=' . $forum_id);
		
		return array('icons' => parse_thread_icons($ret));
	}
	
	/**
		Description: Creates a new Thread
		Parameter: forum_id | required | integer | get | Forum ID
		Parameter: subject | required | string | get | Thread subject
		Parameter: icon_id | required | integer | get | Thread icon ID
		Parameter: message | required | string | post | Thread message
		Return: created | boolean | Thread created or not
	**/
	public function create($forum_id, $subject, $icon_id, $message)
	{
		$data = array(
			'subject' => $subject,
			'message' => $message,
			'f' => $forum_id,
			'iconid' => $icon_id,
			'do' => 'postthread',
			'parseurl' => 1,
			'sbutton' => 'Submit New Thread'
		);
		
		$data = array_merge($data, $this->_get_security_data($id));
		
		$ret = $this->api->request('newreply.php?do=postthread&f=' . $forum_id, $data, 'POST');
		
		return array('created' => true);
	}
	
	public function _get_security_data($id)
	{
		$data = array(
			't' => $id
		);
		
		$ret = $this->api->request('showthread.php', $data);
		
		$posthash = quick_match('\"posthash\":\"([0-9a-z]+)\"', $ret);
		$poststarttime = quick_match('\"poststarttime\":([0-9]+)', $ret);
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
	
	// Locked
	
	$thread['locked'] = $html->find('span[id=reply_button] a', 0)->innertext == 'Reply';
	
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
		$first_last = $pagination->find('span.first_last a', -1);
		$selected = $pagination->find('span.selected a', 0);
		
		$thread['pages'] = array(
			'current' => (int) $selected->plaintext,
			'total' => (int) ($first_last && $first_last->rel != 'start' ? (int) quick_match('page=(\d+)', $first_last->href) : $selected->plaintext)
		);
	}
	else
	{
		$thread['pages'] = array(
			'current' => 1,
			'total' => 1
		);
	}
	
	// Posts
	
	$thread['posts'] = parse_posts($html);
	
	// Viewers
	
	$thread['viewers'] = parse_thread_viewers($html);
	
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
		
		$rating_results_span = $post_row->find('span.rating_results', 0);
		
		if($rating_results_span)
		{
			$ratings = array();
		
			if(preg_match_all('/([a-z_.]+)\" alt\=\"([A-Za-z0-9 ]+)\" \/\> [A-Za-z0-9 ]+? x \<strong\>(\d+)\<\/strong>/', $rating_results_span, $matches, PREG_SET_ORDER))
			{
				foreach($matches as $match)
				{
					$ratings[] = array(
						'title' => $match[2],
						'icon_url' => FACEPUNCH_URL . 'fp/ratings/' . $match[1],
						'count' => $match[3]
					);
				}
			}
			
			$post['ratings'] = $ratings;
		}
		
		// Rate
		
		$postrating_div = $post_row->find('div.postrating', 0);
		
		if($postrating_div)
		{
			$rates = array();
			
			foreach($postrating_div->find('a') as $post_rating_a)
			{
				if($post_rating_a->onclick)
				{
					$onclick = $post_rating_a->onclick;
					
					//echo $onclick;
				
					$rating_id = (int) quick_match('\, \'(\d+)\'\,', $onclick);
					$rating_key = quick_match('\, \'([a-z0-9]+)\' \)', $onclick);
				
					$rating_img = $post_rating_a->find('img', 0);
				
					$image_url = $rating_img->src;
					$title = $rating_img->alt;
				
					$rates[] = array(
						'title' => $title,
						'image_url' => $image_url,
						'rating_id' => $rating_id,
						'rating_key' => $rating_key
					);
				}
			}
			
			$post['rate'] = $rates;
		}
		
		$posts[] = $post;
	}
	
	return $posts;
}

function parse_thread_icons($str)
{
	$html = is_string($str) ? str_get_html($str) : $str;
	
	$icons = array();
	
	$posticon_images = $html->find('div.posticons', 0)->find('img');
	
  foreach($posticon_images as $icon_img)
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

function parse_thread_viewers($str)
{
	$html = is_string($str) ? str_get_html($str) : $str;
	
	$viewers = array();
	
	$whos_reading = $html->find('div[id=whos_reading] ol li a');
	
	foreach($whos_reading as $whos_reading_a)
	{
		$user_id = quick_match('u\=(\d+)', $whos_reading_a->href);
		
		$viewers[] = array(
			'user_id' => $user_id,
			'username' => $whos_reading_a->plaintext,
			'username_html' => $whos_reading_a->innertext,
		);
	}
	
	return $viewers;
}