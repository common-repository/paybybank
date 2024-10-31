(function($) {
	'use strict';

	// Register payment method
	const settings = window.wc.wcSettings.getSetting( 'dc-paybybank_data', {} );
	const label = window.wp.htmlEntities.decodeEntities( settings.title ) || window.wp.i18n.__( 'PayByBank - Bank Transfer', 'paybybank' );
	const Content = () => {
		return window.wp.htmlEntities.decodeEntities( settings.description || '' );
	};

	const Block_Gateway = {
		name: 'dc-paybybank',
		label: label,
		content: Object( window.wp.element.createElement )( Content, null ),
		edit: Object( window.wp.element.createElement )( Content, null ),
		canMakePayment: () => true,
		ariaLabel: label,
		supports: {
			features: settings.supports
		}
	};

	window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );


	// Add action on payment mathod change
	const { extensionCartUpdate } = wc.blocksCheckout;

	wp.hooks.addAction('experimental__woocommerce_blocks-checkout-set-active-payment-method', 'paybybank-method-selected-change', function(shipping) {
		extensionCartUpdate({
			namespace: 'paybybank-method-selected-change',
			data: {
				payment_method: document.querySelector('input[name="radio-control-wc-payment-method-options"]:checked').value
			}
		});
	});

})(jQuery);