<?php
/**
 * 黑貓物流7-11先付款 - 冷凍
 *
 * @package WooCommerceCCatGateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 黑貓物流7-11先付款 - 冷凍運送方式
 */
class CCATPAY_Shipping_711_Prepaid_Frozen extends CCATPAY_Shipping_711_Prepaid {
	/**
	 * 建構函數
	 *
	 * @param int $instance_id 運送方式實例ID.
	 */
	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );
		$this->temperature_type   = 'frozen';
		$this->method_title       = __( '黑貓快速到店(冷凍) 7-11取貨', 'ccat-for-woocommerce');
		$this->title              = __( '黑貓快速到店(冷凍) 7-11取貨', 'ccat-for-woocommerce');
		$this->method_description = __( '黑貓快速到店低溫冷凍 7-11取貨，顧客需先完成付款並選擇超商門市', 'ccat-for-woocommerce');
	}
}
