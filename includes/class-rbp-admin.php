<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RBP_Admin {
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
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
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Recent Reminder Log', 'reviewboost-pro' ); ?></h2>
			<!-- Log table ... -->
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
