<?php
/**
 * 黑貓物流與支付協調器
 *
 * @package WooCommerceCCatGateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 黑貓物流與支付協調器類別
 */
class WC_CCat_Shipping_Payment_Coordinator {
	/**
	 * 初始化協調器
	 */
	public static function init() {
		// 過濾可用的支付方式，根據選擇的運送方式
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'filter_payment_gateways' ) );

		// 處理商店選擇
		add_action( 'woocommerce_after_checkout_form', array( __CLASS__, 'add_store_selection_scripts' ) );
		add_action( 'wp_ajax_ccat_select_store', array( __CLASS__, 'handle_store_selection' ) );
		add_action( 'wp_ajax_nopriv_ccat_select_store', array( __CLASS__, 'handle_store_selection' ) );

		// 儲存選擇的商店到訂單元數據
		add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'save_store_selection_to_order' ) );

		// 驗證商店選擇
		add_action( 'woocommerce_checkout_process', array( __CLASS__, 'validate_store_selection' ) );

		// 前端腳本.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

		// 在後台訂單顯示超商資訊.
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array(
			__CLASS__,
			'display_store_info_in_order'
		) );

		// 在結帳感謝頁和我的帳戶訂單詳情頁顯示超商資訊.
		add_action( 'woocommerce_order_details_after_customer_details', array(
			__CLASS__,
			'display_store_info_in_frontend'
		) );
	}

	/**
	 * 註冊前端腳本
	 */
	public static function enqueue_scripts() {
		if ( is_checkout() ) {
			wp_enqueue_script(
				'ccat-shipping-checkout',
				WC_CCat_Payments::plugin_url() . '/assets/js/ccat-shipping.js',
				array( 'jquery', 'wc-checkout' ),
				WC_CCAT_PAYMENTS_VERSION,
				true
			);

			// 傳遞變數到JS
			wp_localize_script(
				'ccat-shipping-checkout',
				'ccat_shipping_params',
				array(
					'ajax_url'           => admin_url( 'admin-ajax.php' ),
					'select_store_nonce' => wp_create_nonce( 'ccat_select_store' ),
				)
			);
		}
	}

	/**
	 * 根據運送方式過濾支付閘道
	 *
	 * @param array $available_gateways 可用的支付閘道
	 *
	 * @return array 過濾後的支付閘道
	 */
	public static function filter_payment_gateways( $available_gateways ) {
		if ( is_admin() || ! is_checkout() ) {
			return $available_gateways;
		}

		// 獲取選中的運送方式.
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		if ( empty( $chosen_shipping_methods ) ) {
			return $available_gateways;
		}

		$chosen_method   = $chosen_shipping_methods[0];
		$shipping_method = null;

		// 檢查運送方式實例.
		if ( strpos( $chosen_method, ':' ) !== false ) {
			$method_id       = explode( ':', $chosen_method );
			$shipping_method = $method_id[0];
		} else {
			$shipping_method = $chosen_method;
		}

		// 載入運送方式類別.
		$shipping_methods = WC()->shipping()->get_shipping_methods();

		// 檢查是否為黑貓物流COD方式.
		if ( in_array( $shipping_method, array( 'wc_shipping_ccat_cod', 'wc_shipping_ccat_711_cod' ), true ) ) {
			// 對於貨到付款，我們需要建立一個COD支付閘道.
			// 如果不存在，我們可以過濾所有其他閘道.
			foreach ( $available_gateways as $gateway_id => $gateway ) {
				// 保留一個自定義的COD閘道或WooCommerce內建的COD閘道.
				if ( $gateway_id !== 'cod' && $gateway_id !== 'ccat_payment_cod' ) {
					unset( $available_gateways[ $gateway_id ] );
				}
			}
		} elseif ( in_array( $shipping_method, array(
			'wc_shipping_ccat_prepaid',
			'wc_shipping_ccat_711_prepaid'
		), true ) ) {
			// 對於需要預付款的方式，確保COD閘道被禁用
			if ( isset( $available_gateways['cod'] ) ) {
				unset( $available_gateways['cod'] );
			}
			if ( isset( $available_gateways['ccat_payment_cod'] ) ) {
				unset( $available_gateways['ccat_payment_cod'] );
			}
		}

		return $available_gateways;
	}

	/**
	 * 添加商店選擇相關的腳本
	 */
	public static function add_store_selection_scripts() {
		?>
        <div id="ccat-store-selection-container" style="display: none;">
            <div class="ccat-store-selection-wrapper">
                <button type="button" id="ccat-select-store-button" class="button alt">
					<?php echo esc_html__( '選擇超商門市', 'ccat-for-woocommerce' ); ?>
                </button>
                <div id="ccat-selected-store-info"></div>
            </div>
        </div>
		<?php
	}

	/**
	 * 處理商店選擇AJAX請求
	 */
	public static function handle_store_selection() {
		check_ajax_referer( 'ccat_select_store', 'security' );

		$store_id      = isset( $_POST['store_id'] ) ? sanitize_text_field( $_POST['store_id'] ) : '';
		$store_name    = isset( $_POST['store_name'] ) ? sanitize_text_field( $_POST['store_name'] ) : '';
		$store_address = isset( $_POST['store_address'] ) ? sanitize_text_field( $_POST['store_address'] ) : '';

		if ( ! empty( $store_id ) && ! empty( $store_name ) ) {
			// 儲存到會話中
			WC()->session->set( 'ccat_selected_store_id', $store_id );
			WC()->session->set( 'ccat_selected_store_name', $store_name );
			WC()->session->set( 'ccat_selected_store_address', $store_address );

			wp_send_json_success( array(
				'message' => __( '已成功選擇門市', 'ccat-for-woocommerce' ),
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( '請選擇有效的門市', 'ccat-for-woocommerce' ),
			) );
		}

		wp_die();
	}

	/**
	 * 儲存商店選擇到訂單.
	 *
	 * @param int $order_id 訂單ID
	 */
	public static function save_store_selection_to_order( $order_id ) {
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		if ( empty( $chosen_shipping_methods ) ) {
			return;
		}

		$chosen_method   = $chosen_shipping_methods[0];
		$shipping_method = null;

		// 檢查運送方式實例.
		if ( strpos( $chosen_method, ':' ) !== false ) {
			$method_id       = explode( ':', $chosen_method );
			$shipping_method = $method_id[0];
		} else {
			$shipping_method = $chosen_method;
		}

		// 檢查是否需要儲存商店選擇.
		if ( in_array( $shipping_method, array( 'wc_shipping_ccat_711_cod', 'wc_shipping_ccat_711_prepaid' ), true ) ) {
			$store_id      = WC()->session->get( 'ccat_selected_store_id' );
			$store_name    = WC()->session->get( 'ccat_selected_store_name' );
			$store_address = WC()->session->get( 'ccat_selected_store_address' );

			if ( ! empty( $store_id ) && ! empty( $store_name ) ) {
				// 儲存商店信息到訂單元數據.
				update_post_meta( $order_id, '_ccat_store_id', $store_id );
				update_post_meta( $order_id, '_ccat_store_name', $store_name );
				update_post_meta( $order_id, '_ccat_store_address', $store_address );

				// 清除會話中的數據.
				WC()->session->__unset( 'ccat_selected_store_id' );
				WC()->session->__unset( 'ccat_selected_store_name' );
				WC()->session->__unset( 'ccat_selected_store_address' );
			}
		}
	}

	/**
	 * 驗證商店選擇
	 */
	public static function validate_store_selection() {
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		if ( empty( $chosen_shipping_methods ) ) {
			return;
		}

		$chosen_method   = $chosen_shipping_methods[0];
		$shipping_method = null;

		// 檢查運送方式實例.
		if ( strpos( $chosen_method, ':' ) !== false ) {
			$method_id       = explode( ':', $chosen_method );
			$shipping_method = $method_id[0];
		} else {
			$shipping_method = $chosen_method;
		}

		// 檢查是否需要驗證商店選擇.
		if ( in_array( $shipping_method, array( 'wc_shipping_ccat_711_cod', 'wc_shipping_ccat_711_prepaid' ), true ) ) {
			$store_id   = WC()->session->get( 'ccat_selected_store_id' );
			$store_name = WC()->session->get( 'ccat_selected_store_name' );

			if ( empty( $store_id ) || empty( $store_name ) ) {
				wc_add_notice( __( '請選擇取貨的超商門市', 'ccat-for-woocommerce' ), 'error' );
			}
		}
	}

	/**
	 * 在後台訂單顯示超商資訊
	 *
	 * @param WC_Order $order 訂單對象.
	 */
	public static function display_store_info_in_order( $order ) {
		// 獲取運送方式.
		$shipping_method  = '';
		$shipping_methods = $order->get_shipping_methods();
		foreach ( $shipping_methods as $shipping_method_obj ) {
			$shipping_method = $shipping_method_obj->get_method_id();
			break;
		}

		// 檢查是否為超商取貨.
		if ( in_array( $shipping_method, array( 'wc_shipping_ccat_711_cod', 'wc_shipping_ccat_711_prepaid' ), true ) ) {
			$store_id      = get_post_meta( $order->get_id(), '_ccat_store_id', true );
			$store_name    = get_post_meta( $order->get_id(), '_ccat_store_name', true );
			$store_address = get_post_meta( $order->get_id(), '_ccat_store_address', true );

			if ( ! empty( $store_id ) && ! empty( $store_name ) ) {
				echo '<div class="ccat-store-info">';
				echo '<h3>' . esc_html__( '超商取貨資訊', 'ccat-for-woocommerce' ) . '</h3>';
				echo '<p><strong>' . esc_html__( '超商門市：', 'ccat-for-woocommerce' ) . '</strong> ' . esc_html( $store_name ) . '</p>';
				echo '<p><strong>' . esc_html__( '門市代號：', 'ccat-for-woocommerce' ) . '</strong> ' . esc_html( $store_id ) . '</p>';
				if ( ! empty( $store_address ) ) {
					echo '<p><strong>' . esc_html__( '門市地址：', 'ccat-for-woocommerce' ) . '</strong> ' . esc_html( $store_address ) . '</p>';
				}
				echo '</div>';
			}
		}
	}

	/**
	 * 在前台訂單顯示超商資訊
	 *
	 * @param WC_Order $order 訂單對象.
	 */
	public static function display_store_info_in_frontend( $order ) {
		// 獲取運送方式
		$shipping_method  = '';
		$shipping_methods = $order->get_shipping_methods();
		foreach ( $shipping_methods as $shipping_method_obj ) {
			$shipping_method = $shipping_method_obj->get_method_id();
			break;
		}

		// 檢查是否為超商取貨.
		if ( in_array( $shipping_method, array( 'wc_shipping_ccat_711_cod', 'wc_shipping_ccat_711_prepaid' ), true ) ) {
			$store_id      = get_post_meta( $order->get_id(), '_ccat_store_id', true );
			$store_name    = get_post_meta( $order->get_id(), '_ccat_store_name', true );
			$store_address = get_post_meta( $order->get_id(), '_ccat_store_address', true );

			if ( ! empty( $store_id ) && ! empty( $store_name ) ) {
				echo '<section class="woocommerce-store-details">';
				echo '<h2 class="woocommerce-store-details__title">' . esc_html__( '超商取貨資訊', 'ccat-for-woocommerce' ) . '</h2>';
				echo '<table class="woocommerce-table woocommerce-table--store-details">';
				echo '<tr>';
				echo '<th>' . esc_html__( '超商門市', 'ccat-for-woocommerce' ) . '</th>';
				echo '<td>' . esc_html( $store_name ) . '</td>';
				echo '</tr>';
				echo '<tr>';
				echo '<th>' . esc_html__( '門市代號', 'ccat-for-woocommerce' ) . '</th>';
				echo '<td>' . esc_html( $store_id ) . '</td>';
				echo '</tr>';
				if ( ! empty( $store_address ) ) {
					echo '<tr>';
					echo '<th>' . esc_html__( '門市地址', 'ccat-for-woocommerce' ) . '</th>';
					echo '<td>' . esc_html( $store_address ) . '</td>';
					echo '</tr>';
				}
				echo '</table>';
				echo '</section>';
			}
		}
	}
}