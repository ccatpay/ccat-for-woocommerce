<?php
/**
 * 黑貓物流宅配先付款
 *
 * @package WooCommerceCCatGateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 黑貓物流宅配先付款運送方式
 */
class CCATPAY_Shipping_Prepaid extends CCATPAY_Shipping_Abstract {
	/**
	 * 建構函數
	 *
	 * @param int $instance_id 運送方式實例ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->method_title       = __( '黑貓宅配(常溫)', 'ccat-for-woocommerce');
		$this->title              = __( '黑貓宅配(常溫)', 'ccat-for-woocommerce');
		$this->method_description = __( '黑貓宅配常溫商品，顧客需先完成付款', 'ccat-for-woocommerce');

		// 設定需要預付款.
		$this->requires_payment = true;

		parent::__construct( $instance_id );
	}
}