<?php
/**
 * 黑貓物流宅配先付款 - 常溫
 *
 * @package WooCommerceCCatGateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 黑貓物流宅配先付款 - 常溫運送方式
 */
class WC_Shipping_CCat_Prepaid_Normal extends WC_Shipping_CCat_Prepaid {
	/**
	 * 建構函數
	 *
	 * @param int $instance_id 運送方式實例ID.
	 */
	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );
		$this->temperature_type   = 'normal';
		$this->method_title       = __( '黑貓物流宅配 (常溫)', 'ccat-for-woocommerce' );
		$this->title              = __( '黑貓物流宅配 (常溫)', 'ccat-for-woocommerce' );
		$this->method_description = __( '黑貓物流(常溫)宅配，顧客需先完成付款', 'ccat-for-woocommerce' );
	}
}
