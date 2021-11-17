<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;

defined( 'ABSPATH' ) || exit;

/**
 * WC_CO_Blocks_Support class.
 *
 * @extends AbstractPaymentMethodType
 */
final class WC_KCO_Blocks_Support extends AbstractPaymentMethodType {
	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name = 'kco';

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', array( $this, 'add_payment_request_order_meta' ), 8, 2 );

	}

	/**
	 * Init payment method.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_kco_settings', array() );
	}

	/**
	 * @return string[]
	 */
	public function get_payment_method_script_handles() {
		$asset_path   = KCO_WC_PLUGIN_PATH . '/build/index.asset.php';
		$version      = KCO_WC_MIN_WC_VER;
		$dependencies = array();
		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = $asset['version'] ?? $version;
			$dependencies = $asset['dependencies'] ?? $dependencies;
		}
		wp_register_script(
			'wc-kco-blocks-integration',
			plugins_url( 'build/index.js', KCO_WC_MAIN_FILE ),
			$dependencies,
			$version,
			true
		);
		return array( 'wc-kco-blocks-integration' );
	}
	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return ! ( 'yes' !== $this->settings['enabled'] );
	}
	/**
	 * Returns the Stripe Payment Gateway JavaScript configuration object.
	 *
	 * @return array  the JS configuration from the Stripe Payment Gateway.
	 */
	private function get_gateway_javascript_params() {
		return array();
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$pay_for_order = false;
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			$pay_for_order = true;
		}

		return array(
			'title'               => $this->get_title(),
			'supports'            => $this->get_supported_features(),
			'isAdmin'             => is_admin(),
			'snippetKCO'          => kco_wc_show_snippet( false, true ),
			'payForOrder'         => $pay_for_order,
			'getKlarnaOrderUrl'   => WC_AJAX::get_endpoint( 'kco_wc_get_klarna_order' ),
			'getKlarnaOrderNonce' => wp_create_nonce( 'kco_wc_get_klarna_order' ),
		);
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		$gateway = new KCO_Gateway();
		return apply_filters( 'wc_kco_supports', $gateway->supports );
	}

	/**
	 * Returns the title string to use in the UI (customisable via admin settings screen).
	 *
	 * @return string Title / label string
	 */
	private function get_title() {
		return 'Klarna checkout';
	}

	/**
	 * Add payment request data to the order meta as hooked on the
	 * woocommerce_rest_checkout_process_payment_with_context action.
	 *
	 * @param PaymentContext $context Holds context for the payment.
	 * @param PaymentResult  $result  Result object for the payment.
	 */
	public function add_payment_request_order_meta( PaymentContext $context, PaymentResult &$result ) {
		if ( 'kco' === $context->payment_method ) {
			$payment_details                 = $result->payment_details;
			$payment_details['redirect_url'] = add_query_arg(
				array(
					'kco_confirm'  => 'yes',
					'kco_order_id' => '{checkout.order.id}',
				),
				$context->order->get_checkout_order_received_url()
			);
			$result->set_payment_details( $payment_details );
			$result->set_status( 'success' );
		}

	}



}
