<?php
/**
 * CCat Payments Blocks integration
 *
 * @package WooCommerceCCatGateway
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * CCat Payments Blocks integration
 *
 * @since 1.0.0
 */
final class WC_Gateway_CCat_Atm_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var WC_Gateway_CCat_Abstract
	 */
	private WC_Gateway_CCat_Abstract $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'ccat_payment_cvs_atm';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$gateways      = WC()->payment_gateways->payment_gateways();
		$this->gateway = $gateways[ $this->name ];
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active(): bool {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles(): array {
		$script_path = '/resources/js/frontend/atm.js';
		$script_url  = WC_CCat_Payments::plugin_url() . $script_path;

		wp_register_script(
			'wc-ccat-atm-payments-blocks',
			$script_url,
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
			),
			time(),
			true
		);

		return array( 'wc-ccat-atm-payments-blocks' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data(): array {
		return array(
			'title'       => $this->gateway->get_option( 'title' ),
			'description' => $this->gateway->get_option( 'description' ),
			'supports'    => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
		);
	}
}
