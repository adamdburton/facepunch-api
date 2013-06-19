<?php

class Post extends Module
{
	protected $dependencies = array('thread');
	
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
