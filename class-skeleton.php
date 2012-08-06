<?php
/*
	Copyright 2012 Sorin Iclanzan  (email : sorin@hel.io)

	This file is part of Skeleton.

	Skeleton is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	Backup is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with Skeleton. If not, see http://www.gnu.org/licenses/gpl.html.
*/

/**
 * Skeleton class
 *
 * This class is meant to be extended in order to more easily create WordPress plugins.
 */
abstract class Skeleton {

	/**
	 * Stores the name of the plugin as the parent directory name
	 * with only lowercase alphanumeric characters, dashes and underscores.
	 * This is used as the text domain and option name.
	 *
	 * @var string
	 * @access protected
	 */
	public $plugin_name;

	/**
	 * Stores the current plugin version string.
	 * You need to set this in the child class.
	 *
	 * @var string
	 * @access public
	 */
	public $plugin_version = '';

	/**
	 * Stores the plugin directory path.
	 *
	 * @var string
	 * @access protected
	 */
	protected $plugin_dir = '';

	/**
	 * Stores the path of the plugin's main file.
	 *
	 * @var string
	 * @access protected
	 */
	protected $plugin_file = '';

	/**
	 * Stores the minimum WordPress version required.
	 *
	 * @var string
	 * @access public
	 */
	public $min_wp_version = '';

	/**
	 * Stores the list of action hooks to register.
	 *
	 * @var array
	 * @access protected
	 */
	protected $action_hooks = array();

	/**
	 * Stores a list of AJAX actions to register for users.
	 * @var array
	 * @access protected
	 */
	protected $ajax_actions = array();

	/**
	 * Stores a list of AJAX actions to register for visitors.
	 * @var array
	 * @access protected
	 */
	protected $ajax_nopriv_actions = array();

	/**
	 * Stores the plugin's default options.
	 *
	 * @var array
	 * @var protected
	 */
	protected $default_options = array();

	/**
	 * Stores the plugin's options.
	 *
	 * @var array
	 * @var protected
	 */
	protected $options = array();

	/**
	 * Stores admin notices.
	 *
	 * @var WP_Error
	 * @var protected
	 */
	protected $admin_notices;

	/**
	 * Stores errors for debugging.
	 *
	 * @var WP_Error
	 * @var protected
	 */
	protected $errors;

	/**
	 * Override this method to extend the constructor.
	 */
	protected function construct() {

	}

	/**
	 * Override this method to add actions to the 'plugins_loaded' hook.
	 */
	protected function loaded() {

	}

	/**
	 * Override this method to add actions to the 'init' hook.
	 */
	protected function initialize() {

	}

	/**
	 * Override this method to add actions and checks that run on plugin activation.
	 *
	 * @return string Return a string describing why plugin activation failed. On success return an empty string.
	 */
	protected function activate() {
		return '';
	}

	/**
	 * Override this method to add actions that run on plugin deactivation.
	 */
	protected function deactivate() {

	}

	/**
	 * Override this method to add actions that run when the plugin is upgraded.
	 */
	protected function upgrade() {

	}

	/**
	 * Override this method to add actions that run just before the end of script execution.
	 */
	protected function terminate() {

	}

	/**
	 * Override this method to add default options for the plugin.
	 *
	 * @return array Return the array of default options.
	 */
	protected function default_options() {
		return array();
	}

	public final function __construct() {
		// Figure out the path of the plugin's main file.
		global $plugin, $mu_plugin, $network_plugin;
		if ( isset( $plugin ) ) {
		    $this->plugin_file = $plugin;
		}
		elseif ( isset( $mu_plugin ) ) {
		    $this->plugin_file = $mu_plugin;
		}
		elseif ( isset( $network_plugin ) ) {
		    $this->plugin_file = $network_plugin;
		}

		// Die if we can't determine the plugin file path.
		if ( empty( $this->plugin_file ) )
			$this->kill( "Could not determine the main plugin file's path" );

		// Figure out the plugin directory.
		$this->plugin_dir = plugin_dir_path( $this->plugin_file );

		// Work out a name to use.
		$this->plugin_name = sanitize_key( basename( $this->plugin_dir ) );

		// Enable internationalization.
		load_plugin_textdomain( $this->plugin_name, false, $this->plugin_dir . '/languages' );

		// Set the default action hooks to use.
		$this->action_hooks = array(
			'plugins_loaded', 'init', 'activate_plugin', 'deactivated_plugin', 'admin_notices', 'shutdown'
		);

		// Set the minimum required WordPress version.
		$this->min_wp_version = '3.4';

		// Run actions from the child class.
		$this->construct();

		// Register actions with callback functions having the same name as the hook.
		foreach ( $this->action_hooks as $hook )
			if ( method_exists( $this, $hook ) )
				add_action( $hook, array( &$this, $hook ) );

		// Register ajax actions for users.
		foreach ( $this->ajax_actions as $action )
			add_action( "wp_ajax_" . $this->plugin_name . "-" . $action, array( &$this, "$action" ) );

		// Register ajax actions for visitors.
		foreach ( $this->ajax_nopriv_actions as $action )
			add_action( "wp_ajax_nopriv_" . $this->plugin_name . "-" . $action, array( &$this, $action ) );
	}


