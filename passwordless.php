<?php
/*
Plugin Name: Passwordless
Version: 1.0
Plugin URI: http://hel.io/wordpress/passwordless/
Description: Allows users to sign up and log in using only their email addresses.
Author: Sorin Iclanzan
Author URI: http://hel.io/
License: GPL3
Text Domain: passwordless
Domain Path: /languages
*/

/*
	Copyright 2012 Sorin Iclanzan  (email : sorin@hel.io)

	This file is part of Passwordless.

	Passwordless is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	Backup is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with Passwordless. If not, see http://www.gnu.org/licenses/gpl.html.
*/

// Load Skeleton class.
if ( ! class_exists( 'Skeleton' ) )
	require_once( 'class-skeleton.php' );

if ( defined( 'DOING_CRON' ) || isset( $_GET['doing_wp_cron'] ) )
	// required to delete users
	require_once( ABSPATH . 'wp-admin/includes/user.php' );

class Passwordless extends Skeleton {

	/**
	 * Stores login page URI which will replace wp-login.php.
	 *
	 * @var string
	 * @access private
	 */
	private $login_page;

	/**
	 * Set the plugin version.
	 * This method is run inside the parent class's constructor.
	 */
	protected function construct() {
		$this->plugin_version = '1.0';

		if ( defined( 'DOING_CRON' ) || isset( $_GET['doing_wp_cron'] ) ) {
			array_push( $this->action_hooks, 'delete_option', 'wp_scheduled_delete' );
		}
	}

	/**
	 * Set the login uri and set redirects.
	 * This method is run at the 'init' hook.
	 *
	 * @global object $wp_rewrite
	 */
	protected function initialize() {
		global $wp_rewrite;

		if ( $wp_rewrite->using_permalinks() )
			$this->login_page = 'login';
		else
			$this->login_page = '?login';

		// Allow plugins to modify the login page uri.
		$this->login_page = apply_filters( $this->plugin_name . '_login_page', $this->login_page );

		if ( $this->is_login() ) {
			$this->front_notices = new WP_Error();

			// Allow plugins to modify the default password length.
			$this->pass_length = apply_filters( $this->plugin_name . '_pass_length', 24 );

			// Allow plugins to modify the time a login key is valid for.
			$this->key_expire  = apply_filters( $this->plugin_name . '_key_expire', 86400 ); // 1 day

			add_action( 'template_redirect', array( &$this, 'login_page' ) );
		}
		else {
			// Redirect from the WordPress wp-login.php page to our new Passwordless login page.
			$logins = array(
				home_url( 'wp-login.php', 'relative' ),
				home_url( 'wp-register.php', 'relative' ),
				home_url( 'login', 'relative' ),
				site_url( 'wp-login.php', 'relative' ),
				site_url( 'wp-register.php', 'relative' ),
				site_url( 'login', 'relative' ),
			);
			$request = $_SERVER['REQUEST_URI'];
			$query = $_SERVER['QUERY_STRING'];
			if ( !empty( $query ) ) {
				$pos = strpos( $request, '?' );
				$request = substr( $request, 0, $pos );
			}
			if ( in_array( untrailingslashit( $request ), $logins ) ) {
				$redirect_to = home_url( $this->login_page );

				if ( $query )
					if ( strpos( $redirect_to, '?' ) )
						$redirect_to .= '&' . $query;
					else
						$redirect_to .= '?' . $query;

				wp_redirect( $redirect_to );
				exit;
			}
		}

		add_action( 'site_url', array( &$this, 'site_url' ), 10, 3 );

		// Remove password fields from the profile page since we aren't using passwords.
		if ( defined( 'IS_PROFILE_PAGE' ) )
			add_filter( 'show_password_fields', '__return_false' );
	}

