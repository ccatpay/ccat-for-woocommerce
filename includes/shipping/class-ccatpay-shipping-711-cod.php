<?php
/**
 * 黑貓物流7-11貨到付款
 *
 * @package WooCommerceCCatGateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 黑貓物流7-11貨到付款運送方式
 */
class CCATPAY_Shipping_711_COD extends CCATPAY_Shipping_Abstract {
	/**
	 * 建構函數
	 *
	 * @param int $instance_id 運送方式實例ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->method_title       = __( '黑貓快速到店(常溫) 7-11取貨付款', 'ccat-for-woocommerce');
		$this->title              = __( '黑貓快速到店(常溫) 7-11取貨付款', 'ccat-for-woocommerce');
		$this->method_description = __( '黑貓快速到店常溫 7-11取貨付款，顧客需選擇超商門市', 'ccat-for-woocommerce');

		// 設定不需要預付款，但需要選擇商店.
		$this->requires_payment = false;

		parent::__construct( $instance_id );
	}
}
