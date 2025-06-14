<?php
/**
 * CCATPAY_Gateway_COD_Mobile class
 *
 * @package  WooCommerce CCat Payments Gateway
 * @since    1.11.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'class-ccatpay-gateway-cod-abstract.php';

/**
 * CCat 黑貓宅配貨到付款(手機支付)支付閘道.
 *
 * @class    CCATPAY_Gateway_COD_Mobile
 * @version  1.11.0
 */
class CCATPAY_Gateway_COD_Mobile extends CCATPAY_Gateway_COD_Abstract {

	/**
	 * Unique id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'ccat_cod_mobile';

	/**
	 * 初始化黑貓宅配貨到付款(手機支付)支付閘道
	 */
	public function __construct() {
		$this->title       = __( '黑貓宅配貨到付款(手機支付)', WC_CCAT_PAYMENTS_DOMAIN );
		$this->description = __( '透過黑貓宅急便提供貨到手機支付方式', WC_CCAT_PAYMENTS_DOMAIN );
		parent::__construct();
	}

	/**
	 * 初始化設定表單欄位
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( '啟用/停用', WC_CCAT_PAYMENTS_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => __( '啟用黑貓宅配貨到付款(手機支付)', WC_CCAT_PAYMENTS_DOMAIN ),
				'default' => 'yes',
			),
			'title'   => array(
				'title'       => __( '付款標題', WC_CCAT_PAYMENTS_DOMAIN ),
				'type'        => 'text',
				'description' => __( '使用者選擇付款時顯示的文字', WC_CCAT_PAYMENTS_DOMAIN ),
				'default'     => __( '宅配貨到付款(手機支付)', WC_CCAT_PAYMENTS_DOMAIN ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * 定義支付類型
	 *
	 * @return string 支付類型代碼
	 */
	public function payment_type(): string {
		return 'mobile';
	}

	/**
	 * 定義收單機構類型
	 *
	 * @return string 收單機構類型代碼
	 */
	public function acquirer_type(): string {
		return 'mobile';
	}
}
