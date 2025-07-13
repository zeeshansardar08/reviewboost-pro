<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RBP_Admin {
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_menu', [ $this, 'add_logs_dashboard_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
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
		register_setting( 'rbp_settings', 'rbp_pro_enable_whatsapp', [ 'sanitize_callback' => 'absint' ] );
		register_setting( 'rbp_settings', 'rbp_pro_whatsapp_api_key', [ 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'rbp_settings', 'rbp_pro_enable_sms', [ 'sanitize_callback' => 'absint' ] );
		register_setting( 'rbp_settings', 'rbp_pro_sms_provider', [ 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'rbp_settings', 'rbp_pro_sms_sid', [ 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'rbp_settings', 'rbp_pro_sms_token', [ 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'rbp_settings', 'rbp_pro_sms_from', [ 'sanitize_callback' => 'sanitize_text_field' ] );
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
						<td><input type="text" name="rbp_pro_whatsapp_api_key" value="<?php echo esc_attr( get_option( 'rbp_pro_whatsapp_api_key', '' ) ); ?>" class="regular-text" /> <?php esc_html_e( '(Pro)', 'reviewboost-pro' ); ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Enable SMS Reminders', 'reviewboost-pro' ); ?></th>
						<td><input type="checkbox" name="rbp_pro_enable_sms" value="1" <?php checked( 1, get_option( 'rbp_pro_enable_sms', 0 ) ); ?> /> <?php esc_html_e( '(Pro)', 'reviewboost-pro' ); ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'SMS Provider', 'reviewboost-pro' ); ?></th>
						<td>
							<select name="rbp_pro_sms_provider">
								<option value="twilio" <?php selected( get_option( 'rbp_pro_sms_provider', 'twilio' ), 'twilio' ); ?>>Twilio</option>
								<option value="nexmo" <?php selected( get_option( 'rbp_pro_sms_provider', 'twilio' ), 'nexmo' ); ?>>Nexmo</option>
							</select> <?php esc_html_e( '(Pro)', 'reviewboost-pro' ); ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'SMS API SID', 'reviewboost-pro' ); ?></th>
						<td><input type="text" name="rbp_pro_sms_sid" value="<?php echo esc_attr( get_option( 'rbp_pro_sms_sid', '' ) ); ?>" class="regular-text" /> <?php esc_html_e( '(Pro)', 'reviewboost-pro' ); ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'SMS API Token', 'reviewboost-pro' ); ?></th>
						<td><input type="text" name="rbp_pro_sms_token" value="<?php echo esc_attr( get_option( 'rbp_pro_sms_token', '' ) ); ?>" class="regular-text" /> <?php esc_html_e( '(Pro)', 'reviewboost-pro' ); ?></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'SMS From Number', 'reviewboost-pro' ); ?></th>
						<td><input type="text" name="rbp_pro_sms_from" value="<?php echo esc_attr( get_option( 'rbp_pro_sms_from', '' ) ); ?>" class="regular-text" /> <?php esc_html_e( '(Pro)', 'reviewboost-pro' ); ?></td>
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
							<small><?php esc_html_e( 'Available merge tags:', 'reviewboost-pro' ); ?> <code>[customer_name]</code> <code>[coupon_code]</code> <code>[expiry_date]</code></small>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Log Coupon Events', 'reviewboost-pro' ); ?></th>
						<td><input type="checkbox" name="rbp_pro_coupon_log_enabled" value="1" <?php checked( get_option('rbp_pro_coupon_log_enabled', 1), 1 ); ?> /></td>
					</tr>
					<!-- End Auto-Coupon on Review -->
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
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Recent Reminder Log', 'reviewboost-pro' ); ?></h2>
			<!-- Log table ... -->
		</div>
		<?php
	}

	/**
	 * Add Pro Logs Dashboard menu
	 */
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

	/**
	 * Render the Pro Logs Dashboard page
	 */
	public function render_logs_dashboard() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'reviewboost-pro' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ReviewBoost Pro Logs', 'reviewboost-pro' ); ?></h1>
			<p><?php esc_html_e( 'View and export all reminder and coupon events.', 'reviewboost-pro' ); ?></p>
			<!-- Logs table will be rendered here -->
			<div id="rbp-pro-logs-table"></div>
		</div>
		<?php
	}
}

// Initialize admin
add_action( 'plugins_loaded', function() {
	if ( is_admin() ) {
		$GLOBALS['rbp_admin'] = new RBP_Admin();
	}
} );
