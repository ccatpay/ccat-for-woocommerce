<?php
/**
 * CCATPAY_Gateway_COD_711 class
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
 * CCat 超商711貨到付款支付閘道.
 *
 * @class    CCATPAY_Gateway_COD_711
 * @version  1.11.0
 */
class CCATPAY_Gateway_COD_711 extends CCATPAY_Gateway_COD_Abstract {

	/**
	 * Unique id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'ccat_cod_711';

	/**
	 * 初始化超商711貨到付款支付閘道
	 */
	public function __construct() {
		$this->title       = __( '黑貓快速到店711取貨付款', 'ccat-for-woocommerce');
		$this->description = __( '透過黑貓宅急便提供711超商門市取貨付款的方式', 'ccat-for-woocommerce');
		parent::__construct();
	}

	/**
	 * 初始化設定表單欄位
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( '啟用/停用', 'ccat-for-woocommerce'),
				'type'    => 'checkbox',
				'label'   => __( '啟用超商711取貨付款', 'ccat-for-woocommerce'),
				'default' => 'yes',
			),
			'title'   => array(
				'title'       => __( '付款標題', 'ccat-for-woocommerce'),
				'type'        => 'text',
				'description' => __( '使用者選擇付款時顯示的文字', 'ccat-for-woocommerce'),
				'default'     => __( '黑貓快速到店711取貨付款', 'ccat-for-woocommerce'),
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
		return '711';
	}

	/**
	 * 定義收單機構類型
	 *
	 * @return string 收單機構類型代碼
	 */
	public function acquirer_type(): string {
		return '711';
	}
}
