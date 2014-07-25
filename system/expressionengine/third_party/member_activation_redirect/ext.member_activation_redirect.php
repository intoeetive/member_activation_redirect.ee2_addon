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

if ( ! defined('BASEPATH'))
{
	exit('Invalid file request');
}

class Member_activation_redirect_ext
{

	var $name = 'Member Activation Redirect';
	var $version = '1.2';
	var $description = 'Redirect user after he clicks email confirmation link';
	var $settings_exist = 'y';
	var $docs_url = 'https://github.com/intoeetive/member_activation_redirect.ee2_addon/blob/master/README';
	var $settings = array();	
	
	function __construct($settings='')
	{
		$this->EE =& get_instance();      
		$this->settings = $settings;
	}
	
	function activate_extension()
	{

		$settings = array(
			'redirect_url' => '/',
			'auto_login'	=> false
		);
		
		$hooks = array(

            array(
    			'hook'		=> 'member_register_validate_members',
    			'method'	=> 'do_redirect',
    			'priority'	=> 10
    		)
    		
    	);
    	
        foreach ($hooks AS $hook)
    	{
    		$data = array(
        		'class'		=> __CLASS__,
        		'method'	=> $hook['method'],
        		'hook'		=> $hook['hook'],
        		'settings'	=> serialize($settings),
        		'priority'	=> $hook['priority'],
        		'version'	=> $this->version,
        		'enabled'	=> 'y'
        	);
            $this->EE->db->insert('extensions', $data);
    	}	
		
	}
	
	function update_extension($current='')
	{
		if ($current == '' OR $current == $this->version)
    	{
    		return FALSE;
    	}
    	
    	$this->EE->db->where('class', __CLASS__);
    	$this->EE->db->update(
    				'extensions', 
    				array('version' => $this->version)
    	);
	}
	
	function disable_extension()
	{
		$this->EE->db->where('class', __CLASS__);
    	$this->EE->db->delete('extensions');        
	}
	
