<?php

class Forum extends Module
{
	protected $description = 'Get forums or threads from a forum';
	
	/**
		Description: Gets all top level forums
		Return: forums | array | Array of forums
	**/
	public function index()
	{
		$ret = $this->api->request('forum.php');
		
		return array('forums' => parse_categories($ret));
	}
	
	/**
		Description: Gets threads from a Forum
		Parameter: id | required | integer | get | Forum ID
		Parameter: page | optional | integer | get | Page number
		Parameter: sort | optional | string | get | Sorting of returned threads: title, postusername, lastpost, replycount, views
		Parameter: order | optional | string | get | Ordering of returned threads: acc, desc
		Return: subforums | array | Array of subforums
		Return: threads | array | Array of threads
	**/
	public function id($id, $page = 1, $sort = 'lastpost', $order = 'desc')
	{
		$data = array(
			'f' => $id,
			'page' => $page,
			'sort' => $sort,
			'order' => $order
		);
		
		$ret = $this->api->request('forumdisplay.php', $data);
		
		return parse_forum($ret);
	}
}

function parse_categories($str)
{
	$html = is_string($str) ? str_get_html($str) : $str;
	
	$categories = array();
	
	foreach($html->find('table.forums') as $cat_table)
	{
		// ID
		
		$category['forum_id'] = (int) quick_match('cat(\d+)', $cat_table->id);
		
		// Name
		
		$category['name'] = $cat_table->find('h2 a', 0)->plaintext;
		
		// Subforums
		
		$category['forums'] = parse_forums($cat_table);
		
		$categories[] = $category;
	}
	
	return $categories;
}

function parse_forums($str)
{
	$html = is_string($str) ? str_get_html($str) : $str;
	
	$forums = array();
	
	foreach($html->find('tr.forumbit_post') as $forum_row)
	{
		$forum = array();
		
		// ID
		
		$forum['forum_id'] = (int) quick_match('forum(\d+)', $forum_row->id);
		
		// Name
		
		$title = $forum_row->find('h2.forumtitle a', 0);
		
		$forum['name'] = $title->plaintext;
		
		// Description
		
		$forum['description'] = ($desciption = $forum_row->find('p.forumdescription', 0)) ? $desciption->plaintext : '';
		
		// Icon
		
		$forum['icon_url'] = FACEPUNCH_URL . 'fp/forums/' . $forum['forum_id'] . '.png';
		
		// Threads and Posts
		
		$forum['num_threads'] = (int) str_replace(',', '', quick_match('([0-9,]+) Threads', $title->title));
		
		$forum['num_posts'] = (int) str_replace(',', '', quick_match('([0-9,]+) Posts', $title->title));
		
		// Viewers
		
		$forum['num_viewers'] = (int) quick_match('(\d+)', $forum_row->find('span.viewing', 0));
		
		// Last post
		
		$last_post_td = $forum_row->find('td.forumlastpost', 0);
		$last_post_date = $last_post_td->find('p.lastpostdate', 0);
		
		if($last_post_date)
		{
			$last_post_link = $last_post_date->find('a', 0);
			$last_post_user_div = $last_post_td->find('div.LastPostAvatar', 0);
			$last_post_user_a = $last_post_user_div->find('a', 0);
			$last_post_user_id = (int) quick_match('members\/(\d+)', $last_post_user_a->href);
		
			$forum['last_post'] = array(
				'time' => trim($last_post_date->plaintext),
				'post_id' => (int) quick_match('p=(\d+)#', $last_post_link->href),
				'thread_id' => (int) quick_match('t=(\d+)&', $last_post_link->href),
				'title' => $last_post_td->find('p.lastposttitle a', 0)->plaintext,
				'author' => array(
					'user_id' => $last_post_user_id,
					'username' => $last_post_user_a->find('img', 0)->alt,
					'avatar_url' => FACEPUNCH_URL . 'image.php?u=' . $last_post_user_id
				)
			);
		}
		
		$forums[] = $forum;
	}
	
	return $forums;
}

