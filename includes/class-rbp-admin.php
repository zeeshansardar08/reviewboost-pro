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
	}

	public function add_settings_page() {
		add_submenu_page(
			'woocommerce',
			__( 'ReviewBoost', 'reviewboost-pro' ),
			__( 'ReviewBoost', 'reviewboost-pro' ),
			'manage_woocommerce',
			'reviewboost',
			[ $this, 'render_settings_page' ]
		);
	}

	public function add_logs_dashboard_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'ReviewBoost Logs', 'reviewboost-pro' ),
			__( 'ReviewBoost Logs', 'reviewboost-pro' ),
			'manage_woocommerce',
			'rbp-logs',
			[ $this, 'render_logs_dashboard' ]
		);
	}

	public function register_settings() {
		register_setting( 'rbp_settings', 'rbp_reminder_subject', [ 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'rbp_settings', 'rbp_reminder_body', [ 'sanitize_callback' => 'wp_kses_post' ] );
		register_setting( 'rbp_settings', 'rbp_reminder_delay_days', [ 'sanitize_callback' => 'absint' ] );
		register_setting( 'rbp_settings', 'rbp_enabled', [ 'sanitize_callback' => 'absint' ] );
	}

	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ReviewBoost Settings', 'reviewboost-pro' ); ?></h1>
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
									<strong>[order_id]</strong> – <?php esc_html_e('Order ID', 'reviewboost-pro'); ?>
								</p>
								<p>
									<em><?php esc_html_e('Example usage:', 'reviewboost-pro'); ?></em><br>
									<code>Hi [customer_name],<br>Thank you for your purchase! Please leave a review for your order #[order_id].</code>
								</p>
							</div>
						</td>
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

	public function render_logs_dashboard() {
		?>
		<div class="wrap rbp-admin-wrap">
			<div class="rbp-card rbp-dashboard-header">
				<h1 class="rbp-card-title">
					<i class="dashicons dashicons-chart-line" style="color: #7c3aed;"></i>
					<?php esc_html_e('ReviewBoost Logs Dashboard', 'reviewboost-pro'); ?>
				</h1>
				<p class="rbp-card-description"><?php esc_html_e('Analytics and monitoring for all ReviewBoost reminder activities', 'reviewboost-pro'); ?></p>
			</div>
			<!-- Analytics Overview - Only for email reminders -->
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
			</div>
			<!-- Filters (only for email reminders) -->
			<div class="rbp-card rbp-filter-card">
				<div class="rbp-card-header">
					<h3 class="rbp-card-title">
						<i class="dashicons dashicons-filter"></i>
						<?php esc_html_e('Filters', 'reviewboost-pro'); ?>
					</h3>
				</div>
				<form class="rbp-filter-form" method="get">
					<input type="hidden" name="page" value="reviewboost">
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
								<option value="email"><?php esc_html_e('Email', 'reviewboost-pro'); ?></option>
							</select>
						</div>
					</div>
					<div class="rbp-filter-actions">
						<button type="submit" class="rbp-btn rbp-btn-primary">
							<i class="dashicons dashicons-search"></i>
							<?php esc_html_e('Apply Filters', 'reviewboost-pro'); ?>
						</button>
						<button type="button" class="rbp-btn rbp-btn-secondary" onclick="window.location.href='?page=reviewboost'">
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
							<!-- Log rows rendered here -->
						</tbody>
					</table>
				</div>
			</div>
		</div>
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
		// --- Analytics summary ---
		$stats = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) as total, ".
			"SUM(status='sent') as sent, ".
			"SUM(status='failed') as failed, ".
			"SUM(method='email') as email ".
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
			fputcsv($out, ['Date','Order ID','Customer ID','Method','Status','Retry','Details']);
			foreach ($logs as $log) {
				fputcsv($out, [ $log['time_sent'], $log['order_id'], $log['customer_id'], $log['method'], $log['status'], $log['retry_count'], $log['details'] ]);
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
						<?php esc_html_e('Welcome to ReviewBoost!','reviewboost-pro'); ?>
					</h1>
					<p class="rbp-card-subtitle">
						<?php esc_html_e('Thank you for choosing ReviewBoost! Here\'s how to get started and make the most of your review automation:', 'reviewboost-pro'); ?>
					</p>
				</div>

				<div class="rbp-getting-started-steps">
					<ol>
						<li><strong><?php esc_html_e('Customize Reminder Templates:','reviewboost-pro'); ?></strong> <?php esc_html_e('Use the Advanced Template Builder to personalize your email messages.', 'reviewboost-pro'); ?></li>
						<li><strong><?php esc_html_e('Track Results:','reviewboost-pro'); ?></strong> <?php esc_html_e('View all reminder logs and analytics in the ReviewBoost Dashboard.', 'reviewboost-pro'); ?></li>
						<li><strong><?php esc_html_e('Need Help?','reviewboost-pro'); ?></strong> <a href="mailto:support@yourdomain.com" class="rbp-link"><?php esc_html_e('Contact Support','reviewboost-pro'); ?></a> | <a href="https://your-docs-url.com" target="_blank" class="rbp-link"><?php esc_html_e('Read Docs','reviewboost-pro'); ?></a></li>
					</ol>
				</div>

				<div class="rbp-getting-started-actions">
					<a href="<?php echo esc_url(admin_url('admin.php?page=reviewboost')); ?>" class="rbp-btn rbp-btn-primary"><?php esc_html_e('Get Started','reviewboost-pro'); ?></a>
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
}

// Initialize admin
add_action( 'plugins_loaded', function() {
	if ( is_admin() ) {
		$GLOBALS['rbp_admin'] = new RBP_Admin();
	}
} );
