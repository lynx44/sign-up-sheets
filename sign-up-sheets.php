<?php
/*
Plugin Name: Sign-up Sheets (MC)
Description: An online sign-up sheet manager where your users/volunteers can sign up for tasks
Version: 1.0.12
Author: DLS Software Studios - Modified by Matt Clifton
License: GPL2
*/

/*  Copyright 2012  DLS Software Studios

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!class_exists('DLS_SUS_Data')) require_once 'data.php';
if (!class_exists('DLS_SUS_List_Table')) require_once 'list-table.php';

if (!class_exists('DLS_Sign_Up_Sheet')):

class DLS_Sign_Up_Sheet
{
	
    private $data;
    private $table;
    private $plugin_path;
    private $plugin_prefix = 'dls-sus';
    private $admin_settings_slug = 'dls-sus-settings';
    private $email_default_subject = 'Thank you for signing up!';
    private $request_uri;
    private $all_sheets_uri;
    public $db_version = '1.1';
    private $wp_roles;
    public $detailed_errors = false;
    public $go_pro = '<div style="float: right; padding: .6em; text-align: center;" id="dls-sus-go-pro"><a class="button-primary" href="http://www.dlssoftwarestudios.com/sign-up-sheets-wordpress-plugin/" target="_blank">Upgrade to <strong>Sign-up Sheets Pro</strong></a></div>';
    
    public function __construct()
    {
        $this->data = new DLS_SUS_Data();
        
        if (get_option('dls_sus_detailed_errors') === 'true') {
            $this->detailed_errors = true;
            $this->data->detailed_errors = true;
        }
        
        $plugin = plugin_basename(__FILE__);
        
        $this->plugin_path = dirname(__FILE__).'/';
        
        $this->request_uri = $_SERVER['REQUEST_URI'] . ((strstr($_SERVER['REQUEST_URI'], '?') === false) ? '?' : '&amp;');

        $this->all_sheets_uri = substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], "sheet_id="));
        if (substr($this->all_sheets_uri, -1) == '?') $this->all_sheets_uri = substr($this->all_sheets_uri, 0, strlen($this->all_sheets_uri)-1);
        if (substr($this->all_sheets_uri, -1) == '&') $this->all_sheets_uri = substr($this->all_sheets_uri, 0, strlen($this->all_sheets_uri)-1);

        add_shortcode('sign_up_sheet', array(&$this, 'display_sheet'));
	    add_shortcode('sign_up_sheet_form', array(&$this, 'display_form'));
        register_activation_hook(__FILE__, array(&$this, 'activate'));
        register_deactivation_hook( __FILE__, array(&$this, 'deactivate'));

        add_action('admin_head', array(&$this, 'admin_head'));
        if (isset($_GET['page']) && (strpos($_GET['page'], $this->plugin_prefix)) !== false) {
            add_action('admin_footer', array(&$this, 'admin_footer'));
        }
        add_action('wp_enqueue_scripts', array(&$this, 'add_css_and_js_to_frontend'));
        add_action('admin_enqueue_scripts', array(&$this, 'add_scripts_to_admin'));
        add_action('admin_menu', array(&$this, 'admin_menu'));
        add_action('admin_init', array(&$this, 'dup_plugin_check'));
        add_filter("plugin_action_links_$plugin", array(&$this, 'admin_settings_link'));
        add_filter('admin_footer_text', array($this, 'admin_footer_text'), 100);
        $this->activate();
    }
    
    /**
     * Output the volunteer signup form
     * 
     * @param array $atts attributes from shortcode call
     * @return string
     */
    function display_sheet($atts)
    {
        extract( shortcode_atts( array(
            'id' => false,
            'list_title' => 'Current Sign-up Sheets',
        ), $atts ) );
        
        if ($id === false && !empty($_GET['sheet_id'])) $id = $_GET['sheet_id'];
        
        if ($id === false) {
            
            // Display all active
            $return = '<h2>'.$list_title.'</h2>';
            $sheets = $this->data->get_sheets(false, true);
            $sheets = array_reverse($sheets);
            if (empty($sheets)) {
                $return .= '<p>No sheets currently available at this time.</p>';
            } else {
                $return .= '
                    <table class="dls-sus-sheets" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="column-title">Title</th>
                                <th class="column-date">Date</th>
                                <th class="column-open_spots">Open Spots</th>
                                <th class="column-view_link">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                        ';
                        foreach ($sheets AS $sheet) {
                            $open_spots = ($this->data->get_sheet_total_spots($sheet->id) - $this->data->get_sheet_signup_count($sheet->id));
                            $return .= '
                                <tr'.(($open_spots === 0) ? ' class="filled"' : '').'>
                                    <td class="column-title"><a href="'.$this->request_uri.'sheet_id='.$sheet->id.'">'.$sheet->title.'</a></td>
                                    <td class="column-date">'.(($sheet->date == '0000-00-00') ? 'N/A' : date(get_option('date_format'), strtotime($sheet->date))).'</td>
                                    <td class="column-open_spots">'.$open_spots.'</td>
                                    <td class="column-view_link">'.(($open_spots > 0) ? '<a href="'.$this->request_uri.'sheet_id='.$sheet->id.'">View &amp; sign-up &raquo;</a>' : '&#10004; Filled').'</td>
                                </tr>
                            ';
                        }
                        $return .= '
                        </tbody>
                    </table>
                ';
            }
            
        } else {
            
            // Display Individual Sheet
            if (($sheet = $this->data->get_sheet($id)) === false || !empty($sheet->trash)) {
                $return .= '<p>Sign-up sheet not found.</p>';
                return $return;
            } else {
                
                $return .= '
                    <p><a href="'.$this->all_sheets_uri.'">&laquo; View all Sign-up Sheets</a></p>
                    <div class="dls-sus-sheet">
                        <h2>'.$sheet->title.'</h2>
                ';
        	    
			    $submitted = (isset($_POST['mode']) && $_POST['mode'] == 'submitted');
			    $err = 0;
			    $success = false;
			    
			    // Process Sign-up Form
			    if ($submitted) {
				    list( $err, $return, $success ) = $this->submit_form( $err, $return );
			    }
                
                // Display Sign-up Form
			    if (!$submitted || $err) {
				    if (isset($_GET['task_id'])) {
					    $return .= $this->display_signup_form($_GET['task_id']);
					    return $return;
				    }
			    }
			    
			    // Sheet Details
			    if (!$submitted || $success || $err) {
	                $return .= '
                        '.(($sheet->date && $sheet->date != '0000-00-00') ? '<p>Date: '.date(get_option('date_format'), strtotime($sheet->date)).'</p>' : '' ).'
	                    <p>'.$sheet->details.'</p>
	                    <h3>Sign up below...</h3>
	                ';
				    
	                // Tasks
	                if (!($tasks = $this->data->get_tasks($sheet->id))) {
	                    $return .= '<p>No tasks were found.</p>';
	                } else {
	                    $return .= '
	                        <table class="dls-sus-tasks" cellspacing="0">
	                            <thead>
	                                <tr>
	                                    <th>What</th>
	                                    <th>Available Spots</th>
	                                </tr>
	                            </thead>
	                            <tbody>
	                            ';
	                            foreach ($tasks AS $task) {
	                                $return .= '
	                                    <tr>
	                                        <td>'.$task->title.'</td>
	                                        <td>
	                                        ';
	                                        
                                            $i=1;
                                            $signups = $this->data->get_signups($task->id);
                                            foreach ($signups AS $signup) {
                                                if ($i != 1) $return .= '<br />';
                                                $return .= '#'.$i.': <em>'.$signup->firstname.' '.substr($signup->lastname, 0, 1).'.</em>';
                                                $i++;
                                            }
										    for ($i=$i; $i<=$task->qty; $i++) {
											    if ($i != 1) $return .= '<br />';
	                                    	    $return .= '#'.$i.': <a href="'.$this->request_uri.'task_id='.$task->id.'">Sign up &raquo;</a>';
										    }
										    
										    $return .= '
										    </td>
	                                    </tr>
	                                ';
	                            }
	                            $return .= '
	                            </tbody>
	                        </table>
	                    ';
	                }
	            }
                
                $return .= '</div><!-- .dls-sus-sheet -->';
                
            }
        }
        
        return $return;
    }

    /**
     * Output the volunteer signup form
     *
     * @param array $atts attributes from shortcode call
     * @return string
     */
    function display_form($atts)
    {
        extract( shortcode_atts( array(
            'id' => false,
            'list_title' => 'Sign-up',
        ), $atts ) );

        // Display all active
        $return = '';
        $sheets = $this->data->get_sheets(false, true);
        $sheets = array_reverse($sheets);

        $submitted = (isset($_POST['mode']) && $_POST['mode'] == 'submitted');
	    $err = 0;
	    $success = false;

	    $sheet_options = '';
	    $open_sheets_exist = false;

	    foreach ($sheets AS $sheet) {
		    $open_spots = ($this->data->get_sheet_total_spots($sheet->id) - $this->data->get_sheet_signup_count($sheet->id));
		    if($open_spots > 0) {
			    $open_sheets_exist = true;
			    $sheet_options .= '<option value="'.$sheet->id.'">'.$sheet->title.'</option>';
		    }
	    }

	    $fieldspec = $this->data->get_default_fieldspec();

        if(!$submitted) {
	        if($open_sheets_exist) {
		        $return .= '
                    <form name="form1" method="post" action="">
                        <p>		
                            <label for="signup_task_id">Sign-up for</label>
                            <select name="signup_task_id">'.$sheet_options.'</select>
                        </p>
                        <p>
                            <label for="signup_firstname">First Name</label>
                            <input type="text" id="signup_firstname" name="signup_firstname" value="'.((isset($_POST['signup_firstname'])) ? esc_attr($_POST['signup_firstname']) : '').'" />
                        </p>
                        <p>
                            <label for="signup_lastname">Last Name</label>
                            <input type="text" id="signup_lastname" name="signup_lastname" value="'.((isset($_POST['signup_lastname'])) ? esc_attr($_POST['signup_lastname']) : '').'" />
                        </p>
                        <p>
                            <label for="signup_email">E-mail</label>
                            <input type="text" id="signup_email" name="signup_email" value="'.((isset($_POST['signup_email'])) ? esc_attr($_POST['signup_email']) : '').'" />
                        </p>
                        <p>
                            <label for="signup_phone">Phone</label>
                            <input type="text" id="signup_phone" name="signup_phone" value="'.((isset($_POST['signup_phone'])) ? esc_attr($_POST['signup_phone']) : '').'" />
                        </p>';

                        foreach ($fieldspec as $field) {
	                        $return .= '<p>
                                <label for="'.$field->field_name.'">'.$field->label.'</label>
                                <input type="text" id="'.$field->field_name.'" name="'.$field->field_name.'" value="'.((isset($_POST[$field->field_name])) ? esc_attr($_POST[$field->field_name]) : '').'" />
                            </p>';
                        }
                       $return .= '<p>
                            <label for="spam_check">Answer the following: 7 + 1 = </label>
                            <input type="text" id="spam_check" name="spam_check" size="4" value="'.((isset($_POST['spam_check'])) ? esc_attr($_POST['spam_check']) : '').'" />
                        </p>
                        <p class="submit">
                            <input type="hidden" name="mode" value="submitted" />
                            <input type="submit" name="Submit" class="button-primary" value="'.esc_attr('Sign me up!').'" />
                        </p>
                    </form>
                ';
            } else {
	            $return .= '<div class="dls_sus_all_sheets_full_message"><em>Sorry, all sign-up slots have been filled.</em></div>';
            }

        } else {
            list( $err, $return, $success ) = $this->submit_form( $err, $return );
        }

        return $return;
    }

    /**
     * Display signup form
     *
     * @param int $task_id
     * @return string
     */
	public function display_signup_form($task_id)
	{	
        $task = $this->data->get_task($task_id);
		$return = '
			<h3>Sign-up below</h3>
            <p>You are signing up for... <em>'.$task->title.'</em></p>
			<form name="form1" method="post" action="">
				<p>
					<label for="signup_firstname">First Name</label>
					<input type="text" id="signup_firstname" name="signup_firstname" value="'.((isset($_POST['signup_firstname'])) ? esc_attr($_POST['signup_firstname']) : '').'" />
				</p>
				<p>
					<label for="signup_lastname">Last Name</label>
					<input type="text" id="signup_lastname" name="signup_lastname" value="'.((isset($_POST['signup_lastname'])) ? esc_attr($_POST['signup_lastname']) : '').'" />
				</p>
				<p>
					<label for="signup_email">E-mail</label>
					<input type="text" id="signup_email" name="signup_email" value="'.((isset($_POST['signup_email'])) ? esc_attr($_POST['signup_email']) : '').'" />
				</p>
                <p>
                    <label for="signup_phone">Phone</label>
                    <input type="text" id="signup_phone" name="signup_phone" value="'.((isset($_POST['signup_phone'])) ? esc_attr($_POST['signup_phone']) : '').'" />
                </p>
				<p>
					<label for="spam_check">Answer the following: 7 + 1 = </label>
					<input type="text" id="spam_check" name="spam_check" size="4" value="'.((isset($_POST['spam_check'])) ? esc_attr($_POST['spam_check']) : '').'" />
				</p>
                <p class="submit">
                    <input type="hidden" name="signup_task_id" value="'.esc_attr($_GET['task_id']).'" />
                	<input type="hidden" name="mode" value="submitted" />
                	<input type="submit" name="Submit" class="button-primary" value="'.esc_attr('Sign me up!').'" />
                    or <a href="'.$this->all_sheets_uri.((strstr($this->all_sheets_uri, '?') === false) ? '?' : '&amp;').'sheet_id='.$_GET['sheet_id'].'">&laquo; go back to the Sign-Up Sheet</a>
                </p>
			</form>
		';
        $return .= '</div><!-- .dls-sus-sheet -->';
        return $return;
	}
        
    /**
     * Admin Menu
     */
    public function admin_menu()
    {
        add_options_page('Sign-up Sheets Settings', 'Sign-up Sheets', 'manage_options', $this->admin_settings_slug, array(&$this, 'admin_options'));

        // Utility Menu
        add_menu_page('Sign-up Sheets', 'Sign-up Sheets', 'manage_signup_sheets', $this->admin_settings_slug.'_sheets', array(&$this, 'admin_sheet_page'), plugins_url( '/images/admin-icon.png', __FILE__ ));
        add_submenu_page($this->admin_settings_slug.'_sheets', 'Sign-up Sheets ', 'All Sheets', 'manage_signup_sheets', $this->admin_settings_slug.'_sheets', array(&$this, 'admin_sheet_page'));
        add_submenu_page($this->admin_settings_slug.'_sheets', 'Add New Sheet', 'Add New', 'manage_signup_sheets', $this->admin_settings_slug.'_modify_sheet', array(&$this, 'admin_modify_sheet_page'));
    }

    /**
     * Admin Page: Options/Settings
     */
    function admin_options()
    {
        if (!current_user_can('manage_options') && !current_user_can('manage_signup_sheets'))  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        $options = array(
            'Confirmation E-mail Settings',
            array('Subject', 'dls_sus_email_subject', 'text', '(If blank, defaults to... "'.$this->email_default_subject.'")'),
            array('From E-mail Address', 'dls_sus_email_from', 'text', '(If blank, defaults to WordPress email on file under Settings > General)'),
            array('Sheet Administrator Addresses', 'dls_sus_email_admin_emails', 'text', ''),
            array('Display detailed errors', 'dls_sus_detailed_errors', 'checkbox', '(It is recommended to leave this un-checked for production sites)'),
        );
        $hidden_field_name = 'submit_hidden';
        
        echo '
            <div class="wrap dls_sus">
                <div id="icon-dls-sus" class="icon32"><br /></div>
                '.$this->go_pro.'
                <h2>' . __( 'Sign-up Sheets Settings', 'dls-sus-menu' ) . '</h2>
                <form name="form1" method="post" action="">
                    ';
                    
                    $num_saved = 0;
                    foreach ($options AS $key=>$o) {
                        if (!is_array($o)) {
                            if ($key !== 0) echo '</table>';
                            echo '<h3>'.$o.'</h3>';
                            echo '<table class="form-table">';
                            continue;
                        }
                        if ($key === 0) echo '<table class="form-table">';
                        $opt_label = $o[0];
                        $opt_name = $o[1];
                        $opt_type = $o[2];
                        $opt_note = $o[3];
                        $opt_val = get_option($opt_name);
                            
                        if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
                            $opt_val = $_POST[$opt_name];
                            update_option($opt_name, $opt_val);
                            $num_saved++;
                            if ($num_saved === 1) echo '<div class="updated"><p><strong>'.__('Settings saved.', 'dls-sus-menu').'</strong></p></div>';
                        }
                        
                        echo '
                            <tr valign="top">
                                <th scope="row">'.__($opt_label.":", 'dls-sus-menu').'</th>
                                <td>
                                    ';
                                    
                                    switch ($opt_type) {
                                        case 'text':
                                            echo '
                                                <input type="text" name="'.$opt_name.'" value="'.esc_attr($opt_val).'" size="20">
                                            ';
                                            break;
                                        case 'checkbox':
                                            echo '
                                                <input type="checkbox" name="'.$opt_name.'" value="true"'.(($opt_val === 'true') ? ' checked="checked"' : '').' size="20">
                                            ';
                                            break;
                                    }
                                    
                                    echo '
                                    <span class="description">'.$opt_note.'</span>
                                </tr>
                            </tr>
                        ';

                    }
                    
                    echo '
                    </table>
                    <hr />
                    <p class="submit">
                        <input type="hidden" name="'.$hidden_field_name.'" value="Y">
                    	<input type="submit" name="Submit" class="button-primary" value="'.esc_attr('Save Changes').'" />
                    </p>

                </form>
            </div><!-- .wrap -->
        ';
    }

    /**
     * Admin Page: Sheets
     */
    function admin_sheet_page()
    {
        if (!current_user_can('manage_options') && !current_user_can('manage_signup_sheets'))  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        $err = false;
        $success = false;
            
        // Remove signup record
        if (isset($_GET['action']) && $_GET['action'] == 'clear') {
            try {
                $result = $this->data->delete_signup($_GET['signup_id']);
                $success = true;
                if ($result > 0) echo '<div class="updated"><p>Spot has been cleared.</p></div>';
            } catch (DLS_SUS_Data_Exception $e) {
                $err = true;
                echo '<div class="error"><p>Error clearing spot (ID #'.esc_attr($_GET['signup_id']).')</p></div>';
            }
        }
        
        // Set Actons
        $trash = (!empty($_GET['action']) && $_GET['action'] == 'trash');
        $untrash = (!empty($_GET['action']) && $_GET['action'] == 'untrash');
        $delete = (!empty($_GET['action']) && $_GET['action'] == 'delete');
        $copy = (!empty($_GET['action']) && $_GET['action'] == 'copy');
        $view_signups = (!empty($_GET['action']) && $_GET['action'] == 'view_signups');
        $edit = (!$trash && !$untrash && !$delete && !$copy && !empty($_GET['sheet_id']));
        
        echo '<div class="wrap dls_sus">';
        echo '<div id="icon-dls-sus" class="icon32"><br /></div>';
        echo $this->go_pro;
        echo ($edit || $view_signups) ? '<h2>Sheet Details</h2>' : '<h2>Sign-up Sheets 
            <a href="?page='.$this->admin_settings_slug.'_modify_sheet" class="add-new-h2">Add New</a>
            <a href="'.plugins_url( './' , __FILE__ ).'export.php" class="add-new-h2">Export All as CSV</a>
            </h2>
        ';
        
        if ($untrash) {
            try {
                $result = $this->data->update_sheet(array('sheet_trash'=>false), $_GET['sheet_id']);
                echo '<div class="updated"><p>Sheet has been restored.</p></div>';
            } catch (DLS_SUS_Data_Exception $e) {
                echo '<div class="error"><p>Error restoring sheet.'. (($this->detailed_errors === true) ? '.. '.print_r(mysql_error(), true) : '').'</p></div>';
            }
        } elseif ($trash) {
            try {
                $result = $this->data->update_sheet(array('sheet_trash'=>true), $_GET['sheet_id']);
                echo '<div class="updated"><p>Sheet has been moved to trash.</p></div>';
            } catch (DLS_SUS_Data_Exception $e) {
                echo '<div class="error"><p>Error moving sheet to trash.'. (($this->detailed_errors === true) ? '.. '.print_r(mysql_error(), true) : '').'</p></div>';
            }
        } elseif ($delete) {
            try {
                $result = $this->data->delete_sheet($_GET['sheet_id']);
                echo '<div class="updated"><p>Sheet has been permanently deleted.</p></div>';
            } catch (DLS_SUS_Data_Exception $e) {
                echo '<div class="error"><p>Error permanently deleting sheet.'. (($this->detailed_errors === true) ? '.. '.print_r(mysql_error(), true) : '').'</p></div>';
            }
        } elseif ($copy) {
            try {
                $new_id = $this->data->copy_sheet($_GET['sheet_id']);
                echo '<div class="updated"><p>Sheet has been copied to new sheet ID #'.$new_id.' (<a href="?page='.$this->admin_settings_slug.'_modify_sheet&amp;sheet_id='.$new_id.'">Edit</a>).</p></div>';
            } catch (DLS_SUS_Data_Exception $e) {
                echo '<div class="error"><p>Error copying sheet.'. (($this->detailed_errors === true) ? '.. '.print_r(mysql_error(), true) : '').'</p></div>';
            }
        }
        
        if ($edit || $view_signups) {
            
            // View Single Sheet
            if (!($sheet = $this->data->get_sheet($_GET['sheet_id']))) {
                echo '<p class="error">No sign-up sheet found.</p>';
            } else {

                echo '
                <h3>'.$sheet->title.'</h3>
                
                <p>Date: '.(($sheet->date == '0000-00-00') ? 'N/A' : date(get_option('date_format'), strtotime($sheet->date))).'</p>
                
                <p>'.$sheet->details.'</p>
                
                <h4>Signups</h4>
                ';



                // Tasks
                if (!($tasks = $this->data->get_tasks($sheet->id))) {
                    echo '<p>No tasks were found.</p>';
                } else {
	                $default_fieldspec = $this->data->get_default_fieldspec();
                    echo '
                        <table class="wp-list-table widefat" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>What</th>
                                    <th>Name</th>
                                    <th>E-mail</th>
                                    <th>Phone</th>';
                                    foreach($default_fieldspec as $field) {
                                        echo '<th>'.$field->label.'</th>';
                                    }
                                    echo '<th>Reminded *</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            ';
                            foreach ($tasks AS $task) {
                                echo '
                                    <tr>
                                        ';

                                        $i=1;
                                        $signups = $this->data->get_signups($task->id);
                                        foreach ($signups AS $signup) {
	                                        $signup_fields = $this->data->get_signup_fields( $signup->id );

	                                        $signup_fields_by_fieldspec_id = array();
	                                        foreach($signup_fields as $signup_field) {
		                                        $signup_fields_by_fieldspec_id[$signup_field->fieldspec_id] = $signup_field;
                                            }

	                                        $ordered_fieldspec_ids = array_map( function ( $f ) {
		                                        return $f->id;
	                                        }, $default_fieldspec );

	                                        $ordered_signup_fields = array_replace( array_flip( $ordered_fieldspec_ids ), $signup_fields_by_fieldspec_id );

                                            echo '
                                                <tr>
                                                    <td>'.(($i === 1) ? $task->title : '' ).'</td>
                                                    <td>#'.$i.': <em>'.$signup->firstname.' '.$signup->lastname.'</em>
                                                    <td>'.$signup->email.'</td>
                                                    <td>'.$signup->phone.'</td>';
                                                    foreach($ordered_signup_fields as $d_signup_field) {
	                                                    echo '<td>'.$d_signup_field->value.'</td>';
                                                    }
                                              echo '<td>&nbsp;</td>
                                                    <td><span class="delete"><a href="?page='.$this->admin_settings_slug.'_sheets&amp;sheet_id='.$_GET['sheet_id'].'&amp;signup_id='.$signup->id.'&amp;action=clear" onclick="return confirm(\'Are you sure you want to clear this spot?\');">Clear Spot</a></span></td>
                                                </tr>
                                            ';
                                            $i++;
                                        }
                                        // Remaining empty spots
                                        for ($i=$i; $i<=$task->qty; $i++) {
                                            echo '
                                                <tr>
                                                    <td>'.(($i === 1) ? $task->title : '' ).'</td>
                                                    <td colspan="5">#'.$i.': (empty)</td>
                                                </tr>
                                            ';
                                        }
                                        
                                        echo '
                                    </tr>
                                ';
                                $last_task_title = $task->title;
                            }
                            echo '
                            </tbody>
                        </table>
                        <p>* Reminders are only available with <a href="https://www.dlssoftwarestudios.com/sign-up-sheets-wordpress-plugin/">Sign-up Sheets Pro</a>.</p>
                    ';
                }
                
            }
            
        } else {
            
            //View All
            $show_trash = (isset($_GET['sheet_status']) && $_GET['sheet_status'] == 'trash');
            $show_all = !$show_trash;
            
            echo '
            <ul class="subsubsub">
                <li class="all"><a href="admin.php?page='.$this->admin_settings_slug.'_sheets"'.(($show_all) ? ' class="current"' : '').'>All <span class="count">('.$this->data->get_sheet_count().')</span></a> |</li>
                <li class="trash"><a href="admin.php?page='.$this->admin_settings_slug.'_sheets&amp;sheet_status=trash"'.(($show_trash) ? ' class="current"' : '').'>Trash <span class="count">('.$this->data->get_sheet_count(true).')</span></a></li>
            </ul>
            <form method="get" action="">
            ';
                    
            // Get and display data
            $this->table = new DLS_SUS_List_Table($show_trash);
            $this->table->prepare_items();
            $this->table->display();
            
            echo '</form><!-- #sheet-filter -->';
        }
        
        echo '</div><!-- .wrap -->';
        
    }

    /**
     * Admin Page: Add a Sheet Page
     */
    function admin_modify_sheet_page()
    {
        if (!current_user_can('manage_options') && !current_user_can('manage_signup_sheets'))  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        // Set mode vars
        $edit = (empty($_GET['sheet_id'])) ? false : true;
        $add = ($edit) ? false : true;
        $submitted = (isset($_POST['mode']) && $_POST['mode'] == 'submitted');
        $err = 0;
        
        // Process form if submitted
        if($submitted) {
            
            // Sheet
            try {
                
                if ($add) $result = $this->data->add_sheet($_POST);
                else if ($edit) $result = $this->data->update_sheet($_POST, $_GET['sheet_id']);

                // Tasks
                echo '<div class="updated"><p><strong>'.__('Sheet saved.', 'dls-sus-menu').'</strong></p></div>';
                $sheet_id = ($add) ? $this->data->wpdb->insert_id : $_GET['sheet_id'];
                $tasks = $this->data->get_tasks($_GET['sheet_id']);
                $tasks_to_delete = array();
                $tasks_to_update = array();
                $qty_to_update = array();
                $task_err = 0;
                $keys_to_process = array();
                foreach ($_POST['task_title'] AS $key=>$value) {
                    $keys_to_process[] = $key;
                }
                
                // Queue for removal: tasks where the fields were emptied out
                for ($i = 0; $i < $count; $i++) {
                    if (empty($_POST['task_title'][$i])) {
                        if (!empty($_POST['task_id'][$i])) $tasks_to_delete[] = $_POST['task_id'][$i];
                        continue;
                    } else {
                        $tasks_to_update[] = $_POST['task_id'][$i];
                        $signup_count = count($this->data->get_signups($_POST['task_id'][$i]));
                        if ($signup_count > $_POST['task_qty'][$i]) {
                            $err++;
                            if (!empty($err)) echo '<div class="error"><p><strong>'.__('The number of spots for task "'.$task->title.'" cannot be set below '.$signup_count.' because it currently has '.$signup_count.' '.(($signup_count > 1) ? 'people' : 'person').' signed up.  Please clear some spots first before updating this task.').'</strong></p></div>';
                        }
                    }
                }
                // Queue for removal: tasks that are no longer in the list
                foreach ($tasks AS $task) {
                    if (!in_array($task->id, $_POST['task_id'])) {
                        $tasks_to_delete[] = $task->id;
                        $signup_count = count($this->data->get_signups($task->id));
                        if ($signup_count > 0) {
                            $err++;
                            if (!empty($err)) echo '<div class="error"><p><strong>'.__('The task "'.$task->title.'" cannot be removed because it has '.$signup_count.' '.(($signup_count > 1) ? 'people' : 'person').' signed up.  Please clear all spots first before removing this task.').'</strong></p></div>';
                        }
                    }
                }
                
                if (empty($err)) {
                    $i = 0;
                    foreach ($keys_to_process AS $key) {
                        if (empty($_POST['task_title'][$key])) continue;
                        foreach ($this->data->tables['task']['allowed_fields'] AS $field=>$nothing) {
                            $task_data['task_'.$field] = $_POST['task_'.$field][$key];
                            $task_data['task_position'] = $i;
                        }
                        $task_data['task_sheet_id'] = $sheet_id;
                        if (empty($_POST['task_id'][$key])) {
                            if (($result = $this->data->add_task($task_data, $sheet_id)) === false) {
                                $err++;
                            }
                        } else {
                            if (($result = $this->data->update_task($task_data, $_POST['task_id'][$key])) === false) {
                                $err++;
                            }
                        }
                        $i++;
                    }
                    
                    if (!empty($err)) echo '<div class="error"><p><strong>'.__('Error saving '.$err.' task'.(($err > 1) ? 's' : '').'.', 'dls-sus-menu').'</strong></p></div>';
                    
                    // Delete unused tasks
                    foreach ($tasks_to_delete AS $task_id) {
                        if ($this->data->delete_task($task_id) === false) {
                            echo '<div class="error"><p><strong>'.__('Error removing a task.', 'dls-sus-menu').'</strong></p></div>';
                        }
                    }
                }
                
            } catch (DLS_SUS_Data_Exception $e) {
                $err++;
                echo '<div class="error"><p><strong>'.__($e->getMessage()).'</strong></p></div>';
            }
            
        }
        
        // Set field values for form
        $fields = (isset($_POST) && !$add) ? $_POST : null;
        if ($edit && empty($err)) {
            // Pull from DB instead
            if ($sheet = $this->data->get_sheet($_GET['sheet_id'])) {
                $sheet_fields = array();
                foreach($sheet AS $k=>$v) $sheet_fields['sheet_'.$k] = $v;
            }
            if ($tasks = $this->data->get_tasks($_GET['sheet_id'])) {
                $task_fields = array();
                foreach ($tasks AS $task) {
                    $task_fields['task_id'][] = $task->id;
                    $task_fields['task_title'][] = $task->title;
                    $task_fields['task_qty'][] = $task->qty;
                }
            }
            $fields = array_merge((array)$sheet_fields, (array)$task_fields);
        }
        
        // Display Form
        echo '<div class="wrap dls_sus">';
        echo '<div id="icon-dls-sus" class="icon32"><br /></div>';
        echo $this->go_pro;
        echo '<h2>'.(($add) ? 'Add' : 'Edit').' Sign-up Sheet</h2>';
        $this->display_sheet_form($fields);
        echo '</div><!-- .wrap -->';
    }
    
    /**
     * Display the form to add/edit a sheet
     * 
     * @param    array   fields to pass into form, if any
     */
    private function display_sheet_form($f=array())
    {
        $count = (isset($f['task_title'])) ? count($f['task_title']) : 3;
        if ($count < 3) $count = 3;
        
        echo '
            <form name="add_sheet" id="dls-sus-modify-sheet" method="post" action="">
                <p>
                    <label for="sheet_title">Title:</label>
                    <input type="text" id="sheet_title" name="sheet_title" value="'.((isset($f['sheet_title']) ? esc_attr($f['sheet_title']) : '')).'" size="60">
                    
                    <label for="sheet_date">Date:</label>
                    <input type="text" id="sheet_date" name="sheet_date" value="'.((isset($f['sheet_date']) && $f['sheet_date'] != '0000-00-00') ? date('m/d/Y', strtotime($f['sheet_date'])) : '').'" size="20" class="dls-sus-datepicker">
                </p>
                <p>
                    <label for="sheet_details">Details:</label><br />
                    <textarea id="sheet_details" name="sheet_details" style="width: 100%;" rows="3">'.((isset($f['sheet_details']) ? esc_attr($f['sheet_details']) : '')).'</textarea>
                </p>
                <h3>Tasks</h3>
                <ul class="tasks">
                ';
                
                for ($i = 0; $i < $count; $i++) {
                    echo '
                        <li id="task-'.$i.'">
                            <input type="text" name="task_title['.$i.']" value="'.((isset($f['task_title'][$i]) ? esc_attr($f['task_title'][$i]) : '')).'" size="20">&nbsp;&nbsp;&nbsp;
                            # of People Needed: <input type="text" name="task_qty['.$i.']" value="'.((isset($f['task_qty'][$i]) ? $f['task_qty'][$i] : '')).'" size="5">
                            <input type="hidden" name="task_id['.$i.']" value="'.((isset($f['task_id'][$i]) ? $f['task_id'][$i] : '')).'">
                            <a href="#" class="add-task-after">+</a>
                            <a href="#" class="remove-task">-</a>
                        </li>
                    ';
                }
                
                echo '
                </ul>
                
                <ul></ul>
                <hr />
                <p class="submit">
                    <input type="hidden" name="mode" value="submitted" />
                    <input type="submit" name="Submit" class="button-primary" value="'.esc_attr('Save').'" />
                </p>
            </form>
        ';
    }
    
    /**
     * Send email when user signs up
     * 
     * @param    string  $to the email to send the message to
     * @param    int     $task_id
     * @return   bool
     */
    public function send_mail($to, $task_id, $name, $reply_to = null)
    {
        $task = $this->data->get_task($task_id);
        $sheet = $this->data->get_sheet($task->sheet_id);
        
        $from = get_option('dls_sus_email_from');
        if (empty($from)) $from = get_bloginfo('admin_email');
        if ($reply_to == null)
            $reply_to = $from;
        
        $subject = get_option('dls_sus_email_subject');
        if (empty($subject)) $subject = $this->email_default_subject;
        
        $headers = "From: ".get_bloginfo('name')." <$from>\n" .
                "Reply-To: $reply_to\n" .
                "Content-Type: text/plain; charset=iso-8859-1\n";
        $time_string = __('Y-m-d G:i:s');
        $time = date_i18n( __($time_string), current_time('timestamp') );
        $ip = preg_replace( '/[^0-9., ]/', '', $_SERVER['REMOTE_ADDR'] );

        $message =
            "$name, \n\n".
            "This message was sent to confirm that you signed up for...\n\n".
            (($sheet->date != '0000-00-00') ? "Date: ".date(get_option('date_format'), strtotime($sheet->date))."\n" : "").
            "Event: $sheet->title \n".
            "What: $task->title \n\n".
            "If you need to change this or if this was sent in error, please contact us at $from\n\n".
            "Thanks,\n".
            get_bloginfo('name')."\n".
            get_bloginfo('url');

        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Add settings link on plugin page
     * 
     * @param string $links
     * @return string
     */
    function admin_settings_link($links)
    { 
        $settings_link = '<a href="options-general.php?page='.$this->admin_settings_slug.'">Settings</a>'; 
        array_unshift($links, $settings_link); 
        return $links;
    }
    
    /**
     * Enqueue plugin css and js files
     */
    function add_css_and_js_to_frontend()
    {
        wp_register_style('dls-sus-style', plugins_url('css/style.css', __FILE__));
        wp_enqueue_style('dls-sus-style');
    }
    
    /**
     * Enqueue plugin's admin scripts
     */
    function add_scripts_to_admin()
    {
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_style( 'jquery.ui.theme', plugins_url( '/css/smoothness/jquery.ui.datepicker.css', __FILE__ ) );
    }
    
    /**
     * Enqueue plugin css and js files
     */
    function admin_head()
    {
        wp_enqueue_style('dls-sus-admin', plugins_url('css/admin.css', __FILE__));
        
        echo '
            <style type="text/css">
                ';

                // Remove spacing if no bulk actions
                if (count(DLS_SUS_List_Table::get_bulk_actions()) === 0) {
                    echo '
                    .dls_sus .tablenav.top {
                        clear: none;
                        float: right;
                        width: 50%;
                        }
                    ';
                }
                
                echo '
            </style>
        ';
    }
    
    /**
     * Add to admin footer
     */
    public function admin_footer()
    {
        
        ?>
        <script type="text/javascript">
        (function($) {    
            $(document).ready(function(){
                if ($('.tasks LI').is('*')) {
                    var last_css_id = $(".tasks LI").last().attr('id');
                    var row_key = last_css_id.substr(last_css_id.indexOf("-") + 1);
                    $(".add-task-after").live('click', function() {
                        row_key++;
                        var new_row = '<li id="task-' + row_key + '">' +
                            '<input type="text" name="task_title[' + row_key + ']" value="" size="20">&nbsp;&nbsp;&nbsp;' +
                            ' # of People Needed: <input type="text" name="task_qty[' + row_key + ']" value="" size="5">' +
                            ' <input type="hidden" name="task_id[' + row_key + ']" value="">' +
                            ' <a href="#" class="add-task-after">+</a>' +
                            ' <a href="#" class="remove-task">-</a>' +
                        '</li>';
                        $(this).parent("LI").after(new_row);
                        return false;
                    });
                    $(".remove-task").live('click', function() {
                        if ($('.tasks LI').length == 1) {
                            $(this).prev().trigger('click');
                        }
                        $(this).parent("LI").remove();
                        return false;
                    });
                }
                
                $('.dls-sus-datepicker').datepicker({});
                
                $(".tasks").sortable({
                    distance: 5,
                    opacity: 0.6,
                    cursor: 'move'
                });
                
            });
        })(jQuery); 
        </script>
        <?php
        
    }

    /**
     * Override WordPress Footer
     *
     * @param string $admin_footer_text
     * @return string
     */
    public function admin_footer_text($admin_footer_text)
    {
        if (isset($_REQUEST['page']) && strpos($_REQUEST['page'], 'dls-sus-') === 0) {
            $admin_footer_text = '
                <a href="' . __('https://www.dlssoftwarestudios.com/sign-up-sheets-wordpress-plugin/', 'dls-sus') . '" id="dls-sus-footer-logo" title="' . __('DLS Software Studios', 'dls-sus') . '" target="_blank">' . __('DLS Software Studios', 'dls-sus' ) . '</a>
                <span>' . sprintf(__('<a href="%s" target="_blank">Get Sign-up Sheets Pro &raquo;</a>', 'dls-sus' ), __( 'https://www.dlssoftwarestudios.com/sign-up-sheets-wordpress-plugin/', 'dls-sus')) . '</span>
            ';
        }

        return $admin_footer_text;
    }

    /**
     * Duplicate plugin check
     */
    public function dup_plugin_check()
    {
        if (is_plugin_active('sign-up-sheets-pro/sign-up-sheets.php')) {
            deactivate_plugins(plugin_basename(__FILE__));
            add_action('admin_notices', array($this, 'dup_plugin_admin_notice'));
        }
    }

    /**
     * Duplicate plugin admin notice
     */
    function dup_plugin_admin_notice()
    {
        echo '
            <div id="'.$this->plugin_prefix.'-dup-plugin" class="error">
                <p>More than one Sign-up Sheets plugin was found.  Please only activate one at a time.</p>
            </div>
        ';
    }
    
    /**
     * Activate the plugin
     */
    public function activate()
    {
        // Database Tables
        $sql = "CREATE TABLE {$this->data->tables['sheet']['name']} (
            id INT NOT NULL AUTO_INCREMENT,
            title VARCHAR(200) NOT NULL,
            details LONGTEXT NOT NULL,
            date DATE NOT NULL,
            trash BOOL NOT NULL DEFAULT FALSE,
            UNIQUE KEY id (id)
        );";
	    $sql .= "CREATE TABLE {$this->data->tables['default_fieldspec']['name']} (
            id INT NOT NULL AUTO_INCREMENT,
            label VARCHAR(100) NOT NULL,
            field_name VARCHAR(100) NOT NULL,
            required TINYINT(1) NOT NULL DEFAULT 1,
            position INT NOT NULL,
            UNIQUE KEY id (id)
        );";
        $sql .= "CREATE TABLE {$this->data->tables['task']['name']} (
            id INT NOT NULL AUTO_INCREMENT,
            sheet_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            qty INT NOT NULL DEFAULT 1,
            position INT NOT NULL,
            UNIQUE KEY id (id)
        );";
        $sql .= "CREATE TABLE {$this->data->tables['signup']['name']} (
            id INT NOT NULL AUTO_INCREMENT,
            task_id INT NOT NULL,
            firstname VARCHAR(100) NOT NULL,
            lastname VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            UNIQUE KEY id (id)
        );";
	    $sql .= "CREATE TABLE {$this->data->tables['signup_field']['name']} (
            id INT NOT NULL AUTO_INCREMENT,
            signup_id INT NOT NULL,
            fieldspec_id INT NOT NULL,
            value VARCHAR(255) NOT NULL,
            UNIQUE KEY id (id)
        );";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        add_option("dls_sus_db_version", $this->db_version);
        
        // Add custom role and capability
        add_role('signup_sheet_manager', 'Sign-up Sheet Manager');
        $role = get_role('signup_sheet_manager');
        if (is_object($role)) {
            $role->add_cap('manage_signup_sheets');
            $role->add_cap('read');
        }
        $role = get_role('administrator');
        if (is_object($role)) {
            $role->add_cap('manage_signup_sheets');
        }
    }
    
    /**
     * Deactivate the plugin
     */
    public function deactivate()
    {
        // Remove custom role and capability
        $role = get_role('signup_sheet_manager');
        if (is_object($role)) {
            $role->remove_cap('manage_signup_sheets');
            $role->remove_cap('read');
            remove_role('signup_sheet_manager');
        }
        $role = get_role('administrator');
        if (is_object($role)) {
            $role->remove_cap('manage_signup_sheets');
        }
    }

	/**
	 * @param $err
	 * @param $return
	 *
	 * @return array
	 */
	private function submit_form( $err, $return ) {

//Error Handling
		if (
			empty( $_POST['signup_firstname'] )
			|| empty( $_POST['signup_lastname'] )
			|| empty( $_POST['signup_email'] )
			|| empty( $_POST['signup_phone'] )
			|| empty( $_POST['spam_check'] )
		) {
			$err ++;
			$return .= '<p class="dls-sus error">' . __( 'Please complete all fields.' ) . '</p>';
		} elseif ( empty( $_POST['spam_check'] ) || ( ! empty( $_POST['spam_check'] ) && trim( $_POST['spam_check'] ) != '8' ) ) {
			$err ++;
			$return .= '<p class="dls-sus error">' . __( 'Oh dear, 7 + 1 does not equal ' . esc_attr( $_POST['spam_check'] ) . '. Please try again.' ) . '</p>';
		}

		// Add Signup
		if ( ! $err ) {
			try {
				$task_id = $_GET['task_id'] ?: $_POST['signup_task_id'];
				$this->data->add_signup( $_POST, $task_id );
				$success = true;
				$return  .= '<p class="dls-sus updated">' . __( 'You have been signed up!' ) . '</p>';
				if ( $this->send_mail( $_POST['signup_email'], $task_id, $_POST['signup_firstname'] . " " . $_POST['signup_lastname'] ) === false ) {
					$return .= 'ERROR SENDING EMAIL';
				}
				if ( $this->send_mail( get_option('dls_sus_email_admin_emails'), $task_id, $_POST['signup_firstname']  . " " . $_POST['signup_lastname'], $_POST['signup_email'] ) === false ) {
					$return .= 'ERROR SENDING EMAIL';
				}
			} catch ( DLS_SUS_Data_Exception $e ) {
				$err ++;
				$return .= '<p class="dls-sus error">' . __( $e->getMessage() ) . '</p>';
			}
		}

		return array( $err, $return, $success );
	}
	
}

$dls_sus = new DLS_Sign_Up_Sheet();

endif;