<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RBP_Cron {
	public static function clear_schedules() {
		$timestamp = wp_next_scheduled( 'rbp_send_reminder_emails' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'rbp_send_reminder_emails' );
		}
	}
}

// Deactivation hook now registered in main plugin file for WordPress.org compliance
