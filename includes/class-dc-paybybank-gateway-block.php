<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Gateway_DC_PayByBank_Block extends AbstractPaymentMethodType {

	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name = 'dc-paybybank';

	public function initialize() {
		$this->settings = get_option( "woocommerce_{$this->name}_settings", [] );
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {

		$env_type       = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$assets_version = in_array( $env_type, [ 'local', 'development' ] ) ? DC_PAYBYBANK_VERSION . time() : DC_PAYBYBANK_VERSION;

		wp_register_script(
			'wc-payment-method-paybybank',
			plugin_dir_url( __FILE__ ) . 'blocks/wc-payment-method-paybybank.js',
			[
				'jquery',
				'react',
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
				'wp-polyfill'
			],
			$assets_version,
			[ 'strategy' => 'defer' ]
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-payment-method-paybybank', 'paybybank', dirname( plugin_basename( __FILE__ ), 2 ) . '/languages/' );
		}

		return [ 'wc-payment-method-paybybank' ];
	}

	public function get_payment_method_data() {
		return [
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => $this->get_supported_features(),
		];
	}
}