	/**
	 * Render our new login page.
	 */
	public function login_page() {
		$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'login';

		// validate action so as to default to the login screen
		if ( !in_array( $action, array( 'logout', 'login' ), true ) && false === has_filter( 'login_form_' . $action ) )
			$action = 'login';

		status_header( 200 );
		nocache_headers();

		header( 'Content-Type: ' . get_bloginfo( 'html_type' ) . '; charset=' . get_bloginfo( 'charset' ) );

		if ( defined( 'RELOCATE' ) && RELOCATE ) { // Move flag is set
			if ( isset( $_SERVER['PATH_INFO'] ) && ( $_SERVER['PATH_INFO'] != $_SERVER['PHP_SELF'] ) )
				$_SERVER['PHP_SELF'] = str_replace( $_SERVER['PATH_INFO'], '', $_SERVER['PHP_SELF'] );

			$schema = is_ssl() ? 'https://' : 'http://';
			if ( dirname( $schema . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] ) != get_option( 'siteurl' ) )
				update_option('siteurl', dirname( $schema . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] ) );
		}

		//Set a cookie now to see if they are supported by the browser.
		setcookie( TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN );
		if ( SITECOOKIEPATH != COOKIEPATH )
			setcookie( TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN );

		// allow plugins to override the default actions, and to add extra actions if they want
		do_action( 'login_init' );
		do_action( 'login_form_' . $action );

		if ( 'logout' == $action ) {
			check_admin_referer( 'log-out' );
			wp_logout();

			$redirect_to = !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : $this->login_page . '&loggedout=true';
			wp_safe_redirect( $redirect_to );
			exit;
		}

		$user_email = '';
		$request_login = false;
		if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
			$user_email = $_REQUEST['user_email'];
			$request_login = $this->request_login( $user_email );
		}

		$reauth = empty( $_REQUEST['reauth'] ) ? false : true;

		$user_id = 0;
		if ( isset( $_REQUEST['key'] ) ) {
			$user_id = $this->login();
		}

		$redirect_to = !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : admin_url();
		$redirect_to = apply_filters( $this->plugin_name . '_login_redirect', $redirect_to, $user_id );

		if ( $user_id && !$reauth ) {
			if ( ( empty( $redirect_to ) || $redirect_to == 'wp-admin/' || $redirect_to == admin_url() ) ) {
				// If the user doesn't belong to a blog, send him to user admin.
				if ( is_multisite() && !get_active_blog_for_user( $user_id ) && !is_super_admin( $user_id ) )
					$redirect_to = user_admin_url();
				elseif ( is_multisite() && !user_can( $user_id, 'read' ) )
					$redirect_to = get_dashboard_url( $user_id );
				// If the user can't edit posts, send him to his profile.
				elseif ( !user_can( $user_id, 'edit_posts' ) )
					$redirect_to = admin_url( 'profile.php' );
			}

			wp_safe_redirect( $redirect_to );
			exit;
		}

		// Clear errors if loggedout is set.
		if ( !empty($_GET['loggedout']) || $reauth )
			$this->front_notices = new WP_Error();

		// If cookies are disabled we can't log in even with a valid user+pass
		if ( isset( $_POST['testcookie'] ) && empty( $_COOKIE[TEST_COOKIE] ) )
			$this->front_notices->add( 'test_cookie', __( "<strong>ERROR</strong>: Cookies are blocked or not supported by your browser. You must <a href='http://www.google.com/cookies.html'>enable cookies</a> to use WordPress." ) );

		// Some parts of this script use the main login form to display a message
		if ( isset( $_GET['loggedout'] ) && true == $_GET['loggedout'] )
			$this->front_notices->add( 'loggedout', __( 'You are now logged out.' ), 'message' );
		elseif ( !$request_login )
			$this->front_notices->add( 'login_register', __( "We will not make you remember another password. Enter your email and we will send you a login link.", $this->plugin_name ), 'message' );
		elseif ( strpos( $redirect_to, 'about.php?updated' ) )
			$this->front_notices->add( 'updated', __( '<strong>You have successfully updated WordPress!</strong> Please log back in to experience the awesomeness.' ), 'message' );

		// Clear any stale cookies.
		if ( $reauth )
			wp_clear_auth_cookie();

		if ( $located = locate_template( 'login.php' ) )
			require( $located );
		else
			require( $this->plugin_dir . 'login.php' );

		exit;
	}

	/**
	 * Create and email the login link.
	 *
	 * @param  string  $user_email The user's email address
	 * @return boolean             Returns TRUE on success, FALSE on filure.
	 */
	public function request_login( $user_email ) {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'passwordless_login_request' ) )
			wp_die( __( 'Failed security check!', $this->plugin_name ) );

		if ( $user_email == '' ) {
			$this->front_notices->add( 'empty_email', __( '<strong>ERROR</strong>: Please type your e-mail address.' ) );
			return false;
		}
		elseif ( ! is_email( $user_email ) ) {
			$this->front_notices->add( 'invalid_email', __( '<strong>ERROR</strong>: The email address isn&#8217;t correct.' ) );
			return false;
		}

		if ( ! $user_id = email_exists( $user_email ) ) {
			if ( ! get_option( 'users_can_register' ) ) {
				$this->front_notices->add( 'registerdisabled', __( 'User registration is currently not allowed.' ) );
				return false;
			}

			add_filter( 'pre_user_display_name', array( &$this, 'anonymize_user' ) );
			add_filter( 'pre_user_nickname',     array( &$this, 'anonymize_user' ) );
			add_filter( 'sanitize_user',         array( &$this, 'sanitize_user' ), 10, 2 );

			$pass = wp_generate_password( $this->pass_length, false );
			$user_id = wp_create_user( $user_email, $pass, $user_email );
			if ( is_wp_error( $user_id ) || !$user_id ) {
				$this->front_notices->add( 'registerfail', sprintf( __(
					'<strong>ERROR</strong>: Couldn&#8217;t register you... please contact the <a href="mailto:%s">webmaster</a> !'
				), get_option( 'admin_email' ) ) );
				return false;
			}

			add_user_meta( $user_id, 'inactive', true, true );
		}

		$key  = wp_generate_password( $this->pass_length, false );

		set_transient( 'login_hash_' . $user_id, wp_hash_password( $key ), $this->key_expire );

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ) );
		$login_link = add_query_arg( 'key', $key . $user_id, home_url( $this->login_page ) );

		$subject = apply_filters( $this->plugin_name . '_email_subject', __( 'Login Link', $this->plugin_name ), $user_email );

		$body = sprintf(
			__( 'Someone requested to log in to %s using your email address.', $this->plugin_name ),
			$site_name
		) . "\r\n";
		$body .= __( 'If this was you, then please click on the following link:', $this->plugin_name ) . "\r\n\r\n";
		$body .= $login_link . "\r\n\r\n";
		$body .= __( 'If this was a mistake, just ignore this email and nothing will happen.' );

		$body = apply_filters( $this->plugin_name . '_email_body', $body, $user_email );

		do_action( $this->plugin_name . '_send_email', $user_email, $subject, $body );

		if ( WP_DEBUG )
			@wp_mail( $user_email, $subject, $body );
		else
			wp_mail( $user_email, $subject, $body );

		$this->front_notices->add( 'confirm_login', __( 'Check your e-mail for the login link.', $this->plugin_name ), 'message' );
		return true;
	}

	/**
	 * Log a user in.
	 *
	 * @return integer The logged in user ID or 0.
	 */
	protected function login() {
		$key = preg_replace( '/[^a-z0-9]/i', '', $_REQUEST['key'] );

		if ( $this->pass_length + 1 > strlen( $key ) ) {
			$this->front_notices->add( 'invalid_key', __( 'Invalid key' ) );
			return 0;
		}

		$user_id = substr( $key, $this->pass_length );
		$key     = substr( $key, 0, $this->pass_length );

		$hash = get_transient( 'login_hash_' . $user_id );

		if ( ! $hash || ! wp_check_password( $key, $hash ) ) {
			$this->front_notices->add( 'invalid_key', __( 'Invalid key' ) );
			return 0;
		}

		$secure_cookie = '';

		// If the user wants ssl, force a secure cookie.
		if ( get_user_option( 'use_ssl', $user_id ) ) {
			$secure_cookie = true;
			force_ssl_admin( true );
		}

		add_filter( 'auth_cookie_expiration', array( &$this, 'auth_expiration' ) );

		wp_set_auth_cookie( $user_id, true, $secure_cookie );

		delete_transient( 'login_hash_' . $user_id );

		if ( get_user_meta( $user_id, 'inactive', true ) )
			delete_user_meta( $user_id, 'inactive' );

		return $user_id;
	}

	/**
	 * Filter. Sets the auth cookie expiration.
	 */
	public function auth_expiration( $expirein ) {
		return 31556926; // 1 year
	}

	/**
	 * Filter. Sets the nickname and display name for new users.
	 */
	public function anonymize_user( $name ) {
		$name = __( 'Anonymous', $this->plugin_name );
		return $name;
	}

	/**
	 * Filter. Allow the raw username to pass, since it's the email address.
	 */
	public function sanitize_user( $user, $raw_user ) {
		return $raw_user;
	}

	/**
	 * Filter. Rewrites old login URLs.
	 */
	public function site_url( $url, $path, $orig_scheme ) {
		if ( false === strpos( $path, 'wp-login.php' ) )
			return $url;

    return home_url( $this->login_page, $orig_scheme );
	}

	/**
	 * Delete users who never logged in when deleting their expired keys.
	 */
	public function delete_option( $option ) {
		if ( false === strpos( $option, '_transient_login_hash_' ) )
			return;

		$user_id = substr( $option, 22 );

		if ( get_user_meta( $user_id, 'inactive', true ) )
			wp_delete_user( $user_id );
	}

	/**
	 * Delete expired key hashes.
	 *
	 * @global object  $wpdb
	 * @global boolean $_wp_using_ext_object_cache
	 */
	public function wp_scheduled_delete() {
		global $wpdb, $_wp_using_ext_object_cache;

		if( $_wp_using_ext_object_cache )
			return;

		$expired = $wpdb->get_col( $wpdb->prepare(
			"SELECT `option_name` FROM $wpdb->options WHERE `option_name` LIKE %s AND `option_value` < %d",
			'_transient_timeout_login_hash_%',
			time()
		) );

		foreach( $expired as $transient ) {
			$key = str_replace( '_transient_timeout_', '', $transient );
			delete_transient( $key );
		}
	}

	/**
	 * Are we on the login page?
	 *
	 * @return boolean Returns TRUE if the requested page is the login page, FALSE otherwise.
	 */
	public function is_login() {
		if ( !isset( $this->is_login ) ) {
			$login_uri = home_url( $this->login_page, 'relative' );
			$request_uri = $_SERVER['REQUEST_URI'];

			if ( 0 === strpos( trim( $request_uri, '/' ), trim( $login_uri, '/' ) ) )
				$this->is_login = true;
			else
				$this->is_login = false;
		}

		return $this->is_login;
	}
}

$passwordless = new Passwordless();