<?php
/**
 * 黑貓物流7-11貨到付款 - 冷藏
 *
 * @package WooCommerceCCatGateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 黑貓物流7-11貨到付款 - 冷藏運送方式
 */
class CCATPAY_Shipping_711_COD_Refrigerated extends CCATPAY_Shipping_711_COD {
	/**
	 * 建構函數
	 *
	 * @param int $instance_id 運送方式實例ID.
	 */
	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );
		$this->temperature_type   = 'refrigerated';
		$this->method_title       = __( '黑貓快速到店(冷藏) 7-11取貨付款', 'ccat-for-woocommerce');
		$this->title              = __( '黑貓快速到店(冷藏) 7-11取貨付款', 'ccat-for-woocommerce');
		$this->method_description = __( '黑貓快速到店低冷藏 7-11取貨付款，顧客需先選擇超商門市，於門市領取商品時再付款', 'ccat-for-woocommerce');
	}
}
