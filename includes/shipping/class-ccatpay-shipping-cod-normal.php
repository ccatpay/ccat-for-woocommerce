<?php
/**
 * 黑貓物流貨到付款 - 常溫
 *
 * @package WooCommerceCCatGateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 黑貓物流貨到付款 - 常溫運送方式
 */
class CCATPAY_Shipping_COD_Normal extends CCATPAY_Shipping_COD {
	/**
	 * 建構函數
	 *
	 * @param int $instance_id 運送方式實例ID.
	 */
	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );
		$this->temperature_type   = 'normal';
		$this->method_title       = __( '黑貓宅配(常溫) 貨到付款', 'ccat-for-woocommerce' );
		$this->title              = __( '黑貓宅配(常溫) 貨到付款', 'ccat-for-woocommerce' );
		$this->method_description = __( '黑貓宅配常溫商品 貨到付款，顧客收到商品時才付款', 'ccat-for-woocommerce' );
	}
}
