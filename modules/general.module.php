<?php

class General extends Module
{
	protected $description = 'Miscellaneous actions';
	protected $requires_authentication = true;
	
	/**
		Description: Gets any PM notifications
		Return: notifications | array | Array of notifications
	**/
	public function notifications()
	{
		$ret = $this->api->request('fp_rules.php');
		
		return array('notifications' => parse_pms_notifications($ret));
	}
	
	/**
		Description: Gets ticker information
		Parameter: lasttime | optional | integer | get | Unix timestamp
		Return: events | array | Array of events
	**/
	public function ticker($lasttime = false)
	{
		$data = array(
			'aj' => 1,
			'json' => 1,
			'lasttime' => $lasttime
		);
		
		$ret = $this->api->request('fp_ticker.php', $data);
		
		return array('events' => parse_ticker($ret));
	}
	
	/**
		Description: Gets eventlog information
		Parameter: type | optional | string | get | The event type
		Parameter: user_id | optional | integer | get | User DI for events
		Return: events | array | Array of events
	**/
	public function events($type = false, $user_id = false)
	{
		$data = array(
			'aj' => 1,
		);
		
		if($user_id)
		{
			$data['user'] = $user_id;
		}
		elseif($type)
		{
			$data['type'] = $type;
		}
		
		$ret = $this->api->request('fp_events.php', $data);
		
		return array('events' => parse_ticker($ret));
	}
}

function parse_pms_notifications($str)
{
	$html = is_string($str) ? str_get_html($str) : $str;
	
	$notifications = array();
	
	$notifications_div = $html->find('div.notifications', 0);
	
	if($notifications_div)
	{
		foreach($notifications_div->find('a') as $a)
		{
			if(stristr($a->title, 'New PM'))
			{
				$count = quick_match('(\d+) New PM', $a->title);
				$notification = $count . ' new PM' . plural($count);
			}
			else
			{
				$notification = $a->title;
			}
			
			$notifications[] = $notification;
		}
	}
	
	return $notifications;
}