function parse_forum($str, $include_subforums = true)
{
	$html = is_string($str) ? str_get_html($str) : $str;
	
	// Forum
	
	$forum = array();
	
	$breadcrumb_div = $html->find('div[id=breadcrumb]', 0);
	
	$forum['title'] = $breadcrumb_div->find('div[id=lastelement] span', 0)->innertext;
	
	if($breadcrumb_div->find('span.navbit a', -1))
	{
		$forum['parent'] = array(
			'forum_id' => quick_match('f\=(\d+)', $breadcrumb_div->find('span.navbit a', -1)),
			'title' => $breadcrumb_div->find('span.navbit a', -1)->innertext
		);
	}
	
	$forum['viewers'] = parse_forum_viewers($html);
	
	// Pages
	
	$pagination = $html->find('div.threadpagenav', 0);
	
	if($pagination)
	{
		$first_last = $pagination->find('span.first_last a', -1);
		$selected = $pagination->find('span.selected a', 0);
		
		$forum['pages'] = array(
			'current' => (int) $selected->plaintext,
			'total' => (int) ($first_last && $first_last->rel != 'start' ? (int) quick_match('page=(\d+)', $first_last->href) : $selected->plaintext)
		);
	}
	else
	{
		$forum['pages'] = array(
			'current' => 1,
			'total' => 1
		);
	}
	
	// Subforums
	/* Removed for now - there aren't any!
	$subforums = array();
	
	if($include_subforums)
	{
		$above_body_next = $html->find('div.above_body', 0)->nextSibling();
		
		if($above_body_next->tag != 'comment' && $above_body_next->id != 'threadlist')
		{
			foreach($above_body_next->find('a') as $subforum_link)
			{
				$subforum = array();
				
				// ID
				
				$subforum['forum_id'] = (int) quick_match('forums\/(\d+)', $subforum_link->href);
				
				// Name
				
				$subforum['name'] = $subforum_link->plaintext;
				
				$subforums[] = $subforum;
			}
		}
	}
	*/
	
	// Threads
	
	$threads = array();
	
	foreach($html->find('table.threads tr') as $thread_row)
	{
		if(!$thread_row->hasClass('threadbit')) { continue; }
		if($thread_row->hasClass('deleted')) { continue; }
		
		$thread = array();
		
		// ID
		
		$thread['thread_id'] = (int) quick_match('thread_(\d+)', $thread_row->id);
		
		// Status
		
		$thread['new'] = $thread_row->hasClass('new');
		$thread['sticky'] = $thread_row->hasClass('nonsticky') ? false : true;
		$thread['locked'] = $thread_row->hasClass('lock');
		
		// Thread icon
		
		$icon = $thread_row->find('td.threadicon img', 0);
		
		$thread['thread_icon'] = array(
			'image_url' => FACEPUNCH_URL . (substr($icon->src, 0, 1) == '/' ? substr($icon->src, 1) : $icon->src),
			'name' => $thread['locked'] ? 'Locked' : ucwords($icon->alt) // Fixed for locked threads
		);
		
		// Title
		
		$title = $thread_row->find('h3', 0);
		
		$thread['title'] = trim(($title_a = $title->find('a', 0)) ? $title_a->plaintext : $title->plaintext);
		
		// Pages
		
		$thread['num_pages'] = ($last_a = $title->find('span.threadpagenav a', -1)) ? (int) str_replace(' Last', '', $last_a->plaintext) : 1;
		
		// New Posts
		
		$newposts = $title->find('div.newposts a', 0);
		
		if($newposts)
		{
			$thread['num_new_posts'] = (int) quick_match('(\d+) new', $newposts->plaintext);
		}
		
		// Thread Author
		
		$author = $thread_row->find('div.author', 0);
		$author_link = $author->find('a', 0);
		$author_id = (int) quick_match('u=(\d+)', $author_link->href);
		
		$thread['author'] = array(
			'user_id' => $author_id,
			'username' => $author_link->plaintext,
			'avatar_url' => FACEPUNCH_URL . 'image.php?u=' . $author_id
		);
		
		// Viewers
		
		$thread['num_viewers'] = ($viewers = $author->find('span.viewers', 0)) ? (int) str_replace(' reading', '', $viewers->plaintext) : 0;
		
		// Thread rating
		
		$threadrating_div = $thread_row->find('div.threadratings', 0);
		$has_rating = trim($threadrating_div->plaintext) != '';
		
		if($has_rating)
		{
			$thread['thread_rating'] = array(
				'name' => $threadrating_div->find('img', 0)->alt,
				'icon_url' => $threadrating_div->find('img', 0)->src,
				'count' => (int) $threadrating_div->find('strong', 0)->plaintext,
			);
		}
		
		// Last post
		
		$last_post_td = $thread_row->find('td.threadlastpost', 0);
		$last_post_link = $last_post_td->find('a', 0);
		$last_post_user_id = (int) quick_match('u=(\d+)', $last_post_link->href);
		
		$thread['last_post'] = array(
			'time' => $last_post_td->find('dl dd', 0)->plaintext,
			'author' => array(
				'user_id' => $last_post_user_id,
				'username' => $last_post_link->plaintext,
				'avatar_url' => FACEPUNCH_URL . 'image.php?u=' . $last_post_user_id
			)
		);
		
		// Replies
		
		$replies = $thread_row->find('td.threadreplies a', 0);
		
		if($replies)
		{
			$thread['num_replies'] = (int) str_replace(',', '', $replies->plaintext);
		}
		
		// Views
		
		$views = $thread_row->find('td.threadviews span', 0);
		
		if($views)
		{
			$thread['num_views'] = (int) str_replace(',', '', $views->plaintext);
		}
		
		$threads[] = $thread;
	}
	
	return array('forum' => $forum, /* removed 'subforums' => $subforums, */ 'threads' => $threads);
}

function parse_forum_viewers($str)
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