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
		add_action('admin_menu', [ $this, 'add_upgrade_to_pro_page' ]); // New action hook
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
		// Removed all pro and multi-step/advanced template fields for MVP
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
						<th scope="row" colspan="2"><strong><?php esc_html_e( 'Available Template Tags', 'reviewboost-pro' ); ?></strong></th>
					</tr>
					<tr valign="top">
						<td colspan="2">
							<div class="rbp-template-tags-help">
								<p>
									<strong>[customer_name]</strong> – <?php esc_html_e('Customer first name', 'reviewboost-pro'); ?><br>
									<strong>[order_id]</strong> – <?php esc_html_e('Order ID', 'reviewboost-pro'); ?><br>
									<strong>{product_review_links}</strong> – <?php esc_html_e('List of product-specific review links for this order. Outputs as a bulleted HTML list in emails, or plain text list in WhatsApp/SMS.', 'reviewboost-pro'); ?>
								</p>
								<p>
									<em><?php esc_html_e('Example usage:', 'reviewboost-pro'); ?></em><br>
									<code>Hi [customer_name],<br>Thank you for your purchase! Please review your products:<br>{product_review_links}</code>
								</p>
							</div>
						</td>
					</tr>
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
						<tr><th scope="row"><?php esc_html_e('Require Consent for Review Reminders','reviewboost-pro'); ?></th>
							<td><input type="checkbox" name="rbp_pro_gdpr_consent_enabled" value="1" <?php checked( get_option('rbp_pro_gdpr_consent_enabled', 1), 1 ); ?> />
							<?php esc_html_e('Require explicit customer consent at checkout before sending review reminders or marketing messages.','reviewboost-pro'); ?></td></tr>
						<tr><th scope="row"><?php esc_html_e('Consent Checkbox Label','reviewboost-pro'); ?></th>
							<td><input type="text" name="rbp_pro_gdpr_consent_label" value="<?php echo esc_attr(get_option('rbp_pro_gdpr_consent_label',__('I agree to receive review reminders and marketing from this store.','reviewboost-pro'))); ?>" style="width: 100%; max-width: 480px;" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Privacy Policy URL','reviewboost-pro'); ?></th>
							<td><input type="url" name="rbp_pro_gdpr_privacy_url" value="<?php echo esc_attr(get_option('rbp_pro_gdpr_privacy_url','')); ?>" style="width: 100%; max-width: 480px;" />
							<br/><small><?php esc_html_e('Link customers to your privacy policy from the consent checkbox.','reviewboost-pro'); ?></small></td></tr>
					</table>
					<?php if ( function_exists('fs') && ! fs()->can_use_premium_code() ) : ?>
						<div class="rbp-pro-upsell-banner">
							<span class="dashicons dashicons-awards rbp-upsell-icon"></span>
							<div class="rbp-upsell-content">
								<strong><?php esc_html_e('Unlock WhatsApp & SMS Reminders, Advanced Logging, Multi-step Automation, and more!','reviewboost-pro'); ?></strong>
								<p><?php esc_html_e('Upgrade to ReviewBoost Pro and supercharge your store reviews.','reviewboost-pro'); ?></p>
								<a href="https://your-upgrade-page.com" target="_blank" class="rbp-btn rbp-btn-primary"><?php esc_html_e('Learn More & Upgrade','reviewboost-pro'); ?></a>
							</div>
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

	public function render_logs_dashboard() {
		?>
		<div class="wrap rbp-admin-wrap">
			<!-- Premium Dashboard Header -->
			<div class="rbp-card rbp-dashboard-header">
				<h1 class="rbp-card-title">
					<i class="dashicons dashicons-chart-line" style="color: #7c3aed;"></i>
					<?php esc_html_e('ReviewBoost Pro Logs Dashboard', 'reviewboost-pro'); ?>
					<span class="rbp-pro-badge">PRO</span>
				</h1>
				<p class="rbp-card-description"><?php esc_html_e('Comprehensive analytics and monitoring for all ReviewBoost Pro activities', 'reviewboost-pro'); ?></p>
			</div>

			<!-- Analytics Overview - Vertical Column Layout -->
			<div class="rbp-analytics-overview rbp-vertical-cards">
				<div class="rbp-metric-card">
					<div class="rbp-metric-icon">
						<i class="dashicons dashicons-email-alt"></i>
					</div>
					<div class="rbp-metric-content">
						<div class="rbp-metric-number">1,247</div>
						<div class="rbp-metric-label">Total Emails Sent</div>
						<div class="rbp-metric-trend positive">+15% this month</div>
					</div>
				</div>

				<div class="rbp-metric-card">
					<div class="rbp-metric-icon">
						<i class="dashicons dashicons-yes-alt"></i>
					</div>
					<div class="rbp-metric-content">
						<div class="rbp-metric-number">89%</div>
						<div class="rbp-metric-label">Success Rate</div>
						<div class="rbp-metric-trend positive">+3% this week</div>
					</div>
				</div>

				<div class="rbp-metric-card">
					<div class="rbp-metric-icon">
						<i class="dashicons dashicons-star-filled"></i>
					</div>
					<div class="rbp-metric-content">
						<div class="rbp-metric-number">342</div>
						<div class="rbp-metric-label">Reviews Generated</div>
						<div class="rbp-metric-trend positive">+22% this week</div>
					</div>
				</div>

				<div class="rbp-metric-card">
					<div class="rbp-metric-icon">
						<i class="dashicons dashicons-warning"></i>
					</div>
					<div class="rbp-metric-content">
						<div class="rbp-metric-number">23</div>
						<div class="rbp-metric-label">Failed Attempts</div>
						<div class="rbp-metric-trend neutral">-8% this week</div>
					</div>
				</div>

				<div class="rbp-metric-card">
					<div class="rbp-metric-icon">
						<i class="dashicons dashicons-performance"></i>
					</div>
					<div class="rbp-metric-content">
						<div class="rbp-metric-number">4.7</div>
						<div class="rbp-metric-label">Avg Response Time</div>
						<div class="rbp-metric-trend positive">+0.2 this month</div>
					</div>
				</div>
			</div>

			<!-- Advanced Filters -->
			<div class="rbp-card rbp-filter-card">
				<div class="rbp-card-header">
					<h3 class="rbp-card-title">
						<i class="dashicons dashicons-filter"></i>
						<?php esc_html_e('Advanced Filters', 'reviewboost-pro'); ?>
					</h3>
				</div>
				<form class="rbp-filter-form" method="get">
					<input type="hidden" name="page" value="reviewboost-pro">
					<div class="rbp-filter-grid">
						<div class="rbp-filter-group">
							<label for="from_date"><?php esc_html_e('From Date', 'reviewboost-pro'); ?></label>
							<input type="date" id="from_date" name="from_date" class="rbp-input">
						</div>
						<div class="rbp-filter-group">
							<label for="to_date"><?php esc_html_e('To Date', 'reviewboost-pro'); ?></label>
							<input type="date" id="to_date" name="to_date" class="rbp-input">
						</div>
						<div class="rbp-filter-group">
							<label for="status"><?php esc_html_e('Status', 'reviewboost-pro'); ?></label>
							<select id="status" name="status" class="rbp-select">
								<option value=""><?php esc_html_e('All Statuses', 'reviewboost-pro'); ?></option>
								<option value="sent"><?php esc_html_e('Sent', 'reviewboost-pro'); ?></option>
								<option value="failed"><?php esc_html_e('Failed', 'reviewboost-pro'); ?></option>
								<option value="pending"><?php esc_html_e('Pending', 'reviewboost-pro'); ?></option>
								<option value="completed"><?php esc_html_e('Completed', 'reviewboost-pro'); ?></option>
							</select>
						</div>
						<div class="rbp-filter-group">
							<label for="channel_filter"><?php esc_html_e('Channel', 'reviewboost-pro'); ?></label>
							<select id="channel_filter" name="channel_filter" class="rbp-select">
								<option value=""><?php esc_html_e('All Channels', 'reviewboost-pro'); ?></option>
								<option value="email"><?php esc_html_e('Email', 'reviewboost-pro'); ?></option>
								<option value="sms"><?php esc_html_e('SMS', 'reviewboost-pro'); ?></option>
								<option value="webhook"><?php esc_html_e('Webhook', 'reviewboost-pro'); ?></option>
							</select>
						</div>
					</div>
					<div class="rbp-filter-actions">
						<button type="submit" class="rbp-btn rbp-btn-primary">
							<i class="dashicons dashicons-search"></i>
							<?php esc_html_e('Apply Filters', 'reviewboost-pro'); ?>
						</button>
						<button type="button" class="rbp-btn rbp-btn-secondary" onclick="window.location.href='?page=reviewboost-pro'">
							<i class="dashicons dashicons-dismiss"></i>
							<?php esc_html_e('Clear', 'reviewboost-pro'); ?>
						</button>
					</div>
				</form>
			</div>

			<!-- Data Table -->
			<div class="rbp-card">
				<div class="rbp-card-header">
					<h3 class="rbp-card-title">
						<i class="dashicons dashicons-list-view"></i>
						<?php esc_html_e('Activity Log', 'reviewboost-pro'); ?>
					</h3>
					<div class="rbp-card-actions">
						<button class="rbp-btn rbp-btn-secondary">
							<i class="dashicons dashicons-download"></i>
							<?php esc_html_e('Export CSV', 'reviewboost-pro'); ?>
						</button>
					</div>
				</div>
				<div class="rbp-table-container">
					<table class="rbp-data-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Date', 'reviewboost-pro'); ?></th>
								<th><?php esc_html_e('Customer', 'reviewboost-pro'); ?></th>
								<th><?php esc_html_e('Order ID', 'reviewboost-pro'); ?></th>
								<th><?php esc_html_e('Status', 'reviewboost-pro'); ?></th>
								<th><?php esc_html_e('Channel', 'reviewboost-pro'); ?></th>
								<th><?php esc_html_e('Actions', 'reviewboost-pro'); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><strong>2024-01-15 10:30</strong></td>
								<td>
									<div class="rbp-customer-info">
										<strong>John Smith</strong><br>
										<small>john@example.com</small>
									</div>
								</td>
								<td><code class="rbp-order-id">#12345</code></td>
								<td><span class="rbp-status-badge rbp-status-success"><i class="dashicons dashicons-yes-alt"></i> Sent</span></td>
								<td><span class="rbp-channel-badge rbp-channel-email"><i class="dashicons dashicons-email-alt"></i> Email</span></td>
								<td>
									<button class="rbp-btn rbp-btn-small rbp-btn-primary" onclick="viewLogDetails(1)">
										<i class="dashicons dashicons-visibility"></i>
									</button>
									<button class="rbp-btn rbp-btn-small rbp-btn-secondary">
										<i class="dashicons dashicons-admin-generic"></i>
									</button>
								</td>
							</tr>
							<tr>
								<td><strong>2024-01-14 15:45</strong></td>
								<td>
									<div class="rbp-customer-info">
										<strong>Sarah Johnson</strong><br>
										<small>sarah@example.com</small>
									</div>
								</td>
								<td><code class="rbp-order-id">#12346</code></td>
								<td><span class="rbp-status-badge rbp-status-pending"><i class="dashicons dashicons-clock"></i> Pending</span></td>
								<td><span class="rbp-channel-badge rbp-channel-sms"><i class="dashicons dashicons-smartphone"></i> SMS</span></td>
								<td>
									<button class="rbp-btn rbp-btn-small rbp-btn-primary" onclick="viewLogDetails(2)">
										<i class="dashicons dashicons-visibility"></i>
									</button>
									<button class="rbp-btn rbp-btn-small rbp-btn-secondary">
										<i class="dashicons dashicons-admin-generic"></i>
									</button>
								</td>
							</tr>
							<tr>
								<td><strong>2024-01-13 09:20</strong></td>
								<td>
									<div class="rbp-customer-info">
										<strong>Mike Davis</strong><br>
										<small>mike@example.com</small>
									</div>
								</td>
								<td><code class="rbp-order-id">#12347</code></td>
								<td><span class="rbp-status-badge rbp-status-failed"><i class="dashicons dashicons-dismiss"></i> Failed</span></td>
								<td><span class="rbp-channel-badge rbp-channel-email"><i class="dashicons dashicons-email-alt"></i> Email</span></td>
								<td>
									<button class="rbp-btn rbp-btn-small rbp-btn-primary" onclick="viewLogDetails(3)">
										<i class="dashicons dashicons-visibility"></i>
									</button>
									<button class="rbp-btn rbp-btn-small rbp-btn-secondary">
										<i class="dashicons dashicons-admin-generic"></i>
									</button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Pagination -->
			<div class="rbp-pagination">
				<div class="rbp-pagination-info">
					<?php esc_html_e('Showing 1-10 of 1,247 entries', 'reviewboost-pro'); ?>
				</div>
				<div class="rbp-pagination-controls">
					<button class="rbp-btn rbp-btn-secondary" disabled>
						<i class="dashicons dashicons-arrow-left-alt2"></i>
						<?php esc_html_e('Previous', 'reviewboost-pro'); ?>
					</button>
					<div class="rbp-page-numbers">
						<button class="rbp-btn rbp-btn-page active">1</button>
						<button class="rbp-btn rbp-btn-page">2</button>
						<button class="rbp-btn rbp-btn-page">3</button>
						<span class="rbp-page-dots">...</span>
						<button class="rbp-btn rbp-btn-page">125</button>
					</div>
					<button class="rbp-btn rbp-btn-secondary">
						<?php esc_html_e('Next', 'reviewboost-pro'); ?>
						<i class="dashicons dashicons-arrow-right-alt2"></i>
					</button>
				</div>
			</div>

			<!-- Quick Insights -->
			<div class="rbp-insights-grid">
				<div class="rbp-card rbp-insight-card">
					<div class="rbp-insight-icon">
						<i class="dashicons dashicons-chart-bar"></i>
					</div>
					<div class="rbp-insight-content">
						<h4><?php esc_html_e('Peak Activity Hour', 'reviewboost-pro'); ?></h4>
						<p><strong>10-11 AM</strong> - Highest email open rates</p>
					</div>
				</div>
				<div class="rbp-card rbp-insight-card">
					<div class="rbp-insight-icon">
						<i class="dashicons dashicons-megaphone"></i>
					</div>
					<div class="rbp-insight-content">
						<h4><?php esc_html_e('Best Performing Channel', 'reviewboost-pro'); ?></h4>
						<p><strong>Email</strong> - 89% success rate</p>
					</div>
				</div>
				<div class="rbp-card rbp-insight-card">
					<div class="rbp-insight-icon">
						<i class="dashicons dashicons-calendar-alt"></i>
					</div>
					<div class="rbp-insight-content">
						<h4><?php esc_html_e('Most Active Day', 'reviewboost-pro'); ?></h4>
						<p><strong>Tuesday</strong> - 28% of weekly activity</p>
					</div>
				</div>
			</div>
		</div>

		<style>
		/* Vertical Cards Layout */
		.rbp-vertical-cards {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 20px;
			margin-bottom: 30px;
		}

		.rbp-metric-card {
			background: white;
			border-radius: 12px;
			padding: 24px;
			box-shadow: 0 2px 8px rgba(0,0,0,0.1);
			border: 1px solid #e5e7eb;
			transition: all 0.3s ease;
			text-align: center;
		}

		.rbp-metric-card:hover {
			transform: translateY(-2px);
			box-shadow: 0 4px 16px rgba(0,0,0,0.15);
		}

		.rbp-metric-icon {
			width: 48px;
			height: 48px;
			background: linear-gradient(135deg, #7c3aed, #3b82f6);
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			margin: 0 auto 16px;
		}

		.rbp-metric-icon i {
			color: white;
			font-size: 20px;
		}

		.rbp-metric-number {
			font-size: 32px;
			font-weight: 700;
			color: #1f2937;
			margin-bottom: 8px;
		}

		.rbp-metric-label {
			font-size: 14px;
			color: #6b7280;
			font-weight: 500;
			margin-bottom: 8px;
		}

		.rbp-metric-trend {
			font-size: 12px;
			font-weight: 600;
			padding: 4px 8px;
			border-radius: 12px;
			display: inline-block;
		}

		.rbp-metric-trend.positive {
			background: #dcfce7;
			color: #16a34a;
		}

		.rbp-metric-trend.neutral {
			background: #fef3c7;
			color: #d97706;
		}

		/* Responsive Design */
		@media (max-width: 768px) {
			.rbp-vertical-cards {
				grid-template-columns: repeat(2, 1fr);
				gap: 15px;
			}
			
			.rbp-metric-card {
				padding: 16px;
			}
			
			.rbp-metric-number {
				font-size: 24px;
			}
		}

		@media (max-width: 480px) {
			.rbp-vertical-cards {
				grid-template-columns: 1fr;
			}
		}

		/* Additional ReviewBoost Pro Dashboard Styling */
		.rbp-order-id {
			background: #f3f4f6;
			padding: 4px 8px;
			border-radius: 4px;
			font-family: monospace;
			font-weight: bold;
			color: #374151;
		}

		.rbp-channel-badge {
			padding: 4px 8px;
			border-radius: 12px;
			font-size: 12px;
			font-weight: 600;
			display: inline-flex;
			align-items: center;
			gap: 4px;
		}

		.rbp-channel-email {
			background: #dbeafe;
			color: #2563eb;
		}

		.rbp-channel-sms {
			background: #dcfce7;
			color: #16a34a;
		}

		.rbp-status-success {
			background: #dcfce7;
			color: #16a34a;
		}

		.rbp-status-pending {
			background: #fef3c7;
			color: #d97706;
		}

		.rbp-status-failed {
			background: #fee2e2;
			color: #dc2626;
		}

		.rbp-customer-info {
			display: flex;
			flex-direction: column;
		}

		.rbp-customer-info small {
			color: #6b7280;
			font-size: 12px;
		}
		</style>
		<?php
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
		// --- Analytics summary ---
		$stats = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) as total, ".
			"SUM(status='sent') as sent, ".
			"SUM(status='failed') as failed, ".
			"SUM(method='email') as email, ".
			"SUM(method='whatsapp') as whatsapp, ".
			"SUM(method='sms') as sms, ".
			"SUM(method='coupon') as coupon ".
			"FROM $table $where", ...$params ), ARRAY_A );
		// --- End analytics ---
		// Export to CSV (existing code)
		if ( isset($_POST['export_csv']) && check_ajax_referer('rbp_export_logs') ) {
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
		wp_send_json_success([
			'logs'=>$logs,
			'total'=>$total,
			'page'=>$page,
			'per_page'=>$per_page,
			'stats'=>$stats,
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
		if ( isset($_GET['page']) && (
			strpos($_GET['page'], 'reviewboost-pro') !== false ||
			strpos($_GET['page'], 'rbp-') !== false
		) ) {
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
		<div class="wrap rbp-admin-page">
			<div class="rbp-card">
				<div class="rbp-card-header">
					<h1 class="rbp-card-title">
						<span class="dashicons dashicons-star-filled" style="margin-right:8px;"></span>
						<?php esc_html_e('Welcome to ReviewBoost Pro!','reviewboost-pro'); ?>
					</h1>
					<p class="rbp-card-subtitle">
						<?php esc_html_e('Thank you for choosing ReviewBoost Pro! Here\'s how to get started and make the most of your review automation:', 'reviewboost-pro'); ?>
					</p>
				</div>

				<div class="rbp-getting-started-steps">
					<ol>
						<li><strong><?php esc_html_e('Connect WhatsApp & SMS (Pro):','reviewboost-pro'); ?></strong> <?php esc_html_e('Enter your Twilio credentials in the Pro settings to enable WhatsApp and SMS reminders.', 'reviewboost-pro'); ?></li>
						<li><strong><?php esc_html_e('Customize Reminder Templates:','reviewboost-pro'); ?></strong> <?php esc_html_e('Use the Advanced Template Builder to personalize your email, WhatsApp, and SMS messages.', 'reviewboost-pro'); ?></li>
						<li><strong><?php esc_html_e('Enable Multi-step Automations:','reviewboost-pro'); ?></strong> <?php esc_html_e('Set up multi-step reminders for maximum engagement.', 'reviewboost-pro'); ?></li>
						<li><strong><?php esc_html_e('Track Results:','reviewboost-pro'); ?></strong> <?php esc_html_e('View all reminder logs and analytics in the Pro Dashboard.', 'reviewboost-pro'); ?></li>
						<li><strong><?php esc_html_e('Need Help?','reviewboost-pro'); ?></strong> <a href="mailto:support@yourdomain.com" class="rbp-link"><?php esc_html_e('Contact Support','reviewboost-pro'); ?></a> | <a href="https://your-docs-url.com" target="_blank" class="rbp-link"><?php esc_html_e('Read Docs','reviewboost-pro'); ?></a></li>
					</ol>
				</div>

				<div class="rbp-getting-started-actions">
					<a href="https://your-upgrade-page.com" class="rbp-btn rbp-btn-primary rbp-btn-hero"><?php esc_html_e('Upgrade to Pro','reviewboost-pro'); ?></a>
				</div>
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
	 * Add Upgrade to Pro submenu page
	 */
	public function add_upgrade_to_pro_page() {
		add_submenu_page(
			'reviewboost-pro',
			__('Upgrade to Pro','reviewboost-pro'),
			__('Upgrade to Pro','reviewboost-pro'),
			'manage_options',
			'rbp-upgrade-to-pro',
			[ $this, 'render_upgrade_to_pro_page' ]
		);
	}

	/**
	 * Render the Upgrade to Pro page
	 */
	public function render_upgrade_to_pro_page() {
		$upgrade_url = 'https://your-upgrade-page.com'; // TODO: Replace with your real upgrade/pricing page
		$plugin_logo = plugins_url( '../assets/img/reviewboost-logo.png', __FILE__ );
		?>
		<div class="wrap rbp-upgrade-to-pro">
			<div class="rbp-upgrade-container">
				<img src="<?php echo esc_url($plugin_logo); ?>" alt="ReviewBoost Pro" class="rbp-plugin-logo">
				<h1 class="rbp-upgrade-title"><?php esc_html_e('Upgrade to ReviewBoost Pro','reviewboost-pro'); ?></h1>
				<p class="rbp-upgrade-subtitle">
					<?php esc_html_e('Unlock advanced automation, reminders, analytics, and priority support to skyrocket your reviews and customer trust.','reviewboost-pro'); ?>
				</p>
				<div class="rbp-feature-table">
					<table>
						<thead><tr><th><?php esc_html_e('Feature','reviewboost-pro'); ?></th><th><?php esc_html_e('Free','reviewboost-pro'); ?></th><th><?php esc_html_e('Pro','reviewboost-pro'); ?></th></tr></thead>
						<tbody>
							<tr><td>🟢 <?php esc_html_e('WhatsApp Reminders','reviewboost-pro'); ?></td><td><span class="rbp-feature-unavailable">❌</span></td><td><span class="rbp-feature-available">✔️</span></td></tr>
							<tr><td>🟢 <?php esc_html_e('SMS Reminders','reviewboost-pro'); ?></td><td><span class="rbp-feature-unavailable">❌</span></td><td><span class="rbp-feature-available">✔️</span></td></tr>
							<tr><td>📈 <?php esc_html_e('Advanced Logs & Analytics','reviewboost-pro'); ?></td><td><span class="rbp-feature-unavailable">❌</span></td><td><span class="rbp-feature-available">✔️</span></td></tr>
							<tr><td>⚡ <?php esc_html_e('Priority Support','reviewboost-pro'); ?></td><td><span class="rbp-feature-unavailable">❌</span></td><td><span class="rbp-feature-available">✔️</span></td></tr>
							<tr><td>✉️ <?php esc_html_e('Basic Email Reminders','reviewboost-pro'); ?></td><td><span class="rbp-feature-available">✔️</span></td><td><span class="rbp-feature-available">✔️</span></td></tr>
						</tbody>
					</table>
				</div>
				<div class="rbp-trust-badges">
					<span class="rbp-trust-badge success"><?php esc_html_e('30-day Money-Back Guarantee','reviewboost-pro'); ?></span>
					<span class="rbp-trust-badge primary"><?php esc_html_e('Trusted by 1000+ stores','reviewboost-pro'); ?></span>
					<span class="rbp-trust-badge info"><?php esc_html_e('Instant Activation','reviewboost-pro'); ?></span>
				</div>
				<a href="<?php echo esc_url($upgrade_url); ?>" target="_blank" class="rbp-btn rbp-btn-primary">
					<?php esc_html_e('Upgrade Now','reviewboost-pro'); ?>
				</a>
				<div class="rbp-faq">
					<details><summary><?php esc_html_e('Is there a money-back guarantee?','reviewboost-pro'); ?></summary><div class="faq-content"><?php esc_html_e('Yes! You can try Pro risk-free for 30 days. If you’re not happy, get a full refund.','reviewboost-pro'); ?></div></details>
					<details><summary><?php esc_html_e('How fast do I get access after upgrading?','reviewboost-pro'); ?></summary><div class="faq-content"><?php esc_html_e('Instantly! As soon as you upgrade, all Pro features are unlocked.','reviewboost-pro'); ?></div></details>
					<details><summary><?php esc_html_e('Can I get help if I have questions?','reviewboost-pro'); ?></summary><div class="faq-content"><?php esc_html_e('Absolutely! Our support team is here to help you succeed.','reviewboost-pro'); ?></div></details>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Coupon Logs Dashboard
	 */
	public function render_coupon_logs_dashboard() {
		?>
		<div class="wrap rbp-admin-wrap">
			<!-- Premium Coupon Logs Dashboard Header -->
			<div class="rbp-card rbp-dashboard-header">
				<h1 class="rbp-card-title">
					<i class="dashicons dashicons-tickets-alt" style="color: #7c3aed;"></i>
					<?php esc_html_e('ReviewBoost Pro Coupon Logs', 'reviewboost-pro'); ?>
					<span class="rbp-pro-badge">PRO</span>
				</h1>
				<p class="rbp-card-description"><?php esc_html_e('Track and analyze all coupon activities generated by ReviewBoost Pro', 'reviewboost-pro'); ?></p>
			</div>

			<!-- Analytics Overview Cards - Vertical Layout -->
			<div class="rbp-analytics-overview rbp-vertical-cards">
				<div class="rbp-metric-card">
					<div class="rbp-metric-icon">
						<i class="dashicons dashicons-tickets-alt"></i>
					</div>
					<div class="rbp-metric-content">
						<div class="rbp-metric-number" id="total-coupons">-</div>
						<div class="rbp-metric-label"><?php esc_html_e('Total Coupons', 'reviewboost-pro'); ?></div>
						<div class="rbp-metric-trend neutral" id="total-coupons-trend">-</div>
					</div>
				</div>

				<div class="rbp-metric-card">
					<div class="rbp-metric-icon">
						<i class="dashicons dashicons-plus-alt"></i>
					</div>
					<div class="rbp-metric-content">
						<div class="rbp-metric-number" id="generated-coupons">-</div>
						<div class="rbp-metric-label"><?php esc_html_e('Generated', 'reviewboost-pro'); ?></div>
						<div class="rbp-metric-trend positive" id="generated-coupons-trend">-</div>
					</div>
				</div>

				<div class="rbp-metric-card">
					<div class="rbp-metric-icon">
						<i class="dashicons dashicons-email-alt"></i>
					</div>
					<div class="rbp-metric-content">
						<div class="rbp-metric-number" id="sent-coupons">-</div>
						<div class="rbp-metric-label"><?php esc_html_e('Sent', 'reviewboost-pro'); ?></div>
						<div class="rbp-metric-trend positive" id="sent-coupons-trend">-</div>
					</div>
				</div>

				<div class="rbp-metric-card">
					<div class="rbp-metric-icon">
						<i class="dashicons dashicons-yes-alt"></i>
					</div>
					<div class="rbp-metric-content">
						<div class="rbp-metric-number" id="redeemed-coupons">-</div>
						<div class="rbp-metric-label"><?php esc_html_e('Redeemed', 'reviewboost-pro'); ?></div>
						<div class="rbp-metric-trend positive" id="redeemed-coupons-trend">-</div>
					</div>
				</div>

				<div class="rbp-metric-card">
					<div class="rbp-metric-icon">
						<i class="dashicons dashicons-clock"></i>
					</div>
					<div class="rbp-metric-content">
						<div class="rbp-metric-number" id="expired-coupons">-</div>
						<div class="rbp-metric-label"><?php esc_html_e('Expired', 'reviewboost-pro'); ?></div>
						<div class="rbp-metric-trend neutral" id="expired-coupons-trend">-</div>
					</div>
				</div>
			</div>

			<!-- Filter Form -->
			<div class="rbp-card">
				<div class="rbp-card-header">
					<h2 class="rbp-card-title"><?php esc_html_e('Filter Coupon Logs', 'reviewboost-pro'); ?></h2>
				</div>
				<form class="rbp-filter-form" id="coupon-logs-filter">
					<div class="rbp-filter-grid">
						<div class="rbp-filter-group">
							<label for="filter-event"><?php esc_html_e('Event Type', 'reviewboost-pro'); ?></label>
							<select id="filter-event" name="event">
								<option value=""><?php esc_html_e('All Events', 'reviewboost-pro'); ?></option>
								<option value="generated"><?php esc_html_e('Generated', 'reviewboost-pro'); ?></option>
								<option value="sent"><?php esc_html_e('Sent', 'reviewboost-pro'); ?></option>
								<option value="redeemed"><?php esc_html_e('Redeemed', 'reviewboost-pro'); ?></option>
								<option value="expired"><?php esc_html_e('Expired', 'reviewboost-pro'); ?></option>
							</select>
						</div>
						<div class="rbp-filter-group">
							<label for="filter-customer"><?php esc_html_e('Customer ID', 'reviewboost-pro'); ?></label>
							<input type="number" id="filter-customer" name="customer_id" placeholder="<?php esc_attr_e('Enter customer ID', 'reviewboost-pro'); ?>">
						</div>
						<div class="rbp-filter-group">
							<label for="filter-coupon-code"><?php esc_html_e('Coupon Code', 'reviewboost-pro'); ?></label>
							<input type="text" id="filter-coupon-code" name="coupon_code" placeholder="<?php esc_attr_e('Enter coupon code', 'reviewboost-pro'); ?>">
						</div>
						<div class="rbp-filter-group">
							<label for="filter-date-from"><?php esc_html_e('Date From', 'reviewboost-pro'); ?></label>
							<input type="date" id="filter-date-from" name="date_from">
						</div>
						<div class="rbp-filter-group">
							<label for="filter-date-to"><?php esc_html_e('Date To', 'reviewboost-pro'); ?></label>
							<input type="date" id="filter-date-to" name="date_to">
						</div>
					</div>
					<div class="rbp-filter-actions">
						<button type="submit" class="rbp-btn rbp-btn-primary">
							<i class="dashicons dashicons-search"></i>
							<?php esc_html_e('Apply Filters', 'reviewboost-pro'); ?>
						</button>
						<button type="button" class="rbp-btn rbp-btn-secondary" onclick="window.location.href='?page=rbp-coupon-logs'">
							<i class="dashicons dashicons-dismiss"></i>
							<?php esc_html_e('Clear Filters', 'reviewboost-pro'); ?>
						</button>
						<button type="button" class="rbp-btn rbp-btn-secondary" id="export-coupon-logs">
							<i class="dashicons dashicons-download"></i>
							<?php esc_html_e('Export CSV', 'reviewboost-pro'); ?>
						</button>
					</div>
				</form>
			</div>

			<!-- Coupon Logs Data Table -->
			<div class="rbp-card">
				<div class="rbp-card-header">
					<h2 class="rbp-card-title"><?php esc_html_e('Coupon Activity Logs', 'reviewboost-pro'); ?></h2>
				</div>
				<div class="rbp-data-table-container">
					<table class="rbp-data-table" id="coupon-logs-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Date', 'reviewboost-pro'); ?></th>
								<th><?php esc_html_e('Event', 'reviewboost-pro'); ?></th>
								<th><?php esc_html_e('Coupon Code', 'reviewboost-pro'); ?></th>
								<th><?php esc_html_e('Customer', 'reviewboost-pro'); ?></th>
								<th><?php esc_html_e('Amount', 'reviewboost-pro'); ?></th>
								<th><?php esc_html_e('Status', 'reviewboost-pro'); ?></th>
								<th><?php esc_html_e('Actions', 'reviewboost-pro'); ?></th>
							</tr>
						</thead>
						<tbody id="coupon-logs-tbody">
							<tr>
								<td colspan="7" class="rbp-loading">
									<i class="dashicons dashicons-update-alt"></i>
									<?php esc_html_e('Loading coupon logs...', 'reviewboost-pro'); ?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="rbp-pagination" id="coupon-logs-pagination">
					<!-- Pagination will be inserted here via JavaScript -->
				</div>
			</div>

			<!-- Upgrade Banner for Enhanced Features -->
			<div class="rbp-upgrade-container">
				<div class="rbp-upgrade-banner">
					<div class="rbp-upgrade-content">
						<h3><?php esc_html_e('🚀 Unlock Advanced Coupon Analytics', 'reviewboost-pro'); ?></h3>
						<p><?php esc_html_e('Get detailed coupon performance metrics, conversion tracking, and advanced filtering with ReviewBoost Pro Premium.', 'reviewboost-pro'); ?></p>
					</div>
					<a href="<?php echo esc_url(admin_url('admin.php?page=reviewboost-pro-pricing')); ?>" target="_blank" class="rbp-btn rbp-btn-primary">
						<?php esc_html_e('Upgrade Now', 'reviewboost-pro'); ?>
					</a>
				</div>
			</div>
		</div>

		<!-- Coupon Log Detail Modal -->
		<div id="coupon-log-modal" class="rbp-modal" style="display: none;">
			<div class="rbp-modal-content">
				<div class="rbp-modal-header">
					<h2><?php esc_html_e('Coupon Log Details', 'reviewboost-pro'); ?></h2>
					<button class="rbp-modal-close">&times;</button>
				</div>
				<div class="rbp-modal-body" id="coupon-log-details">
					<!-- Details will be loaded here -->
				</div>
			</div>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Initialize coupon logs dashboard
			loadCouponLogs();
			loadCouponStats();

			// Filter form submission
			$('#coupon-logs-filter').on('submit', function(e) {
				e.preventDefault();
				loadCouponLogs();
			});

			// Export functionality
			$('#export-coupon-logs').on('click', function() {
				exportCouponLogs();
			});

			// Modal functionality
			$(document).on('click', '.view-coupon-log', function() {
				var logId = $(this).data('log-id');
				showCouponLogDetails(logId);
			});

			$('.rbp-modal-close, .rbp-modal').on('click', function(e) {
				if (e.target === this) {
					$('#coupon-log-modal').hide();
				}
			});

			function loadCouponLogs(page = 1) {
				var formData = $('#coupon-logs-filter').serialize();
				formData += '&action=rbp_fetch_coupon_logs&page=' + page + '&nonce=' + '<?php echo wp_create_nonce('rbp_coupon_logs_dashboard'); ?>';

				$.post(ajaxurl, formData, function(response) {
					if (response.success) {
						updateCouponLogsTable(response.data);
					} else {
						$('#coupon-logs-tbody').html('<tr><td colspan="7" class="rbp-error">' + (response.data.message || '<?php esc_html_e('Error loading coupon logs', 'reviewboost-pro'); ?>') + '</td></tr>');
					}
				});
			}

			function loadCouponStats() {
				$.post(ajaxurl, {
					action: 'rbp_fetch_coupon_stats',
					nonce: '<?php echo wp_create_nonce('rbp_coupon_stats'); ?>'
				}, function(response) {
					if (response.success) {
						$('#total-coupons').text(response.data.total || 0);
						$('#generated-coupons').text(response.data.generated || 0);
						$('#sent-coupons').text(response.data.sent || 0);
						$('#redeemed-coupons').text(response.data.redeemed || 0);
						$('#expired-coupons').text(response.data.expired || 0);
					}
				});
			}

			function updateCouponLogsTable(data) {
				var tbody = $('#coupon-logs-tbody');
				tbody.empty();

				if (data.logs && data.logs.length > 0) {
					$.each(data.logs, function(index, log) {
						var row = '<tr>' +
							'<td>' + log.date + '</td>' +
							'<td><span class="rbp-status rbp-status-' + log.event + '">' + log.event + '</span></td>' +
							'<td><code>' + (log.coupon_code || '-') + '</code></td>' +
							'<td>' + (log.customer_name || 'Guest') + '</td>' +
							'<td>' + (log.amount || '-') + '</td>' +
							'<td><span class="rbp-status rbp-status-' + log.status + '">' + log.status + '</span></td>' +
							'<td><button class="rbp-btn rbp-btn-small view-coupon-log" data-log-id="' + log.id + '"><?php esc_html_e('View', 'reviewboost-pro'); ?></button></td>' +
							'</tr>';
						tbody.append(row);
					});
				} else {
					tbody.html('<tr><td colspan="7" class="rbp-no-data"><?php esc_html_e('No coupon logs found', 'reviewboost-pro'); ?></td></tr>');
				}

				// Update pagination
				updatePagination(data);
			}

			function updatePagination(data) {
				// Pagination implementation would go here
				// For now, just show basic info
				$('#coupon-logs-pagination').html('<p><?php esc_html_e('Showing', 'reviewboost-pro'); ?> ' + (data.logs ? data.logs.length : 0) + ' <?php esc_html_e('results', 'reviewboost-pro'); ?></p>');
			}

			function exportCouponLogs() {
				var formData = $('#coupon-logs-filter').serialize();
				formData += '&action=rbp_export_coupon_logs&nonce=' + '<?php echo wp_create_nonce('rbp_export_coupon_logs'); ?>';
				
				// Create a temporary form to trigger download
				var form = $('<form>', {
					method: 'POST',
					action: ajaxurl
				});
				
				$.each(formData.split('&'), function(index, field) {
					var parts = field.split('=');
					form.append($('<input>', {
						type: 'hidden',
						name: decodeURIComponent(parts[0]),
						value: decodeURIComponent(parts[1] || '')
					}));
				});
				
				$('body').append(form);
				form.submit();
				form.remove();
			}

			function showCouponLogDetails(logId) {
				$.post(ajaxurl, {
					action: 'rbp_get_coupon_log_details',
					log_id: logId,
					nonce: '<?php echo wp_create_nonce('rbp_coupon_log_details'); ?>'
				}, function(response) {
					if (response.success) {
						$('#coupon-log-details').html(response.data.html);
						$('#coupon-log-modal').show();
					}
				});
			}
		});
		</script>
		<?php
	}
}

// Initialize admin
add_action( 'plugins_loaded', function() {
	if ( is_admin() ) {
		$GLOBALS['rbp_admin'] = new RBP_Admin();
	}
} );
