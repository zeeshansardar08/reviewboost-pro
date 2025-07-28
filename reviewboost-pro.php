<?php
/**
 * Plugin Name: ReviewBoost Pro
 * Plugin URI: https://zignites.com/plugins/reviewboost-pro
 * Description: Boost your WooCommerce reviews by sending automated reminders via Email after order completion. Modular, secure, and fully translatable.
 * Version: 1.0.0
 * Author: Muhammad Zeeshan Sardar
 * Author URI: https://zignites.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: reviewboost-pro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.9
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) exit;

// Load core plugin files (only free features)
require_once plugin_dir_path(__FILE__) . 'includes/class-rbp-core.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-rbp-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-rbp-cron.php';

// Initialize the plugin
add_action( 'plugins_loaded', 'rbp_init_plugin' );
function rbp_init_plugin() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'ReviewBoost Pro requires WooCommerce to be installed and active.', 'reviewboost-pro' ) . '</p></div>';
        });
        return;
    }
    // Initialize core plugin functionality
    if ( class_exists( 'RBP_Core' ) ) {
        RBP_Core::instance();
    }
}
