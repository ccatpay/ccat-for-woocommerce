<?php
/**
 * 黑貓物流宅配先付款 - 冷凍
 *
 * @package WooCommerceCCatGateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 黑貓物流宅配先付款 - 冷凍運送方式
 */
class CCATPAY_Shipping_Prepaid_Frozen extends CCATPAY_Shipping_Prepaid {
	/**
	 * 建構函數
	 *
	 * @param int $instance_id 運送方式實例ID.
	 */
	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );
		$this->temperature_type   = 'frozen';
		$this->method_title       = __( '黑貓宅配(冷凍)', 'ccat-for-woocommerce');
		$this->title              = __( '黑貓宅配(冷凍)', 'ccat-for-woocommerce');
		$this->method_description = __( '黑貓宅配低溫冷凍商品，顧客需先完成付款', 'ccat-for-woocommerce');
	}
}
