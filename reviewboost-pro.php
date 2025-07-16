<?php
/**
 * Plugin Name: ReviewBoost Pro
 * Plugin URI: https://reviewboostpro.com/
 * Description: Boost your WooCommerce reviews by sending automated reminders via Email, WhatsApp, and SMS after order completion. Modular, secure, and fully translatable.
 * Version: 1.0.0
 * Author: Muhammad Zeeshan Sardar
 * Author URI: https://reviewboostpro.com/
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

// Load Freemius SDK and initialize before anything else
// require_once plugin_dir_path(__FILE__) . 'includes/class-rbp-freemius.php';

if ( ! function_exists( 'rbp_fs' ) ) {
    // Create a helper function for easy SDK access.
    function rbp_fs() {
        global $rbp_fs;

        if ( ! isset( $rbp_fs ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/freemius/start.php';
            $rbp_fs = fs_dynamic_init( array(
                'id'                  => '19869',
                'slug'                => 'review-booster-pro',
                'type'                => 'plugin',
                'public_key'          => 'pk_8e10271b6efd64ed441eb0063aad2',
                'is_premium'          => false,
                'has_addons'          => false,
                'has_paid_plans'      => false,
                'menu'                => array(
                    'account'        => false,
                    'support'        => false,
                ),
            ) );
        }

        return $rbp_fs;
    }

    // Init Freemius.
    rbp_fs();
    // Signal that SDK was initiated.
    do_action( 'rbp_fs_loaded' );
}

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants
define( 'RBP_VERSION', '1.0.0' );
define( 'RBP_PLUGIN_FILE', __FILE__ );
define( 'RBP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'RBP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RBP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RBP_TEXT_DOMAIN', 'reviewboost-pro' );

// Load text domain for translations
add_action( 'plugins_loaded', function() {
	load_plugin_textdomain( RBP_TEXT_DOMAIN, false, dirname( RBP_PLUGIN_BASENAME ) . '/languages/' );
} );

// Autoload core classes
require_once RBP_PLUGIN_DIR . 'includes/class-rbp-core.php';
if ( is_admin() ) {
	require_once RBP_PLUGIN_DIR . 'includes/class-rbp-admin.php';
}
require_once RBP_PLUGIN_DIR . 'includes/class-rbp-logger.php';
require_once RBP_PLUGIN_DIR . 'includes/class-rbp-email.php';
require_once RBP_PLUGIN_DIR . 'includes/class-rbp-cron.php';

// Pro features loader (only if Pro add-on detected)
if ( file_exists( RBP_PLUGIN_DIR . 'includes/class-rbp-pro.php' ) ) {
	require_once RBP_PLUGIN_DIR . 'includes/class-rbp-pro.php';
}
