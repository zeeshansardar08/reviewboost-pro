<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ReviewBoost Pro Module Loader
 * Loads all premium features for Pro users only.
 *
 * Features scaffolded for future implementation:
 * - WhatsApp reminders (API integration)
 * - SMS reminders (Twilio/Nexmo)
 * - Multi-step reminders (3, 7, 14 days)
 * - Advanced template builder
 * - Conditional reminders (order total, category, country)
 * - Auto-coupon on review
 * - Pro logs/dashboard enhancements
 * - Licensing and settings
 * - API webhooks
 * - Priority support widget
 */
class RBP_Pro {
    public function __construct() {
        // WhatsApp Reminders
        add_action( 'rbp_send_whatsapp_reminders', [ $this, 'send_whatsapp_reminders' ] );
        // SMS Reminders
        add_action( 'rbp_send_sms_reminders', [ $this, 'send_sms_reminders' ] );
        // Schedule WhatsApp reminders if enabled
        if ( get_option( 'rbp_pro_enable_whatsapp', 0 ) && get_option( 'rbp_pro_whatsapp_api_key', '' ) ) {
            if ( ! wp_next_scheduled( 'rbp_send_whatsapp_reminders' ) ) {
                wp_schedule_event( time() + 600, 'hourly', 'rbp_send_whatsapp_reminders' );
            }
        }
        // Schedule SMS reminders if enabled
        if ( get_option( 'rbp_pro_enable_sms', 0 ) && get_option( 'rbp_pro_sms_sid', '' ) && get_option( 'rbp_pro_sms_token', '' ) ) {
            if ( ! wp_next_scheduled( 'rbp_send_sms_reminders' ) ) {
                wp_schedule_event( time() + 900, 'hourly', 'rbp_send_sms_reminders' );
            }
        }
        // Multi-step Reminders
        add_action( 'woocommerce_order_status_completed', [ $this, 'schedule_multistep_reminders' ], 20, 1 );
        add_action( 'woocommerce_order_status_processing', [ $this, 'schedule_multistep_reminders' ], 20, 1 );
        add_action( 'rbp_send_multistep_reminder', [ $this, 'send_multistep_reminder' ], 10, 3 );
        // Advanced Template Builder
        // ... (future logic)
        // Conditional Reminders
        // ... (future logic)
        // Auto-Coupon on Review
        // ... (future logic)
        // Pro Logs/Dashboard
        // ... (future logic)
        // Licensing
        // ... (future logic)
        // API Webhooks
        // ... (future logic)
        // Priority Support Widget
        // ... (future logic)
    }

    /**
     * Send WhatsApp reminders (stub)
     */
    public function send_whatsapp_reminders() {
        // Integrate with WhatsApp API (Twilio/Meta)
        // TODO: Implement WhatsApp reminders for Pro users
        // Example: Fetch eligible orders and send WhatsApp messages
    }

    /**
     * Send SMS reminders (stub)
     */
    public function send_sms_reminders() {
        // Integrate with Twilio/Nexmo
        // TODO: Implement SMS reminders for Pro users
        // Example: Fetch eligible orders and send SMS messages
    }

    /**
     * Schedule multi-step reminders for an order
     */
    public function schedule_multistep_reminders( $order_id ) {
        $steps = get_option( 'rbp_pro_multistep_reminders', [] );
        if ( empty( $steps ) ) return;
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        foreach ( $steps as $idx => $step ) {
            $days = absint( $step['days'] ?? 0 );
            $channel = sanitize_text_field( $step['channel'] ?? '' );
            $subject = sanitize_text_field( $step['subject'] ?? '' );
            $body = wp_kses_post( $step['body'] ?? '' );
            if ( $days < 1 || ! in_array( $channel, [ 'email', 'whatsapp', 'sms' ] ) ) continue;
            $send_time = strtotime( '+' . $days . ' days', current_time( 'timestamp' ) );
            wp_schedule_single_event( $send_time, 'rbp_send_multistep_reminder', [ $order_id, $idx, $channel ] );
        }
    }

    /**
     * Send a multi-step reminder
     */
    public function send_multistep_reminder( $order_id, $step_idx, $channel ) {
        $steps = get_option( 'rbp_pro_multistep_reminders', [] );
        if ( empty( $steps[$step_idx] ) ) return;
        $step = $steps[$step_idx];
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        // Prevent duplicate sends
        $meta_key = '_rbp_multistep_sent_' . $step_idx . '_' . $channel;
        if ( get_post_meta( $order_id, $meta_key, true ) ) return;
        // Send via the selected channel
        if ( $channel === 'email' ) {
            $subject = $step['subject'] ?: __( 'We’d love your review!', 'reviewboost-pro' );
            $body = $step['body'] ?: __( 'Please review your order.', 'reviewboost-pro' );
            // Merge tags
            $merge_tags = [
                '[customer_name]' => esc_html( $order->get_billing_first_name() ),
                '[order_id]'      => $order->get_id(),
            ];
            foreach ( $merge_tags as $tag => $value ) {
                $subject = str_replace( $tag, $value, $subject );
                $body = str_replace( $tag, $value, $body );
            }
            $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
            wp_mail( $order->get_billing_email(), $subject, $body, $headers );
        } elseif ( $channel === 'whatsapp' ) {
            // TODO: Implement WhatsApp sending
        } elseif ( $channel === 'sms' ) {
            // TODO: Implement SMS sending
        }
        update_post_meta( $order_id, $meta_key, 1 );
        // Log event
        RBP_Logger::log_event( $order_id, $order->get_customer_id(), $channel, current_time( 'mysql' ), 'sent', 0 );
    }
}

// Initialize Pro module if not already loaded
add_action( 'plugins_loaded', function() {
    if ( ! isset( $GLOBALS['rbp_pro'] ) ) {
        $GLOBALS['rbp_pro'] = new RBP_Pro();
    }
} );
