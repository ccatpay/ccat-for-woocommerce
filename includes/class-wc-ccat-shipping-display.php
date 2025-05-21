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

		// 註冊變更門市的 Ajax 處理.
		add_action( 'wp_ajax_get_store_selection_url', array( $this, 'handle_store_selection_url' ) );

		// 註冊儲存門市的 Ajax 處理.
		add_action( 'wp_ajax_save_store_ajax', array( $this, 'handle_save_store_ajax' ) );
	}

	/**
	 * 處理商店選擇跳轉 URL 請求
	 *
	 * 驗證請求的安全性，並通過 API 創建地圖選擇頁面跳轉的回調 URL。
	 * 支援存儲臨時變數以供回調時使用。
	 * 如果請求或 API 操作失敗，則返回錯誤響應
	 */
	public function handle_store_selection_url() {
		// 驗證 nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ccat-logistics-nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( '安全驗證失敗', 'ccat-for-woocommerce' ),
				)
			);
			wp_die();
		}

		// 獲取運送方式.
		$shipping_method = isset( $_POST['shippingMethod'] ) ? sanitize_text_field( wp_unslash( $_POST['shippingMethod'] ) ) : '';
		$store_category  = isset( $_POST['storeCategory'] ) ? sanitize_text_field( wp_unslash( $_POST['storeCategory'] ) ) : '';

		Ccat711_Blocks_Integration::openMapForStore( $store_category, $shipping_method );
		wp_die();
	}


	/**
	 * 顯示物流按鈕
	 *
	 * @param WC_Order $order 訂單資訊.
	 */
	public function display_admin_shipping_info( WC_Order $order ) {
		$shipping_method = $order->get_shipping_method();

		// 判斷是否為黑貓物流.
		if ( $this->is_ccat_shipping( $order ) ) {
			// 檢查是否已列印託運單.
			$has_printed = get_post_meta( $order->get_id(), '_ccat_shipping_printed', true );

			// 檢查付款方式是否為貨到付款.
			$payment_method = $order->get_payment_method();
			$is_cod         = strpos( $payment_method, 'cod' ) !== false;

			// 檢查訂單是否已付款（如果不是貨到付款）.
			$is_paid = $order->is_paid() || $is_cod;

			// 只有在貨到付款或已付款的情況下才顯示按鈕.
			if ( $is_paid ) {
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

		if ( $screen && 'woocommerce_page_wc-orders' === $screen->id ) {
			if ( ! isset( $_GET['id'] ) ) { // phpcs:ignore WordPress
				return;
			}
			$order_id = absint( $_GET['id'] ); // phpcs:ignore WordPress

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}
			$shipping_methods = $order->get_shipping_methods();
			foreach ( $shipping_methods as $shipping_method_obj ) {
				$shipping_method = $shipping_method_obj->get_method_id();
				break; // 只取第一個運送方法.
			}
			if ( empty( $shipping_method ) ) {
				return;
			}
			// 根據運送方式類型決定門市類別.
			if ( false !== strpos( $shipping_method, 'refrigerated' ) ) {
				$store_category = '15'; // 冷藏.
			} elseif ( false !== strpos( $shipping_method, 'frozen' ) ) {
				$store_category = '14'; // 冷凍.
			} else {
				$store_category = '13'; // 常溫.
			}
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
					'ajax_url'        => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( 'ccat-logistics-nonce' ),
					'store_category'  => $store_category,
					'shipping_method' => $shipping_method,
					'order_id'        => $order_id,
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
				.ccat-store-modal {
					display: none;
					position: fixed;
					z-index: 1000;
					left: 0;
					top: 0;
					width: 100%;
					height: 100%;
					overflow: auto;
					background-color: rgba(0,0,0,0.4);
				}
				.ccat-store-modal-content {
					background-color: #fefefe;
					margin: 5% auto;
					padding: 20px;
					border: 1px solid #888;
					width: 80%;
					max-width: 800px;
					border-radius: 5px;
					box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2);
				}
				.ccat-store-close {
					color: #aaa;
					float: right;
					font-size: 28px;
					font-weight: bold;
					cursor: pointer;
				}
				.ccat-store-close:hover,
				.ccat-store-close:focus {
					color: black;
					text-decoration: none;
					cursor: pointer;
				}
				.ccat-store-search {
					margin-bottom: 20px;
				}
				.ccat-store-search-fields {
					display: flex;
					flex-wrap: wrap;
					gap: 10px;
					margin-bottom: 10px;
				}
				.ccat-store-search-field {
					flex: 1;
					min-width: 150px;
				}
				.ccat-store-search-button {
					display: block;
					width: 100%;
					text-align: center;
				}
				.ccat-store-list {
					max-height: 400px;
					overflow-y: auto;
					border: 1px solid #ddd;
					margin-bottom: 20px;
				}
				.ccat-store-list table {
					width: 100%;
					border-collapse: collapse;
				}
				.ccat-store-list th, 
				.ccat-store-list td {
					padding: 8px;
					text-align: left;
					border-bottom: 1px solid #ddd;
				}
				.ccat-store-list th {
					background-color: #f2f2f2;
				}
				.ccat-store-list tr:hover {
					background-color: #f5f5f5;
				}
				.ccat-store-list tr.selected {
					background-color: #e7f7e7;
				}
				.ccat-store-actions {
					text-align: right;
				}
				.ccat-store-loading {
					text-align: center;
					padding: 20px;
				}
				'
			);
		}
	}


	/**
	 * 處理儲存門市的 Ajax 請求
	 */
	public function handle_save_store_ajax() {
		check_ajax_referer( 'ccat-logistics-nonce', 'nonce' );

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( '無效的訂單 ID', 'ccat-for-woocommerce' ) ) );

			return;
		}
		// 獲取臨時變數和門市資訊.
		$store_name    = isset( $_POST['storename'] ) ? sanitize_text_field( wp_unslash( $_POST['storename'] ) ) : ''; // phpcs:ignore WordPress
		$store_id      = isset( $_POST['storeid'] ) ? sanitize_text_field( wp_unslash( $_POST['storeid'] ) ) : '';  // phpcs:ignore WordPress
		$store_address = isset( $_POST['storeaddress'] ) ? sanitize_text_field( wp_unslash( $_POST['storeaddress'] ) ) : '';  // phpcs:ignore WordPress
		$outside       = isset( $_POST['outside'] ) ? sanitize_text_field( wp_unslash( $_POST['outside'] ) ) : '0'; //  // phpcs:ignore WordPress
		$ship          = isset( $_POST['ship'] ) ? sanitize_text_field( wp_unslash( $_POST['ship'] ) ) : '1111111'; //  // phpcs:ignore WordPress
		$city          = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : ''; // phpcs:ignore WordPress
		$district      = isset( $_POST['district'] ) ? sanitize_text_field( wp_unslash( $_POST['district'] ) ) : ''; // phpcs:ignore WordPress
		$postcode      = isset( $_POST['postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) : ''; // phpcs:ignore WordPress

		if ( empty( $store_id ) || empty( $store_name ) || empty( $store_address ) ) {
			wp_send_json_error( array( 'message' => __( '門市資訊不完整', 'ccat-for-woocommerce' ) ) );

			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( '找不到此訂單', 'ccat-for-woocommerce' ) ) );

			return;
		}

		// 更新門市資訊.
		$order->update_meta_data( WC_Gateway_CCat_Abstract::META_STORE_ID, $store_id );
		$order->update_meta_data( WC_Gateway_CCat_Abstract::META_STORE_NAME, $store_name );
		$order->update_meta_data( WC_Gateway_CCat_Abstract::META_STORE_ADDRESS, $store_address );
		$order->update_meta_data( WC_Gateway_CCat_Abstract::META_OUTSIDE, $outside );
		$order->update_meta_data( WC_Gateway_CCat_Abstract::META_SHIP, $ship );

		// 更新訂單的運送地址.
		$shipping_address = array(
			'first_name' => $order->get_shipping_first_name(),
			'last_name'  => $order->get_shipping_last_name(),
			'company'    => $order->get_shipping_company(),
			'address_1'  => $store_name . ' (' . $store_id . ')',
			'address_2'  => $store_address,
			'city'       => $city,
			'state'      => $district,
			'postcode'   => $postcode,
			'country'    => $order->get_shipping_country(),
		);

		// 更新訂單的發貨地址.
		$order->set_address( $shipping_address, 'shipping' );

		$order->save();

		// 添加訂單備註.
		$order->add_order_note(
			sprintf(
				__( '門市已變更為: %1$s (%2$s) %3$s', 'ccat-for-woocommerce' ),
				$store_name,
				$store_id,
				$store_address
			),
			false, // 不顯示給客戶.
			true // 由系統新增.
		);

		wp_send_json_success(
			array(
				'message'       => __( '門市變更成功', 'ccat-for-woocommerce' ),
				'store_id'      => $store_id,
				'store_name'    => $store_name,
				'store_address' => $store_address,
			)
		);
	}
}
