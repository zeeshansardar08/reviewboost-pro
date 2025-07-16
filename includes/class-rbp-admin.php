<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RBP_Admin {
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_menu', [ $this, 'add_logs_dashboard_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'wp_ajax_rbp_fetch_logs', [ $this, 'ajax_fetch_logs' ] );
		add_action( 'wp_ajax_rbp_export_logs', [ $this, 'ajax_export_logs' ] );
		add_action('admin_menu', [ $this, 'add_getting_started_page' ]);
		add_action('admin_init', [ $this, 'maybe_redirect_to_getting_started' ]);
		add_action('admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ]);
		add_action('admin_menu', [ $this, 'add_coupon_logs_dashboard_menu' ]);
	}

	public function add_settings_page() {
		add_submenu_page(
			'woocommerce',
			__( 'ReviewBoost Pro', 'reviewboost-pro' ),
			__( 'ReviewBoost Pro', 'reviewboost-pro' ),
			'manage_woocommerce',
			'reviewboost-pro',
			[ $this, 'render_settings_page' ]
		);
	}

	public function add_logs_dashboard_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'ReviewBoost Pro Logs', 'reviewboost-pro' ),
			__( 'ReviewBoost Pro Logs', 'reviewboost-pro' ),
			'manage_woocommerce',
			'rbp-pro-logs',
			[ $this, 'render_logs_dashboard' ]
		);
	}


	public function register_settings() {
		register_setting( 'rbp_settings', 'rbp_reminder_subject', [ 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'rbp_settings', 'rbp_reminder_body', [ 'sanitize_callback' => 'wp_kses_post' ] );
		register_setting( 'rbp_settings', 'rbp_reminder_delay_days', [ 'sanitize_callback' => 'absint' ] );
		register_setting( 'rbp_settings', 'rbp_enabled', [ 'sanitize_callback' => 'absint' ] );
		// Pro fields
		register_setting( 'rbp_pro_settings', 'rbp_pro_enable_whatsapp', [ 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean' ] );
		register_setting( 'rbp_pro_settings', 'rbp_pro_whatsapp_api_sid', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'rbp_pro_settings', 'rbp_pro_whatsapp_api_token', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'rbp_pro_settings', 'rbp_pro_whatsapp_from', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'rbp_pro_settings', 'rbp_pro_whatsapp_template', [ 'type' => 'string', 'sanitize_callback' => 'wp_kses_post' ] );

		register_setting( 'rbp_pro_settings', 'rbp_pro_enable_sms', [ 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean' ] );
		register_setting( 'rbp_pro_settings', 'rbp_pro_sms_api_sid', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'rbp_pro_settings', 'rbp_pro_sms_api_token', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'rbp_pro_settings', 'rbp_pro_sms_from', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'rbp_pro_settings', 'rbp_pro_sms_template', [ 'type' => 'string', 'sanitize_callback' => 'wp_kses_post' ] );
		// Multi-step reminders (Pro)
		register_setting( 'rbp_settings', 'rbp_pro_multistep_reminders', [ 'sanitize_callback' => [ $this, 'sanitize_multistep_reminders' ] ] );
		// Advanced Template Builder (Pro)
		register_setting( 'rbp_settings', 'rbp_pro_templates', [ 'sanitize_callback' => [ $this, 'sanitize_templates' ] ] );
		// Auto-Coupon on Review (Pro)
		register_setting( 'rbp_settings', 'rbp_pro_enable_coupon_on_review', [ 'sanitize_callback' => 'absint' ] );
		register_setting( 'rbp_settings', 'rbp_pro_coupon_type', [ 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'rbp_settings', 'rbp_pro_coupon_amount', [ 'sanitize_callback' => 'floatval' ] );
		register_setting( 'rbp_settings', 'rbp_pro_coupon_min_spend', [ 'sanitize_callback' => 'floatval' ] );
		register_setting( 'rbp_settings', 'rbp_pro_coupon_usage_limit', [ 'sanitize_callback' => 'absint' ] );
		register_setting( 'rbp_settings', 'rbp_pro_coupon_expiry_days', [ 'sanitize_callback' => 'absint' ] );
		register_setting( 'rbp_settings', 'rbp_pro_coupon_email_template', [ 'sanitize_callback' => 'wp_kses_post' ] );
		register_setting( 'rbp_settings', 'rbp_pro_coupon_log_enabled', [ 'sanitize_callback' => 'absint' ] );
		// GDPR Consent (Pro)
		register_setting( 'rbp_settings', 'rbp_pro_gdpr_consent_enabled', [ 'sanitize_callback' => 'absint' ] );
		register_setting( 'rbp_settings', 'rbp_pro_gdpr_consent_label', [ 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'rbp_settings', 'rbp_pro_gdpr_privacy_url', [ 'sanitize_callback' => 'esc_url_raw' ] );
		// License key and status (remove, now handled by Freemius)
		// register_setting( 'rbp_pro_settings', 'rbp_pro_license_key', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
		// register_setting( 'rbp_pro_settings', 'rbp_pro_license_status', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
	}

	/**
	 * Sanitize and validate multi-step reminders array
	 */
	public function sanitize_multistep_reminders( $input ) {
		if ( ! is_array( $input ) ) return [];
		$sanitized = [];
		foreach ( $input as $step ) {
			$sanitized[] = [
				'days'    => absint( $step['days'] ?? 0 ),
				'channel' => sanitize_text_field( $step['channel'] ?? '' ),
				'subject' => sanitize_text_field( $step['subject'] ?? '' ),
				'body'    => wp_kses_post( $step['body'] ?? '' ),
				// Conditions
				'order_total' => isset($step['order_total']) ? floatval($step['order_total']) : '',
				'products'    => isset($step['products']) && is_array($step['products']) ? array_map('absint', $step['products']) : [],
				'categories'  => isset($step['categories']) && is_array($step['categories']) ? array_map('absint', $step['categories']) : [],
				'countries'   => isset($step['countries']) && is_array($step['countries']) ? array_map('sanitize_text_field', $step['countries']) : [],
			];
		}
		return $sanitized;
	}

	/**
	 * Sanitize templates array
	 */
	public function sanitize_templates( $input ) {
		if ( ! is_array( $input ) ) return [];
		$sanitized = [];
		foreach ( $input as $type => $tpl ) {
			$sanitized[ $type ] = [
				'header' => wp_kses_post( $tpl['header'] ?? '' ),
				'body'   => wp_kses_post( $tpl['body'] ?? '' ),
				'footer' => wp_kses_post( $tpl['footer'] ?? '' ),
				'button' => wp_kses_post( $tpl['button'] ?? '' ),
			];
		}
		return $sanitized;
	}

	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ReviewBoost Pro Settings', 'reviewboost-pro' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'rbp_settings' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Enable Reminders', 'reviewboost-pro' ); ?></th>
						<td><input type="checkbox" name="rbp_enabled" value="1" <?php checked( 1, get_option( 'rbp_enabled', 1 ) ); ?> /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Reminder Delay (days)', 'reviewboost-pro' ); ?></th>
						<td><input type="number" name="rbp_reminder_delay_days" value="<?php echo esc_attr( get_option( 'rbp_reminder_delay_days', 3 ) ); ?>" min="1" max="30" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Email Subject', 'reviewboost-pro' ); ?></th>
						<td><input type="text" name="rbp_reminder_subject" value="<?php echo esc_attr( get_option( 'rbp_reminder_subject', __( 'We’d love your review! [Order #[order_id]]', 'reviewboost-pro' ) ) ); ?>" class="regular-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Email Body', 'reviewboost-pro' ); ?></th>
						<td><?php
							wp_editor( get_option( 'rbp_reminder_body', __( 'Hi [customer_name],<br><br>Thank you for your purchase! Please leave a review for your order #[order_id].', 'reviewboost-pro' ) ), 'rbp_reminder_body', [ 'textarea_name' => 'rbp_reminder_body', 'media_buttons' => false, 'textarea_rows' => 8 ] );
						?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Enable WhatsApp Reminders', 'reviewboost-pro' ); ?></th>
						<td><input type="checkbox" name="rbp_pro_enable_whatsapp" value="1" <?php checked( 1, get_option( 'rbp_pro_enable_whatsapp', 0 ) ); ?> /> <?php esc_html_e( '(Pro)', 'reviewboost-pro' ); ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'WhatsApp API Key', 'reviewboost-pro' ); ?></th>
						<td><input type="text" name="rbp_pro_whatsapp_api_sid" value="<?php echo esc_attr( get_option( 'rbp_pro_whatsapp_api_sid', '' ) ); ?>" class="regular-text" /> <?php esc_html_e( '(Pro)', 'reviewboost-pro' ); ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'WhatsApp API Token', 'reviewboost-pro' ); ?></th>
						<td><input type="password" name="rbp_pro_whatsapp_api_token" value="<?php echo esc_attr( get_option( 'rbp_pro_whatsapp_api_token', '' ) ); ?>" class="regular-text" autocomplete="off" /> <?php esc_html_e( '(Pro)', 'reviewboost-pro' ); ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'WhatsApp From Number', 'reviewboost-pro' ); ?></th>
						<td><input type="text" name="rbp_pro_whatsapp_from" value="<?php echo esc_attr( get_option( 'rbp_pro_whatsapp_from', '' ) ); ?>" class="regular-text" /> <?php esc_html_e( '(Pro)', 'reviewboost-pro' ); ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'WhatsApp Message Template', 'reviewboost-pro' ); ?></th>
						<td><textarea name="rbp_pro_whatsapp_template" rows="4" cols="60"><?php echo esc_textarea( get_option( 'rbp_pro_whatsapp_template', 'Hi [customer_name], please review your order [order_id]: [review_link]' ) ); ?></textarea> <?php esc_html_e( '(Pro)', 'reviewboost-pro' ); ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Enable SMS Reminders', 'reviewboost-pro' ); ?></th>
						<td><input type="checkbox" name="rbp_pro_enable_sms" value="1" <?php checked( 1, get_option( 'rbp_pro_enable_sms', 0 ) ); ?> /> <?php esc_html_e( '(Pro)', 'reviewboost-pro' ); ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'SMS API SID', 'reviewboost-pro' ); ?></th>
						<td><input type="text" name="rbp_pro_sms_api_sid" value="<?php echo esc_attr( get_option( 'rbp_pro_sms_api_sid', '' ) ); ?>" class="regular-text" /> <?php esc_html_e( '(Pro)', 'reviewboost-pro' ); ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'SMS API Token', 'reviewboost-pro' ); ?></th>
						<td><input type="password" name="rbp_pro_sms_api_token" value="<?php echo esc_attr( get_option( 'rbp_pro_sms_api_token', '' ) ); ?>" class="regular-text" autocomplete="off" /> <?php esc_html_e( '(Pro)', 'reviewboost-pro' ); ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'SMS From Number', 'reviewboost-pro' ); ?></th>
						<td><input type="text" name="rbp_pro_sms_from" value="<?php echo esc_attr( get_option( 'rbp_pro_sms_from', '' ) ); ?>" class="regular-text" /> <?php esc_html_e( '(Pro)', 'reviewboost-pro' ); ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'SMS Message Template', 'reviewboost-pro' ); ?></th>
						<td><textarea name="rbp_pro_sms_template" rows="4" cols="60"><?php echo esc_textarea( get_option( 'rbp_pro_sms_template', 'Hi [customer_name], please review your order [order_id]: [review_link]' ) ); ?></textarea> <?php esc_html_e( '(Pro)', 'reviewboost-pro' ); ?></td>
					</tr>
					<tr valign="top">
						<th scope="row" colspan="2"><strong><?php esc_html_e( 'Multi-step Reminders (Pro)', 'reviewboost-pro' ); ?></strong></th>
					</tr>
					<tr valign="top">
						<td colspan="2">
							<div id="rbp-multistep-reminders">
							<?php
							$steps = get_option( 'rbp_pro_multistep_reminders', [] );
							if ( empty( $steps ) ) {
								$steps = [ [ 'days' => 3, 'channel' => 'email', 'subject' => '', 'body' => '', 'order_total' => '', 'products' => [], 'categories' => [], 'countries' => [] ] ];
							}
							// WooCommerce data for selects
							$all_products = function_exists('wc_get_products') ? wc_get_products([ 'limit'=>1000, 'status'=>'publish', 'return'=>'ids' ]) : [];
							$product_options = [];
							foreach ( $all_products as $pid ) {
								$product_options[$pid] = get_the_title($pid);
							}
							$all_categories = get_terms([ 'taxonomy'=>'product_cat', 'hide_empty'=>false ]);
							$category_options = [];
							foreach ( $all_categories as $cat ) {
								$category_options[$cat->term_id] = $cat->name;
							}
							$all_countries = function_exists('WC') ? WC()->countries->get_countries() : [];
							foreach ( $steps as $i => $step ) {
								?>
								<div class="rbp-reminder-step" style="margin-bottom:1em;padding:1em;border:1px solid #ddd;">
									<label><?php esc_html_e( 'Days after completion:', 'reviewboost-pro' ); ?></label>
									<input type="number" name="rbp_pro_multistep_reminders[<?php echo $i; ?>][days]" value="<?php echo esc_attr( $step['days'] ); ?>" min="1" max="60" style="width:60px;" />
									&nbsp;
									<label><?php esc_html_e( 'Channel:', 'reviewboost-pro' ); ?></label>
									<select name="rbp_pro_multistep_reminders[<?php echo $i; ?>][channel]">
										<option value="email" <?php selected( $step['channel'], 'email' ); ?>>Email</option>
										<option value="whatsapp" <?php selected( $step['channel'], 'whatsapp' ); ?>>WhatsApp</option>
										<option value="sms" <?php selected( $step['channel'], 'sms' ); ?>>SMS</option>
									</select>
									<br/>
									<label><?php esc_html_e( 'Subject (Email only):', 'reviewboost-pro' ); ?></label>
									<input type="text" name="rbp_pro_multistep_reminders[<?php echo $i; ?>][subject]" value="<?php echo esc_attr( $step['subject'] ); ?>" class="regular-text" />
									<br/>
									<label><?php esc_html_e( 'Message:', 'reviewboost-pro' ); ?></label>
									<textarea name="rbp_pro_multistep_reminders[<?php echo $i; ?>][body]" rows="3" cols="60"><?php echo esc_textarea( $step['body'] ); ?></textarea>
									<br/>
									<!-- Conditional fields -->
									<fieldset style="margin-top:1em;padding:0.5em 1em;background:#f9f9f9;border:1px dashed #ccc;">
										<legend><?php esc_html_e('Send only if:', 'reviewboost-pro'); ?></legend>
										<label><?php esc_html_e('Order total >=', 'reviewboost-pro'); ?></label>
										<input type="number" step="0.01" min="0" name="rbp_pro_multistep_reminders[<?php echo $i; ?>][order_total]" value="<?php echo esc_attr($step['order_total'] ?? ''); ?>" style="width:90px;" />
										<br/>
										<label><?php esc_html_e('Product(s):', 'reviewboost-pro'); ?></label>
										<select name="rbp_pro_multistep_reminders[<?php echo $i; ?>][products][]" multiple style="min-width:150px;">
											<?php foreach ($product_options as $pid=>$title): ?>
												<option value="<?php echo esc_attr($pid); ?>" <?php if (!empty($step['products']) && in_array($pid, $step['products'])) echo 'selected'; ?>><?php echo esc_html($title); ?></option>
											<?php endforeach; ?>
										</select>
										<br/>
										<label><?php esc_html_e('Category(ies):', 'reviewboost-pro'); ?></label>
										<select name="rbp_pro_multistep_reminders[<?php echo $i; ?>][categories][]" multiple style="min-width:150px;">
											<?php foreach ($category_options as $cid=>$name): ?>
												<option value="<?php echo esc_attr($cid); ?>" <?php if (!empty($step['categories']) && in_array($cid, $step['categories'])) echo 'selected'; ?>><?php echo esc_html($name); ?></option>
											<?php endforeach; ?>
										</select>
										<br/>
										<label><?php esc_html_e('Country(ies):', 'reviewboost-pro'); ?></label>
										<select name="rbp_pro_multistep_reminders[<?php echo $i; ?>][countries][]" multiple style="min-width:150px;">
											<?php foreach ($all_countries as $code=>$name): ?>
												<option value="<?php echo esc_attr($code); ?>" <?php if (!empty($step['countries']) && in_array($code, $step['countries'])) echo 'selected'; ?>><?php echo esc_html($name); ?></option>
											<?php endforeach; ?>
										</select>
									</fieldset>
								</div>
								<?php
							}
							?>
							<!-- Add/Remove JS (simple, for now) -->
							<button type="button" id="rbp-add-reminder-step" class="button">+ <?php esc_html_e( 'Add Step', 'reviewboost-pro' ); ?></button>
							<script type="text/javascript">
							jQuery(document).ready(function($){
								$('#rbp-add-reminder-step').click(function(){
									var idx = $('#rbp-multistep-reminders .rbp-reminder-step').length;
									var html = `<div class=\"rbp-reminder-step\" style=\"margin-bottom:1em;padding:1em;border:1px solid #ddd;\">`+
										'<label><?php esc_html_e( 'Days after completion:', 'reviewboost-pro' ); ?></label>'+
										'<input type=\"number\" name=\"rbp_pro_multistep_reminders['+idx+'][days]\" value=\"3\" min=\"1\" max=\"60\" style=\"width:60px;\" />'+
										'&nbsp;'+
										'<label><?php esc_html_e( 'Channel:', 'reviewboost-pro' ); ?></label>'+
										'<select name=\"rbp_pro_multistep_reminders['+idx+'][channel]\">'+
											'<option value=\"email\">Email</option>'+
											'<option value=\"whatsapp\">WhatsApp</option>'+
											'<option value=\"sms\">SMS</option>'+
										'</select><br/>'+ 
										'<label><?php esc_html_e( 'Subject (Email only):', 'reviewboost-pro' ); ?></label>'+
										'<input type=\"text\" name=\"rbp_pro_multistep_reminders['+idx+'][subject]\" value=\"\" class=\"regular-text\" /><br/>'+ 
										'<label><?php esc_html_e( 'Message:', 'reviewboost-pro' ); ?></label>'+
										'<textarea name=\"rbp_pro_multistep_reminders['+idx+'][body]\" rows=\"3\" cols=\"60\"></textarea><br/>'+ 
										'<fieldset style=\"margin-top:1em;padding:0.5em 1em;background:#f9f9f9;border:1px dashed #ccc;\">'+
											'<legend><?php esc_html_e('Send only if:', 'reviewboost-pro'); ?></legend>'+
											'<label><?php esc_html_e('Order total >=', 'reviewboost-pro'); ?></label>'+
											'<input type=\"number\" step=\"0.01\" min=\"0\" name=\"rbp_pro_multistep_reminders['+idx+'][order_total]\" value=\"\" style=\"width:90px;\" /><br/>'+ 
											'<label><?php esc_html_e('Product(s):', 'reviewboost-pro'); ?></label>'+
											'<select name=\"rbp_pro_multistep_reminders['+idx+'][products][]\" multiple style=\"min-width:150px;\"><?php foreach ($product_options as $pid=>$title): ?><option value=\"<?php echo esc_attr($pid); ?>\"><?php echo esc_html($title); ?></option><?php endforeach; ?></select><br/>'+ 
											'<label><?php esc_html_e('Category(ies):', 'reviewboost-pro'); ?></label>'+
											'<select name=\"rbp_pro_multistep_reminders['+idx+'][categories][]\" multiple style=\"min-width:150px;\"><?php foreach ($category_options as $cid=>$name): ?><option value=\"<?php echo esc_attr($cid); ?>\"><?php echo esc_html($name); ?></option><?php endforeach; ?></select><br/>'+ 
											'<label><?php esc_html_e('Country(ies):', 'reviewboost-pro'); ?></label>'+
											'<select name=\"rbp_pro_multistep_reminders['+idx+'][countries][]\" multiple style=\"min-width:150px;\"><?php foreach ($all_countries as $code=>$name): ?><option value=\"<?php echo esc_attr($code); ?>\"><?php echo esc_html($name); ?></option><?php endforeach; ?></select>'+ 
										'</fieldset>'+ 
									'</div>';
								$('#rbp-multistep-reminders').append(html);
							});
						});
						</script>
					</td>
					</tr>
					<!-- End Multi-step Reminders -->
					<tr valign="top">
						<th scope="row" colspan="2"><strong><?php esc_html_e( 'Auto-Coupon on Review (Pro)', 'reviewboost-pro' ); ?></strong></th>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Enable Auto-Coupon', 'reviewboost-pro' ); ?></th>
						<td><input type="checkbox" name="rbp_pro_enable_coupon_on_review" value="1" <?php checked( get_option('rbp_pro_enable_coupon_on_review', 0), 1 ); ?> /> <?php esc_html_e( '(Pro)', 'reviewboost-pro' ); ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Coupon Type', 'reviewboost-pro' ); ?></th>
						<td>
							<select name="rbp_pro_coupon_type">
								<option value="fixed_cart" <?php selected( get_option('rbp_pro_coupon_type', 'fixed_cart'), 'fixed_cart' ); ?>><?php esc_html_e( 'Fixed Cart Discount', 'reviewboost-pro' ); ?></option>
								<option value="percent" <?php selected( get_option('rbp_pro_coupon_type', 'percent'), 'percent' ); ?>><?php esc_html_e( 'Percentage Discount', 'reviewboost-pro' ); ?></option>
								<option value="fixed_product" <?php selected( get_option('rbp_pro_coupon_type', 'fixed_product'), 'fixed_product' ); ?>><?php esc_html_e( 'Fixed Product Discount', 'reviewboost-pro' ); ?></option>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Coupon Amount', 'reviewboost-pro' ); ?></th>
						<td><input type="number" step="0.01" min="0.01" name="rbp_pro_coupon_amount" value="<?php echo esc_attr( get_option('rbp_pro_coupon_amount', 5) ); ?>" style="width:90px;" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Minimum Spend', 'reviewboost-pro' ); ?></th>
						<td><input type="number" step="0.01" min="0" name="rbp_pro_coupon_min_spend" value="<?php echo esc_attr( get_option('rbp_pro_coupon_min_spend', 0) ); ?>" style="width:90px;" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Usage Limit', 'reviewboost-pro' ); ?></th>
						<td><input type="number" min="1" name="rbp_pro_coupon_usage_limit" value="<?php echo esc_attr( get_option('rbp_pro_coupon_usage_limit', 1) ); ?>" style="width:90px;" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Expiry (days)', 'reviewboost-pro' ); ?></th>
						<td><input type="number" min="1" name="rbp_pro_coupon_expiry_days" value="<?php echo esc_attr( get_option('rbp_pro_coupon_expiry_days', 7) ); ?>" style="width:90px;" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Coupon Email Template', 'reviewboost-pro' ); ?></th>
						<td>
							<textarea name="rbp_pro_coupon_email_template" rows="4" cols="70"><?php echo esc_textarea( get_option('rbp_pro_coupon_email_template', __( 'Thank you for your review! Here is your coupon code: [coupon_code]', 'reviewboost-pro' ) ) ); ?></textarea>
							<br/>
							<small><?php esc_html_e('Available merge tags:', 'reviewboost-pro'); ?> <code>[customer_name]</code> <code>[coupon_code]</code> <code>[expiry_date]</code></small>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Log Coupon Events', 'reviewboost-pro' ); ?></th>
						<td><input type="checkbox" name="rbp_pro_coupon_log_enabled" value="1" <?php checked( get_option('rbp_pro_coupon_log_enabled', 1), 1 ); ?> /></td>
					</tr>
					<!-- End Auto-Coupon on Review -->
					<h2><?php esc_html_e('GDPR & Privacy (Pro)','reviewboost-pro'); ?></h2>
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php esc_html_e('Require Consent for Review Reminders','reviewboost-pro'); ?></th>
							<td><input type="checkbox" name="rbp_pro_gdpr_consent_enabled" value="1" <?php checked( get_option('rbp_pro_gdpr_consent_enabled', 1), 1 ); ?> />
							<?php esc_html_e('Require explicit customer consent at checkout before sending review reminders or marketing messages.','reviewboost-pro'); ?></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e('Consent Checkbox Label','reviewboost-pro'); ?></th>
							<td><input type="text" name="rbp_pro_gdpr_consent_label" value="<?php echo esc_attr(get_option('rbp_pro_gdpr_consent_label',__('I agree to receive review reminders and marketing from this store.','reviewboost-pro'))); ?>" style="width: 100%; max-width: 480px;" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e('Privacy Policy URL','reviewboost-pro'); ?></th>
							<td><input type="url" name="rbp_pro_gdpr_privacy_url" value="<?php echo esc_attr(get_option('rbp_pro_gdpr_privacy_url','')); ?>" style="width: 100%; max-width: 480px;" />
							<br/><small><?php esc_html_e('Link customers to your privacy policy from the consent checkbox.','reviewboost-pro'); ?></small></td>
						</tr>
					</table>
					<h2><?php esc_html_e( 'Advanced Template Builder (Pro)', 'reviewboost-pro' ); ?></h2>
					<div id="rbp-template-builder">
						<ul class="rbp-template-tabs">
							<li class="active"><a href="#rbp-tpl-email">Email</a></li>
							<li><a href="#rbp-tpl-whatsapp">WhatsApp</a></li>
							<li><a href="#rbp-tpl-sms">SMS</a></li>
						</ul>
						<div class="rbp-template-tab-content" id="rbp-tpl-email">
							<?php $tpl = get_option('rbp_pro_templates', []); $email = $tpl['email'] ?? ['header'=>'','body'=>'','footer'=>'','button'=>'']; ?>
							<h4><?php esc_html_e('Email Template', 'reviewboost-pro'); ?></h4>
							<label><?php esc_html_e('Header:', 'reviewboost-pro'); ?></label>
							<?php wp_editor( $email['header'], 'rbp_pro_templates_email_header', [ 'textarea_name' => 'rbp_pro_templates[email][header]', 'media_buttons' => false, 'textarea_rows' => 2 ] ); ?>
							<label><?php esc_html_e('Body:', 'reviewboost-pro'); ?></label>
							<?php wp_editor( $email['body'], 'rbp_pro_templates_email_body', [ 'textarea_name' => 'rbp_pro_templates[email][body]', 'media_buttons' => false, 'textarea_rows' => 6 ] ); ?>
							<label><?php esc_html_e('Button (HTML allowed):', 'reviewboost-pro'); ?></label>
							<input type="text" name="rbp_pro_templates[email][button]" value="<?php echo esc_attr($email['button']); ?>" class="regular-text" />
							<label><?php esc_html_e('Footer:', 'reviewboost-pro'); ?></label>
							<?php wp_editor( $email['footer'], 'rbp_pro_templates_email_footer', [ 'textarea_name' => 'rbp_pro_templates[email][footer]', 'media_buttons' => false, 'textarea_rows' => 2 ] ); ?>
							<p><?php esc_html_e('Available merge tags:', 'reviewboost-pro'); ?> <code>[customer_name]</code> <code>[order_id]</code> <code>[product_list]</code> <code>[review_url]</code></p>
							<!-- TODO: Add live preview, emoji picker, and drag/drop sections -->
						</div>
						<div class="rbp-template-tab-content" id="rbp-tpl-whatsapp" style="display:none;">
							<?php $wa = $tpl['whatsapp'] ?? ['body'=>'']; ?>
							<h4><?php esc_html_e('WhatsApp Template', 'reviewboost-pro'); ?></h4>
							<textarea name="rbp_pro_templates[whatsapp][body]" rows="4" cols="80"><?php echo esc_textarea($wa['body']); ?></textarea>
							<p><?php esc_html_e('Available merge tags:', 'reviewboost-pro'); ?> <code>[customer_name]</code> <code>[order_id]</code> <code>[review_url]</code></p>
							<!-- TODO: Add emoji picker -->
						</div>
						<div class="rbp-template-tab-content" id="rbp-tpl-sms" style="display:none;">
							<?php $sms = $tpl['sms'] ?? ['body'=>'']; ?>
							<h4><?php esc_html_e('SMS Template', 'reviewboost-pro'); ?></h4>
							<textarea name="rbp_pro_templates[sms][body]" rows="3" cols="80"><?php echo esc_textarea($sms['body']); ?></textarea>
							<p><?php esc_html_e('Available merge tags:', 'reviewboost-pro'); ?> <code>[customer_name]</code> <code>[order_id]</code> <code>[review_url]</code></p>
							<!-- TODO: Add emoji picker -->
						</div>
						<script type="text/javascript">
						jQuery(document).ready(function($){
							$('.rbp-template-tabs a').click(function(e){
								e.preventDefault();
								$('.rbp-template-tabs li').removeClass('active');
								$(this).parent().addClass('active');
								$('.rbp-template-tab-content').hide();
								$($(this).attr('href')).show();
							});
						});
						</script>
					</div>
					<?php settings_fields( 'rbp_pro_settings' ); ?>
					<h2><?php esc_html_e('License','reviewboost-pro'); ?></h2>
					<table class="form-table">
						<tr><th scope="row"><?php esc_html_e('License Key','reviewboost-pro'); ?></th>
							<td><?php esc_html_e('License management is now handled by Freemius.','reviewboost-pro'); ?></td></tr>
					</table>

					<?php if ( function_exists('fs') && ! fs()->can_use_premium_code() ) : ?>
						<div class="rbp-pro-upsell-banner" style="border:2px solid #7f54b3;background:#f9f6ff;padding:18px 18px 18px 60px;position:relative;margin:24px 0;">
							<span class="dashicons dashicons-awards" style="position:absolute;left:18px;top:18px;font-size:28px;color:#7f54b3;"></span>
							<strong><?php esc_html_e('Unlock WhatsApp & SMS Reminders, Advanced Logging, Multi-step Automation, and more!','reviewboost-pro'); ?></strong><br>
							<?php esc_html_e('Upgrade to ReviewBoost Pro and supercharge your store reviews.','reviewboost-pro'); ?>
							<br><a href="https://your-upgrade-page.com" target="_blank" class="button button-primary" style="margin-top:10px;"><?php esc_html_e('Learn More & Upgrade','reviewboost-pro'); ?></a>
						</div>
					<?php endif; ?>
					<?php submit_button(); ?>
				</form>

				<hr />
				<h2><?php esc_html_e( 'Recent Reminder Log', 'reviewboost-pro' ); ?></h2>
				<!-- Log table ... -->
			</div>
			<?php
	}

	/**
	 * Render the Pro Logs Dashboard page
	 */
	public function render_logs_dashboard() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'reviewboost-pro' ) );
		}
		global $wpdb;
		$table = $wpdb->prefix . 'reviewboost_logs';
		// Coupon analytics summary
		$coupon_args = [
			'post_type' => 'shop_coupon',
			'posts_per_page' => -1,
			'meta_query' => [
				[
					'key' => '_rbp_coupon_for_review',
					'compare' => 'EXISTS',
				]
			]
		];
		$coupons = get_posts( $coupon_args );
		$total_coupons = count($coupons);
		$total_redeemed = 0;
		$total_expired = 0;
		$total_active = 0;
		$now = current_time('timestamp');
		foreach ( $coupons as $coupon ) {
			$usage_count = intval( get_post_meta( $coupon->ID, 'usage_count', true ) );
			$date_expires = get_post_meta( $coupon->ID, 'date_expires', true );
			if ( $usage_count > 0 ) {
				$total_redeemed++;
			} elseif ( $date_expires && strtotime($date_expires) < $now ) {
				$total_expired++;
			} else {
				$total_active++;
			}
		}
		echo '<div style="margin-bottom:24px;padding:16px;border:1px solid #e2e2e2;background:#fafafa;display:flex;gap:32px;align-items:center;border-radius:8px;">';
		echo '<div><strong>' . esc_html__('Coupons Generated','reviewboost-pro') . ':</strong> ' . intval($total_coupons) . '</div>';
		echo '<div><strong>' . esc_html__('Active','reviewboost-pro') . ':</strong> ' . intval($total_active) . '</div>';
		echo '<div><strong>' . esc_html__('Redeemed','reviewboost-pro') . ':</strong> ' . intval($total_redeemed) . '</div>';
		echo '<div><strong>' . esc_html__('Expired','reviewboost-pro') . ':</strong> ' . intval($total_expired) . '</div>';
		echo '</div>';
		// Filters (existing code)
		$method = isset($_GET['method']) ? sanitize_text_field($_GET['method']) : '';
		$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
		$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : '';
		$customer_id = isset($_GET['customer_id']) ? absint($_GET['customer_id']) : '';
		$q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
		$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
		$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
		$page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
		$per_page = 20;
		$where = 'WHERE 1=1';
		$params = [];
		if ( $method ) { $where .= ' AND method = %s'; $params[] = $method; }
		if ( $status ) { $where .= ' AND status = %s'; $params[] = $status; }
		if ( $order_id ) { $where .= ' AND order_id = %d'; $params[] = $order_id; }
		if ( $customer_id ) { $where .= ' AND customer_id = %d'; $params[] = $customer_id; }
		if ( $date_from ) { $where .= ' AND time_sent >= %s'; $params[] = $date_from . ' 00:00:00'; }
		if ( $date_to ) { $where .= ' AND time_sent <= %s'; $params[] = $date_to . ' 23:59:59'; }
		if ( $q ) {
			$where .= ' AND (CAST(order_id AS CHAR) LIKE %s OR CAST(customer_id AS CHAR) LIKE %s OR details LIKE %s)';
			$params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%';
		}
		// Consent filter
		$consent = isset($_GET['consent']) ? sanitize_text_field($_GET['consent']) : '';
		if ( $consent !== '' ) {
			$where .= ' AND order_id IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key="_rbp_gdpr_consent" AND meta_value=%s)';
			$params[] = $consent;
		}
		// --- Analytics summary ---
		$stats = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) as total, SUM(status='sent') as sent, SUM(status='failed') as failed, SUM(method='email') as email, SUM(method='whatsapp') as whatsapp, SUM(method='sms') as sms, SUM(method='coupon') as coupon FROM $table $where", ...$params ), ARRAY_A );
		$recent = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(time_sent) FROM $table $where", ...$params ) );
		// --- End analytics ---
		// Export to CSV (existing code)
		if ( isset($_GET['export_csv']) && check_admin_referer('rbp_export_logs') ) {
			$logs = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $table $where ORDER BY time_sent DESC",
				...$params
			), ARRAY_A );
			headers_sent() || header('Content-Type: text/csv');
			headers_sent() || header('Content-Disposition: attachment; filename=reviewboost-logs.csv');
			$out = fopen('php://output', 'w');
			fputcsv($out, ['Date','Order ID','Customer ID','Method','Status','Retry','Details', 'Coupon Status']);
			foreach ($logs as $log) {
				fputcsv($out, [ $log['time_sent'], $log['order_id'], $log['customer_id'], $log['method'], $log['status'], $log['retry_count'], $log['details'], $this->get_coupon_status($log) ]);
			}
			fclose($out); exit;
		}
		// Pagination (existing code)
		$total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table $where", ...$params ) );
		$offset = ( $page - 1 ) * $per_page;
		$logs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table $where ORDER BY time_sent DESC LIMIT %d OFFSET %d", ...array_merge($params, [$per_page, $offset]) ) );
		// Get unique methods/status for filters
		$methods = $wpdb->get_col( "SELECT DISTINCT method FROM $table ORDER BY method" );
		$statuses = $wpdb->get_col( "SELECT DISTINCT status FROM $table ORDER BY status" );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ReviewBoost Pro Logs', 'reviewboost-pro' ); ?></h1>
			<p><?php esc_html_e( 'View and export all reminder and coupon events.', 'reviewboost-pro' ); ?></p>
			<!-- Analytics summary -->
			<div class="rbp-logs-analytics" style="margin-bottom:20px;background:#f9f9f9;border:1px solid #e1e1e1;padding:12px 18px;display:flex;gap:32px;align-items:center;border-radius:8px;">
				<div><strong><?php esc_html_e('Total','reviewboost-pro'); ?>:</strong> <?php echo intval($stats['total']); ?></div>
				<div><strong><?php esc_html_e('Sent','reviewboost-pro'); ?>:</strong> <?php echo intval($stats['sent']); ?></div>
				<div><strong><?php esc_html_e('Failed','reviewboost-pro'); ?>:</strong> <?php echo intval($stats['failed']); ?></div>
				<div><strong><?php esc_html_e('Email','reviewboost-pro'); ?>:</strong> <?php echo intval($stats['email']); ?></div>
				<div><strong><?php esc_html_e('WhatsApp','reviewboost-pro'); ?>:</strong> <?php echo intval($stats['whatsapp']); ?></div>
				<div><strong><?php esc_html_e('SMS','reviewboost-pro'); ?>:</strong> <?php echo intval($stats['sms']); ?></div>
				<div><strong><?php esc_html_e('Coupon','reviewboost-pro'); ?>:</strong> <?php echo intval($stats['coupon']); ?></div>
				<div><strong><?php esc_html_e('Most Recent','reviewboost-pro'); ?>:</strong> <?php echo esc_html($recent ? $recent : __('N/A','reviewboost-pro')); ?></div>
			</div>
			<!-- End analytics summary -->
			<form method="get">
				<input type="hidden" name="page" value="rbp-pro-logs" />
				<input type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="<?php esc_attr_e('Search Order/Customer/Details','reviewboost-pro'); ?>" />
				<select name="method"><option value=""><?php esc_html_e('All Methods','reviewboost-pro'); ?></option><?php foreach($methods as $m): ?><option value="<?php echo esc_attr($m); ?>" <?php selected($m,$method); ?>><?php echo esc_html($m); ?></option><?php endforeach; ?></select>
				<select name="status"><option value=""><?php esc_html_e('All Status','reviewboost-pro'); ?></option><?php foreach($statuses as $s): ?><option value="<?php echo esc_attr($s); ?>" <?php selected($s,$status); ?>><?php echo esc_html($s); ?></option><?php endforeach; ?></select>
				<input type="number" name="order_id" value="<?php echo esc_attr($order_id); ?>" placeholder="<?php esc_attr_e('Order ID','reviewboost-pro'); ?>" style="width:90px;" />
				<input type="number" name="customer_id" value="<?php echo esc_attr($customer_id); ?>" placeholder="<?php esc_attr_e('Customer ID','reviewboost-pro'); ?>" style="width:90px;" />
				<input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" />
				<input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" />
				<select name="consent"><option value=""><?php esc_html_e('All Consent','reviewboost-pro'); ?></option><option value="yes" <?php selected($consent,'yes'); ?>><?php esc_html_e('Yes','reviewboost-pro'); ?></option><option value="no" <?php selected($consent,'no'); ?>><?php esc_html_e('No','reviewboost-pro'); ?></option></select>
				<button type="submit" class="button"><?php esc_html_e('Filter','reviewboost-pro'); ?></button>
				<?php wp_nonce_field('rbp_export_logs'); ?>
				<button type="submit" name="export_csv" value="1" class="button button-secondary"><?php esc_html_e('Export CSV','reviewboost-pro'); ?></button>
			</form>
			<table class="widefat striped" style="margin-top:1em;">
				<thead><tr>
					<th><?php esc_html_e('Date','reviewboost-pro'); ?></th>
					<th><?php esc_html_e('Order ID','reviewboost-pro'); ?></th>
					<th><?php esc_html_e('Customer ID','reviewboost-pro'); ?></th>
					<th><?php esc_html_e('Method','reviewboost-pro'); ?></th>
					<th><?php esc_html_e('Status','reviewboost-pro'); ?></th>
					<th><?php esc_html_e('Retry','reviewboost-pro'); ?></th>
					<th><?php esc_html_e('Consent','reviewboost-pro'); ?></th>
					<th><?php esc_html_e('Details','reviewboost-pro'); ?></th>
					<th><?php esc_html_e('Coupon Status','reviewboost-pro'); ?></th>
				</tr></thead>
				<tbody>
				<?php if (empty($logs)): ?><tr><td colspan="9"><?php esc_html_e('No logs found.','reviewboost-pro'); ?></td></tr><?php else: ?>
				<?php foreach($logs as $log): ?>
				<tr class="rbp-log-row" data-log='<?php echo esc_attr(json_encode($log)); ?>' style="cursor:pointer;<?php if($log->status=='failed') echo 'background:#ffeaea;'; elseif($log->retry_count>0) echo 'background:#fffbe5;'; ?>">
					<td><?php echo esc_html($log->time_sent); ?></td>
					<td><?php echo esc_html($log->order_id); ?></td>
					<td><?php echo esc_html($log->customer_id); ?></td>
					<td><?php echo esc_html($log->method); ?></td>
					<td><?php echo esc_html($log->status); ?></td>
					<td><?php echo esc_html($log->retry_count); ?></td>
					<td><?php echo esc_html(get_post_meta($log->order_id, '_rbp_gdpr_consent', true)==='yes'?__('Yes','reviewboost-pro'):__('No','reviewboost-pro')); ?></td>
					<td><pre style="white-space:pre-wrap;word-break:break-all;"><?php echo esc_html($log->details); ?></pre></td>
					<td><?php echo $this->get_coupon_status($log); ?></td>
				</tr>
				<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
			<!-- Log detail modal -->
			<div id="rbp-log-modal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.3);z-index:9999;align-items:center;justify-content:center;">
				<div style="background:#fff;padding:24px 32px;max-width:600px;width:90vw;max-height:90vh;overflow:auto;position:relative;box-shadow:0 2px 16px #0002;">
					<button onclick="document.getElementById('rbp-log-modal').style.display='none';" style="position:absolute;top:8px;right:8px;">&times;</button>
					<h2><?php esc_html_e('Log Details','reviewboost-pro'); ?></h2>
					<pre id="rbp-log-modal-content" style="white-space:pre-wrap;word-break:break-all;background:#f9f9f9;padding:12px 16px;"></pre>
				</div>
			</div>
			<script>
			(function(){
				document.querySelectorAll('.rbp-log-row').forEach(function(row){
					row.addEventListener('click', function(){
						var log = JSON.parse(this.getAttribute('data-log'));
						var content = '';
						for(var k in log){
							content += k+': '+(typeof log[k]==='object'?JSON.stringify(log[k],null,2):log[k])+"\n";
						}
						document.getElementById('rbp-log-modal-content').textContent = content;
						document.getElementById('rbp-log-modal').style.display = 'flex';
					});
				});
			})();
			</script>
			<?php
			// Pagination (existing code)
			$total_pages = ceil($total/$per_page);
			if ( $total_pages > 1 ) {
				$page_base = remove_query_arg('paged');
				echo '<div class="tablenav"><span class="pagination-links">';
				for ( $p = 1; $p <= $total_pages; $p++ ) {
					if ( $p == $page ) {
						echo '<span class="tablenav-pages-navspan">' . $p . '</span> ';
					} else {
						echo '<a href="' . esc_url(add_query_arg('paged', $p, $page_base)) . '">' . $p . '</a> ';
					}
				}
				echo '</span></div>';
			}
			?>
		</div>
		<?php
	}

	public function get_coupon_status($log) {
		if ($log->method === 'coupon') {
			$details = is_array($log->details) ? $log->details : json_decode($log->details, true);
			$coupon_code = '';
			if (is_array($details) && isset($details['coupon_code'])) {
				$coupon_code = $details['coupon_code'];
			} elseif (is_string($log->details) && preg_match('/[A-Z0-9\-_]{6,}/', $log->details, $m)) {
				$coupon_code = $m[0]; // fallback: extract code
			}
			if ($coupon_code) {
				$coupon_id = wc_get_coupon_id_by_code($coupon_code);
				if ($coupon_id) {
					$usage_count = intval(get_post_meta($coupon_id, 'usage_count', true));
					$date_expires = get_post_meta($coupon_id, 'date_expires', true);
					$now = strtotime(date('Y-m-d'));
					if ($usage_count > 0) {
						return '<span style="color:#388e3c;font-weight:bold;">' . esc_html__('Redeemed','reviewboost-pro') . '</span>';
					} elseif ($date_expires && strtotime($date_expires) < $now) {
						return '<span style="color:#d32f2f;font-weight:bold;">' . esc_html__('Expired','reviewboost-pro') . '</span>';
					} else {
						return '<span style="color:#1976d2;font-weight:bold;">' . esc_html__('Active','reviewboost-pro') . '</span>';
					}
				} else {
					return esc_html__('Not found','reviewboost-pro');
				}
			} else {
				return esc_html__('N/A','reviewboost-pro');
			}
		} else {
			return '&mdash;';
		}
	}

	/**
	 * AJAX handler for logs table
	 */
	public function ajax_fetch_logs() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer('rbp_logs_dashboard','nonce', false) ) {
			wp_send_json_error(['message'=>__('Unauthorized','reviewboost-pro')]);
		}
		global $wpdb;
		$table = $wpdb->prefix . 'reviewboost_logs';
		// Collect filters
		$method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : '';
		$status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
		$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : '';
		$customer_id = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : '';
		$q = isset($_POST['q']) ? sanitize_text_field($_POST['q']) : '';
		$date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
		$date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
		$page = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;
		$per_page = 20;
		$where = 'WHERE 1=1';
		$params = [];
		if ( $method ) { $where .= ' AND method = %s'; $params[] = $method; }
		if ( $status ) { $where .= ' AND status = %s'; $params[] = $status; }
		if ( $order_id ) { $where .= ' AND order_id = %d'; $params[] = $order_id; }
		if ( $customer_id ) { $where .= ' AND customer_id = %d'; $params[] = $customer_id; }
		if ( $date_from ) { $where .= ' AND time_sent >= %s'; $params[] = $date_from . ' 00:00:00'; }
		if ( $date_to ) { $where .= ' AND time_sent <= %s'; $params[] = $date_to . ' 23:59:59'; }
		if ( $q ) {
			$where .= ' AND (CAST(order_id AS CHAR) LIKE %s OR CAST(customer_id AS CHAR) LIKE %s OR details LIKE %s)';
			$params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%';
		}
		// Consent filter
		$consent = isset($_POST['consent']) ? sanitize_text_field($_POST['consent']) : '';
		if ( $consent !== '' ) {
			$where .= ' AND order_id IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key="_rbp_gdpr_consent" AND meta_value=%s)';
			$params[] = $consent;
		}
		$total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table $where", ...$params ) );
		$offset = ( $page - 1 ) * $per_page;
		$logs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table $where ORDER BY time_sent DESC LIMIT %d OFFSET %d", ...array_merge($params, [$per_page, $offset]) ) );
		$stats = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) as total, SUM(status='sent') as sent, SUM(status='failed') as failed, SUM(method='email') as email, SUM(method='whatsapp') as whatsapp, SUM(method='sms') as sms, SUM(method='coupon') as coupon FROM $table $where", ...$params ), ARRAY_A );
		$recent = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(time_sent) FROM $table $where", ...$params ) );
		$method_counts = $wpdb->get_results( $wpdb->prepare( "SELECT method, COUNT(*) as count FROM $table $where GROUP BY method", ...$params ), ARRAY_A );
		$status_counts = $wpdb->get_results( $wpdb->get_prepare( "SELECT status, COUNT(*) as count FROM $table $where GROUP BY status", ...$params ), ARRAY_A );
		$methods = $wpdb->get_col( "SELECT DISTINCT method FROM $table ORDER BY method" );
		$statuses = $wpdb->get_col( "SELECT DISTINCT status FROM $table ORDER BY status" );
		wp_send_json_success([
			'logs'=>$logs,
			'total'=>$total,
			'page'=>$page,
			'per_page'=>$per_page,
			'stats'=>$stats,
			'recent'=>$recent,
			'method_counts'=>$method_counts,
			'status_counts'=>$status_counts,
			'methods'=>$methods,
			'statuses'=>$statuses
		]);
	}

	/**
	 * AJAX handler for exporting logs as CSV
	 */
	public function ajax_export_logs() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer('rbp_export_logs') ) {
			wp_send_json_error(['message'=>__('Unauthorized','reviewboost-pro')]);
		}
		global $wpdb;
		$table = $wpdb->prefix . 'reviewboost_logs';
		$method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : '';
		$status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
		$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : '';
		$customer_id = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : '';
		$q = isset($_POST['q']) ? sanitize_text_field($_POST['q']) : '';
		$date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
		$date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
		$where = 'WHERE 1=1';
		$params = [];
		if ( $method ) { $where .= ' AND method = %s'; $params[] = $method; }
		if ( $status ) { $where .= ' AND status = %s'; $params[] = $status; }
		if ( $order_id ) { $where .= ' AND order_id = %d'; $params[] = $order_id; }
		if ( $customer_id ) { $where .= ' AND customer_id = %d'; $params[] = $customer_id; }
		if ( $date_from ) { $where .= ' AND time_sent >= %s'; $params[] = $date_from . ' 00:00:00'; }
		if ( $date_to ) { $where .= ' AND time_sent <= %s'; $params[] = $date_to . ' 23:59:59'; }
		if ( $q ) {
			$where .= ' AND (CAST(order_id AS CHAR) LIKE %s OR CAST(customer_id AS CHAR) LIKE %s OR details LIKE %s)';
			$params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%';
		}
		// Consent filter
		$consent = isset($_POST['consent']) ? sanitize_text_field($_POST['consent']) : '';
		if ( $consent !== '' ) {
			$where .= ' AND order_id IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key="_rbp_gdpr_consent" AND meta_value=%s)';
			$params[] = $consent;
		}
		$logs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table $where ORDER BY time_sent DESC", ...$params ), ARRAY_A );
		if ( empty( $logs ) ) {
			wp_send_json_error(['message'=>__('No logs found for export.','reviewboost-pro')]);
		}
		$filename = 'reviewboost-logs-' . date('Ymd-Hi') . '.csv';
		headers_sent() || header( 'Content-Type: text/csv; charset=utf-8' );
		headers_sent() || header( 'Content-Disposition: attachment; filename=' . $filename );
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array_keys( $logs[0] ) );
		foreach ( $logs as $log ) {
			fputcsv( $output, $log );
		}
		fclose( $output );
		exit;
	}

	/**
	 * Enqueue custom admin CSS for ReviewBoost Pro
	 */
	public function enqueue_admin_assets($hook) {
		// Only load on ReviewBoost Pro admin pages
		if ( isset($_GET['page']) && strpos($_GET['page'], 'reviewboost-pro') !== false ) {
			wp_enqueue_style('rbp-admin-css', plugins_url('../assets/admin.css', __FILE__), [], '1.0');
		}
	}

	/**
	 * Add Getting Started page and helpful links
	 */
	public function add_getting_started_page() {
		add_submenu_page(
			'reviewboost-pro',
			__('Getting Started','reviewboost-pro'),
			__('Getting Started','reviewboost-pro'),
			'manage_options',
			'rbp-getting-started',
			[ $this, 'render_getting_started_page' ]
		);
		// Add Docs and Support links
		add_submenu_page(
			'reviewboost-pro',
			__('Docs','reviewboost-pro'),
			__('Docs','reviewboost-pro'),
			'manage_options',
			'rbp-docs',
			function() { wp_redirect('https://your-docs-url.com'); exit; }
		);
		add_submenu_page(
			'reviewboost-pro',
			__('Get Support','reviewboost-pro'),
			__('Get Support','reviewboost-pro'),
			'manage_options',
			'rbp-support',
			function() { wp_redirect('https://your-support-url.com'); exit; }
		);
	}

	/**
	 * Render the Getting Started page
	 */
	public function render_getting_started_page() {
		?>
		<div class="wrap rbp-getting-started">
			<h1 style="color:#7f54b3;"><span class="dashicons dashicons-star-filled" style="font-size:32px;vertical-align:middle;margin-right:8px;"></span> <?php esc_html_e('Welcome to ReviewBoost Pro!','reviewboost-pro'); ?></h1>
			<p style="font-size:18px;max-width:680px;line-height:1.6;">
				<?php esc_html_e('Thank you for choosing ReviewBoost Pro! Here’s how to get started and make the most of your review automation:', 'reviewboost-pro'); ?>
			</p>
			<ol style="font-size:16px;max-width:700px;">
				<li><strong><?php esc_html_e('Connect WhatsApp & SMS (Pro):','reviewboost-pro'); ?></strong> <?php esc_html_e('Enter your Twilio credentials in the Pro settings to enable WhatsApp and SMS reminders.', 'reviewboost-pro'); ?></li>
				<li><strong><?php esc_html_e('Customize Reminder Templates:','reviewboost-pro'); ?></strong> <?php esc_html_e('Use the Advanced Template Builder to personalize your email, WhatsApp, and SMS messages.', 'reviewboost-pro'); ?></li>
				<li><strong><?php esc_html_e('Enable Multi-step Automations:','reviewboost-pro'); ?></strong> <?php esc_html_e('Set up multi-step reminders for maximum engagement.', 'reviewboost-pro'); ?></li>
				<li><strong><?php esc_html_e('Track Results:','reviewboost-pro'); ?></strong> <?php esc_html_e('View all reminder logs and analytics in the Pro Dashboard.', 'reviewboost-pro'); ?></li>
				<li><strong><?php esc_html_e('Need Help?','reviewboost-pro'); ?></strong> <a href="https://your-support-url.com" target="_blank"><?php esc_html_e('Get Support','reviewboost-pro'); ?></a> | <a href="https://your-docs-url.com" target="_blank"><?php esc_html_e('Read Docs','reviewboost-pro'); ?></a></li>
			</ol>
			<div style="margin-top:32px;">
				<a href="https://your-upgrade-page.com" class="button button-primary button-hero" style="font-size:18px;"><?php esc_html_e('Upgrade to Pro','reviewboost-pro'); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Redirect to Getting Started page after activation
	 */
	public function maybe_redirect_to_getting_started() {
		if ( get_transient('rbp_do_activation_redirect') ) {
			delete_transient('rbp_do_activation_redirect');
			if ( ! isset($_GET['activate-multi']) ) {
				wp_safe_redirect( admin_url('admin.php?page=rbp-getting-started') );
				exit;
			}
		}
	}

	/**
	 * Add Coupon Logs Dashboard submenu
	 */
	public function add_coupon_logs_dashboard_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'ReviewBoost Coupon Logs', 'reviewboost-pro' ),
			__( 'Coupon Logs', 'reviewboost-pro' ),
			'manage_woocommerce',
			'rbp-coupon-logs',
			[ $this, 'render_coupon_logs_dashboard' ]
		);
	}

	/**
	 * Render the Coupon Logs Dashboard page (skeleton for now)
	 */
	public function render_coupon_logs_dashboard() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'reviewboost-pro' ) );
		}
		global $wpdb;
		$table = $wpdb->prefix . 'reviewboost_logs';
		$event_types = ['generated','sent','redeemed','expired'];
		// Filters
		$event = isset($_GET['event']) ? sanitize_text_field($_GET['event']) : '';
		$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
		$customer = isset($_GET['customer']) ? sanitize_text_field($_GET['customer']) : '';
		$coupon_code = isset($_GET['coupon_code']) ? sanitize_text_field($_GET['coupon_code']) : '';
		$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
		$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
		$page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
		$per_page = 20;
		$where = "WHERE method = 'coupon'";
		$params = [];
		if ( $event ) { $where .= ' AND status = %s'; $params[] = $event; }
		if ( $customer ) { $where .= ' AND customer_id = %s'; $params[] = $customer; }
		if ( $date_from ) { $where .= ' AND time_sent >= %s'; $params[] = $date_from . ' 00:00:00'; }
		if ( $date_to ) { $where .= ' AND time_sent <= %s'; $params[] = $date_to . ' 23:59:59'; }
		// Coupon code filter (in details JSON)
		if ( $coupon_code ) {
			$where .= " AND details LIKE %s";
			$params[] = '%' . $coupon_code . '%';
		}
		// Analytics summary
		$stats = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) as total, ".
			"SUM(status='generated') as generated, ".
			"SUM(status='sent') as sent, ".
			"SUM(status='redeemed') as redeemed, ".
			"SUM(status='expired') as expired ".
			"FROM $table $where", ...$params ), ARRAY_A );
		// Query logs
		$total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table $where", ...$params ) );
		$offset = ( $page - 1 ) * $per_page;
		$logs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table $where ORDER BY time_sent DESC LIMIT %d OFFSET %d", ...array_merge($params, [$per_page, $offset]) ) );
		// CSV export
		if ( isset($_GET['export_coupon_csv']) && check_admin_referer('rbp_export_coupon_logs') ) {
			$all_logs = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $table $where ORDER BY time_sent DESC",
				...$params
			) );
			headers_sent() || header('Content-Type: text/csv');
			headers_sent() || header('Content-Disposition: attachment; filename=reviewboost-coupon-logs.csv');
			$out = fopen('php://output', 'w');
			fputcsv($out, ['Date','Customer ID','Event','Coupon Code','Order/Review ID','Status','Details']);
			foreach ($all_logs as $log) {
				$details = is_array($log->details) ? $log->details : json_decode($log->details, true);
				$coupon_code = is_array($details) && isset($details['coupon_code']) ? $details['coupon_code'] : '';
				$review_id = is_array($details) && isset($details['review_id']) ? $details['review_id'] : '';
				fputcsv($out, [ $log->time_sent, $log->customer_id, $log->status, $coupon_code, $review_id, $this->get_coupon_status($log), $log->details ]);
			}
			fclose($out); exit;
		}
		// UI
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('ReviewBoost Coupon Logs','reviewboost-pro') . '</h1>';
		// Analytics summary
		echo '<div style="margin-bottom:24px;padding:16px;border:1px solid #e2e2e2;background:#fafafa;display:flex;gap:32px;align-items:center;border-radius:8px;">';
		echo '<div><strong>' . esc_html__('Total','reviewboost-pro') . ':</strong> ' . intval($stats['total']) . '</div>';
		echo '<div><strong>' . esc_html__('Generated','reviewboost-pro') . ':</strong> ' . intval($stats['generated']) . '</div>';
		echo '<div><strong>' . esc_html__('Sent','reviewboost-pro') . ':</strong> ' . intval($stats['sent']) . '</div>';
		echo '<div><strong>' . esc_html__('Redeemed','reviewboost-pro') . ':</strong> ' . intval($stats['redeemed']) . '</div>';
		echo '<div><strong>' . esc_html__('Expired','reviewboost-pro') . ':</strong> ' . intval($stats['expired']) . '</div>';
		echo '</div>';
		// Filters
		echo '<form method="get" style="margin:18px 0;display:flex;gap:18px;align-items:flex-end;flex-wrap:wrap;">';
		echo '<input type="hidden" name="page" value="rbp-coupon-logs" />';
		echo '<label>' . esc_html__('Event','reviewboost-pro') . ': <select name="event"><option value="">' . esc_html__('All','reviewboost-pro') . '</option>';
		foreach($event_types as $e) {
			echo '<option value="' . esc_attr($e) . '"' . selected($e,$event,false) . '>' . esc_html(ucfirst($e)) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . esc_html__('Status','reviewboost-pro') . ': <select name="status"><option value="">' . esc_html__('All','reviewboost-pro') . '</option>';
		echo '<option value="active"' . selected($status,'active',false) . '>' . esc_html__('Active','reviewboost-pro') . '</option>';
		echo '<option value="redeemed"' . selected($status,'redeemed',false) . '>' . esc_html__('Redeemed','reviewboost-pro') . '</option>';
		echo '<option value="expired"' . selected($status,'expired',false) . '>' . esc_html__('Expired','reviewboost-pro') . '</option>';
		echo '</select></label>';
		echo '<label>' . esc_html__('Customer ID','reviewboost-pro') . ': <input type="text" name="customer" value="' . esc_attr($customer) . '" /></label>';
		echo '<label>' . esc_html__('Coupon Code','reviewboost-pro') . ': <input type="text" name="coupon_code" value="' . esc_attr($coupon_code) . '" /></label>';
		echo '<label>' . esc_html__('From','reviewboost-pro') . ': <input type="date" name="date_from" value="' . esc_attr($date_from) . '" /></label>';
		echo '<label>' . esc_html__('To','reviewboost-pro') . ': <input type="date" name="date_to" value="' . esc_attr($date_to) . '" /></label>';
		echo '<button type="submit" class="button">' . esc_html__('Filter','reviewboost-pro') . '</button>';
		wp_nonce_field('rbp_export_coupon_logs');
		echo '<button type="submit" name="export_coupon_csv" value="1" class="button button-secondary">' . esc_html__('Export CSV','reviewboost-pro') . '</button>';
		echo '</form>';
		// Logs table
		echo '<table class="widefat striped" style="margin-top:1em;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('Date','reviewboost-pro') . '</th>';
		echo '<th>' . esc_html__('Customer ID','reviewboost-pro') . '</th>';
		echo '<th>' . esc_html__('Event','reviewboost-pro') . '</th>';
		echo '<th>' . esc_html__('Coupon Code','reviewboost-pro') . '</th>';
		echo '<th>' . esc_html__('Order/Review ID','reviewboost-pro') . '</th>';
		echo '<th>' . esc_html__('Status','reviewboost-pro') . '</th>';
		echo '<th>' . esc_html__('Details','reviewboost-pro') . '</th>';
		echo '</tr></thead><tbody>';
		if (empty($logs)) {
			echo '<tr><td colspan="7">' . esc_html__('No logs found.','reviewboost-pro') . '</td></tr>';
		} else {
			foreach($logs as $log) {
				$details = is_array($log->details) ? $log->details : json_decode($log->details, true);
				$coupon_code = is_array($details) && isset($details['coupon_code']) ? $details['coupon_code'] : '';
				$review_id = is_array($details) && isset($details['review_id']) ? $details['review_id'] : '';
				// Status filter (active, redeemed, expired)
				$row_status = $this->get_coupon_status($log);
				if ($status) {
					$plain = strtolower(strip_tags($row_status));
					if ($plain !== $status) continue;
				}
				echo '<tr>';
				echo '<td>' . esc_html($log->time_sent) . '</td>';
				echo '<td>' . esc_html($log->customer_id) . '</td>';
				echo '<td>' . esc_html(ucfirst($log->status)) . '</td>';
				echo '<td>' . esc_html($coupon_code) . '</td>';
				echo '<td>' . esc_html($review_id) . '</td>';
				echo '<td>' . $row_status . '</td>';
				echo '<td><pre style="white-space:pre-wrap;word-break:break-all;">' . esc_html(is_string($log->details) ? $log->details : json_encode($log->details)) . '</pre></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		// Pagination
		$total_pages = ceil($total/$per_page);
		if ( $total_pages > 1 ) {
			$page_base = remove_query_arg('paged');
			echo '<div class="tablenav"><span class="pagination-links">';
			for ( $p = 1; $p <= $total_pages; $p++ ) {
				if ( $p == $page ) {
					echo '<span class="tablenav-pages-navspan">' . $p . '</span> ';
				} else {
					echo '<a href="' . esc_url(add_query_arg('paged', $p, $page_base)) . '">' . $p . '</a> ';
				}
			}
			echo '</span></div>';
		}
		echo '</div>';
	}

}

// Initialize admin
add_action( 'plugins_loaded', function() {
	if ( is_admin() ) {
		$GLOBALS['rbp_admin'] = new RBP_Admin();
	}
} );
