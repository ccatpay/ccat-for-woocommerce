<?php
/**
 * CCat PayuniCard Blocks integration
 *
 * @package WooCommerceCCatGateway
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * CCat COD Blocks integration
 */
class CCATPAY_Gateway_COD_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var CCATPAY_Gateway_Abstract|null
	 */
	protected ?CCATPAY_Gateway_Abstract $gateway = null;

	/**
	 * 支付方法名稱
	 *
	 * @var string
	 */
	protected $name = 'ccat_cod_card';

	/**
	 * 建構子
	 *
	 * @param string $name 支付方法名稱.
	 */
	public function __construct( string $name = 'ccat_cod_card' ) {
		$this->name = $name;
	}

	/**
	 * 初始化
	 */
	public function initialize() {
		$gateways      = ( function_exists( 'WC' ) && WC()->payment_gateways ) ? WC()->payment_gateways->payment_gateways() : array();
		$this->gateway = $gateways[ $this->name ] ?? null;
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active(): bool {
		return ( $this->gateway instanceof CCATPAY_Gateway_Abstract ) && $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles(): array {
		$script_path = '/resources/js/frontend/cod.js';
		$script_url  = CCATPAY_Payments::plugin_url() . $script_path;

		wp_register_script(
			'wc-ccat-cod-blocks',
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

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-ccat-cod-blocks', CCATPAYMENTS_DOMAIN, CCATPAY_Payments::plugin_abspath() . 'languages/' );
		}

		return array( 'wc-ccat-cod-blocks' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data(): array {
		if ( ! $this->gateway ) {
			return array();
		}

		return array(
			'title'       => $this->gateway->get_option( 'title', '' ),
			'description' => $this->gateway->get_option( 'description', '' ),
			'supports'    => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
		);
	}
}

/**
 * CCat COD Cash Blocks integration
 */
class CCATPAY_Gateway_COD_Cash_Blocks_Support extends CCATPAY_Gateway_COD_Blocks_Support {

	/**
	 * 支付方法名稱
	 *
	 * @var string
	 */
	protected $name = 'ccat_cod_cash';

	/**
	 * 建構子
	 */
	public function __construct() {
		parent::__construct( 'ccat_cod_cash' );
	}
}

/**
 * CCat COD Card Blocks integration
 */
class CCATPAY_Gateway_COD_Card_Blocks_Support extends CCATPAY_Gateway_COD_Blocks_Support {

	/**
	 * 支付方法名稱
	 *
	 * @var string
	 */
	protected $name = 'ccat_cod_card';

	/**
	 * 建構子
	 */
	public function __construct() {
		parent::__construct( 'ccat_cod_card' );
	}
}

/**
 * CCat COD 7-11 Blocks integration
 */
class CCATPAY_Gateway_COD_711_Blocks_Support extends CCATPAY_Gateway_COD_Blocks_Support {

	/**
	 * 支付方法名稱
	 *
	 * @var string
	 */
	protected $name = 'ccat_cod_711';

	/**
	 * 建構子
	 */
	public function __construct() {
		parent::__construct( 'ccat_cod_711' );
	}
}
