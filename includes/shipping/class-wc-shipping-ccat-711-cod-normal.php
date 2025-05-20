<?php
/**
 * 黑貓物流7-11貨到付款 - 常溫
 *
 * @package WooCommerceCCatGateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 黑貓物流7-11貨到付款 - 常溫運送方式
 */
class WC_Shipping_CCat_711_COD_Normal extends WC_Shipping_CCat_711_COD {
	/**
	 * 建構函數
	 *
	 * @param int $instance_id 運送方式實例ID.
	 */
	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );
		$this->temperature_type   = 'normal';
		$this->method_title       = __( '黑貓物流7-11貨到付款 (常溫)', 'ccat-for-woocommerce' );
		$this->title              = __( '黑貓物流7-11貨到付款 (常溫)', 'ccat-for-woocommerce' );
		$this->method_description = __( '黑貓物流(常溫)7-11貨到付款，顧客需先選擇超商門市再付款', 'ccat-for-woocommerce' );
	}
}