	function settings()
	{
		$settings = array();

	    $settings['redirect_url']      = array('i', ' width="100%"', '/');
	    $settings['auto_login']      = array('c', array('y'=>'Yes'), array());
		
		return $settings;
	}
	
	
	function _login_by_id($member_id, $multi = FALSE, $temp_password='')
    {

        $site_id = $this->EE->config->item('site_id');
        
        if ($multi == FALSE && ($member_id=='' || $member_id==0))
        {
            return false;
        }
        
        // Auth library will not work here, as we don't have password
        // so using old fashion session routines...

		if ($this->EE->session->userdata['is_banned'] == TRUE)
		{
            $this->EE->output->show_user_error('general', $this->EE->lang->line('not_authorized'));
            return;
		}

		/* -------------------------------------------
		/* 'member_member_login_start' hook.
		/*  - Take control of member login routine
		/*  - Added EE 1.4.2
		*/
			$edata = $this->EE->extensions->call('member_member_login_start');
			if ($this->EE->extensions->end_script === TRUE) return;
		/*
		/* -------------------------------------------*/

		if ( $multi == FALSE )
		{
			$this->EE->db->select('member_id, unique_id, group_id')
                        ->from('exp_members')
                        ->where('member_id', $member_id);
                        
			$query = $this->EE->db->get();

		}
		else
		{
			if ($this->EE->config->item('allow_multi_logins') == 'n' || ! $this->EE->config->item('multi_login_sites') || $this->EE->config->item('multi_login_sites') == '')
			{
                $this->EE->output->show_user_error('general', $this->EE->lang->line('not_authorized'));
                return;
			}

			if ($this->EE->input->get('cur') === FALSE || $this->EE->input->get_post('orig') === FALSE || $this->EE->input->get('orig_site_id') === FALSE)
			{
                $this->EE->output->show_user_error('general', $this->EE->lang->line('not_authorized'));
                return;
			}

			// remove old sessions
			$this->EE->session->gc_probability = 100;
			$this->EE->session->delete_old_sessions();

			// Check Session ID

			$this->EE->db->select('member_id, unique_id')
                        ->from('exp_sessions')
                        ->join('exp_members', 'exp_sessions.member_id = exp_members.member_id', 'left')
                        ->where('session_id', $this->EE->input->get('multi'))
                        ->where('exp_sessions.last_activity > 0');
                        
			$query = $this->EE->db->get();

			if ($query->num_rows() > 0)
			{

    			//start setting cookies
        		$this->EE->functions->set_cookie($this->EE->session->c_expire , time(), 0);
    
    			if ($this->EE->config->item('user_session_type') == 'cs' || $this->EE->config->item('user_session_type') == 's')
    			{
    				$this->EE->functions->set_cookie($this->EE->session->c_session, 
                                                    $this->EE->input->get('multi'), 
                                                    $this->EE->session->session_length);
    			}
    
    			// -------------------------------------------
    			// 'member_member_login_multi' hook.
    			//  - Additional processing when a member is logging into multiple sites
    			//
    				$edata = $this->EE->extensions->call('member_member_login_multi', $query->row());
    				if ($this->EE->extensions->end_script === TRUE) return;
    			//
    			// -------------------------------------------
    
    			//more sites to log in?
                $sites_list		=  explode('|',$this->EE->config->item('multi_login_sites'));
                $sites_list = array_filter($sites_list, 'strlen');
                
                if ($this->EE->input->get('orig') == $this->EE->input->get('cur') + 1)
                {
                    $next = $this->EE->input->get_post('cur') + 2;
                }
                else
                {
                    $next = $this->EE->input->get('cur') + 1;
                }

    			if ( isset($sites_list[$next]) )
    			{
        			$next_qs = array(
        				'ACT'	=> $this->EE->functions->fetch_action_id('Member', 'member_login'),
        				'cur'	=> $next,
        				'orig'	=> $this->EE->input->get('orig'),
        				'multi'	=> $this->EE->input->get('multi'),
        				'orig_site_id' => $this->EE->input->get('orig_site_id')
        			);
        			
        			$next_url = $sites_list[$next].'?'.http_build_query($next_qs);
        
        			return $this->EE->functions->redirect($next_url);
    			}
                else
                {
                    return true;
                }
            }
		}

		// any chance member does not exist? :)
        if ($query->num_rows() == 0)
		{
            $this->EE->output->show_user_error('submission', $this->EE->lang->line('auth_error'));
            return;
		}

		// member pending?
        if ($query->row('group_id') == 4)
		{
            $this->EE->output->show_user_error('general', $this->EE->lang->line('mbr_account_not_active'));
            return;
		}

        
        // allow multi login check?
		if ($this->EE->config->item('allow_multi_logins') == 'n')
		{

			$this->EE->session->gc_probability = 100;
			$this->EE->session->delete_old_sessions();
            
            $ts = time() - $this->EE->session->session_length;
			$this->EE->db->select('ip_address, user_agent')
                        ->from('exp_sessions')
                        ->where('member_id', $member_id)
                        ->where('last_activity > '.$ts)
                        ->where('site_id', $site_id);
            $sess_check = $this->EE->db->get();

			if ($sess_check->num_rows() > 0)
			{
				if ($this->EE->session->userdata['ip_address'] != $sess_check->row('ip_address')  ||  $this->EE->session->userdata['user_agent'] != $sess_check->row('user_agent')  )
				{
					$this->EE->output->show_user_error('general', $this->EE->lang->line('multi_login_warning'));
                    return;
				}
			}
		}

		//start setting cookies
		$this->EE->functions->set_cookie($this->EE->session->c_expire , time(), 0);

		$this->EE->session->create_new_session($member_id);

		// -------------------------------------------
		// 'member_member_login_single' hook.
		//  - Additional processing when a member is logging into single site
		//
			$edata = $this->EE->extensions->call('member_member_login_single', $query->row());
			if ($this->EE->extensions->end_script === TRUE) return;
		//
		// -------------------------------------------

		//stats update
        $enddate = $this->EE->localize->now - (15 * 60);
		$this->EE->db->query("DELETE FROM exp_online_users WHERE site_id = '".$site_id."' AND ((ip_address = '".$this->EE->input->ip_address()."' AND member_id = '0') OR date < ".$enddate.")");
		$data = array(
						'member_id'		=> $member_id,
						'name'			=> ($this->EE->session->userdata['screen_name'] == '') ? $this->EE->session->userdata['username'] : $this->EE->session->userdata['screen_name'],
						'ip_address'	=> $this->EE->input->ip_address(),
						'date'			=> $this->EE->localize->now,
						'anon'			=> '',
						'site_id'		=> $site_id
					);
		$this->EE->db->update('exp_online_users', $data, array("ip_address" => $this->EE->input->ip_address(), "member_id" => $member_id));

		// now, are there any other sites to log in? 
        if ($this->EE->config->item('allow_multi_logins') == 'y' && $this->EE->config->item('multi_login_sites') != '')
		{
			$sites_list		=  explode('|',$this->EE->config->item('multi_login_sites'));
            $sites_list = array_filter($sites_list, 'strlen');
			$current_site	= $this->EE->functions->fetch_site_index();

			if (count($sites_list) > 1 && in_array($current, $sites_list))
			{
				$orig = array_search($current_site, $sites_list);
				$next = ($orig == '0') ? '1' : '0';

    			$next_qs = array(
    				'ACT'	=> $this->EE->functions->fetch_action_id('Member', 'member_login'),
    				'cur'	=> $next,
    				'orig'	=> $orig,
    				'multi'	=> $this->EE->session->userdata['session_id'],
    				'orig_site_id' => $orig
    			);
    			
    			$next_url = $sites_list[$next].'?'.http_build_query($next_qs);
    
    			return $this->EE->functions->redirect($next_url);
			}
		}
        
        // success!!
   
    }
	
	
	function do_redirect($member_id)
	{
		if (isset($this->settings['auto_login']) && $this->settings['auto_login'][0]=='y')
		{
			$this->_login_by_id($member_id);
		}
		
		
		$this->EE->stats->update_member_stats();
		
		if (!isset($this->settings['redirect_url']) || $this->settings['redirect_url']=='')
        {
            $return = $this->EE->functions->fetch_site_index();
        }
        else if (strpos($this->settings['redirect_url'], "http://")!==FALSE || strpos($this->settings['redirect_url'], "https://")!==FALSE)
        {
            $return = $this->settings['redirect_url'];
        }
        else
        {
            $return = $this->EE->functions->create_url($this->settings['redirect_url']);
        }
        
        if (strpos($return, LD.'member_id'.RD)!==false)
        {
        	$return = str_replace(LD.'member_id'.RD, $member_id, $return);
        }
		
		$this->EE->functions->redirect($return);

	}
}

/* End of file ext.bn_member_activation_redirect.php */
/* Location: ./system/extensions/ext.bn_member_activation_redirect.php */