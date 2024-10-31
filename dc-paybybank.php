<?php

/**
 *
 * @wordpress-plugin
 * Plugin Name:       PayByBank Integration for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/paybybank/
 * Description:       Add PayByBank as a payment gateway for WooCommerce. Create automatically payment RF codes and get notified instantly about successful payments from customers.
 * Version:           2.1.1
 * Requires at least: 5.6
 * Tested up to:      6.5.3
 * Requires PHP:      7.2
 * WC requires at least: 5.6.0
 * WC tested up to:   8.9
 * Author:            PayByBank
 * Author URI:        https://www.paybybank.eu/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       paybybank
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Runs only if WooCommerce is active.
 *
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	/**
	 * Currently plugin version.
	 */
	define( 'DC_PAYBYBANK_VERSION', '2.1.1' );
	define( 'DC_PAYBYBANK_SLUG', 'paybybank' );
	define( 'DC_PAYBYBANK_BASE_TEST_PATH', 'https://testapi.e-paylink.com/gateway/rest/api/v1' );
	define( 'DC_PAYBYBANK_BASE_PATH', 'https://www.wu-online.gr/gateway/rest/api/v1' );
	define( 'DC_PAYBYBANK_SUBMIT_ORDER_PATH', '/order/merchant/' );

	/**
	 * The code that runs during plugin activation.
	 * This action is documented in includes/class-dc-paybybank-activator.php
	 */
	function activate_dc_paybybank() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-dc-paybybank-activator.php';
		Dc_Paybybank_Activator::activate();
	}

	/**
	 * The code that runs during plugin deactivation.
	 * This action is documented in includes/class-dc-paybybank-deactivator.php
	 */
	function deactivate_dc_paybybank() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-dc-paybybank-deactivator.php';
		Dc_Paybybank_Deactivator::deactivate();
	}

	register_activation_hook( __FILE__, 'activate_dc_paybybank' );
	register_deactivation_hook( __FILE__, 'deactivate_dc_paybybank' );

	/**
	 * The core plugin class that is used to define internationalization,
	 * admin-specific hooks, and public-facing site hooks.
	 */
	require plugin_dir_path( __FILE__ ) . 'includes/class-dc-paybybank.php';

	/**
	 * Begins execution of the plugin.
	 *
	 * Since everything within the plugin is registered via hooks,
	 * then kicking off the plugin from this point in the file does
	 * not affect the page life cycle.
	 *
	 * @since    1.0.0
	 */
	function run_dc_paybybank() {

		$plugin = new Dc_Paybybank();
		$plugin->run();
	}

	// load after WooCommerce
	add_action( 'woocommerce_loaded', 'run_dc_paybybank' );
}