function parse_ticker($str)
{
	$json_data = json_decode($str, true);
	
	$events = array();
	
	foreach($json_data as $item)
	{
		$html = html_entity_decode($item['html']);
		$html = str_get_html($html);
		$icon = $html->find('img', 0);
		$links = $html->find('a');
		$bolds = $html->find('b');

		$event = array();
		$event['timestamp'] = (int) $item['date'];

		if(!$icon)
		{
			$event['type'] = 'post';
			
			$linkparts = explode('?', $links[2]->href);
			$linkparts2 = explode('#post', $links[2]->href);
	
			$event['username'] = $links[1]->innertext;
			$event['userid'] = quick_match('u\=(\d+)', $links[1]->href);
			$event['forum'] = $links[0]->innertext;
			$event['forumid'] = str_replace('forumdisplay.php?f=', '', $links[0]->href);
			$event['thread'] = $links[2]->innertext;
			$event['threadid'] = quick_match('t\=(\d+)', $links[2]->href);
			$event['postid'] = intval($linkparts2[1]);
			
			foreach($html->find('div div') as $div)
			{
				if($div->innertext == 'mentioned')
				{
					$event['mentioned'] = true;
				}
			}
			
			if(strpos($html, 'background-color: rgba(190, 220, 255') !== false)
			{
				$event['lastread'] = capitalise_month($links[3]->innertext);
			}
		}
		else if(strpos($icon->src, 'fp/events/pban.png') !== false)
		{
			$event['type'] = 'permaban';
			$event['modusername'] = $links[0]->innertext;
			$event['moduserid'] = quick_match('u\=(\d+)', $links[0]->href);
			$event['username'] = $links[2]->innertext;
			$event['userid'] = quick_match('u\=(\d+)', $links[2]->href);
			$event['thread'] = $links[4]->innertext;
			$event['threadid'] = quick_match('t\=(\d+)', $links[4]->href);
			$event['postid'] = quick_match('p\=(\d+)', $links[4]->href);
			
			if($bolds)
			{
				$event['reason'] = $bolds[0]->innertext;
			}
		}
		else if(strpos($icon->src, 'fp/events/ban.png') !== false)
		{
			$event['type'] = 'ban';
			$event['modusername'] = $links[0]->innertext;
			$event['moduserid'] = quick_match('u\=(\d+)', $links[0]->href);
			$event['username'] = $links[2]->innertext;
			$event['userid'] = quick_match('u\=(\d+)', $links[2]->href);
			$event['thread'] = $links[4]->innertext;
			$event['threadid'] = quick_match('t\=(\d+)', $links[4]->href);
			$event['postid'] = quick_match('p\=(\d+)', $links[4]->href);
			$event['duration'] = strtolower($bolds[0]->innertext);
			
			if(isset($bolds[1]))
			{
				$event['reason'] = $bolds[1]->innertext;
			}
		}
		else if(strpos($icon->src, 'fp/events/unban.png') !== false)
		{
			$event['type'] = 'unban';
			$event['modusername'] = $links[0]->innertext;
			$event['moduserid'] = quick_match('u\=(\d+)', $links[0]->href);
			
			if(isset($links[2]))
			{
				$event['username'] = $links[2]->innertext;
				$event['userid'] = quick_match('u\=(\d+)', $links[2]->href);
			}
			
			if($bolds)
			{
				$event['reason'] = $bolds[0]->innertext;
			}
		}
		else if(strpos($icon->src, 'fp/events/closed.png') !== false)
		{
			$event['type'] = 'closed';
			$event['modusername'] = $links[0]->innertext;
			$event['moduserid'] = quick_match('u\=(\d+)', $links[0]->href);
			$event['username'] = $links[2]->innertext;
			$event['userid'] = quick_match('u\=(\d+)', $links[2]->href);
			$event['thread'] = $links[4]->innertext;
			$event['threadid'] = quick_match('t\=(\d+)', $links[4]->href);
			
			if($bolds)
			{
				$event['reason'] = $bolds[0]->innertext;
			}
		}
		else if(strpos($icon->src, 'fp/events/ddt.png') !== false)
		{
			$event['type'] = 'ddt';
			$event['modusername'] = $links[0]->innertext;
			$event['moduserid'] = quick_match('u\=(\d+)', $links[0]->href);
			$event['username'] = $links[2]->innertext;
			$event['userid'] = quick_match('u\=(\d+)', $links[2]->href);
			$event['thread'] = $links[2]->innertext;
			$event['threadid'] = quick_match('t\=(\d+)', $links[2]->href);
			
			if($bolds)
			{
				$event['reason'] = $bolds[0]->innertext;
			}
		}
		else if(strpos($icon->src, 'fp/events/join.png') !== false)
		{
			$event['type'] = 'newuser';
			$event['username'] = $links[0]->innertext;
			$event['userid'] = quick_match('u\=(\d+)', $links[0]->href);
		}
		else if(strpos($icon->src, 'fp/events/spmb.png') !== false)
		{				
			$event['type'] = 'spambot';
			$event['username'] = $links[0]->innertext;
			$event['userid'] = quick_match('u\=(\d+)', $links[0]->href);
		}
		else if(strpos($html, 'rated your post') !== false)
		{
			//global $ratingimgmap;
			
			//$ratingimg = str_replace('.png', '', str_replace('/fp/ratings/', '', $html->find('img', 0)->src));
			
			//$rating = $ratingimgmap[$ratingimg];
			$event['type'] = 'rating';
			//$event['rating'] = $rating;
			$event['username'] = $links[0]->innertext;
			$event['userid'] = quick_match('u\=(\d+)', $links[0]->href);
			$event['thread'] = $links[1]->innertext;
			$event['threadid'] = quick_match('t\=(\d+)', $links[1]->href);
			$event['postid'] = quick_match('p\=(\d+)', $links[1]->href);
		}

		$events[] = $event;
	}
	
	return $events;
}

