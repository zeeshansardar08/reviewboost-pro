<?php
// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

// Optionally, clean up plugin options, logs, and transients here
// Example: delete_option( 'rbp_settings' );
// global $wpdb;
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}reviewboost_logs" );
