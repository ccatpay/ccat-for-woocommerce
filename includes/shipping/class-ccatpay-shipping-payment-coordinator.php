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
class CCATPAY_Shipping_Payment_Coordinator {

	/**
	 * 黑貓專屬貨到付款閘道 ID 清單
	 *
	 * @var string[]
	 */
	const CCAT_COD_GATEWAYS = array(
		'ccat_cod_711',
		'ccat_cod_card',
		'ccat_cod_cash',
		'ccat_cod_mobile',
	);

	/**
	 * 黑貓宅配貨到付款閘道 ID 清單
	 *
	 * @var string[]
	 */
	const CCAT_HOME_COD_GATEWAYS = array(
		'ccat_cod_card',
		'ccat_cod_cash',
		'ccat_cod_mobile',
	);

	/**
	 * 初始化協調器
	 */
	public static function init() {
		// 添加過濾付款方式的鉤子.
		add_filter(
			'woocommerce_available_payment_gateways',
			array(
				__CLASS__,
				'filter_payment_gateways_by_shipping',
			)
		);
		add_filter(
			'woocommerce_rest_api_get_setting_payment_gateways',
			array(
				__CLASS__,
				'filter_api_payment_gateways',
			)
		);
		add_filter(
			'woocommerce_payment_gateways_available',
			array(
				__CLASS__,
				'filter_api_payment_gateways',
			)
		);

		// 自動補全顧客預設國家地區為 TW (使用標準優先權 10，避免覆蓋多國定位外掛).
		add_filter(
			'woocommerce_customer_default_location',
			array(
				__CLASS__,
				'ensure_default_customer_country',
			),
			10
		);

		// 在 WooCommerce Blocks 結帳與傳統結帳建立訂單時，統一儲存 7-11 門市資訊並驗證電話
		// 確保選擇「黑貓 7-11 先付款」搭配非黑貓金流（如銀行轉帳或其他第三方金流外掛）時也能正確寫入門市資料.
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			array(
				__CLASS__,
				'handle_blocks_checkout_order_shipping_data',
			),
			10,
			2
		);
		add_action(
			'woocommerce_checkout_create_order',
			array(
				__CLASS__,
				'handle_classic_checkout_order_shipping_data',
			),
			10,
			2
		);
	}

	/**
	 * 根據已選擇的物流方式過濾付款閘道
	 *
	 * @param array $available_gateways 可用的付款閘道.
	 *
	 * @return array 過濾後的付款閘道
	 */
	public static function filter_payment_gateways_by_shipping( array $available_gateways ): array {
		// 檢查 WC 是否已初始化.
		if ( ! function_exists( 'WC' ) || ! isset( WC()->session ) ) {
			return $available_gateways;
		}

		// 獲取已選擇的物流方式.
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( empty( $chosen_shipping_methods ) || ! is_array( $chosen_shipping_methods ) ) {
			return $available_gateways;
		}

		// 若顧客選取的不是黑貓物流（例如其他物流外掛或一般運送方式），僅隱藏黑貓專屬的貨到付款閘道，絕不干涉其他金流或內建 cod.
		if ( ! self::is_ccat_shipping_selected( $chosen_shipping_methods ) ) {
			foreach ( $available_gateways as $id => $gateway ) {
				if ( self::is_ccat_cod_gateway( (string) $id ) ) {
					unset( $available_gateways[ $id ] );
				}
			}
			return $available_gateways;
		}

		// 檢查選擇的黑貓物流方式是否為貨到付款類型.
		if ( self::is_cod_shipping_selected( $chosen_shipping_methods ) ) {
			if ( self::is_ccat_711_shipping_selected( $chosen_shipping_methods ) ) {
				// 如果選擇了黑貓 7-11 貨到付款物流，只允許使用 7-11 貨到付款支付方式.
				foreach ( $available_gateways as $id => $gateway ) {
					if ( 'ccat_cod_711' !== $id ) {
						unset( $available_gateways[ $id ] );
					}
				}
			} else {
				// 如果選擇了黑貓宅配貨到付款物流，只允許使用黑貓宅配貨到付款支付方式.
				foreach ( $available_gateways as $id => $gateway ) {
					if ( ! in_array( $id, self::CCAT_HOME_COD_GATEWAYS, true ) ) {
						unset( $available_gateways[ $id ] );
					}
				}
			}
		} else {
			// 如果選擇了黑貓「先付款」物流，排除貨到付款支付方式.
			foreach ( $available_gateways as $id => $gateway ) {
				if ( self::is_ccat_cod_gateway( (string) $id ) || 'cod' === $id ) {
					unset( $available_gateways[ $id ] );
				}
			}
		}

		return $available_gateways;
	}

	/**
	 * 過濾 API 響應中的支付方式
	 *
	 * @param array $payment_gateways 支付方式 ID 列表.
	 *
	 * @return array 過濾後的支付方式 ID 列表
	 */
	public static function filter_api_payment_gateways( array $payment_gateways ): array {
		// 檢查 WC 是否已初始化.
		if ( ! function_exists( 'WC' ) || ! isset( WC()->session ) ) {
			return $payment_gateways;
		}

		// 獲取已選擇的物流方式.
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( empty( $chosen_shipping_methods ) || ! is_array( $chosen_shipping_methods ) ) {
			return $payment_gateways;
		}

		// 若顧客選取的不是黑貓物流，僅移除黑貓專屬貨到付款閘道，保留其他外掛的所有支付方式.
		if ( ! self::is_ccat_shipping_selected( $chosen_shipping_methods ) ) {
			return array_filter(
				$payment_gateways,
				function ( $gateway ) {
					return ! self::is_ccat_cod_gateway( (string) $gateway );
				}
			);
		}

		// 檢查選擇的黑貓物流方式是否為貨到付款類型.
		if ( self::is_cod_shipping_selected( $chosen_shipping_methods ) ) {
			if ( self::is_ccat_711_shipping_selected( $chosen_shipping_methods ) ) {
				// 如果選擇了黑貓 7-11 貨到付款物流，只允許使用 7-11 貨到付款支付方式.
				return array_filter(
					$payment_gateways,
					function ( $gateway ) {
						return 'ccat_cod_711' === $gateway;
					}
				);
			} else {
				// 如果選擇了黑貓宅配貨到付款物流，允許使用黑貓宅配貨到付款支付方式.
				return array_filter(
					$payment_gateways,
					function ( $gateway ) {
						return in_array( $gateway, self::CCAT_HOME_COD_GATEWAYS, true );
					}
				);
			}
		} else {
			// 如果選擇了黑貓「先付款」物流，排除貨到付款支付方式.
			return array_filter(
				$payment_gateways,
				function ( $gateway ) {
					return ! self::is_ccat_cod_gateway( (string) $gateway ) && 'cod' !== $gateway;
				}
			);
		}
	}

	/**
	 * 處理 WooCommerce Blocks 結帳時的黑貓物流門市資料儲存與電話驗證
	 *
	 * @param WC_Order        $order   訂單物件.
	 * @param WP_REST_Request $request REST 請求物件.
	 * @throws Exception 當電話或 7-11 門市未填寫時拋出例外.
	 */
	public static function handle_blocks_checkout_order_shipping_data( WC_Order $order, $request ): void {
		if ( ! CCATPAY_Payments::is_shipping_enabled() ) {
			return;
		}

		$shipping_method_id = self::get_order_first_shipping_method_id( $order );
		if ( false === strpos( $shipping_method_id, 'ccatpay_shipping_' ) ) {
			return;
		}

		self::validate_order_taiwan_phone( $order );

		if ( false === strpos( $shipping_method_id, 'ccatpay_shipping_711' ) ) {
			return;
		}

		$store_info = array();
		if ( is_object( $request ) && is_callable( array( $request, 'get_param' ) ) ) {
			$extensions = $request->get_param( 'extensions' );
			if ( is_array( $extensions ) && ! empty( $extensions['ccat_711_store_info'] ) && is_array( $extensions['ccat_711_store_info'] ) ) {
				$store_info = $extensions['ccat_711_store_info'];
			}
		}

		if ( empty( $store_info ) ) {
			$raw_data  = file_get_contents( 'php://input' );
			$post_data = is_string( $raw_data ) ? json_decode( $raw_data, true ) : array();
			if ( is_array( $post_data ) && ! empty( $post_data['extensions']['ccat_711_store_info'] ) && is_array( $post_data['extensions']['ccat_711_store_info'] ) ) {
				$store_info = $post_data['extensions']['ccat_711_store_info'];
			}
		}

		if ( ! empty( $store_info['storeId'] ) ) {
			$order->update_meta_data( '_ccat_store_id', sanitize_text_field( $store_info['storeId'] ) );
			$order->update_meta_data( '_ccat_store_name', sanitize_text_field( $store_info['storeName'] ?? '' ) );
			$order->update_meta_data( '_ccat_store_address', sanitize_text_field( $store_info['storeAddress'] ?? '' ) );
			$order->update_meta_data( '_ccat_outside', sanitize_text_field( $store_info['outside'] ?? '0' ) );
			$order->update_meta_data( '_ccat_ship', sanitize_text_field( $store_info['ship'] ?? '1111111' ) );
		} elseif ( empty( $order->get_meta( '_ccat_store_id' ) ) ) {
			throw new Exception( esc_html__( '請選取超商門市', 'ccat-for-woocommerce' ) );
		}
	}

	/**
	 * 處理傳統 Shortcode 結帳時的黑貓物流門市資料儲存與電話驗證
	 *
	 * @param WC_Order $order 訂單物件.
	 * @param array    $data  結帳表單資料.
	 * @throws Exception 當電話格式不正確時拋出例外.
	 */
	public static function handle_classic_checkout_order_shipping_data( WC_Order $order, array $data ): void {
		if ( ! CCATPAY_Payments::is_shipping_enabled() ) {
			return;
		}

		$shipping_method_id = self::get_order_first_shipping_method_id( $order );
		if ( false === strpos( $shipping_method_id, 'ccatpay_shipping_' ) ) {
			return;
		}

		self::validate_order_taiwan_phone( $order );
	}

	/**
	 * 取得訂單第一個運送方式 ID
	 *
	 * @param WC_Order $order 訂單物件.
	 * @return string
	 */
	private static function get_order_first_shipping_method_id( WC_Order $order ): string {
		foreach ( $order->get_shipping_methods() as $shipping_method ) {
			return (string) $shipping_method->get_method_id();
		}
		return '';
	}

	/**
	 * 驗證黑貓物流訂單的電話號碼
	 *
	 * @param WC_Order $order 訂單物件.
	 * @throws Exception 當電話為空或格式無效時拋出例外.
	 */
	private static function validate_order_taiwan_phone( WC_Order $order ): void {
		$phone = $order->get_billing_phone();
		if ( empty( $phone ) ) {
			$phone = $order->get_shipping_phone();
		}

		if ( empty( $phone ) ) {
			throw new Exception( esc_html__( '使用黑貓物流時電話為必填', 'ccat-for-woocommerce' ) );
		}

		$digits = preg_replace( '/[^0-9]/', '', (string) $phone );
		if ( empty( $digits ) || ( ! preg_match( '/^09\d{8}$/', $digits ) && strlen( $digits ) < 7 ) ) {
			throw new Exception( esc_html__( '請輸入有效的台灣手機號碼(09開頭)或市話號碼', 'ccat-for-woocommerce' ) );
		}
	}

	/**
	 * 自動補全顧客預設國家地區為 TW
	 *
	 * @param array|string $location 位置資訊.
	 *
	 * @return array|string
	 */
	public static function ensure_default_customer_country( $location ) {
		if ( is_array( $location ) ) {
			if ( empty( $location['country'] ) ) {
				$location['country'] = 'TW';
			}
			return $location;
		}

		if ( empty( $location ) ) {
			return 'TW';
		}

		return $location;
	}

	/**
	 * 檢查閘道 ID 是否為黑貓專屬貨到付款閘道
	 *
	 * @param string $gateway_id 支付閘道 ID.
	 * @return bool
	 */
	private static function is_ccat_cod_gateway( string $gateway_id ): bool {
		return in_array( $gateway_id, self::CCAT_COD_GATEWAYS, true ) || 0 === strpos( $gateway_id, 'ccat_cod_' );
	}

	/**
	 * 檢查是否選擇了黑貓物流方式
	 *
	 * @param array $chosen_methods 已選擇的物流方式.
	 * @return bool
	 */
	private static function is_ccat_shipping_selected( array $chosen_methods ): bool {
		foreach ( $chosen_methods as $method ) {
			if ( is_string( $method ) && false !== strpos( $method, 'ccatpay_shipping_' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 檢查是否選擇了黑貓 7-11 物流方式
	 *
	 * @param array $chosen_methods 已選擇的物流方式.
	 * @return bool
	 */
	private static function is_ccat_711_shipping_selected( array $chosen_methods ): bool {
		foreach ( $chosen_methods as $method ) {
			if ( is_string( $method ) && false !== strpos( $method, 'ccatpay_shipping_711' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 檢查是否選擇了黑貓貨到付款物流方式
	 *
	 * @param array $chosen_methods 已選擇的物流方式.
	 *
	 * @return bool 是否選擇了黑貓貨到付款物流
	 */
	private static function is_cod_shipping_selected( array $chosen_methods ): bool {
		foreach ( $chosen_methods as $method ) {
			if (
				is_string( $method ) &&
				false !== strpos( $method, 'ccatpay_shipping_' ) &&
				false !== strpos( $method, '_cod' )
			) {
				return true;
			}
		}

		return false;
	}
}
