<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Dc_Paybybank
 * @subpackage Dc_Paybybank/admin
 * @author     Digital Challenge <info@dicha.gr>
 */
class Dc_Paybybank_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Payment Gateway settings.
	 *
	 * @since    2.1.0
	 * @access   private
	 */
	private $api_key;
	private $mode;
	private $instructions;
	private $email_instructions;
	private $order_status;
	private $order_status_success;
	private $extra_fee;
	private $paid_email;
	private $logger;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		// load gateway settings
		$gateway_settings           = get_option( 'woocommerce_dc-paybybank_settings', [] );
		$this->api_key              = isset( $gateway_settings['api_key'] ) ? $gateway_settings['api_key'] : '';
		$this->mode                 = isset( $gateway_settings['mode'] ) ? $gateway_settings['mode'] : '';
		$this->instructions         = isset( $gateway_settings['instructions'] ) ? $gateway_settings['instructions'] : '';
		$this->email_instructions   = isset( $gateway_settings['email_instructions'] ) ? $gateway_settings['email_instructions'] : '';
		$this->order_status         = isset( $gateway_settings['order_status'] ) ? $gateway_settings['order_status'] : 'wc-on-hold';
		$this->order_status_success = isset( $gateway_settings['order_status_success'] ) ? $gateway_settings['order_status_success'] : 'wc-paybybank-paid';
		$this->extra_fee            = isset( $gateway_settings['extra_fee'] ) ? $gateway_settings['extra_fee'] : '';
		$this->paid_email           = isset( $gateway_settings['paid_email'] ) ? $gateway_settings['paid_email'] : '';
		$this->logger               = isset( $gateway_settings['enable_log'] ) && wc_string_to_bool( $gateway_settings['enable_log'] ) ? wc_get_logger() : false;
	}


	/**
	 *********************************
	 ***** PAYMENT GATEWAY SETUP *****
	 *********************************
	 */

	/**
	 * Add PayByBank as a new payment gateway
	 *
	 * @param $methods
	 *
	 * @return array
	 */
	public function add_dc_paybybank_gateway_class( $methods ) {
		$methods[] = 'WC_Gateway_DC_PayByBank';

		return $methods;
	}


	/**
	 * Display the merchant PaymentURL who needed to PayByBank.
	 *
	 * @param $description string
	 * @param $method WC_Payment_Gateway
	 *
	 * @return string
	 */
	function filter_woocommerce_settings_api_form_fields_id( $description, $method ) {

		if ( 'dc-paybybank' !== $method->id ) {
			return $description;
		}

		if ( ! isset( $_GET['page'], $_GET['tab'], $_GET['section'] ) || 'checkout' !== $_GET['tab'] || 'dc-paybybank' !== $_GET['section'] ) {
			return $description;
		}

		$merchant_payment_url = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? "https" : "http" ) . "://$_SERVER[HTTP_HOST]/wp-json/dc-paybybank/paybybank-success/";

		ob_start();
		?>
        <p class="dc-paybybank-instructions">
            <span class="dc-paybybank-instructions-title">Merchant PaymentURL</span>
            <span class="dc-paybybank-instructions-subtitle"><?php esc_html_e( 'You have to send the following URL address to PayByBank.', 'paybybank' ); ?></span>
            <code class="dc-paybybank-instructions-url"><?php echo esc_url( $merchant_payment_url ); ?></code>
        </p>
		<?php

		return $description . ob_get_clean();
	}



	/**
	 *********************************
	 ***** API ENDPOINT REQUESTS *****
	 *********************************
	 */

	/**
	 *  Register a new route to get the payment status update from PayByBank service.
	 */
	public function api_register_paybybank_payment_url() {

		register_rest_route( 'dc-paybybank', '/paybybank-success/', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_status_update_request_from_webhook' ],
			'permission_callback' => '__return_true'
		] );
	}


	/**
	 * Receive the payment status update from PayByBank service and return the appropriate response.
	 *
	 * @param WP_REST_Request $request_data A PayByBank "Order" object.
	 */
	public function handle_status_update_request_from_webhook( $request_data ) {

		$update_eshop_order_result = $this->update_eshop_order_status_from_pbb_order( $request_data );

		if ( is_wp_error( $update_eshop_order_result ) ) {

			$error_code    = $update_eshop_order_result->get_error_code();
			$error_message = $update_eshop_order_result->get_error_message();

			// return error result to PayByBank
			echo $error_code;

			// log error
			if ( $this->logger ) {
				$this->logger->error( __( 'An error occurred when PayByBank API sent a status update to our webhook.', 'paybybank' ), [ 'source' => DC_PAYBYBANK_SLUG ] );
				$this->logger->error( esc_html( $error_message ), [ 'source' => DC_PAYBYBANK_SLUG ] );
			}
		}
		else {
			// if not WP_Error, then eshop status changed successfully, so return success response to PayByBank
			echo 'OK';

			if ( $this->logger ) {
				$pbb_order_data = $request_data->get_params();
				$order_id       = $pbb_order_data['merchantOrderId'];
				$this->logger->info( sprintf( __( 'Successful update (via webhook) from PayByBank API for order #%s', 'paybybank' ), $order_id ), [ 'source' => DC_PAYBYBANK_SLUG ] );
			}
		}
	}


	/**
	 * Updates the eshop order status, based on PayByBank "Order" object.
	 * Also updates pbb database for this specific pbb_order record.
	 *
	 * @param WP_REST_Request $pbb_order A single PayByBank "Order" object.
	 *
	 * @return string|WP_Error Returns 'payment_success' or 'payment_failed' or WP_Error.
	 */
	private function update_eshop_order_status_from_pbb_order( $pbb_order ) {

		$pbb_order_data = $pbb_order->get_params();
		$order_id       = (int) $pbb_order_data['merchantOrderId'];
		$order          = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'order_not_found', sprintf( __( 'Error: Eshop order with merchantOrderId #%s not found.', 'paybybank' ), $order_id ) );
		}

		$pbb_status = ! empty( $pbb_order_data['omtTransactionBank']['merchantOrderStatus'] ) ? trim( $pbb_order_data['omtTransactionBank']['merchantOrderStatus'] ) : '';

		// update status for the database record
		if ( ! empty( $pbb_status ) ) {
			global $wpdb;
			$wpdb->update( $wpdb->prefix . 'pbb_payment_orders', [ 'pbb_status' => $pbb_status ], [ 'order_id' => $order_id ] );
		}

		$pbb_payment_status = $this->check_pbb_order_status( $pbb_status );

		if ( 'payment_success' === $pbb_payment_status ) {

			$transaction_id = $pbb_order_data['omtTransactionBank']['txn_id'];

			$this->pbb_update_eshop_order( $pbb_payment_status, $order, $transaction_id );

			return $pbb_payment_status;
		}
		elseif ( 'payment_failed' === $pbb_payment_status ) {

			$this->pbb_update_eshop_order( $pbb_payment_status, $order );

			return $pbb_payment_status;
		}
		else {
			return new WP_Error( $pbb_payment_status->get_error_code(), $pbb_payment_status->get_error_message() . ' - merchantOrderId #' . $order_id );
		}
	}


	/**
	 * Updates the eshop order status (if needed) and adds an order note.
	 *
	 * @param string   $pbb_payment_status The payment status -> 'payment_success' OR 'payment_failed'.
	 * @param WC_Order $order              The order object to update.
	 * @param string   $transaction_id     A transaction ID to add to order (optional).
	 *
	 * @return void
	 */
	private function pbb_update_eshop_order( $pbb_payment_status, $order, $transaction_id = '' ) {

		if ( 'payment_success' === $pbb_payment_status ) {

			$status_after_payment = 'wc-' === substr( $this->order_status_success, 0, 3 ) ? substr( $this->order_status_success, 3 ) : $this->order_status_success;

			if ( $status_after_payment === $order->get_status() ) {
				// fallback if status already changed to paid for some reason
				$order->add_order_note( esc_html__( 'Order paid successfully by PayByBank.', 'paybybank' ) );
			}
			else {
				
				if ( ! empty( $transaction_id ) ) {
					$order->set_transaction_id( wc_clean( $transaction_id ) );
				}

				$order->update_status( $status_after_payment, esc_html__( 'Order paid successfully by PayByBank. Status changed automatically after client payment.', 'paybybank' ) );
			}
		}
		elseif ( 'payment_failed' === $pbb_payment_status ) {

			if ( in_array( $order->get_status(), [ 'failed', 'cancelled' ] ) ) {
				// fallback if status already changed to cancelled/failed for some reason
				$order->add_order_note( esc_html__( 'Payment via PayByBank failed.', 'paybybank' ) );
			}
			else {
				$order->update_status( 'failed', esc_html__( 'Payment via PayByBank failed.', 'paybybank' ) );
			}
		}
	}


	/**
	 * Checks the pbb order status and calculates the payment status.
	 *
	 * @param string $pbb_status A PayByBank order status.
	 *
	 * @return string|WP_Error Returns 'payment_success' or 'payment_failed' or WP_Error for statuses we don't know how to handle.
	 */
	private function check_pbb_order_status( $pbb_status ) {

		if ( in_array( $pbb_status, $this->get_pbb_success_payment_statuses() ) ) {
			return 'payment_success';
		}
		elseif ( in_array( $pbb_status, $this->get_pbb_failed_payment_statuses() ) ) {
			return 'payment_failed';
		}

		return new WP_Error( 'unexpected_order_status', sprintf( __( 'Error: Unexpected order status received (%s)', 'paybybank' ), $pbb_status ) );
	}


	/**
	 * PayByBank "Order Statuses" that are considered as successful payments.
	 *
	 * @return string[]
	 */
	private function get_pbb_success_payment_statuses() {
		return [
			'PAID',
			'COMPLETED'
		];
	}


	/**
	 * PayByBank "Order Statuses" that are considered as failed/cancelled payments.
	 *
	 * @return string[]
	 */
	private function get_pbb_failed_payment_statuses() {
		return [
			'READY_TO_CANCEL',
			'CANCELLED',
			'CANCELLED_BY_MERCHANT'
		];
	}


	/**
	 * Schedules a daily cron to mass get status for pending orders.
	 *
	 * @return void
	 */
	public function dc_schedule_mass_get_status_request() {

		$hook              = 'paybybank_mass_get_status';
		$args              = [];
		$group             = DC_PAYBYBANK_SLUG;
		$already_scheduled = function_exists( 'as_has_scheduled_action' ) ? as_has_scheduled_action( $hook, $args, $group ) : as_next_scheduled_action( $hook, $args, $group );

		if ( ! $already_scheduled ) {

			// set random execution time between 0AM and 5AM (UTC time)
			$cron_schedule = sprintf( '%1$d %2$d * * *', rand( 0, 59 ), rand( 0, 5 ) );

			as_schedule_cron_action( time(), $cron_schedule, $hook, $args, $group, true, 9 );
		}
	}


	/**
	 * Makes a request to PBB API, to get status update for all pending orders.
	 * Only the local database is updated, not WC orders.
	 * Also, an async action scheduler action is scheduled to update WC Orders later.
	 *
	 * @return void
	 */
	public function mass_get_status_for_pending_payments() {

		global $wpdb;
		$pbb_table_name = $wpdb->prefix . 'pbb_payment_orders';

		// get pending payments from custom table
		$pending_orders = $wpdb->get_results( "SELECT order_id FROM {$pbb_table_name} WHERE pbb_status = 'PENDING'" );
		$pending_orders = wp_list_pluck( $pending_orders, 'order_id' );

		if ( $this->logger ) {
			$this->logger->info( __( 'Mass status update started for these pending orders:', 'paybybank' ), [ 'source' => DC_PAYBYBANK_SLUG ] );
			$this->logger->info( wc_print_r( $pending_orders, true ), [ 'source' => DC_PAYBYBANK_SLUG ] );
		}

		// get orders from pbb api and update our custom table
		$update_from_api_result = $this->update_pbb_database_from_pbb_api( $pending_orders );

		if ( $this->logger ) {
			if ( is_wp_error( $update_from_api_result ) ) {
				$this->logger->error( __( 'An error occurred while performing a mass status update', 'paybybank' ), [ 'source' => DC_PAYBYBANK_SLUG ] );
				$this->logger->error( $update_from_api_result->get_error_message(), [ 'source' => DC_PAYBYBANK_SLUG ] );
			}
			else {
				if ( empty( $update_from_api_result ) ) {
					$this->logger->info( __( 'The sync with PayByBank system for the mass status update is completed.', 'paybybank' ) . ' ' . __( 'No orders require status update.', 'paybybank' ), [ 'source' => DC_PAYBYBANK_SLUG ] );
				}
				else {
					$this->logger->info( __( 'The sync with PayByBank system for the mass status update is completed.', 'paybybank' ) . ' ' . __( 'The following orders required a status update:', 'paybybank' ), [ 'source' => DC_PAYBYBANK_SLUG ] );
					$this->logger->info(  wc_print_r( $update_from_api_result, true ), [ 'source' => DC_PAYBYBANK_SLUG ] );
				}
			}
		}

		// schedule async action to update eshop orders
		$hook  = 'paybybank_update_eshop_orders';
		$args  = [];
		$group = DC_PAYBYBANK_SLUG;

		as_enqueue_async_action( $hook, $args, $group, true, 9 );
	}


	/**
	 * Requests payment status updates from PBB API and updates the local database.
	 *
	 * @param array $orders WC Order ID(s) to request update for.
	 * @param bool $async Whether to async update the WC Order later or not. Async should be false only when manually updating a single order.
	 *
	 * @return array|WP_Error An array with updated orders, or WP_Error for update failure.
	 */
	public function update_pbb_database_from_pbb_api( $orders, $async = true ) {

		global $wpdb;

		$pbb_table_name = $wpdb->prefix . 'pbb_payment_orders';
		$base_path      = 'yes' === $this->mode ? DC_PAYBYBANK_BASE_TEST_PATH : DC_PAYBYBANK_BASE_PATH;
		$get_url        = trailingslashit( $base_path . DC_PAYBYBANK_SUBMIT_ORDER_PATH . $this->api_key );

		$orders_updated_statuses = [];
		$orders_in_chuncks       = array_chunk( (array) $orders, 100, true );

		foreach ( $orders_in_chuncks as $orders_chunk ) {

			$orders_to_request = implode( ',', $orders_chunk );
			$result            = wp_remote_get( $get_url . $orders_to_request, [
				// 'sslverify' => false,
				'timeout' => 90
			] );

			if ( is_wp_error( $result ) || empty( $result['body'] ) || empty( $result['response']['code'] ) || (int) $result['response']['code'] !== 200 ) {
				return new WP_Error( 'communication_api_error', __( 'Error: Unable to connect with PayByBank system. Please try again.', 'paybybank' ) );
			}

			$pbb_orders = json_decode( $result['body'], true );

			$values = [];

			foreach ( $pbb_orders as $pbb_order ) {

				if ( empty( $pbb_order['omtTransactionBank']['merchantOrderStatus'] ) ||
				     $pbb_order['omtTransactionBank']['merchantOrderStatus'] === 'PENDING' ) {
					continue;
				}

				// flag to update later async or not
				// async is false for single manual request status update
				$async_update = $async ? 1 : 0;

				$values[] = "('{$pbb_order['merchantOrderId']}', '{$pbb_order['id']}', '{$pbb_order['omtTransactionBank']['bankPaymentCode']}', '{$pbb_order['omtTransactionBank']['merchantOrderStatus']}', {$async_update})";

				$orders_updated_statuses[ $pbb_order['merchantOrderId'] ] = $pbb_order['omtTransactionBank']['merchantOrderStatus'];
			}

			if ( ! empty( $values ) ) {

				// An INSERT ON DUPLICATE KEY UPDATE is performed as a hack to quickly mass update multiple records with a single SQL query.
				$values = implode( ", ", $values );
				$sql    = "INSERT INTO {$pbb_table_name} (order_id, pbb_id, rf_code, pbb_status, update_pending) VALUES {$values} AS newval ON DUPLICATE KEY UPDATE pbb_status = newval.pbb_status, update_pending = newval.update_pending";
				$res    = $wpdb->query( $sql );
			}
		}

		return $orders_updated_statuses;
	}


	/**
	 * Updates the WC Orders that have a status update pending.
	 *
	 * @return void
	 */
	public function pbb_update_eshop_orders_status_async() {

		global $wpdb;

		// get pending payments from custom table
		$pbb_table_name        = $wpdb->prefix . 'pbb_payment_orders';
		$pending_update_orders = $wpdb->get_results( "SELECT order_id, pbb_id, pbb_status FROM {$pbb_table_name} WHERE update_pending = 1", OBJECT_K );

		if ( $this->logger ) {

			if ( empty( $pending_update_orders ) ) {
				$this->logger->info( __( 'No orders have a status update pending.', 'paybybank' ), [ 'source' => DC_PAYBYBANK_SLUG ] );
			}
			else {
				$this->logger->info( __( 'Async orders status update started for these pending orders:', 'paybybank' ), [ 'source' => DC_PAYBYBANK_SLUG ] );
				$this->logger->info( wc_print_r( $pending_update_orders, true ), [ 'source' => DC_PAYBYBANK_SLUG ] );
			}
		}

		foreach ( $pending_update_orders as $pending_order_data ) {

			$pending_order  = wc_get_order( $pending_order_data->order_id );
			$payment_status = $this->check_pbb_order_status( $pending_order_data->pbb_status );

			if ( $pending_order && ! is_wp_error( $payment_status ) ) {

				$transaction_id = $pending_order_data->pbb_id;

				$this->pbb_update_eshop_order( $payment_status, $pending_order, $transaction_id );
			}

			$update_sql = $wpdb->prepare( "UPDATE {$pbb_table_name} SET update_pending = 0 WHERE order_id = %s", $pending_order_data->order_id );
			$wpdb->query( $update_sql );
		}

		if ( $this->logger ) {
			$this->logger->info( __( 'Async orders status update finished successfully', 'paybybank' ), [ 'source' => DC_PAYBYBANK_SLUG ] );
		}
	}


	/**
	 * Create a custom database table to log PayByBank payment orders.
	 *
	 * DB Version changes log:
	 * 2.1 -> Custom table created in the database.
	 *
	 * @return void
	 * @since 2.1.0
	 */
	public function maybe_update_pbb_database() {

		$db_option_name    = 'paybybank_db_version';
		$target_db_version = '2.1';

		if ( get_option( $db_option_name ) === $target_db_version ) return;

		global $wpdb;

		$pbb_table_name = $wpdb->prefix . 'pbb_payment_orders';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$pbb_table_name}'" ) !== $pbb_table_name ) {

			$charset_collate = $wpdb->get_charset_collate();

			// Table does not exist
			$query = "CREATE TABLE IF NOT EXISTS {$pbb_table_name} (
			    id bigint(20) unsigned NOT NULL auto_increment,
			    order_id bigint(20) unsigned NOT NULL UNIQUE,
			    pbb_id varchar(20) default NULL,
	 			rf_code varchar(64) NOT NULL,
	 			order_data longtext default NULL,
			    created datetime NOT NULL default current_timestamp,
			    pbb_status varchar(64) NOT NULL default 'PENDING',
			   	update_pending int(1) NOT NULL default '0',
			    PRIMARY KEY (id)
			) {$charset_collate};";

			$create_table_result = $wpdb->query( $query );

			if ( $create_table_result === true ) {
				update_option( $db_option_name, $target_db_version, true );
			}
		}
	}



	/**
	 **************************************
	 ***** WOOCOMMERCE CUSTOMIZATIONS *****
	 **************************************
	 */

	/**
	 * WooCommerce Add fee to checkout for a gateway ID
	 *
	 * @since 2.0.0 Filters added to enable tax calculations
	 */
	public function dc_paybybank_add_checkout_fee_for_gateway() {

		if ( is_admin() && ! wp_doing_ajax() ) return;

		$chosen_gateway = WC()->session->get( 'chosen_payment_method' );

		if ( 'dc-paybybank' === $chosen_gateway ) {

			if ( isset( $this->extra_fee ) && (float) $this->extra_fee > 0 ) {

				$fee_name  = esc_html__( 'PayByBank Fee', 'paybybank' );
				$taxable   = wc_string_to_bool( apply_filters( 'paybybank_enable_tax_fee', false ) );
				$tax_class = apply_filters( 'paybybank_fee_tax_class', '' );

				WC()->cart->add_fee( $fee_name, round( (float) $this->extra_fee, 2 ), $taxable, $tax_class );
			}
		}
	}


	/**
	 * Print instructions for the "thank-you" page.
	 * This function is attached to a hook that fires only for orders with PayByBank as a payment method.
	 *
	 * @param int $order_id
	 *
	 * @return void
	 */
	public function dc_paybybank_add_thankyou_message( $order_id ) {

		$instructions = trim( $this->instructions );

		if ( empty( $instructions ) ) return;

		$order = wc_get_order( $order_id );

		if ( ! $order ) return;

		// wp_enqueue_script( 'wc-clipboard', WC()->plugin_url() . '/assets/js/admin/wc-clipboard.min.js', [ 'jquery' ], $this->version, [ 'strategy' => 'defer' ] );
		wp_enqueue_script( 'dc-pbb-thankyou', plugin_dir_url( __FILE__ ) . 'js/dc-pbb-thankyou.js', [ 'jquery' ], $this->version, [ 'strategy' => 'defer' ] );

		$reference_code_html = '';
		$reference_code      = $order->get_meta( 'dc_reference_code' );

		if ( ! empty( $reference_code ) ) {

			$reference_code_html = sprintf(
				'<span class="copy-pbb-rf-code" style="cursor:pointer;"><strong class="pbb-rf-code" style="font-size: 1.1em;">%1$s</strong> <span class="pbb-rf-copy" style="background: #808080;color: #ffffff;padding: 1px 6px;border-radius: 30px;font-size: 0.9em;">%2$s</span></span>',
				$reference_code,
				__( 'Click to copy', 'paybybank' )
			);
		}

		// prevent instruction message with no rf code placeholder
		if ( strpos( $instructions, '{pbb_rf_code}' ) === false ) {
			$instructions .= '<br>' . __( 'PayByBank Reference Code', 'paybybank' ) . ': {pbb_rf_code}';
		}

		$instructions = str_replace( '{pbb_rf_code}', $reference_code_html, $instructions );

		echo wpautop( wptexturize( wp_kses_post( $instructions ) ) );
	}


	/**
	 * Add content to the WC emails.
	 *
	 * @access public
	 *
	 * @param WC_Order $order
	 * @param bool $sent_to_admin
	 * @param bool $plain_text
	 * @param WC_Email $email
	 */
	public function dc_paybybank_add_email_instructions( $order, $sent_to_admin, $plain_text, $email ) {

		// Don't include for other payment mathods
		if ( ! $order instanceof WC_Order || 'dc-paybybank' !== $order->get_payment_method() ) return;

		// Include instructions in emails only for orders initial status (when waiting for PayByBank payment)
		if ( 'wc-' . $order->get_status() !== $this->order_status ) return;

		$reference_code = $order->get_meta( 'dc_reference_code' );
		$reference_code = ! empty( $reference_code ) ? '<strong class="pbb-rf-code">' . $reference_code . '</strong>' : '';

		// Send different messages to admin and customers
		if ( ! $sent_to_admin ) {

			if ( ! empty( $this->email_instructions ) ) {

				// prevent instruction message with no rf code placeholder
				if ( strpos( $this->email_instructions, '{pbb_rf_code}' ) === false ) {
					$this->email_instructions .= ' <br>' . __( 'PayByBank Reference Code', 'paybybank' ) . ': {pbb_rf_code}';
				}

				$instructions = str_replace( '{pbb_rf_code}', $reference_code, $this->email_instructions ) ;
			}

		}
		else {
			$instructions = __( 'PayByBank Reference Code', 'paybybank' ) . ': ' . $reference_code;
		}

		// omit if empty
		if ( empty( $instructions ) ) return;

		if ( $plain_text ) {
			echo strip_tags( $instructions ) . "\n\n"; // checked ok
		}
		else {
			echo wpautop( wptexturize( wp_kses_post( $instructions . '<br>' ) ) ); // checked ok
		}
	}


	/**
	 * Appends the payment status to the PayByBank payment title.
	 * Makes more clear the payment status mainly to the customer in emails.
	 * Filter priority >10 needed to work with WPML extension for WooCommerce.
	 *
	 * @param string   $title Payment method title.
	 * @param WC_Order $order
	 *
	 * @return  string  Payment method title.
	 * @since    2.1.0
	 */
	function dc_add_payment_status_to_pbb_payment_title( $title, $order ) {

		if ( 'dc-paybybank' !== $order->get_payment_method() ) return $title;

		$payment_status         = $this->get_payment_status_from_db( $order->get_id() );
		$statuses_to_print_info = array_merge( [ 'PENDING' ], $this->get_pbb_success_payment_statuses(), $this->get_pbb_failed_payment_statuses() );

		if ( ! in_array( $payment_status, $statuses_to_print_info ) ) return $title;

		$payment_status_label = $this->get_payment_status_display_label( $payment_status );

		if ( empty( $payment_status_label ) ) return $title;

		$title .= ' (' . esc_html( $payment_status_label ) . ')';

		return $title;
	}


	/**
	 * Display PBB payment info and actions on the order edit page.
	 *
	 * @param WC_Order $order
	 */
	public function dc_paybybank_checkout_field_display_admin_order_meta( $order ) {

		if ( 'dc-paybybank' !== $order->get_payment_method() ) return;

		wp_enqueue_style( $this->plugin_name );
		wp_enqueue_script( 'dc-pbb-order-edit' );

		$reference_code       = $order->get_meta( 'dc_reference_code' );
		$reference_code       = ! empty( $reference_code ) ? $reference_code : __( 'N/A', 'woocommerce' );
		$payment_status       = $this->get_payment_status_from_db( $order->get_id() );
		$payment_status_label = $this->get_payment_status_display_label( $payment_status );

		?>
		<div class="paybybank-order-info">
			<strong><?php esc_html_e( 'PayByBank RF Code', 'paybybank' ); ?>:</strong> <?php echo esc_html( $reference_code ); ?>
			<br>
			<strong><?php esc_html_e( 'Payment status', 'paybybank' ); ?>:</strong>
			<span class="<?php echo esc_html( $payment_status ); ?>"><?php echo esc_html( $payment_status_label ); ?></span>
			<?php if ( 'PENDING' === $payment_status || 'unknown_status' === $payment_status ) : ?>
				<br>
				<button id="pbb_request_status_update" class="button"><?php esc_html_e( 'Request status update', 'paybybank' ); ?></button>
			<?php endif ?>
		</div>
		<?php
	}


	/**
	 * Get pbb payment status from the local pbb database table.
	 *
	 * @param int $order_id The WC order ID.
	 *
	 * @return string The PBB payment status.
	 */
	private function get_payment_status_from_db( $order_id ) {
		global $wpdb;

		$pbb_table_name = $wpdb->prefix . 'pbb_payment_orders';
		$select_sql     = $wpdb->prepare( "SELECT pbb_status FROM {$pbb_table_name} WHERE order_id = %s", $order_id );
		$payment_status = $wpdb->get_var( $select_sql );

		return ! empty( $payment_status ) ? $payment_status : 'unknown_status';
	}


	/**
	 * Get display text for each PBB payment status.
	 *
	 * @param string $payment_status The PBB payment status.
	 *
	 * @return string
	 */
	private function get_payment_status_display_label( $payment_status ) {

		if ( 'PENDING' === $payment_status ) {
			$payment_status_label = __( 'Pending', 'paybybank' );
		}
		elseif ( in_array( $payment_status, $this->get_pbb_success_payment_statuses() ) ) {
			$payment_status_label = __( 'Paid', 'paybybank' );
		}
		elseif ( in_array( $payment_status, $this->get_pbb_failed_payment_statuses() ) ) {
			$payment_status_label = __( 'Failed', 'paybybank' );
		}
		elseif ( 'unknown_status' === $payment_status ) {
			$payment_status_label = __( 'Unknown status', 'paybybank' );
		}
		else {
			$payment_status_label = $payment_status;
		}

		return $payment_status_label;
	}


	/**
	 * Request payment status update for a specific order and updates db and WC order status.
	 * Runs via AJAX.
	 *
	 * @return void
	 */
	public function pbb_request_status_update() {

		if ( ! isset( $_POST['nonce_ajax'] ) || ! wp_verify_nonce( $_POST['nonce_ajax'], 'pbb-order-edit-nonce' ) ) {
			wp_send_json_error( 'Unauthorized request. Go away!' );
		}

		if ( empty( $_POST['order_id'] ) ) {
			wp_send_json_error( 'Missing order ID.' );
		}

		$order_id = $_POST['order_id'];
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( 'Order not found.' );
		}

		$updated_orders = $this->update_pbb_database_from_pbb_api( [ $order_id ], false );

		if ( is_wp_error( $updated_orders ) ) {
			wp_send_json_error( $updated_orders->get_error_message() );
		}

		if ( empty( $updated_orders[ $order_id ] ) ) {
			wp_send_json_error( esc_html__( 'The payment is still PENDING', 'paybybank' ) );
		}

		$pbb_status     = $updated_orders[ $order_id ];
		$payment_status = $this->check_pbb_order_status( $pbb_status );

		if ( ! is_wp_error( $payment_status ) ) {

			$this->pbb_update_eshop_order( $payment_status, $order );
		}

		wp_send_json_success( [
			'pbb_status'  => $pbb_status,
			'updated_msg' => sprintf( __( 'The payment status is: %s', 'paybybank' ), $pbb_status )
		] );
	}



	/**
	 *******************************
	 ***** CUSTOM ORDER STATUS *****
	 *******************************
	 */

	/**
	 * Register PayByBank paid status
	 */
	public function dc_paybybank_register_paybybank_paid_order_status() {

		register_post_status( 'wc-paybybank-paid', [
			'label'                     => _x( 'Paid by PayByBank', 'Order status', 'paybybank' ),
			'public'                    => false,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => true,
			'exclude_from_search'       => false,
			'label_count'               => _n_noop( 'Paid by PayByBank <span class="count">(%s)</span>', 'Paid by PayByBank <span class="count">(%s)</span>', 'paybybank' ),
		] );
	}


	/**
	 * Add custom Paid by PayByBank status in WooCommerce statuses.
	 *
	 * @param $order_statuses array
	 *
	 * @return array
	 */
	public function add_paybybank_paid_to_order_statuses( $order_statuses ) {

		$new_order_statuses = [];

		foreach ( $order_statuses as $key => $status ) {

			$new_order_statuses[ $key ] = $status;

			if ( 'wc-processing' === $key ) {
				$new_order_statuses['wc-paybybank-paid'] = _x( 'Paid by PayByBank', 'Order status', 'paybybank' );
			}
		}

		return $new_order_statuses;
	}


	/**
     * Add Paid by PayByBank custom order status to paid statuses list.
     *
	 * @param $paid_statuses array All order statuses considered as "paid".
	 *
	 * @return array
     *
     * @since 2.0.0
	 */
	public function mark_paybybank_paid_as_paid_status( $paid_statuses ) {

	    $paid_statuses[] = 'paybybank-paid';

        return $paid_statuses;
    }


	/**
	 * Send "Paid by PayByBank" emails to customer and admin, when an order status changes TO "paybybank-paid".
     * Using other WC emails as a base, not own template files.
	 *
	 * @param $order_id int
	 * @param $order WC_Order
	 */
	public function dc_paybybank_email_order_status_paybybank_paid( $order_id, $order ) {

		if ( ! wc_string_to_bool( $this->paid_email ) ) return;

		$heading = esc_html__( 'Paid by PayByBank', 'paybybank' );
		$subject = esc_html__( 'Paid by PayByBank', 'paybybank' );

		// Get WooCommerce email objects
		$mailer = WC()->mailer();

		if ( empty( $mailer ) ) return;

		$emails = $mailer->get_emails();

		// Use one of the active emails e.g. "Customer_Completed_Order"
		// Won't work if you choose an object that is not active
		// Assign heading & subject to chosen object
		if ( ! empty( $emails['WC_Email_Customer_Processing_Order'] ) ) {
			$emails['WC_Email_Customer_Processing_Order']->heading             = $heading;
			$emails['WC_Email_Customer_Processing_Order']->settings['heading'] = $heading;
			$emails['WC_Email_Customer_Processing_Order']->subject             = $subject;
			$emails['WC_Email_Customer_Processing_Order']->settings['subject'] = $subject;
			$emails['WC_Email_Customer_Processing_Order']->trigger( $order_id );
		}

		if ( ! empty( $emails['WC_Email_New_Order'] ) ) {
			$emails['WC_Email_New_Order']->heading             = $heading;
			$emails['WC_Email_New_Order']->settings['heading'] = $heading;
			$emails['WC_Email_New_Order']->subject             = $subject;
			$emails['WC_Email_New_Order']->settings['subject'] = $subject;
			$emails['WC_Email_New_Order']->trigger( $order_id );
		}
	}


	public function send_processing_email_when_changing_from_custom_status( $order_id, $order ) {

		// Get WooCommerce email objects
		$mailer = WC()->mailer();

		if ( empty( $mailer ) ) return;

		$email_notifications = $mailer->get_emails();
		$email_notifications['WC_Email_Customer_Processing_Order']->trigger( $order_id );
	}



	/**
	 ****************
	 ***** MISC *****
	 ****************
	 */

	/**
	 * Add Settings link in plugin page.
	 *
	 * @param   array $actions
	 * @param   string $plugin_file
	 * @return  array $actions
	 * @since   2.0.0
	 */
	function dc_paybybank_plugins_list_action_links( $actions, $plugin_file ) {

		if ( in_array( $plugin_file, [ 'paybybank/dc-paybybank.php', 'dc-paybybank/dc-paybybank.php' ] ) ) {

			$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=dc-paybybank' ) ) . '">' . esc_html__( 'Settings', 'paybybank' ) . '</a>';
			array_unshift( $actions, $settings_link );
		}

		return $actions;
	}


	/**
	 * Declare compatibility with WooCommerce Features (HPOS, Cart & Checkout Blocks).
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	function declare_compatibility_with_wc_features() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', 'paybybank/dc-paybybank.php' );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', 'paybybank/dc-paybybank.php' );
		}
	}



	/**
	 ********************
	 ***** ENQUEUES *****
	 ********************
	 */

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    2.0.0
	 */
	public function enqueue_styles( $hook ) {

		$env_type       = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$assets_version = in_array( $env_type, [ 'local', 'development' ] ) ? $this->version . time() : $this->version;

		wp_register_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/dc-paybybank-admin.css', [], $assets_version );

		if ( 'woocommerce_page_wc-settings' === $hook && isset( $_GET['tab'] ) && isset( $_GET['section'] ) && 'checkout' === $_GET['tab'] && 'dc-paybybank' === $_GET['section'] ) {
			wp_enqueue_style( $this->plugin_name );
		}
	}

	public function enqueue_scripts( $hook ) {

		$env_type       = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$assets_version = in_array( $env_type, [ 'local', 'development' ] ) ? $this->version . time() : $this->version;

		$ajax_data = [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pbb-order-edit-nonce' ),
		];

		wp_register_script( 'dc-pbb-order-edit', plugin_dir_url( __FILE__ ) . 'js/dc-pbb-order-edit.js', [ 'jquery' ], $assets_version, [ 'strategy' => 'defer' ] );
		wp_localize_script( 'dc-pbb-order-edit', 'dc_pbb_data', $ajax_data );
	}
}
