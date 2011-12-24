<?php
/*
=====================================================
 Member activation redirect
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2011 Yuri Salimovskiy
 Lecensed under MIT License
 http://www.opensource.org/licenses/mit-license.php
=====================================================
 This software is based upon and derived from
 ExpressionEngine software protected under
 copyright dated 2004 - 2011. Please see
 http://expressionengine.com/docs/license.html
=====================================================
 File: ext.member_activation_redirect.php
-----------------------------------------------------
 Purpose: Redirect user after he clicks email confirmation link
=====================================================
*/

if ( ! defined('EXT'))
{
	exit('Invalid file request');
}

class Member_activation_redirect
{

	var $name = 'Member Activation Redirect';
	var $version = '1.0';
	var $description = 'Redirect user after he clicks email confirmation link';
	var $settings_exist = 'y';
	var $docs_url = 'http://barrettnewton.com';
	var $settings = array();	
	
	function Bn_member_activation_redirect($settings='')
	{
		$this->settings = $settings;
	}
	
	function activate_extension()
	{
		global $DB;
		
		$DB->query($DB->insert_string(
			'exp_extensions',
			array(
				'class' => $this->classname,
				'method' => 'member_register_validate_members',
				'hook' => 'member_register_validate_members',
				'settings' => '',
				'priority' => 10,
				'version' => $this->version,
				'enabled' => 'y'
			)
		));
	}
	
	function update_extension($current='')
	{
		global $DB;
		
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}
		
		$DB->query($DB->update_string(
			'exp_extensions',
			array('version' => $this->version),
			array('class' => $this->classname)
		));
	}
	
	function disable_extension()
	{
		global $DB;
	
		$DB->query("DELETE FROM exp_extensions WHERE class = '".$DB->escape_str($this->classname)."'");
	}
	
	function settings()
	{
		$settings = array(
			'redirect' => '',
			'show_user_message' => array('r', array('1' => 'yes', '0' => 'no'), '0')
		);
		
		return $settings;
	}
	
	function member_register_validate_members($member_id)
	{
		global $EXT, $FNS, $LANG, $OUT, $PREFS, $STAT;
		
		if ( ! empty($this->settings['redirect']))
		{
			$redirect = $this->settings['redirect'];
			
			if (stripos($redirect, '{member_id}') !== FALSE)
			{
				$redirect = str_replace('{member_id}', $member_id, $redirect);
			}
			
			if ( ! preg_match('/^https?:\/\/|^\//', $redirect))
			{
				$redirect = $FNS->create_url($redirect);
			}
			
			$STAT->update_member_stats();
			
			if ( ! empty($this->settings['show_user_message']))
			{
				
				$data = array(
					'title' => $LANG->line('mbr_activation'),
					'heading' => $LANG->line('thank_you'),
					'content' => $LANG->line('mbr_activation_success')."\n\n".$LANG->line('mbr_may_now_log_in'),
					'link' => array($redirect, ($PREFS->ini('site_name') == '') ? $LANG->line('back') : stripslashes($PREFS->ini('site_name')))
				);
				
				$OUT->show_message($data);
			}
			else
			{
				$FNS->redirect($redirect);
			}
			
			$EXT->end_script = TRUE;
		}
	}
}

/* End of file ext.bn_member_activation_redirect.php */
/* Location: ./system/extensions/ext.bn_member_activation_redirect.php */