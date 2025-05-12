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
class WC_Shipping_CCat_711_COD extends WC_Shipping_CCat_Abstract {
	/**
	 * 建構函數
	 *
	 * @param int $instance_id 運送方式實例ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'wc_shipping_ccat_711_cod';
		$this->method_title       = __( '黑貓物流7-11貨到付款', 'ccat-for-woocommerce' );
		$this->title              = __( '黑貓物流7-11貨到付款', 'ccat-for-woocommerce' );
		$this->method_description = __( '黑貓物流7-11貨到付款，顧客需選擇超商門市', 'ccat-for-woocommerce' );

		// 設定不需要預付款，但需要選擇商店.
		$this->requires_payment         = false;
		$this->requires_store_selection = true;
		$this->store_selection_url      = 'https://logistics.ccat.com.tw/store-selection'; // 以實際的URL替換.

		parent::__construct( $instance_id );
	}
}
