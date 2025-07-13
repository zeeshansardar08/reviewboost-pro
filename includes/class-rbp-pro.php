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
        // Use advanced template if available
        $tpl = get_option('rbp_pro_templates', []);
        if ( $channel === 'email' ) {
            $email_tpl = $tpl['email'] ?? [];
            $subject = $step['subject'] ?: __( 'We’d love your review!', 'reviewboost-pro' );
            $header = $email_tpl['header'] ?? '';
            $body = $step['body'] ?: ( $email_tpl['body'] ?? '' );
            $footer = $email_tpl['footer'] ?? '';
            $button = $email_tpl['button'] ?? '';
            // Merge tags
            $merge_tags = [
                '[customer_name]' => esc_html( $order->get_billing_first_name() ),
                '[order_id]'      => $order->get_id(),
                '[product_list]'  => $this->get_product_list( $order ),
                '[review_url]'    => $this->get_review_url( $order ),
            ];
            foreach ( $merge_tags as $tag => $value ) {
                $subject = str_replace( $tag, $value, $subject );
                $header = str_replace( $tag, $value, $header );
                $body = str_replace( $tag, $value, $body );
                $footer = str_replace( $tag, $value, $footer );
                $button = str_replace( $tag, $value, $button );
            }
            $email_content = $header . '<br>' . $body;
            if ( $button ) {
                $email_content .= '<br><a href="' . esc_url( $merge_tags['[review_url]'] ) . '" style="display:inline-block;background:#3c8dbc;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;">' . $button . '</a>';
            }
            $email_content .= '<br>' . $footer;
            $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
            wp_mail( $order->get_billing_email(), $subject, $email_content, $headers );
        } elseif ( $channel === 'whatsapp' ) {
            $wa_tpl = $tpl['whatsapp']['body'] ?? '';
            $msg = $step['body'] ?: $wa_tpl;
            $merge_tags = [
                '[customer_name]' => esc_html( $order->get_billing_first_name() ),
                '[order_id]'      => $order->get_id(),
                '[review_url]'    => $this->get_review_url( $order ),
            ];
            foreach ( $merge_tags as $tag => $value ) {
                $msg = str_replace( $tag, $value, $msg );
            }
            // TODO: Send WhatsApp message
        } elseif ( $channel === 'sms' ) {
            $sms_tpl = $tpl['sms']['body'] ?? '';
            $msg = $step['body'] ?: $sms_tpl;
            $merge_tags = [
                '[customer_name]' => esc_html( $order->get_billing_first_name() ),
                '[order_id]'      => $order->get_id(),
                '[review_url]'    => $this->get_review_url( $order ),
            ];
            foreach ( $merge_tags as $tag => $value ) {
                $msg = str_replace( $tag, $value, $msg );
            }
            // TODO: Send SMS message
        }
        update_post_meta( $order_id, $meta_key, 1 );
        // Log event
        RBP_Logger::log_event( $order_id, $order->get_customer_id(), $channel, current_time( 'mysql' ), 'sent', 0 );
    }

    /**
     * Get product list as string
     */
    private function get_product_list( $order ) {
        $names = [];
        foreach ( $order->get_items() as $item ) {
            $names[] = $item->get_name();
        }
        return implode( ', ', $names );
    }

    /**
     * Get review URL for order (default: My Account > Orders)
     */
    private function get_review_url( $order ) {
        // TODO: Support per-product review links or external URLs
        return wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) );
    }
}

// Initialize Pro module if not already loaded
add_action( 'plugins_loaded', function() {
    if ( ! isset( $GLOBALS['rbp_pro'] ) ) {
        $GLOBALS['rbp_pro'] = new RBP_Pro();
    }
} );
