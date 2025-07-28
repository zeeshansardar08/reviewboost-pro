<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RBP_Core {
	public function __construct() {
		add_action( 'init', [ $this, 'register_cron_schedule' ] );
		add_action( 'rbp_send_reminder_emails', [ $this, 'process_reminders' ] );
		add_action( 'woocommerce_order_status_completed', [ $this, 'schedule_reminder_for_order' ], 10, 1 );
		add_action( 'woocommerce_order_status_processing', [ $this, 'schedule_reminder_for_order' ], 10, 1 );
	}

	public function register_cron_schedule() {
		if ( ! wp_next_scheduled( 'rbp_send_reminder_emails' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'rbp_send_reminder_emails' );
		}
	}

	public function schedule_reminder_for_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		$delay_days = (int) get_option( 'rbp_reminder_delay_days', 3 );
		$send_time = strtotime( '+' . $delay_days . ' days', current_time( 'timestamp' ) );
		add_post_meta( $order_id, '_rbp_reminder_scheduled', $send_time, true );
	}

	public function process_reminders() {
		$args = [
			'post_type'   => 'shop_order',
			'post_status' => array( 'wc-completed', 'wc-processing' ),
			'posts_per_page' => 20,
			'fields'      => 'ids',
			'meta_query'  => [
				[
					'key'   => '_rbp_reminder_scheduled',
					'value' => current_time( 'timestamp' ),
					'compare' => '<=',
					'type' => 'NUMERIC'
				],
				[
					'key' => '_rbp_reminder_sent',
					'compare' => 'NOT EXISTS'
				]
			]
		];
		$orders = get_posts( $args );
		foreach ( $orders as $order_id ) {
			// Removed GDPR consent check for MVP free version
			RBP_Email::send_reminder( $order_id );
			update_post_meta( $order_id, '_rbp_reminder_sent', 1 );
			RBP_Logger::log_event( $order_id, get_post_meta( $order_id, '_customer_user', true ), 'email', current_time( 'mysql' ), 'sent', 0 );
		}
	}
}

// Initialize core
add_action( 'plugins_loaded', function() {
	$GLOBALS['rbp_core'] = new RBP_Core();
} );
