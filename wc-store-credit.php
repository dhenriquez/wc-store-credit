<?php
/**
 * Plugin Name: WooCommerce Store Credit
 * Plugin URI: https://dhenriquez.cl
 * Description: Sistema de Store Credit con mecánica de cashback para WooCommerce.
 * Version: 1.0.0
 * Author: Daniel Henriquez
 * Author URI: https://dhenriquez.cl
 * Text Domain: wc-store-credit
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package WcStoreCredit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'WC_STORE_CREDIT_VERSION', '1.0.0' );
define( 'WC_STORE_CREDIT_FILE', __FILE__ );
define( 'WC_STORE_CREDIT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_STORE_CREDIT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if WooCommerce is active
 */
function wc_store_credit_check_dependencies() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="error"><p><strong>WooCommerce Store Credit</strong> requiere que WooCommerce esté instalado y activo.</p></div>';
		} );
		return false;
	}
	return true;
}

add_action( 'plugins_loaded', function() {
	if ( wc_store_credit_check_dependencies() ) {
		require_once WC_STORE_CREDIT_PATH . 'includes/class-wc-store-credit-settings.php';
		require_once WC_STORE_CREDIT_PATH . 'includes/class-wc-store-credit-ledger.php';
		require_once WC_STORE_CREDIT_PATH . 'includes/class-wc-store-credit-earning.php';
		require_once WC_STORE_CREDIT_PATH . 'includes/class-wc-store-credit-checkout.php';
		require_once WC_STORE_CREDIT_PATH . 'includes/class-wc-store-credit-refunds.php';
		require_once WC_STORE_CREDIT_PATH . 'includes/class-wc-store-credit-emails.php';
		require_once WC_STORE_CREDIT_PATH . 'includes/class-wc-store-credit-display.php';
		require_once WC_STORE_CREDIT_PATH . 'includes/class-wc-store-credit-admin-profile.php';
		require_once WC_STORE_CREDIT_PATH . 'includes/class-wc-store-credit-admin-list.php';
		require_once WC_STORE_CREDIT_PATH . 'includes/class-wc-store-credit-my-account.php';
	}
} );



/**
 * Activation Hook: Create custom table for ledger
 */
function wc_store_credit_activate() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'store_credit_transactions';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		user_id bigint(20) NOT NULL,
		amount decimal(10,2) NOT NULL,
		type varchar(20) NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'available',
		comment text,
		order_id bigint(20) DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY status (status)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
register_activation_hook( __FILE__, 'wc_store_credit_activate' );
