<?php

class Post extends Module
{
	protected $description = 'Get posts';
	protected $dependencies = array('thread');
	
	/**
		Description: Gets a Post by ID
		Parameter: id | required | integer | get | Post ID
		Return: thread | object | Thread
		Return: post | object | Post
	**/
	public function id($id)
	{
		$data = array(
			'p' => $id,
		);
		
		$ret = $this->api->request('showthread.php', $data);
		
		$thread = parse_thread($ret);
		$posts = parse_posts($ret);
		
		return array('thread' => $thread, 'post' => $posts[0]);
	}
	
	/**
		Description: Gets BBCode to quote a Post by ID
		Parameter: id | required | integer | get | Post ID
		Return: quote | string | BBCode quote for the Post
	**/
	public function quote($id)
	{
		$data = array(
			'p' => $id,
			'do' => 'getquotes'
		);
		
		$data = array_merge($data, $this->_get_security_data($id));
		
		$ret = $this->api->request('ajax.php?do=getquotes&p=' . $id, $data, 'POST');
		
		return array('quote' => trim(quick_match('\<\!\[CDATA\[(.*)\]\]\>', $ret)));
	}
	
	/**
		Description: Gets BBCode to edit a Post by ID
		Parameter: id | required | integer | get | Post ID
		Return: edit | string | BBCode for the Post
	**/
	public function edit($id)
	{
		$data = array(
			'p' => $id,
			'do' => 'quickedit',
			'editorid' => 'vB_Editor_QE_1'
		);
		
		$data = array_merge($data, $this->_get_security_data($id));
		
		$ret = $this->api->request('ajax.php?do=getquotes&p=' . $id, $data, 'POST');
		
		return array('edit' => trim(quick_match('tabindex\=\"1\">(.*)\<\/textarea\>', $ret)));
	}
	
	/**
		Description: Updates a Post by ID
		Parameter: id | required | integer | get | Post ID
		Parameter: message | required | string | post | Post Message
		Return: updated | boolean | Post updated or not
	**/
	public function update($id)
	{
		$message = $_POST['message'];
		
		$data = array(
			'postid' => $id,
			'message' => $message,
			'do' => 'updatepost',
			'parseurl' => 1,
			'reason' => ''
		);
		
		$data = array_merge($data, $this->_get_security_data($id));
		
		$ret = $this->api->request('editpost.php?do=updatepost&p=' . $id, $data, 'POST');
		
		return array('updated' => true);
	}
	
	/**
		Description: Rate a Post
		Parameter: id | required | integer | get | Post ID
		Parameter: rating | required | integer | get | Rating ID
		Parameter: key | required | string | get | Rating key
		Return: rated | boolean | Post rated or not
		Return: ratings | array | Array of updated ratings
	**/
	public function rate($id, $rating, $key)
	{
		$data = array(
			'do' => 'rate_post',
			'postid' => $id,
			'rating' => $rating,
 			'key' => $key
		);
		
		$data = array_merge($data, $this->_get_security_data($id));
		
		$ret = $this->api->request('ajax.php', $data, 'POST');
		
		$json = json_decode($ret, true);
		
		if(isset($json['error']))
		{
			return array('rated' => false);
		}
		else
		{
			$ratings = array();
			
			if(preg_match_all('/([a-z_.]+)\" alt\=\"([A-Za-z0-9 ]+)\" \/\>  x \<strong\>(\d+)\<\/strong>/', $json['result'], $matches, PREG_SET_ORDER))
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
			
			return array('rated' => true, 'ratings' => $ratings);
		}
	}
	
	public function _get_security_data($id)
	{
		$data = array(
			'p' => $id
		);
		
		$ret = $this->api->request('showthread.php', $data);
		
		$posthash = quick_match('\"posthash\":\"([0-9a-z]+)\"', $ret);
		$poststarttime = quick_match('\"poststarttime\":([0-9]+)', $ret);
		$securitytoken = quick_match('SECURITYTOKEN = \"([0-9a-z\-]+)\";', $ret);
		
		if(!$securitytoken)
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
