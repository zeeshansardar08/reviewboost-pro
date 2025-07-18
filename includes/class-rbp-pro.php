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
        if ( get_option( 'rbp_pro_enable_whatsapp', 0 ) && get_option( 'rbp_pro_whatsapp_api_sid', '' ) && get_option( 'rbp_pro_whatsapp_api_token', '' ) && get_option( 'rbp_pro_whatsapp_from', '' ) ) {
            if ( ! wp_next_scheduled( 'rbp_send_whatsapp_reminders' ) ) {
                wp_schedule_event( time() + 600, 'hourly', 'rbp_send_whatsapp_reminders' );
            }
        }
        // Schedule SMS reminders if enabled
        if ( get_option( 'rbp_pro_enable_sms', 0 ) && get_option( 'rbp_pro_sms_api_sid', '' ) && get_option( 'rbp_pro_sms_api_token', '' ) && get_option( 'rbp_pro_sms_from', '' ) ) {
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
        add_action( 'comment_post', [ $this, 'maybe_generate_coupon_on_review' ], 20, 3 );
        // Pro Logs/Dashboard
        // ... (future logic)
        // Licensing
        // ... (future logic)
        // API Webhooks
        // ... (future logic)
        // Priority Support Widget
        // ... (future logic)
        add_action( 'admin_notices', [ $this, 'show_license_required_notice' ] );
        if ( function_exists('fs') && fs()->can_use_premium_code() ) {
            // GDPR Consent on Checkout
            if ( get_option('rbp_pro_gdpr_consent_enabled', 1) ) {
                add_action( 'woocommerce_review_order_before_submit', [ $this, 'add_gdpr_consent_checkbox' ] );
                add_action( 'woocommerce_checkout_process', [ $this, 'validate_gdpr_consent_checkbox' ] );
                add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_gdpr_consent_order_meta' ] );
            }
        }
    }

    /**
     * Send WhatsApp reminders
     */
    public function send_whatsapp_reminders() {
        global $wpdb;
        $table = $wpdb->prefix . 'reviewboost_reminders';
        $now = current_time('mysql');
        $orders = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table WHERE channel = %s AND send_time <= %s AND sent = 0 LIMIT 20", 'whatsapp', $now) );
        foreach ( $orders as $reminder ) {
            $order = wc_get_order( $reminder->order_id );
            if ( ! $order ) continue;
            // GDPR consent check
            if ( get_option('rbp_pro_gdpr_consent_enabled', 1) ) {
                $consent = get_post_meta( $order->get_id(), '_rbp_gdpr_consent', true );
                if ( $consent !== 'yes' ) {
                    RBP_Logger::log_event( $order->get_id(), $order->get_customer_id(), 'whatsapp', current_time('mysql'), 'skipped_no_consent', 0 );
                    continue;
                }
            }
            $template = $reminder->template ?? '';
            // Per-product review links tag
            if ( strpos( $template, '{product_review_links}' ) !== false ) {
                $links_text = self::get_product_review_links_for_order( $order, 'text' );
                $template = str_replace( '{product_review_links}', $links_text, $template );
            }
            $phone = $reminder->recipient ?? '';
            $message = $template;
            $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $review_link = get_permalink( wc_get_page_id( 'myaccount' ) ) . 'review/' . $order->get_id();
            $template = get_option('rbp_pro_whatsapp_template','Hi [customer_name], please review your order [order_id]: [review_link]');
            $message = str_replace([
                '[customer_name]','[order_id]','[review_link]'
            ], [
                $customer_name,
                $order->get_id(),
                $review_link
            ], $template);
            $result = $this->send_twilio_message('whatsapp', $phone, $message);
            // Log event
            RBP_Logger::log_event(
                $order->get_id(),
                $order->get_customer_id(),
                'whatsapp',
                current_time('mysql'),
                $result['success'] ? 'sent' : 'failed',
                0,
                $result['log']
            );
            // Mark as sent
            if($result['success']) {
                $wpdb->update($table, ['sent'=>1], ['id'=>$reminder->id]);
            }
        }
    }

    /**
     * Send SMS reminders
     */
    public function send_sms_reminders() {
        global $wpdb;
        $table = $wpdb->prefix . 'reviewboost_reminders';
        $now = current_time('mysql');
        $orders = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table WHERE channel = %s AND send_time <= %s AND sent = 0 LIMIT 20", 'sms', $now) );
        foreach ( $orders as $reminder ) {
            $order = wc_get_order( $reminder->order_id );
            if ( ! $order ) continue;
            // GDPR consent check
            if ( get_option('rbp_pro_gdpr_consent_enabled', 1) ) {
                $consent = get_post_meta( $order->get_id(), '_rbp_gdpr_consent', true );
                if ( $consent !== 'yes' ) {
                    RBP_Logger::log_event( $order->get_id(), $order->get_customer_id(), 'sms', current_time('mysql'), 'skipped_no_consent', 0 );
                    continue;
                }
            }
            $template = $reminder->template ?? '';
            // Per-product review links tag
            if ( strpos( $template, '{product_review_links}' ) !== false ) {
                $links_text = self::get_product_review_links_for_order( $order, 'text' );
                $template = str_replace( '{product_review_links}', $links_text, $template );
            }
            $phone = $order->get_billing_phone();
            if ( ! $phone ) continue;
            $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $review_link = get_permalink( wc_get_page_id( 'myaccount' ) ) . 'review/' . $order->get_id();
            $template = get_option('rbp_pro_sms_template','Hi [customer_name], please review your order [order_id]: [review_link]');
            $message = str_replace([
                '[customer_name]','[order_id]','[review_link]'
            ], [
                $customer_name,
                $order->get_id(),
                $review_link
            ], $template);
            $result = $this->send_twilio_message('sms', $phone, $message);
            // Log event
            RBP_Logger::log_event(
                $order->get_id(),
                $order->get_customer_id(),
                'sms',
                current_time('mysql'),
                $result['success'] ? 'sent' : 'failed',
                0,
                $result['log']
            );
            // Mark as sent
            if($result['success']) {
                $wpdb->update($table, ['sent'=>1], ['id'=>$reminder->id]);
            }
        }
    }

    /**
     * Send WhatsApp or SMS via Twilio
     */
    private function send_twilio_message($channel, $to, $body) {
        $sid = $channel === 'whatsapp' ? get_option('rbp_pro_whatsapp_api_sid','') : get_option('rbp_pro_sms_api_sid','');
        $token = $channel === 'whatsapp' ? get_option('rbp_pro_whatsapp_api_token','') : get_option('rbp_pro_sms_api_token','');
        $from = $channel === 'whatsapp' ? get_option('rbp_pro_whatsapp_from','') : get_option('rbp_pro_sms_from','');
        if(!$sid || !$token || !$from) return ['success'=>false,'log'=>'Missing Twilio credentials'];
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';
        $args = [
            'body' => [
                'To' => ($channel==='whatsapp' ? 'whatsapp:' : '') . $to,
                'From' => $from,
                'Body' => $body
            ],
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($sid . ':' . $token)
            ],
            'timeout' => 20
        ];
        $response = wp_remote_post($url, $args);
        if(is_wp_error($response)) return ['success'=>false,'log'=>$response->get_error_message()];
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if($code>=200 && $code<300) return ['success'=>true,'log'=>$body];
        return ['success'=>false,'log'=>$body];
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
            // --- Conditional Logic ---
            $matches = true;
            // Order total
            if ( isset($step['order_total']) && $step['order_total'] !== '' ) {
                if ( floatval($order->get_total()) < floatval($step['order_total']) ) $matches = false;
            }
            // Products
            if ( !empty($step['products']) ) {
                $order_product_ids = array_map(function($item){ return $item->get_product_id(); }, $order->get_items());
                if ( !array_intersect($step['products'], $order_product_ids) ) $matches = false;
            }
            // Categories
            if ( !empty($step['categories']) ) {
                $order_cat_ids = [];
                foreach ( $order->get_items() as $item ) {
                    $prods = wc_get_product( $item->get_product_id() );
                    if ( $prods ) {
                        $order_cat_ids = array_merge( $order_cat_ids, wp_get_post_terms( $prods->get_id(), 'product_cat', [ 'fields' => 'ids' ] ) );
                    }
                }
                if ( !array_intersect($step['categories'], $order_cat_ids) ) $matches = false;
            }
            // Countries
            if ( !empty($step['countries']) ) {
                $billing_country = $order->get_billing_country();
                if ( !in_array($billing_country, $step['countries']) ) $matches = false;
            }
            if ( !$matches ) continue;
            // --- End Conditional Logic ---
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
        // GDPR consent check
        if ( get_option('rbp_pro_gdpr_consent_enabled', 1) ) {
            $consent = get_post_meta( $order->get_id(), '_rbp_gdpr_consent', true );
            if ( $consent !== 'yes' ) {
                RBP_Logger::log_event( $order->get_id(), $order->get_customer_id(), $channel, current_time('mysql'), 'skipped_no_consent', 0 );
                return;
            }
        }
        // --- Conditional Logic (repeat at send time) ---
        $matches = true;
        if ( isset($step['order_total']) && $step['order_total'] !== '' ) {
            if ( floatval($order->get_total()) < floatval($step['order_total']) ) $matches = false;
        }
        if ( !empty($step['products']) ) {
            $order_product_ids = array_map(function($item){ return $item->get_product_id(); }, $order->get_items());
            if ( !array_intersect($step['products'], $order_product_ids) ) $matches = false;
        }
        if ( !empty($step['categories']) ) {
            $order_cat_ids = [];
            foreach ( $order->get_items() as $item ) {
                $prods = wc_get_product( $item->get_product_id() );
                if ( $prods ) {
                    $order_cat_ids = array_merge( $order_cat_ids, wp_get_post_terms( $prods->get_id(), 'product_cat', [ 'fields' => 'ids' ] ) );
                }
            }
            if ( !array_intersect($step['categories'], $order_cat_ids) ) $matches = false;
        }
        if ( !empty($step['countries']) ) {
            $billing_country = $order->get_billing_country();
            if ( !in_array($billing_country, $step['countries']) ) $matches = false;
        }
        if ( !$matches ) return;
        // --- End Conditional Logic ---
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
            $result = $this->send_twilio_message('whatsapp', $order->get_billing_phone(), $msg);
            // Log event
            RBP_Logger::log_event(
                $order->get_id(),
                $order->get_customer_id(),
                'whatsapp',
                current_time('mysql'),
                $result['success'] ? 'sent' : 'failed',
                0,
                $result['log']
            );
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
            $result = $this->send_twilio_message('sms', $order->get_billing_phone(), $msg);
            // Log event
            RBP_Logger::log_event(
                $order->get_id(),
                $order->get_customer_id(),
                'sms',
                current_time('mysql'),
                $result['success'] ? 'sent' : 'failed',
                0,
                $result['log']
            );
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

    /**
     * Maybe generate and send coupon after a product review
     */
    public function maybe_generate_coupon_on_review( $comment_ID, $comment_approved, $commentdata ) {
        if ( ! get_option( 'rbp_pro_enable_coupon_on_review', 0 ) ) return;
        if ( $comment_approved != 1 ) return;
        $comment = get_comment( $comment_ID );
        if ( ! $comment || $comment->comment_type !== '' ) return;
        $post_id = $comment->comment_post_ID;
        if ( get_post_type( $post_id ) !== 'product' ) return;
        $user_id = $comment->user_id;
        $email = $comment->comment_author_email;
        // Prevent duplicate coupon for this review
        if ( get_comment_meta( $comment_ID, '_rbp_coupon_sent', true ) ) return;
        // Find customer/order (basic: by user ID or email)
        $customer_id = $user_id ? $user_id : false;
        if ( ! $customer_id && $email ) {
            $user = get_user_by( 'email', $email );
            if ( $user ) $customer_id = $user->ID;
        }
        if ( ! $customer_id ) return;
        // Gather review context for rules
        $rating = 0;
        if ( isset( $commentdata['comment_post_ID'] ) ) {
            $rating = intval( get_comment_meta( $comment_ID, 'rating', true ) );
        }
        $order_total = 0;
        // Optionally, find order total if available (customize as needed)
        // $order_total = ...
        $context = [
            'rating' => $rating,
            'order_total' => $order_total,
            'order_id' => $post_id,
            'user_id' => $customer_id,
            'review_id' => $comment_ID,
        ];
        $manager = RBP_Coupon_Manager::instance();
        if ( ! $manager->evaluate_coupon_rules( $context ) ) return;
        // Generate coupon code
        $coupon_code = $manager->generate_unique_coupon_code( $customer_id, $comment_ID );
        // Create coupon
        $coupon_id = $manager->create_coupon( $coupon_code, [
            '_rbp_coupon_for_review' => $comment_ID,
            '_rbp_coupon_for_user' => $customer_id,
        ] );
        if ( ! $coupon_id ) return;
        // Mark review as coupon sent
        update_comment_meta( $comment_ID, '_rbp_coupon_sent', $coupon_code );
        // Send coupon to customer
        $expiry_days = absint( get_option('rbp_pro_coupon_expiry_days', 7) );
        $expiry_date = date( 'Y-m-d', strtotime( "+$expiry_days days" ) );
        $manager->send_coupon_to_customer( $coupon_code, $customer_id, $email, $expiry_date );
        // Log event
        $manager->log_coupon_event( $customer_id, 'generated', [ 'coupon_code' => $coupon_code, 'review_id' => $comment_ID ] );
    }

    /**
     * Show license required notice
     */
    public function show_license_required_notice() {
        if ( ! current_user_can('manage_options') ) return;
        $upgrade_url = 'https://your-upgrade-page.com'; // TODO: Replace with your real upgrade/pricing page
        echo '<div class="notice notice-warning rbp-pro-locked-notice" style="border-left:6px solid #7f54b3;padding:18px 12px 18px 18px;display:flex;align-items:center;">'
            .'<span class="dashicons dashicons-lock" style="font-size:28px;color:#7f54b3;margin-right:18px;"></span>'
            .'<div>'
            .'<strong>'.esc_html__('ReviewBoost Pro features are locked.','reviewboost-pro').'</strong><br>'
            .esc_html__('Activate your license to unlock all premium features, including WhatsApp/SMS reminders, advanced logs, and more!','reviewboost-pro')
            .'<br><a href="'.esc_url($upgrade_url).'" target="_blank" class="button button-primary" style="margin-top:10px;">'.esc_html__('Learn More & Upgrade','reviewboost-pro').'</a>'
            .'</div>'
            .'</div>';
    }

    /**
     * Add GDPR consent checkbox to WooCommerce checkout
     */
    public function add_gdpr_consent_checkbox() {
        /* translators: Consent checkbox label for GDPR at checkout */
        $label = get_option('rbp_pro_gdpr_consent_label', __('I agree to receive review reminders and marketing from this store.','reviewboost-pro'));
        $privacy_url = get_option('rbp_pro_gdpr_privacy_url','');
        if ( $privacy_url ) {
            $label .= ' <a href="' . esc_url($privacy_url) . '" target="_blank">' . esc_html__('Privacy Policy','reviewboost-pro') . '</a>';
        }
        woocommerce_form_field( 'rbp_gdpr_consent', [
            'type'    => 'checkbox',
            'class'   => ['form-row-wide'],
            'label'   => wp_kses_post($label),
            'required'=> true,
        ], WC()->checkout->get_value( 'rbp_gdpr_consent' ) );
    }

    /**
     * Validate GDPR consent checkbox
     */
    public function validate_gdpr_consent_checkbox() {
        /* translators: Error shown if customer does not check GDPR consent */
        if ( ! isset( $_POST['rbp_gdpr_consent'] ) ) {
            wc_add_notice( __( 'Please provide consent to receive review reminders and marketing.', 'reviewboost-pro' ), 'error' );
        }
    }

    /**
     * Save GDPR consent to order meta
     */
    public function save_gdpr_consent_order_meta( $order_id ) {
        update_post_meta( $order_id, '_rbp_gdpr_consent', isset($_POST['rbp_gdpr_consent']) ? 'yes' : 'no' );
    }

        /**
     * Generate per-product review links for an order.
     * @param WC_Order $order
     * @param string $format 'html' or 'text'
     * @return string
     */
    public static function get_product_review_links_for_order( $order, $format = 'html' ) {
        if ( ! $order || ! is_a( $order, 'WC_Order' ) ) return '';
        $items = $order->get_items();
        if ( empty( $items ) ) return '';
        $links = [];
        foreach ( $items as $item ) {
            $product = $item->get_product();
            if ( ! $product ) continue;
            $name = $product->get_name();
            $url = get_permalink( $product->get_id() );
            if ( $url ) {
                // WooCommerce reviews are usually at #reviews anchor
                $review_url = $url . '#reviews';
                if ( $format === 'html' ) {
                    $links[] = sprintf( '<li><a href="%s">%s</a></li>', esc_url( $review_url ), esc_html( $name ) );
                } else {
                    $links[] = sprintf( '%s: %s', $name, $review_url );
                }
            }
        }
        if ( empty( $links ) ) return '';
        if ( $format === 'html' ) {
            return '<ul>' . implode( '', $links ) . '</ul>';
        } else {
            return implode( "\n", $links );
        }
    }
}

// Initialize Pro module if not already loaded
add_action( 'plugins_loaded', function() {
    if ( ! isset( $GLOBALS['rbp_pro'] ) ) {
        $GLOBALS['rbp_pro'] = new RBP_Pro();
    }
} );
