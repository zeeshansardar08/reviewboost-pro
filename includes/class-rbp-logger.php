<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RBP_Logger {
	public static function log_event( $order_id, $customer_id, $method, $time_sent, $status, $retry_count = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'reviewboost_logs';
		$wpdb->insert( $table, [
			'order_id'    => intval( $order_id ),
			'customer_id' => intval( $customer_id ),
			'method'      => sanitize_text_field( $method ),
			'time_sent'   => sanitize_text_field( $time_sent ),
			'status'      => sanitize_text_field( $status ),
			'retry_count' => intval( $retry_count ),
		] );
	}

	public static function get_recent_logs( $limit = 10 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'reviewboost_logs';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY time_sent DESC LIMIT %d", $limit ) );
	}
}

// Create log table on plugin activation
register_activation_hook( RBP_PLUGIN_FILE, function() {
	global $wpdb;
	$table = $wpdb->prefix . 'reviewboost_logs';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		order_id bigint(20) unsigned NOT NULL,
		customer_id bigint(20) unsigned NOT NULL,
		method varchar(20) NOT NULL,
		time_sent datetime NOT NULL,
		status varchar(20) NOT NULL,
		retry_count int(11) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id)
	) $charset_collate;";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
} );
