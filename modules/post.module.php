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
		
		$ret = $this->api->request('showpost.php', $data);
		
		$thread = parse_thread($ret);
		$posts = parse_posts($ret);
		
		return array('thread' => $thread, 'post' => $posts[0]);
	}
}
