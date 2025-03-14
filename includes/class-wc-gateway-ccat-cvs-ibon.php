<?php
/**
 * WC_Gateway_CCat class
 *
 * @author   sakilu <brian@sakilu.com>
 * @package  WooCommerce CCat Payments Gateway
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'class-wc-gateway-ccat-abstract.php';

/**
 * CCat Gateway.
 *
 * @class    WC_Gateway_CCat_Cvs_Ibon
 * @version  1.0
 */
class WC_Gateway_CCat_Cvs_Ibon extends WC_Gateway_CCat_Cvs_Abstract {

	/**
	 * Unique id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'ccat_payment_cvs_ibon';

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		$this->title        = __( '黑貓Pay - Ibon繳款', 'woocommerce-gateway-ccat' );
		$this->description  = __( '使用黑貓Pay Ibon，付款更安心。', 'woocommerce-gateway-ccat' );
//		$this->redirect_url = get_site_url( null, 'ccat-ibon' );
//		WC_CCat_Payments::log( "> set url:" . $this->redirect_url );
		parent::__construct();
	}


	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( '啟用', 'woocommerce-gateway-ccat' ),
				'type'    => 'checkbox',
				'label'   => __( '啟用', 'woocommerce-gateway-ccat' ),
				'default' => 'yes',
			),
			'title'   => array(
				'title'       => __( '付款標題', 'woocommerce-gateway-ccat' ),
				'type'        => 'text',
				'description' => __( '使用者選擇付款時顯示的文字', 'woocommerce-gateway-ccat' ),
				'default'     => __( '黑貓Pay - Ibon繳款', 'woocommerce-gateway-ccat' ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Retrieves the configured payment type for the payment gateway.
	 *
	 * @return string The payment type as configured in the gateway settings.
	 */
	public function payment_type(): string {
		return '0';
	}

    /**
     * Retrieves the configured acquirer type for the payment gateway.
     *
     * @return string The acquirer type as configured in the gateway settings.
     */
    public function acquirer_type(): string
    {
        return '2';
    }
}
