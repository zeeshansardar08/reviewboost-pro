<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RBP_Email {
	public static function send_reminder( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return false;
		$customer_email = $order->get_billing_email();
		if ( ! is_email( $customer_email ) ) return false;
		
		$subject = get_option( 'rbp_reminder_subject', __( 'We’d love your review! [Order #[order_id]]', 'reviewboost-pro' ) );
		$template = get_option( 'rbp_reminder_body', __( 'Hi [customer_name],<br><br>Thank you for your purchase! Please leave a review for your order #[order_id].', 'reviewboost-pro' ) );
		
		$merge_tags = [
			'[customer_name]' => esc_html( $order->get_billing_first_name() ),
			'[order_id]'     => $order->get_id(),
		];
		foreach ( $merge_tags as $tag => $value ) {
			$subject = str_replace( $tag, $value, $subject );
			$template = str_replace( $tag, $value, $template );
		}
		// Per-product review links tag
		if ( strpos( $template, '{product_review_links}' ) !== false ) {
			if ( class_exists( 'RBP_Pro' ) ) {
				$links_html = RBP_Pro::get_product_review_links_for_order( $order, 'html' );
				$template = str_replace( '{product_review_links}', $links_html, $template );
			}
		}
		
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		return wp_mail( $customer_email, $subject, $template, $headers );
	}
}
