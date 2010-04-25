<?php
/*
 *	ScTBDev - A bittorrent tracker source based on SceneTorrents.org
 *	Copyright (C) 2005-2010 ScTBDev.ca
 *
 *	This file is part of ScTBDev.
 *
 *	ScTBDev is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	ScTBDev is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with ScTBDev.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once(__DIR__.DIRECTORY_SEPARATOR.'class_config.php');
require_once(CLASS_PATH.'bt_pm.php');
require_once(CLASS_PATH.'bt_bitmask.php');
require_once(CLASS_PATH.'bt_sql.php');

class bt_user {
	// User classes
	const UC_USER = 0;
	const UC_POWER_USER = 1;
	const UC_XTREME_USER = 2;
	const UC_LOVER = 3;
	const UC_WHORE = 4;
	const UC_SUPER_WHORE = 5;
	const UC_SEED_WHORE = 6;
	const UC_OVERSEEDER = 7;
	const UC_VIP = 8;
	const UC_UPLOADER = 9;
	const UC_FORUM_MODERATOR = 10;
	const UC_MODERATOR = 11;
	const UC_ADMINISTRATOR = 12;
	const UC_LEADER = 13;

	// Staff class
	const UC_MIN = 0;
	const UC_MAX = 13;
	const UC_STAFF = 10;

	private static $_users_cache = array();
	private static $_mod_comments = array();
	private static $_mod_comments_del = array();
	public static $current = NULL;

	public static $class_names = array(
		self::UC_USER				=> 'User',
		self::UC_POWER_USER			=> 'Power User',
		self::UC_XTREME_USER		=> 'Xtreme User',
		self::UC_LOVER				=> 'ScT Lover',
		self::UC_WHORE				=> 'ScT Whore',
		self::UC_SUPER_WHORE		=> 'ScT Super Whore',
		self::UC_SEED_WHORE			=> 'ScT Seed Whore',
		self::UC_OVERSEEDER			=> 'The Overseeder',
		self::UC_VIP				=> 'VIP',
		self::UC_UPLOADER			=> 'Uploader',
		self::UC_FORUM_MODERATOR	=> 'Forum Moderator',
		self::UC_MODERATOR			=> 'Global Moderator',
		self::UC_ADMINISTRATOR		=> 'Administrator',
		self::UC_LEADER				=> 'Staff Leader'
	);

	public static function prepare_curuser(&$user) {
		if (empty($user))
			die;

		$user['id'] = 0 + $user['id'];
		$user['class'] = 0 + $user['class'];
		$user['theme'] - 0 + $user['theme'];
		$user['stylesheet'] = 0 + $user['stylesheet'];
		$user['added'] = 0 + $user['added'];
		$user['last_login'] = 0 + $user['last_login'];
		$user['last_access'] = 0 + $user['last_access'];
		$user['uploaded'] = (float)$user['uploaded'];
		$user['downloaded'] = (float)$user['downloaded'];
		$user['payed_uploaded'] = (float)$user['payed_uploaded'];
		$user['seeding'] = 0 + $user['seeding'];
		$user['leeching'] = 0 + $user['leeching'];
		$user['country'] = 0 + $user['country'];
		$user['timezone'] = (float)$user['timezone'];
		$user['dst_offset'] = 0 + $user['dst_offset'];
		$user['warneduntil'] = 0 + $user['warneduntil'];
		$user['torrentsperpage'] = 0 + $user['torrentsperpage'];
		$user['topicsperpage'] = 0 + $user['topicsperpage'];
		$user['postsperpage'] = 0 + $user['postsperpage'];
		$user['last_browse'] = 0 + $user['last_browse'];
		$user['inbox_new'] = 0 + $user['inbox_new'];
		$user['inbox'] = 0 + $user['inbox'];
		$user['sentbox'] = 0 + $user['sentbox'];
		$user['posts'] = 0 + $user['posts'];
		$user['last_forum_visit'] = 0 + $user['last_forum_visit'];
		$user['invites'] = 0 + $user['invites'];
		$user['invitedby'] = 0 + $user['invitedby'];
		$user['flags'] = (int)$user['flags'];
		$user['donations'] = (float)$user['donations'];
		$user['irc_time'] = 0 + $user['irc_time'];
		$user['ip'] = (int)$user['ip'];
		$user['realip'] = (int)$user['realip'];
		$user['settings'] = bt_bitmask::fetch_all($user['flags']);
	}

	public static function valid_class($class) {
		$class = (int)$class;
		return (bool)($class >= self::UC_MIN && $class <= self::UC_MAX);
	}

	public static function required_class($min = self::UC_MIN, $max = self::UC_MAX) {
		$minclass = (int)$min;
		$maxclass = (int)$max;
		if (empty(self::$current))
			return false;
		if (!self::valid_class($minclass) || !self::valid_class($maxclass))
			return false;
		if ($maxclass < $minclass)
			return false;

		return (bool)(self::$current['class'] >= $minclass && self::$current['class'] <= $maxclass);
	}

	public static function get_class_name($class) {
		$class = (int)$class;

		if (!self::valid_class($class))
			return '';

		if (isset(self::$class_names[$class]))
			return self::$class_names[$class];
		else
			return '';
	}

	public static function auto_demote($fromclass, $toclass, $minratio, $remove_flags = 0, $remove_chans = 0) {
		$fromclass = (int)$fromclass;
		$toclass   = (int)$toclass;
		$minratio  = (float)$minratio;

		$fromname = self::get_class_name($fromclass);
		$toname   = self::get_class_name($toclass);

		if ($fromname == '' || $toname == '' || $fromclass <= $toclass)
			return false;

		self::_cache_users();

		if (!isset(self::$_users_cache[$fromclass]))
			return false;

		$msg = 'You have been auto-demoted from [b]'.$fromname.'[/b] to [b]'.$toname.'[/b] because your share ratio '.
			'has dropped below '.$minratio;
		$title = 'Demoted to '.$toname;
		$comment = 'Auto-demoted from '.$fromname.' to '.$toname;

		foreach (self::$_users_cache[$fromclass] as $aid => $arr) {
			if ($arr['ratio'] > $minratio)
				continue;

			unset(self::$_users_cache[$fromclass][$aid]);
			self::$_users_cache[$toclass][] = $arr;

			bt_sql::query('UPDATE users SET class = '.$toclass.($remove_flags ? ', flags = (flags & ~'.$remove_flags.')' : '').
				($remove_chans ? ', chans = (chans & ~'.$remove_chans.')' : '').' WHERE id = '.$arr['id']);
			self::mod_comment($arr['id'], $comment);
			bt_pm::send(0, $arr['id'], $msg, $title);
		}

		return true;
	}

	public static function auto_promote($fromclass, $toclass, $minratio, $uplimit, $regtime, $extmsg = '', $add_flags = 0, $add_chans = 0, $downlimit = 0) {
		$fromclass	= (int)$fromclass;
		$toclass	= (int)$toclass;
		$minratio	= (float)$minratio;
		$uplimit	= 0 + $uplimit;
		$downlimit	= 0 + $downlimit;
		$regtime	= (int)$regtime;
		$extmsg		= (string)$extmsg;

		$maxdt		= time() - $regtime;
		$fromname 	= self::get_class_name($fromclass);
		$toname		= self::get_class_name($toclass);


		if ($fromname == '' || $toname == '' || $fromclass >= $toclass || $uplimit == 0)
			return false;

		self::_cache_users();

		if (!isset(self::$_users_cache[$fromclass]))
			return false;

		$msg = 'Congratulations, you have been auto-promoted to [b]'.$toname.'[/b], because you have met the necessary requirements.'."\n".
			'Thank you for sharing your files on our network.'.($extmsg != '' ? "\n\n".$extmsg : '');
		$title = 'Promoted to '.$toname;
		$comment = 'Auto-promoted from '.$fromname.' to '.$toname;

		foreach (self::$_users_cache[$fromclass] as $aid => $arr) {
			if ($arr['ratio'] < $minratio || $arr['uploaded'] < $uplimit || $arr['added'] > $maxdt || $arr['downloaded'] < $downlimit)
				continue;

			unset(self::$_users_cache[$fromclass][$aid]);
			self::$_users_cache[$toclass][] = $arr;

			bt_sql::query('UPDATE users SET class = '.$toclass.($add_flags ? ', flags = (flags | '.$add_flags.')' : '').
				($add_chans ? ', chans = (chans | '.$add_chans.')' : '').' WHERE id = '.$arr['id']);
			self::mod_comment($arr['id'], $comment);
			bt_pm::send(0, $arr['id'], $msg, $title);
		}
		unset(self::$_users_cache[$fromclass]);

		return true;
	}

	private static function _cache_users() {
		if (!empty(self::$_users_cache))
			return;

		$res = bt_sql::query('SELECT id, uploaded, payed_uploaded, downloaded, added, class FROM users WHERE enabled = "yes"');
		while ($arr = $res->fetch_assoc()) {
			$class = (int)$arr['class'];

			if (!isset(self::$_users_cache[$class]))
				self::$_users_cache[$class] = array();

			$uploaded = 0 + ($arr['payed_uploaded'] > $arr['uploaded'] ? 1 : ($arr['uploaded'] - $arr['payed_uploaded']));
			$ratio = $arr['downloaded'] == 0 ? 1 : ($uploaded / $arr['downloaded']);

			self::$_users_cache[$class][] = array(
				'id'			=> 0 + $arr['id'],
				'uploaded'		=> $uploaded,
				'downloaded'	=> 0 + $arr['downloaded'],
				'ratio'			=> $ratio,
				'added'			=> 0 + $arr['added'],
			);
		}
		$res->free();;
	}

	public static function init_mod_comment($user, $deleted = false) {
		$user = (int)$user;
		if (!isset(self::$_mod_comments[$user]))
			self::$_mod_comments[$user] = array();
		if ($deleted)
			self::$_mod_comments_del[$user] = true;
	}

	public static function mod_comment($user, $comment) {
		$user = (int)$user;
		$comment = (string)trim($comment);

		if (isset(self::$_mod_comments[$user])) {
			self::$_mod_comments[$user][] = gmdate('Y-m-d (H:i:s)') . ' - '.$comment;
		}
		else {
			$res = bt_sql::query('SELECT modcomment FROM users WHERE id = '.$user);
			$qmc = $res->fetch_assoc();
			$res->free();

			$mc = gmdate('Y-m-d (H:i:s)') . ' - '.$comment."\n".$qmc['modcomment'];
			$res2 = bt_sql::query('UPDATE users SET modcomment = '.bt_sql::esc($mc).' WHERE id = '.$user);
			return $res2 ? true : false;
		}
	}

	public static function comit_mod_comments() {
		foreach (self::$_mod_comments as $user => $comments) {
			if (!count($comments))
				continue;

			unset(self::$_mod_comments[$user]);

			krsort($comments, SORT_NUMERIC);
			$comment = join("\n", $comments);

			$table = isset(self::$_mod_comments_del[$user]) ? 'users_deleted' : 'users';

			$res = bt_sql::query('SELECT modcomment FROM '.$table.' WHERE id = '.$user);
			$qmc = $res->fetch_assoc();
			$res->free();

			$mc = $comment."\n".$qmc['modcomment'];
			$res2 = bt_sql::query('UPDATE '.$table.' SET modcomment = '.bt_sql::esc($mc).' WHERE id = '.$user);
		}
	}

	public static function mkpasskey($length = 32) {
		$chars = '0123456789abcdefghijklmnopqrstuvwxyz';
		$max = strlen($chars) - 1;
		$passkey = '';
		for ($i = 0; $i < $length; $i++)
			$passkey .= $chars[mt_rand(0, $max)];

		return $passkey;
	}
}
?>