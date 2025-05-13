<?php
/**
 * WC_Gateway_CCat_COD class
 *
 * @author   Your Name <your.email@example.com>
 * @package  WooCommerce CCat Payments Gateway
 * @since    1.10.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'class-wc-gateway-ccat-cvs-abstract.php';

/**
 * CCat 黑貓貨到付款支付閘道.
 *
 * @class    WC_Gateway_CCat_COD
 * @version  1.10.4
 */
class WC_Gateway_CCat_COD extends WC_Gateway_CCat_Abstract {

	/**
	 * Unique id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'ccat_cod';

	/**
	 * 初始化黑貓貨到付款支付閘道
	 */
	public function __construct() {
		$this->title       = __( '黑貓貨到付款', 'ccat-for-woocommerce' );
		$this->description = __( '透過黑貓宅急便提供貨到付款的付款方式', 'ccat-for-woocommerce' );

		add_action( 'woocommerce_thankyou', array(
			$this,
			'display_virtual_account_details',
		) );
		add_action( 'woocommerce_view_order', array(
			$this,
			'display_virtual_account_details',
		) );
		add_action( 'woocommerce_admin_order_data_after_order_details',
			array(
				$this,
				'display_virtual_account_details',
			) );
		parent::__construct();
	}

	/**
	 * 初始化設定表單欄位
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( '啟用/停用', 'ccat-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( '啟用黑貓貨到付款', 'ccat-for-woocommerce' ),
				'default' => 'yes',
			),
			'title'   => array(
				'title'       => __( '付款標題', 'ccat-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( '使用者選擇付款時顯示的文字', 'ccat-for-woocommerce' ),
				'default'     => __( '黑貓貨到付款', 'ccat-for-woocommerce' ),
				'desc_tip'    => true,
			),
		);
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
			wc_add_notice( __( '送貨地址為必填項目', 'ccat-for-woocommerce' ), 'error' );

			return array(
				'result' => 'failure',
			);
		}

		// 設置訂單狀態為處理中.
		$order->update_status(
			'processing',
			__( '訂單已建立，等待貨到付款', 'ccat-for-woocommerce' )
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