	/**
	 * This method is hooked to 'plugins_loaded', which is the first hook available
	 * after all plugins and functions have been loaded.
	 */
	public function plugins_loaded() {
		// Set the default options.
		$default_options = array(
			'version' => $this->plugin_version
		);

		// Extend with the default options from the child class.
		$this->default_options = array_merge( $default_options, (array) $this->default_options() );

		// Check if we need to install the plugin.
		if ( ! $this->options = get_option( $this->plugin_name ) ) {
			$this->install();
		}

		// Check if we need to upgrade the plugin.
		elseif ( version_compare( $this->plugin_version, $this->options['version'], '>' ) ) {
			$this->update();
		}

		// Run actions from the child class.
		$this->loaded();
	}

	/**
	 * This method is hooked to 'init', where most plugins and themes hook to do their magic.
	 */
	public function init() {
		// Run actions from the child class.
		$this->initialize();
	}

	/**
	 * This method is hooked to 'activate_plugin' which is an alternative to register_activation_hook().
	 * I use this because it integrates better with Skeleton.
	 */
	public function activate_plugin( $plugin ) {
		if ( WP_PLUGIN_DIR . '/' . $plugin != $this->plugin_file )
			return;

		global $wp_version;

		$error = '';

		// check WordPress version
		if ( version_compare( $wp_version, $this->min_wp_version, '<' ) )
		  $error .= sprintf(
		  	__( 'This plugin requires WordPress version %s or higher!', $this->plugin_name ),
		  	$this->min_wp_version
		  );

		// Run checks from the child class.
		$error .= $this->activate();

		if( !empty( $error ) ) {
			deactivate_plugins( $this->plugin_file, true );
			echo $error;
		}
	}

	/**
	 * This method is hooked to 'deactivated_plugin' which is an alternative to register_deactivation_hook().
	 * I use this because it integrates better with Skeleton.
	 */
	public function deactivated_plugin( $plugin ) {
		if ( WP_PLUGIN_DIR . '/' . $plugin != $this->plugin_file )
			return;

		// Unschedule events.
		if ( wp_next_scheduled( $this->plugin_name . '_schedule' ) ) {
			wp_clear_scheduled_hook( $this->plugin_name . '_schedule' );
		}

		// Run actions from the child class.
		$this->deactivate();
	}

	/**
	 * This method is hooked to 'shutdown'. It gets triggered just before the end of script execution.
	 * It runs even when timeouts occur or exit is called, making it very useful.
	 */
	public function shutdown() {
		// Run actions from the child class.
		$this->terminate();

		// Update plugin options.
		update_option( $this->plugin_name, $this->options );
	}

	/**
	 * This method runs the first time the plugin is activated, setting the default options.
	 * Can be overriden.
	 */
	protected function install() {
		// Add the default options.
		$this->options = $this->default_options;
	}

	/**
	 * This method runs when a change in the plugin version string is detected, allowing update routines to be made.
	 */
	protected function update() {
		// Add new options without overwriting the old ones which might have been customized by the user.
		$this->options = $this->options + $this->default_options;

		// Run actions from the child class.
		$this->upgrade();

		// Update the version number
		$this->options['version'] = $this->plugin_version;
	}

	/**
	 * Prints admin notices.
	 */
	public function admin_notices() {
		if ( !is_wp_error( $this->admin_notices ) )
			return;

		if ( !$this->front_notices->get_error_code() )
			return;

		$errors = '';
		$messages = '';
		foreach ( $this->front_notices->get_error_codes() as $code ) {
			$severity = $this->front_notices->get_error_data( $code );
			foreach ( $this->front_notices->get_error_messages( $code ) as $error ) {
				if ( 'message' == $severity )
					$messages .= '<p>' . $error . '</p>';
				else
					$errors .= '<p>' . $error . '</p>';
			}
		}

		if ( !empty( $errors ) )
			echo '<div id="error">' . $errors . "</div>";
		if ( !empty( $messages ) )
			echo '<div class="updated">' . $messages . "</div>";
	}

	/**
	 * Trigger errors.
	 *
	 * @uses WP_Error to store errors.
	 * @param  string  $code    Error code
	 * @param  string  $message Error message
	 * @param  mixed   $data    Optional data to store
	 * @return boolean          Returns TRUE on success, FALSE on failure.
	 */
	protected function error( $code = '', $message = '', $data = '' ) {
		if ( empty( $code ) || empty( $message ) )
			return false;

		if ( WP_DEBUG )
			trigger_error( $message, E_USER_ERROR );

		if ( !is_wp_error( $this->errors ) )
			$this->errors = new WP_Error();

		$this->errors->add( $code, $message, $data );
		return true;
	}

	/**
	 * Kill script execution.
	 *
	 * @param string $message Error message
	 */
	protected function kill( $message = '' ) {
		$this->error( 'fatal_error', $message );
		die();
	}
}