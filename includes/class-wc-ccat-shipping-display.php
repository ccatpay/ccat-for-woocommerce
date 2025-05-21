<?php
/**
 * 黑貓物流
 *
 * @package WooCommerceCCatGateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_CCat_Shipping_Display class handles the display of electronic invoice information
 * both on the frontend and backend order detail pages.
 */
class WC_CCat_Shipping_Display {

	/**
	 * 初始化 hooks
	 */
	public function __construct() {
		// 前台訂單詳細頁面.
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_invoice_info' ) );

		// 後台訂單詳細頁面.
		add_action(
			'woocommerce_admin_order_data_after_shipping_address',
			array(
				$this,
				'display_admin_shipping_info',
			)
		);

		// 註冊和加載後台 JS 和 CSS.
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ) );
	}

	/**
	 * 顯示前台發票資訊
	 *
	 * @param WC_Order $order 訂單資訊.
	 */
	public function display_admin_shipping_info( WC_Order $order ) {
		$this->add_logistics_buttons( $order );
	}

	/**
	 * 在訂單明細頁面添加物流功能按鈕
	 *
	 * @param WC_Order $order 訂單物件.
	 */
	public function add_logistics_buttons( WC_Order $order ) {
		$shipping_method = $order->get_shipping_method();

		// 判斷是否為黑貓物流.
		if ( $this->is_ccat_shipping( $order ) ) {
			// 檢查是否已列印託運單.
			$has_printed = get_post_meta( $order->get_id(), '_ccat_shipping_printed', true );

			echo '<div class="ccat-logistics-buttons">';

			// 超商取貨且尚未列印過，顯示變更門市按鈕.
			if ( $this->is_convenience_store_shipping( $order ) && ! $has_printed ) {
				echo '<button type="button" class="button change-store" data-order-id="' . esc_attr( $order->get_id() ) . '">' .
					esc_html__( '變更門市', 'ccat-for-woocommerce' ) .
					'</button>';
			}

			// 尚未列印過，顯示建立物流訂單按鈕.
			if ( ! $has_printed ) {
				echo '<button type="button" class="button create-logistics-order" data-order-id="' . esc_attr( $order->get_id() ) . '">' .
					esc_html__( '建立物流訂單', 'ccat-for-woocommerce' ) .
					'</button>';
			}

			// 顯示下載託運單按鈕（無論是否已列印）.
			echo '<button type="button" class="button download-shipping-label" data-order-id="' . esc_attr( $order->get_id() ) . '">' .
				esc_html__( '下載託運單', 'ccat-for-woocommerce' ) .
				'</button>';

			echo '</div>';
		}
	}

	/**
	 * 判斷訂單是否使用黑貓物流
	 *
	 * @param WC_Order $order 訂單物件.
	 *
	 * @return bool 是否為黑貓物流.
	 */
	private function is_ccat_shipping( WC_Order $order ): bool {
		$shipping_methods = $order->get_shipping_methods();

		foreach ( $shipping_methods as $shipping_method ) {
			$method_id = $shipping_method->get_method_id();

			// 檢查運送方式ID是否包含"wc_shipping_ccat".
			if ( strpos( $method_id, 'wc_shipping_ccat' ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 判斷是否為超商取貨
	 *
	 * @param WC_Order $order 訂單物件.
	 *
	 * @return bool 是否為超商取貨.
	 */
	private function is_convenience_store_shipping( WC_Order $order ): bool {
		$shipping_methods = $order->get_shipping_methods();

		foreach ( $shipping_methods as $shipping_method ) {
			$method_id = $shipping_method->get_method_id();

			// 檢查是否為7-11超商取貨.
			if ( strpos( $method_id, '711' ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 註冊和加載後台 JS 和 CSS
	 */
	public function register_admin_scripts() {
		$screen = get_current_screen();

		// WC_CCAT_PAYMENTS_VERSION 只在訂單編輯頁面加載 JS 和 CSS.
		if ( $screen && 'woocommerce_page_wc-orders' === $screen->id ) {
			// 註冊並加載 JS.
			wp_register_script(
				'ccat-logistics-buttons',
				WC_CCat_Payments::plugin_url() . '/logistics-buttons.js',
				array( 'jquery' ),
				time(),
				true
			);

			// 將必要的變數傳遞給 JS.
			wp_localize_script(
				'ccat-logistics-buttons',
				'ccat_logistics_params',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'ccat-logistics-nonce' ),
				)
			);

			// 加載 JS.
			wp_enqueue_script( 'ccat-logistics-buttons' );

			// 加載 CSS.
			wp_add_inline_style(
				'woocommerce_admin_styles', // 使用 WooCommerce 的管理樣式.
				'
				.ccat-logistics-buttons {
					margin-top: 10px;
				}
				.ccat-logistics-buttons .button {
					margin-right: 5px;
					margin-bottom: 5px;
				}
				'
			);
		}
	}
}
