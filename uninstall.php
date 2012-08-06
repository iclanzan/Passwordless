<?php

if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit;

$name = sanitize_key( basename( dirname( __FILE__ ) ) );

delete_option( $name );