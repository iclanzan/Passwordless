<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
	<head>
		<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />
		<title><?php bloginfo('name'); ?> &rsaquo; <?php echo __( 'Log in' ); ?></title>
	<?php

	wp_admin_css( 'wp-admin', true );
	wp_admin_css( 'colors-fresh', true );

	// Don't index any of these forms
	add_action( 'login_head', 'wp_no_robots' );

	if ( wp_is_mobile() ) { ?>
		<meta name="viewport" content="width=320; initial-scale=0.9; maximum-scale=1.0; user-scalable=0;" /><?php
	}

	// Shake it!
	$shake_error_codes = array( 'empty_email', 'invalid_email' );
	$shake_error_codes = apply_filters( 'shake_error_codes', $shake_error_codes );

	if ( $shake_error_codes && !wp_is_mobile() && $this->front_notices->get_error_code() && in_array( $this->front_notices->get_error_code(), $shake_error_codes ) ) {
?>
		<script type="text/javascript">
		addLoadEvent = function(func){if(typeof jQuery!="undefined")jQuery(document).ready(func);else if(typeof wpOnload!='function'){wpOnload=func;}else{var oldonload=wpOnload;wpOnload=function(){oldonload();func();}}};
		function s(id,pos){g(id).left=pos+'px';}
		function g(id){return document.getElementById(id).style;}
		function shake(id,a,d){c=a.shift();s(id,c);if(a.length>0){setTimeout(function(){shake(id,a,d);},d);}else{try{g(id).position='static';wp_attempt_focus();}catch(e){}}}
		addLoadEvent(function(){ var p=new Array(15,30,15,0,-15,-30,-15,0);p=p.concat(p.concat(p));var i=document.forms[0].id;g(i).position='relative';shake(i,p,20);});
		</script>
<?php
	}

	do_action( 'login_enqueue_scripts' );
	do_action( 'login_head' );

	if ( is_multisite() ) {
		$login_header_url   = network_home_url();
		$login_header_title = $current_site->site_name;
	} else {
		$login_header_url   = __( 'http://wordpress.org/' );
		$login_header_title = __( 'Powered by WordPress' );
	}

	$login_header_url   = apply_filters( 'login_headerurl',   $login_header_url   );
	$login_header_title = apply_filters( 'login_headertitle', $login_header_title );

	?>
	</head>
	<body class="login<?php if ( wp_is_mobile() ) echo ' mobile'; ?>">
		<div id="login">
			<h1><a href="<?php echo esc_url( $login_header_url ); ?>" title="<?php echo esc_attr( $login_header_title ); ?>"><?php bloginfo( 'name' ); ?></a></h1>
	<?php

	unset( $login_header_url, $login_header_title );

	$message = '';
	$message = apply_filters('login_message', $message);
	if ( !empty( $message ) )
		echo $message . "\n";

	if ( $this->front_notices->get_error_code() ) {
		$errors = '';
		$messages = '';
		foreach ( $this->front_notices->get_error_codes() as $code ) {
			$severity = $this->front_notices->get_error_data($code);
			foreach ( $this->front_notices->get_error_messages($code) as $error ) {
				if ( 'message' == $severity )
					$messages .= '	' . $error . "<br />\n";
				else
					$errors .= '	' . $error . "<br />\n";
			}
		}
		if ( !empty($errors) )
			echo '<div id="login_error">' . apply_filters('login_errors', $errors) . "</div>\n";
		if ( !empty($messages) )
			echo '<p class="message">' . apply_filters('login_messages', $messages) . "</p>\n";
	}

	if ( !$request_login ) : ?>
			<form name="loginform" id="loginform" action="<?php echo esc_url( home_url( $this->login_page, 'login_post' ) ); ?>" method="post">
				<p>
					<label for="user_email"><?php _e('E-mail') ?><br />
					<input type="email" name="user_email" id="user_email" class="input" value="<?php echo esc_attr(stripslashes($user_email)); ?>" size="25" /></label>
				</p>
				<?php do_action('login_form'); ?>
				<p class="submit">
					<input type="submit" name="wp-submit" id="wp-submit" class="button-primary" value="<?php esc_attr_e('Log In'); ?>" />
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
					<input type="hidden" name="testcookie" value="1" />
					<?php wp_nonce_field( 'passwordless_login_request', 'nonce', false ) ?>
				</p>
			</form>
			<script type="text/javascript">
				try{document.getElementById('user_email').focus();}catch(e){}
				if(typeof wpOnload=='function')wpOnload();
			</script>
<?php
 endif;
?>
			<p id="backtoblog"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php esc_attr_e( 'Are you lost?' ); ?>"><?php printf( __( '&larr; Back to %s' ), get_bloginfo( 'title', 'display' ) ); ?></a></p>
		</div>
		<?php do_action('login_footer'); ?>
		<div class="clear"></div>
	</body>
</html>