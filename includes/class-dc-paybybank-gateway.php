<?php
/**
 * PayByBank Payment Gateway.
 *
 * Creates the PayByBank Payment Gateway.
 */
function init_dc_paybybank_gateway_class() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class WC_Gateway_DC_PayByBank extends WC_Payment_Gateway {

		public $api_key;
		public $mode;
		public $instructions;
		public $email_instructions;
		public $payment_code_life;
		public $order_status;
		public $order_status_success;
		public $extra_fee;
		public $paid_email;
		public $logger;

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {

			$this->id                 = 'dc-paybybank';
			$this->icon               = apply_filters( 'woocommerce_paybybank_gateway_icon', '' );
			$this->has_fields         = false;
			$this->method_title       = __( 'PayByBank', 'paybybank' );
			$this->method_description = __( 'Allows payments with PayByBank.', 'paybybank' );

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Define user set variables
			$this->title                = $this->get_option( 'title' );
			$this->description          = $this->get_option( 'description' );
			$this->instructions         = $this->get_option( 'instructions' );
			$this->email_instructions   = $this->get_option( 'email_instructions' );
			$this->api_key              = $this->get_option( 'api_key' );
			$this->mode                 = $this->get_option( 'mode' );
			$this->payment_code_life    = $this->get_option( 'payment_code_life', 720 );
			$this->order_status         = $this->get_option( 'order_status', 'wc-on-hold' );
			$this->order_status_success = $this->get_option( 'order_status_success', 'wc-paybybank-paid' );
			$this->extra_fee            = $this->get_option( 'extra_fee' );
			$this->paid_email           = $this->get_option( 'paid_email' );
			$this->logger               = wc_string_to_bool( $this->get_option( 'enable_log' ) ) ? wc_get_logger() : false;

			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		}

		/**
		 * Initialise Gateway Settings Form Fields.
		 */
		public function init_form_fields() {

			$this->form_fields = [
				'enabled'              => [
					'title'   => __( 'Enable/Disable', 'paybybank' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable PayByBank Payment', 'paybybank' ),
					'default' => 'no'
				],
				'title'                => [
					'title'       => __( 'Title', 'paybybank' ),
					'type'        => 'safe_text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'paybybank' ),
					'default'     => __( 'PayByBank - Bank Transfer', 'paybybank' ),
				],
				'order_status'         => [
					'title'       => __( 'Initial Order Status', 'paybybank' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'This will be the initial status when an order is created and PayByBank is selected as payment method.', 'paybybank' ),
					'default'     => 'wc-on-hold',
					'options'     => $this->get_order_statuses_available_for_selection()
				],
				'order_status_success' => [
					'title'       => __( 'Order Status after Successful Payment', 'paybybank' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'This will be the updated status when the customer completes the payment successfully. The status will change automatically when PayByBank API sends an update about a payment.', 'paybybank' ),
					'default'     => 'wc-paybybank-paid',
					'options'     => $this->get_order_statuses_available_for_selection()
				],
				'api_key'              => [
					'title'       => __( 'API Key', 'paybybank' ),
					'type'        => 'text',
					'description' => __( 'The API key provided by PayByBank. Do not change it unless you are in alignment with PayByBank.', 'paybybank' ),
					'default'     => 'XXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX',
				],
				'mode'                 => [
					'title'       => __( 'Mode', 'paybybank' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable test mode', 'paybybank' ),
					'description' => __( 'This changes the payment mode from Test to Live.', 'paybybank' ) . '<br>' . __( 'Please deselect it after you add the live API Key.', 'paybybank' ),
					'default'     => 'yes'
				],
				'payment_code_life'    => [
					'title'             => __( 'Payment Code Life (hours)', 'paybybank' ),
					'type'              => 'number',
					'description'       => __( 'The life of Payment Code in <strong>hours</strong>. Default is 720 hours (30 days)', 'paybybank' ),
					'default'           => 720,
					'custom_attributes' => [
						'min'  => 1,
						'step' => 1
					],
				],
				'extra_fee'            => [
					'title'       => __( 'Extra Fee', 'paybybank' ),
					'type'        => 'price',
					'description' => __( 'Set the extra fee (if applicable) for this payment method. Leave empty or zero if you don\'t want to add extra fee for this payment method', 'paybybank' ),
				],
				'description'          => [
					'title'       => __( 'Description', 'paybybank' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'paybybank' ),
					'default'     => __( 'Pay your order, with an RF code from the safe environment of your e-banking with no extra charges!', 'paybybank' ),
				],
				'instructions'         => [
					'title'       => __( 'Instructions for "Thank you" page', 'paybybank' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page. You can use the placeholder {pbb_rf_code} to display the RF code.', 'paybybank' ) . '<br>' .
					                 __( 'Leave empty to cancel the display of this message.', 'paybybank' ) . '<br>' .
					                 __( 'HTML tags are allowed.', 'paybybank' ),
					'default'     => __( "Pay for your order via e-banking. Select Payments > Single Payment RF and enter the payment code {pbb_rf_code} and the exact amount. \n\nUpon payment completion, we will be notified automatically and ship your order. There are no additional costs if you pay via a Greek bank.", 'paybybank' ),
				],
				'email_instructions'   => [
					'title'       => __( 'Instructions for Customer Emails', 'paybybank' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to customer emails. You can use the placeholder {pbb_rf_code} to display the RF code.', 'paybybank' ) . '<br>' .
					                 __( 'Leave empty to cancel the display of this message.', 'paybybank' ) . '<br>' .
					                 __( 'HTML tags are allowed.', 'paybybank' ),
					'default'     => __( "Pay for your order via e-banking. Select Payments > Single Payment RF and enter the payment code {pbb_rf_code} and the exact amount. \n\nUpon payment completion, we will be notified automatically and ship your order. There are no additional costs if you pay via a Greek bank.", 'paybybank' ),
				],
				'paid_email'           => [
					'title'       => __( 'Send extra email when Paid', 'paybybank' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable extra email for "Paid by PayByBank" status', 'paybybank' ),
					'description' => __( 'If you enable this, an extra email will be sent to customer (and admin) when an order status changes to "Paid by PayByBank" status, to inform about the successful payment.', 'paybybank' ),
					'default'     => 'yes'
				],
				'enable_log'           => [
					'title'       => __( 'Enable logging', 'paybybank' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable logging for PayByBank plugin\'s actions', 'paybybank' ),
					'description' => __( 'This option should be disabled, except for when testing or debugging an issue.', 'paybybank' ) . '<br>' .
					                 sprintf( __( 'You can check the logs at <a href="%s" target="_blank">WooCommerce > Status > Logs</a>. PayByBank\'s log files have "paybybank" as a source.', 'paybybank' ), esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ) ),
					'default'     => 'no'
				]
			];
		}


		/**
		 * Process the payment and return the result.
		 *
		 * @param int $order_id
		 *
		 * @return array
		 */
		public function process_payment( $order_id ) {

			$order = wc_get_order( $order_id );

			// Custom function to make the call to API to create the reference code for payment
			$rf_code = $this->create_reference_code( $order );

			if ( is_wp_error( $rf_code ) ) {

				if ( $this->logger ) {
					$this->logger->error( __( 'An error occurred while creating the RF code', 'paybybank' ), [ 'source' => DC_PAYBYBANK_SLUG ] );
					$this->logger->error( $rf_code->get_error_message(), [ 'source' => DC_PAYBYBANK_SLUG ] );
				}

				wc_add_notice( esc_html( $rf_code->get_error_message() ), 'error' );

				// stay on checkout and show notice
				return [
					'result'   => 'fail',
					'redirect' => '',
				];
			}

			if ( $this->logger ) {
				$this->logger->info( sprintf( __( 'RF code created successfully: %s', 'paybybank' ), $rf_code ), [ 'source' => DC_PAYBYBANK_SLUG ] );
			}

			// Save RF code to order meta
			$order->update_meta_data( 'dc_reference_code', wc_clean( $rf_code ) );

			// Set order status for the new created order
			$status_for_created_order = 'wc-' === substr( $this->order_status, 0, 3 ) ? substr( $this->order_status, 3 ) : $this->order_status;
			$order->update_status( $status_for_created_order, esc_html__( 'Checkout with PayByBank payment.', 'paybybank' ) );

			// Reduce stock levels
			wc_maybe_reduce_stock_levels( $order_id );

			// Remove cart
			WC()->cart->empty_cart();

			// Return thankyou redirect
			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			];
		}


		/**
		 *  Create reference code for PayByBank from POST request
		 *
		 * @param WC_Order $order
		 *
		 * @return string|WP_Error The RF Code on success, WP_Error on errors.
		 */
		public function create_reference_code( $order ) {

			$base_path   = 'yes' === $this->mode ? DC_PAYBYBANK_BASE_TEST_PATH : DC_PAYBYBANK_BASE_PATH;
			$api_params  = DC_PAYBYBANK_SUBMIT_ORDER_PATH . $this->api_key;
			$order_id    = $order->get_id();
			$customer_id = $order->get_customer_id();

			$post_url_params = [
				'merchant_order_id' => $order_id,
				'amount'            => round( $order->get_total(), 2 ), // up to 2 decimals allowed
				'payment_code_life' => $this->payment_code_life > 0 ? $this->payment_code_life : 720,
			];

			if ( $customer_id > 0 ) {
				$post_url_params['merchant_customer_id'] = $customer_id;
			}

			$post_url = add_query_arg( $post_url_params, $base_path . $api_params );
			$result   = wp_remote_post( $post_url, [
				//'sslverify' => false
				'timeout' => 10
			] );


			if ( ! is_wp_error( $result ) && is_array( $result ) && ! empty( $result['body'] )
			     && ! empty( $result['response']['code'] ) && (int) $result['response']['code'] === 200 ) {

				$response_body = json_decode( $result['body'], true );

				if ( ! empty( $response_body['error_code'] ) ) {

					if ( ! empty( $response_body['error_message'] ) ) {
						$message = sprintf( __( '%1$s (Error code: %2$s)', 'paybybank' ), $response_body['error_message'], $response_body['error_code'] );
					}
					else {
						$message = sprintf( __( 'Error code: %s', 'paybybank' ), $response_body['error_code'] );
					}

					return new WP_Error( 'api_error', $message );
				}


				// All OK with RF Code, finally...
				if ( ! empty( $response_body['omtTransactionBank']['bankPaymentCode'] ) ) {

					global $wpdb;

					$pbb_table_name = $wpdb->prefix . 'pbb_payment_orders';
					$data           = [
						'order_id' => $order_id,
						'pbb_id'   => $response_body['id'],
						'rf_code'  => $response_body['omtTransactionBank']['bankPaymentCode']
					];

					// save payment order data to our custom table
					$wpdb->insert( $pbb_table_name, $data, '%s' );

					return $response_body['omtTransactionBank']['bankPaymentCode'];
				}

				return new WP_Error( 'communication_api_error', __( 'PayByBank error: Problem with bankPaymentCode', 'paybybank' ) );
			}

			return new WP_Error( 'communication_api_error', __( 'Error: Unable to connect with PayByBank system. Please try again.', 'paybybank' ) );
		}


		/**
		 * Returns order statuses available for selection as PBB order status before and after payment.
		 *
		 * @return array
		 * @since 2.1.0
		 */
		private function get_order_statuses_available_for_selection() {

			$order_statuses = wc_get_order_statuses();

			unset(
				$order_statuses['wc-cancelled'],
				$order_statuses['wc-refunded'],
				$order_statuses['wc-failed'],
				$order_statuses['wc-failed'],
				$order_statuses['wc-checkout-draft'],
			);

			return $order_statuses;
		}
	}
}