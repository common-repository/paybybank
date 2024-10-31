<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Dc_Paybybank
 * @subpackage Dc_Paybybank/includes
 * @author     Digital Challenge <info@dicha.gr>
 */
class Dc_Paybybank {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Dc_Paybybank_Loader $loader Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $plugin_name The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $version The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		$this->version     = defined( 'DC_PAYBYBANK_VERSION' ) ? DC_PAYBYBANK_VERSION : '1.0.0';
		$this->plugin_name = 'paybybank';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_payment_gateway_hooks();
		$this->define_payment_block_gateway();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Dc_Paybybank_Loader. Orchestrates the hooks of the plugin.
	 * - Dc_Paybybank_i18n. Defines internationalization functionality.
	 * - Dc_Paybybank_Admin. Defines all hooks for the admin area.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dc-paybybank-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dc-paybybank-i18n.php';

		/**
		 * The class responsible for payment gateway.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dc-paybybank-gateway.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-dc-paybybank-admin.php';

		$this->loader = new Dc_Paybybank_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Dc_Paybybank_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Dc_Paybybank_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Dc_Paybybank_Admin( $this->get_plugin_name(), $this->get_version() );

		// PAYMENT GATEWAY SETUP
		$this->loader->add_filter( 'woocommerce_payment_gateways', $plugin_admin, 'add_dc_paybybank_gateway_class' );
		$this->loader->add_filter( 'woocommerce_gateway_method_description', $plugin_admin, 'filter_woocommerce_settings_api_form_fields_id', 10, 2 );
		$this->loader->add_action( 'init', $plugin_admin, 'maybe_update_pbb_database', 20 );

		// API REQUESTS
		$this->loader->add_action( 'rest_api_init', $plugin_admin, 'api_register_paybybank_payment_url' );
		$this->loader->add_action( 'init', $plugin_admin, 'dc_schedule_mass_get_status_request', 99 );
		$this->loader->add_action( 'paybybank_mass_get_status', $plugin_admin, 'mass_get_status_for_pending_payments' );
		$this->loader->add_action( 'paybybank_update_eshop_orders', $plugin_admin, 'pbb_update_eshop_orders_status_async' );

		// WOOCOMMERCE CUSTOMIZATIONS
		$this->loader->add_action( 'woocommerce_cart_calculate_fees', $plugin_admin, 'dc_paybybank_add_checkout_fee_for_gateway' );
		$this->loader->add_filter( 'woocommerce_thankyou_dc-paybybank', $plugin_admin, 'dc_paybybank_add_thankyou_message', 10, 3 );
		$this->loader->add_filter( 'woocommerce_email_before_order_table', $plugin_admin, 'dc_paybybank_add_email_instructions', 10, 4 );
		$this->loader->add_action( 'woocommerce_admin_order_data_after_billing_address', $plugin_admin, 'dc_paybybank_checkout_field_display_admin_order_meta' );
		$this->loader->add_action( 'wp_ajax_pbb_request_status_update', $plugin_admin, 'pbb_request_status_update' );
		$this->loader->add_filter( 'woocommerce_order_get_payment_method_title', $plugin_admin, 'dc_add_payment_status_to_pbb_payment_title', 99, 2 );

		// CUSTOM ORDER STATUS
		$this->loader->add_action( 'init', $plugin_admin, 'dc_paybybank_register_paybybank_paid_order_status' );
		$this->loader->add_filter( 'wc_order_statuses', $plugin_admin, 'add_paybybank_paid_to_order_statuses', 10, 3 );
		$this->loader->add_filter( 'woocommerce_order_is_paid_statuses', $plugin_admin, 'mark_paybybank_paid_as_paid_status' );
		$this->loader->add_action( 'woocommerce_order_status_paybybank-paid', $plugin_admin, 'dc_paybybank_email_order_status_paybybank_paid', 20, 2 );
		$this->loader->add_action( 'woocommerce_order_status_paybybank-paid_to_processing', $plugin_admin, 'send_processing_email_when_changing_from_custom_status', 20, 2 );

		// MISC
		$this->loader->add_filter( 'plugin_action_links', $plugin_admin, 'dc_paybybank_plugins_list_action_links',10,2 );
		$this->loader->add_action( 'before_woocommerce_init', $plugin_admin, 'declare_compatibility_with_wc_features' );

		// ENQUEUES
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
	}


	/**
	 * Load payment gateway.
	 *
	 * @since    2.0.0
	 */
	private function define_payment_gateway_hooks() {
		add_action( 'plugins_loaded', 'init_dc_paybybank_gateway_class' );
	}


	/**
	 * Load payment gateway for the new Block Checkout mode.
	 *
	 * @return void
	 * @since    2.1.0
	 */
	private function define_payment_block_gateway() {

		$this->loader->add_action( 'woocommerce_blocks_loaded', $this, 'register_paybybank_block_payment_method' );
	}


	/**
	 * Loads gateway's class, assets and hooks for the new Block Checkout mode.
	 *
	 * @return void
	 * @since    2.1.0
	 */
	public function register_paybybank_block_payment_method() {

		// Check if the required class exists
		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) return;

		// Include the PBB Block Checkout class
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dc-paybybank-gateway-block.php';

		add_action( 'woocommerce_blocks_payment_method_type_registration',
			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new WC_Gateway_DC_PayByBank_Block );
			}
		);

		// Register a callback for gateway's custom hooks
		if ( function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			woocommerce_store_api_register_update_callback(
				[
					'namespace' => 'paybybank-method-selected-change',
					'callback'  => [ $this, 'pbb_update_cart_fees' ],
				]
			);
		}
	}


	/**
	 * Update selected payment method and re-calculate totals.
	 *
	 * @param array $data Hook data from the JS file.
	 *
	 * @return void
	 * @since    2.1.0
	 */
	function pbb_update_cart_fees( $data ) {

		if ( isset( $data['payment_method'] ) ) {
			WC()->session->set( 'chosen_payment_method', $data['payment_method'] );
		}

		WC()->cart->calculate_totals();
	}


	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}


	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @return    string    The name of the plugin.
	 * @since     1.0.0
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}


	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @return    Dc_Paybybank_Loader    Orchestrates the hooks of the plugin.
	 * @since     1.0.0
	 */
	public function get_loader() {
		return $this->loader;
	}


	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return    string    The version number of the plugin.
	 * @since     1.0.0
	 */
	public function get_version() {
		return $this->version;
	}
}