function parse_events($str)
{
	$html = is_string($str) ? str_get_html($str) : $str;
	
	$events = array();
	
	foreach($html->find('div[class=eventtime]') as $eventtime)
	{
		foreach($eventtimes as $eventtime_node)
		{
			$eventtime = array();

			$eventtime['name'] = ucfirst(strtolower($eventtime_node->find('text', 0)->plaintext));
			$eventtime['events'] = array();

			$eventlist = $eventtime_node->find('ul div li');

			foreach($eventlist as $event_node)
			{
				$icon = $event_node->find('img', 0);
				$links = $event_node->find('a');
				$bolds = $event_node->find('b');

				$event = array();
				$event['type'] = str_replace('fp_events.php?type=', '', $event_node->find('a', 0)->getAttribute('href'));

				switch($event['type'])
				{
					case 'pban':
						$event['modusername'] = $links[1]->innertext;
						$event['moduserid'] = quick_match('u\=(\d+)', $links[1]->href);
						$event['username'] = $links[2]->innertext;
						$event['userid'] = quick_match('u\=(\d+)', $links[2]->href);
						
						if(isset($links[5]))
						{
							$event['thread'] = $links[3]->innertext;
							$event['threadid'] = quick_match('t\=(\d+)', $links[3]->href);
							$event['postid'] = quick_match('p\=(\d+)', $links[3]->href);
						}
						
						if($bolds)
						{
							$event['reason'] = $bolds[0]->innertext;
						}
						
						break;
						
					case 'ban':
						$event['modusername'] = $links[1]->innertext;
						$event['moduserid'] = quick_match('u\=(\d+)', $links[1]->href);
						$event['username'] = $links[2]->innertext;
						$event['userid'] = quick_match('u\=(\d+)', $links[2]->href);
						
						if(isset($links[5]))
						{
							$event['thread'] = $links[3]->innertext;
							$event['threadid'] = quick_match('t\=(\d+)', $links[3]->href);
							$event['postid'] = quick_match('p\=(\d+)', $links[3]->href);
						}
						
						$event['duration'] = strtolower($bolds[0]->innertext);
						
						if(isset($bolds[1]))
						{
							$event['reason'] = $bolds[1]->innertext;
						}
						
						break;
						
					case 'unban':
						if(count($links) < 3)
						{
							continue;
						}
						
						$event['modusername'] = $links[1]->innertext;
						$event['moduserid'] = quick_match('u\=(\d+)', $links[1]->href);
						$event['username'] = $links[2]->innertext;
						$event['userid'] = quick_match('u\=(\d+)', $links[2]->href);
						
						if($bolds)
						{
							$event['reason'] = $bolds[0]->innertext;
						}
						
						break;
						
					case 'closed':
						$event['modusername'] = $links[1]->innertext;
						$event['moduserid'] = quick_match('u\=(\d+)', $links[1]->href);
						$event['username'] = $links[2]->innertext;
						$event['userid'] = quick_match('u\=(\d+)', $links[2]->href);
						$event['thread'] = $links[3]->innertext;
						$event['threadid'] = quick_match('t\=(\d+)', $links[3]->href);
						$event['forum'] = $links[4]->innertext;
						$event['forumid'] = quick_match('f\=(\d+)', $links[4]->href);
						
						if($bolds)
						{
							$event['reason'] = $bolds[0]->innertext;
						}
						
						break;
						
					case 'opened':
						$event['modusername'] = $links[1]->innertext;
						$event['moduserid'] = quick_match('u\=(\d+)', $links[1]->href);
						$event['thread'] = $links[2]->innertext;
						$event['threadid'] = quick_match('t\=(\d+)', $links[2]->href);
						$event['forum'] = $links[3]->innertext;
						$event['forumid'] = quick_match('f\=(\d+)', $links[3]->href);
						
						if($bolds)
						{
							$event['reason'] = $bolds[0]->innertext;
						}
						
						break;
						
					case 'ddt':
						$event['modusername'] = $links[1]->innertext;
						$event['moduserid'] = quick_match('u\=(\d+)', $links[1]->href);
						$event['thread'] = $links[2]->innertext;
						$event['threadid'] = quick_match('t\=(\d+)', $links[2]->href);
						$event['forum'] = $links[3]->innertext;
						$event['forumid'] = quick_match('f\=(\d+)', $links[3]->href);
						
						if($bolds)
						{
							$event['reason'] = $bolds[0]->innertext;
						}
						
						break;
						
					case 'rename':
						$event['modusername'] = $links[1]->innertext;
						$event['moduserid'] = quick_match('u\=(\d+)', $links[1]->href);
						$event['thread'] = $links[3]->innertext;
						$event['threadid'] = quick_match('t\=(\d+)', $links[3]->href);
						$event['forum'] = $links[2]->innertext;
						$event['forumid'] = quick_match('f\=(\d+)', $links[2]->href);
						$event['oldname'] = $bolds[0]->innertext;
						
						break;
						
					case 'delhard':
						$event['modusername'] = $links[1]->innertext;
						$event['moduserid'] = quick_match('u\=(\d+)', $links[1]->href);
						$event['thread'] = $bolds[0]->innertext;
						$event['forum'] = $links[2]->innertext;
						$event['forumid'] = quick_match('f\=(\d+)', $links[2]->href);
						
						break;
						
					case 'delsoft':
						$event['modusername'] = $links[1]->innertext;
						$event['moduserid'] = quick_match('u\=(\d+)', $links[1]->href);
						$event['thread'] = $links[2]->innertext;
						$event['threadid'] = quick_match('t\=(\d+)', $links[2]->href);
						$event['forum'] = $links[3]->innertext;
						$event['forumid'] = quick_match('f\=(\d+)', $links[3]->href);
						
						break;
						
					case 'mov':
						$event['modusername'] = $links[1]->innertext;
						$event['moduserid'] = quick_match('u\=(\d+)', $links[1]->href);
						$event['username'] = $links[2]->innertext;
						$event['userid'] = quick_match('u\=(\d+)', $links[2]->href);
						$event['thread'] = $links[3]->innertext;
						$event['threadid'] = quick_match('t\=(\d+)', $links[3]->href);
						$event['forum'] = $links[4]->innertext;
						$event['forumid'] = quick_match('f\=(\d+)', $links[4]->href);
						$event['toforum'] = $links[5]->innertext;
						$event['toforumid'] = quick_match('f\=(\d+)', $links[5]->href);
						
						break;
						
					case 'capsfix':
						$event['modusername'] = $links[1]->innertext;
						$event['moduserid'] = quick_match('u\=(\d+)', $links[1]->href);
						$event['thread'] = $links[2]->innertext;
						$event['threadid'] = quick_match('t\=(\d+)', $links[2]->href);
						
						break;
						
					case 'toobig':
						$event['thread'] = $links[1]->innertext;
						$event['threadid'] = quick_match('t\=(\d+)', $links[1]->href);
						$event['numposts'] = intval($bolds[0]->innertext);
						
						break;
						
					case 'title':
						if(count($links) > 3)
						{
							$event['modusername'] = $links[1]->innertext;
							$event['moduserid'] = quick_match('u\=(\d+)', $links[1]->href);
							$event['username'] = $links[2]->innertext;
							$event['userid'] = quick_match('u\=(\d+)', $links[2]->href);
						}
						else
						{
							$event['username'] = $links[1]->innertext;
							$event['userid'] = quick_match('u\=(\d+)', $links[1]->href);
						}
						
						break;
						
					case 'regi':
						$event['username'] = $links[1]->innertext;
						$event['userid'] = quick_match('u\=(\d+)', $links[1]->href);
						$event['altusername'] = $links[2]->innertext;
						$event['altuserid'] = quick_match('u\=(\d+)', $links[2]->href);
						$event['ip'] = $bolds[0]->innertext;
						
						break;
						
					case 'spmb':
						$event['username'] = $links[1]->innertext;
						$event['userid'] = quick_match('u\=(\d+)', $links[1]->href);
						$event['ip'] = $links[3]->innertext;
						
						break;
						
					case 'join':
						$event['username'] = $links[1]->innertext;
						$event['userid'] = quick_match('u\=(\d+)', $links[1]->href);
						
						break;
						
					default:
						continue;
						break;
				}
				
				$eventtime['events'][] = $event;
		}

		$events[] = $eventtime;
	}
	
	return $events;
}