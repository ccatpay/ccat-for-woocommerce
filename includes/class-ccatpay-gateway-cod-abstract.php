<?php
/**
 * CCATPAY_Gateway_COD_Abstract class
 *
 * @author   Your Name <your.email@example.com>
 * @package  WooCommerce CCat Payments Gateway
 * @since    1.10.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'class-ccatpay-gateway-abstract.php';

/**
 * CCat 黑貓貨到付款支付閘道.
 *
 * @class    CCATPAY_Gateway_COD_Abstract
 * @version  1.10.4
 */
class CCATPAY_Gateway_COD_Abstract extends CCATPAY_Gateway_Abstract {

	/**
	 * Unique id for the gateway.
	 *
	 * @var string
	 */
	public $id = '';

	/**
	 * 初始化黑貓貨到付款支付閘道
	 */
	public function __construct() {
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		parent::__construct();
	}

	/**
	 * 定義支付類型
	 *
	 * @return string 支付類型代碼
	 */
	public function payment_type(): string {
		return '';
	}

	/**
	 * 處理支付流程
	 *
	 * @param int $order_id 訂單ID.
	 *
	 * @return array 處理結果
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );

		// 檢查送貨地址是否完整.
		if ( empty( $order->get_shipping_address_1() ) ) {
			wc_add_notice( __( '送貨地址為必填項目', 'ccat-for-woocommerce'), 'error' );

			return array(
				'result' => 'failure',
			);
		}
		if ( CCATPAY_Payments::is_shipping_enabled() ) {
			$this->add_shipping_data( array(), $order );
		}
		// 設置訂單狀態為處理中.
		$order->update_status(
			'processing',
			__( '訂單已建立，等待貨到付款', 'ccat-for-woocommerce')
		);

		// 清空購物車.
		WC()->cart->empty_cart();

		// 重定向到感謝頁面.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * 定義收單機構類型
	 *
	 * @return string 收單機構類型代碼
	 */
	public function acquirer_type(): string {
		return '';
	}
}
