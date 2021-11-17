<?php
/**
 * File to add support for Checkout blocks for Klarna checkout.
 *
 * @package Klarna_Checkout/Includes
 */

defined( 'ABSPATH' ) || exit;

add_action( 'woocommerce_blocks_loaded', 'woocommerce_gateway_kco_block_support' );
/**
 * Adds support for woocommerce blocks.
 *
 * @return mixed
 */
function woocommerce_gateway_kco_block_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		include_once KCO_WC_PLUGIN_PATH . '/classes/class-wc-kco-blocks-support.php';

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$container = Automattic\WooCommerce\Blocks\Package::container();
				// registers as shared instance.
				$container->register(
					WC_KCO_Blocks_Support::class,
					function() {
						return new WC_KCO_Blocks_Support();

					}
				);
				$payment_method_registry->register(
					$container->get( WC_KCO_Blocks_Support::class )
				);
			}
		);
	}
}
