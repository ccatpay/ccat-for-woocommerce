<?php
/**
 * WooCommerce Analytics Orders 報表黑貓物流資訊匯出擴充
 *
 * @package WooCommerceCCatGateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CCATPAY_Analytics_Orders_Export
 *
 * 處理 WooCommerce 後台 > 分析 (Analytics) > 訂單 (Orders) 的 CSV 匯出，
 * 增加黑貓物流相關資訊（收件人姓名、Email、託運單號、配送方式、收件地址、預計出貨日、希望配達日等）。
 */
class CCATPAY_Analytics_Orders_Export {

	/**
	 * 單例實例
	 *
	 * @var CCATPAY_Analytics_Orders_Export|null
	 */
	private static ?CCATPAY_Analytics_Orders_Export $instance = null;

	/**
	 * 取得單例實例並初始化
	 *
	 * @return CCATPAY_Analytics_Orders_Export
	 */
	public static function init(): CCATPAY_Analytics_Orders_Export {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * 建構子：註冊各項 hooks
	 */
	public function __construct() {
		// 1. Analytics > Orders CSV 匯出欄位名稱定義.
		add_filter( 'woocommerce_report_orders_export_columns', array( $this, 'filter_export_columns' ), 20 );

		// 2. Analytics > Orders CSV 匯出單筆資料準備.
		add_filter( 'woocommerce_report_orders_prepare_export_item', array( $this, 'prepare_export_item' ), 20, 2 );

		// 3. Analytics 訂單查詢資料過濾 (供前端表格及 REST API 使用).
		add_filter( 'woocommerce_analytics_orders_select_query', array( $this, 'filter_analytics_orders_select_query' ), 20, 2 );

		// 4. REST API 訂單報表單筆項目回應過濾.
		add_filter( 'woocommerce_rest_prepare_report_orders', array( $this, 'filter_rest_prepare_report_orders' ), 20, 3 );

		// 5. 後台資源加載 (Analytics 頁面加載前端腳本，支援前端即時單頁 CSV 下載).
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * 取得黑貓物流擴充欄位定義清單 (欄位鍵值 => 欄位標題)
	 *
	 * @return array<string, string>
	 */
	public function get_export_column_definitions(): array {
		$columns = array(
			'ccat_recipient_name'  => __( '收件人姓名', 'ccat-for-woocommerce' ),
			'ccat_recipient_phone' => __( '收件人電話', 'ccat-for-woocommerce' ),
			'ccat_customer_email'  => __( 'Email', 'ccat-for-woocommerce' ),
			'ccat_shipping_method' => __( '配送方式', 'ccat-for-woocommerce' ),
			'ccat_thermosphere'    => __( '溫層', 'ccat-for-woocommerce' ),
			'ccat_shipping_address' => __( '收件地址', 'ccat-for-woocommerce' ),
			'ccat_store_info'      => __( '7-11 門市資訊', 'ccat-for-woocommerce' ),
			'ccat_obt_number'      => __( '託運單號', 'ccat-for-woocommerce' ),
			'ccat_shipment_date'   => __( '預計出貨日', 'ccat-for-woocommerce' ),
			'ccat_delivery_date'   => __( '希望配達日', 'ccat-for-woocommerce' ),
			'ccat_delivery_time'   => __( '希望配達時段', 'ccat-for-woocommerce' ),
			'ccat_shipping_status' => __( '黑貓物流狀態', 'ccat-for-woocommerce' ),
		);

		return apply_filters( 'ccatpay_analytics_orders_export_columns', $columns );
	}

	/**
	 * 過濾 Analytics Orders CSV 匯出欄位標題
	 *
	 * @param array $export_columns 原始欄位陣列.
	 * @return array
	 */
	public function filter_export_columns( array $export_columns ): array {
		$custom_columns = $this->get_export_column_definitions();

		// 將黑貓物流自訂欄位附加於 CSV 尾端，避免干擾原有欄位順序.
		foreach ( $custom_columns as $key => $label ) {
			if ( ! isset( $export_columns[ $key ] ) ) {
				$export_columns[ $key ] = $label;
			}
		}

		return $export_columns;
	}

	/**
	 * 準備 CSV 匯出中的單筆訂單黑貓物流資料
	 *
	 * @param array        $export_item 匯出資料陣列 (key => value).
	 * @param array|object $item        原始報表項目資料.
	 * @return array
	 */
	public function prepare_export_item( array $export_item, $item ): array {
		$order_id = $this->extract_order_id_from_item( $item );

		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof WC_Order ) {
				$shipping_data = $this->get_order_ccat_shipping_data( $order );
				foreach ( $shipping_data as $key => $value ) {
					$export_item[ $key ] = $value;
				}
				return $export_item;
			}
		}

		// 若無法讀取訂單，填入預設空白符號.
		$custom_columns = $this->get_export_column_definitions();
		foreach ( $custom_columns as $key => $label ) {
			if ( ! isset( $export_item[ $key ] ) ) {
				$export_item[ $key ] = '-';
			}
		}

		return $export_item;
	}

	/**
	 * 擴充 Analytics 訂單查詢結果 (供 REST API 及前端 Table 取得黑貓物流資料)
	 *
	 * @param object|array $results    查詢結果物件或陣列.
	 * @param array        $query_args 查詢參數.
	 * @return object|array
	 */
	public function filter_analytics_orders_select_query( $results, $query_args ) {
		if ( is_object( $results ) && isset( $results->data ) && is_array( $results->data ) ) {
			foreach ( $results->data as $index => $row ) {
				$order_id = isset( $row['order_id'] ) ? absint( $row['order_id'] ) : ( isset( $row['id'] ) ? absint( $row['id'] ) : 0 );
				if ( $order_id ) {
					$order = wc_get_order( $order_id );
					if ( $order instanceof WC_Order ) {
						$shipping_data = $this->get_order_ccat_shipping_data( $order );
						foreach ( $shipping_data as $key => $val ) {
							$results->data[ $index ][ $key ] = $val;
						}
					}
				}
			}
		} elseif ( is_array( $results ) ) {
			foreach ( $results as $index => $row ) {
				$order_id = isset( $row['order_id'] ) ? absint( $row['order_id'] ) : ( isset( $row['id'] ) ? absint( $row['id'] ) : 0 );
				if ( $order_id ) {
					$order = wc_get_order( $order_id );
					if ( $order instanceof WC_Order ) {
						$shipping_data = $this->get_order_ccat_shipping_data( $order );
						foreach ( $shipping_data as $key => $val ) {
							$results[ $index ][ $key ] = $val;
						}
					}
				}
			}
		}

		return $results;
	}

	/**
	 * 過濾 REST API 單筆項目回應
	 *
	 * @param WP_REST_Response $response 原始回應物件.
	 * @param array|object     $item     報表項目資料.
	 * @param WP_REST_Request  $request  REST 請求物件.
	 * @return WP_REST_Response
	 */
	public function filter_rest_prepare_report_orders( $response, $item, $request ) {
		if ( ! ( $response instanceof WP_REST_Response ) ) {
			return $response;
		}

		$data     = $response->get_data();
		$order_id = $this->extract_order_id_from_item( $item );

		if ( ! $order_id && isset( $data['order_id'] ) ) {
			$order_id = absint( $data['order_id'] );
		}

		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof WC_Order ) {
				$shipping_data = $this->get_order_ccat_shipping_data( $order );
				foreach ( $shipping_data as $key => $val ) {
					$data[ $key ] = $val;
				}
				$response->set_data( $data );
			}
		}

		return $response;
	}

	/**
	 * 後台加載腳本 (於 Analytics 頁面載入前端 table filter，支援即時單頁 CSV 下載)
	 *
	 * @param string $hook 目前後台頁面 hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || 'woocommerce_page_wc-admin' !== $screen->id ) {
			return;
		}

		$custom_columns = array();
		foreach ( $this->get_export_column_definitions() as $key => $label ) {
			$custom_columns[] = array(
				'key'   => $key,
				'label' => $label,
			);
		}

		wp_register_script(
			'ccatpay-analytics-orders',
			CCATPAY_Payments::plugin_url() . '/resources/js/admin/ccatpay-analytics-orders.js',
			array( 'wp-hooks', 'wp-i18n' ),
			CCATPAYMENTS_VERSION,
			true
		);

		wp_localize_script(
			'ccatpay-analytics-orders',
			'ccatpayAnalyticsData',
			array(
				'columns' => $custom_columns,
			)
		);

		wp_enqueue_script( 'ccatpay-analytics-orders' );
	}

	/**
	 * 獲取單筆訂單的完整黑貓物流資料陣列
	 *
	 * @param WC_Order $order 訂單物件.
	 * @return array<string, string>
	 */
	public function get_order_ccat_shipping_data( WC_Order $order ): array {
		$shipping_info = $this->get_shipping_method_info( $order );
		$is_711        = $shipping_info['is_711'];
		$is_ccat       = $shipping_info['is_ccat'];

		$recipient_name    = $this->get_recipient_name( $order );
		$recipient_phone   = $this->get_recipient_phone( $order );
		$customer_email    = $this->get_customer_email( $order );
		$recipient_address = $this->get_recipient_address( $order, $is_711 );
		$store_info        = $this->get_store_info( $order, $is_711 );
		$obt_number        = $this->get_obt_number( $order );

		$dates_and_time = $this->get_dates_and_time_info( $order, $is_711, $obt_number, $is_ccat );

		return array(
			'ccat_recipient_name'   => $recipient_name,
			'ccat_recipient_phone'  => $recipient_phone,
			'ccat_customer_email'   => $customer_email,
			'ccat_shipping_method'  => $shipping_info['method_title'],
			'ccat_thermosphere'     => $shipping_info['thermosphere'],
			'ccat_shipping_address' => $recipient_address,
			'ccat_store_info'       => $store_info,
			'ccat_obt_number'       => $obt_number,
			'ccat_shipment_date'    => $dates_and_time['shipment_date'],
			'ccat_delivery_date'    => $dates_and_time['delivery_date'],
			'ccat_delivery_time'    => $dates_and_time['delivery_time'],
			'ccat_shipping_status'  => $dates_and_time['shipping_status'],
		);
	}

	/**
	 * 取得收件人姓名
	 *
	 * @param WC_Order $order 訂單物件.
	 * @return string
	 */
	private function get_recipient_name( WC_Order $order ): string {
		$last_name  = trim( (string) $order->get_shipping_last_name() );
		$first_name = trim( (string) $order->get_shipping_first_name() );

		if ( ! empty( $last_name ) || ! empty( $first_name ) ) {
			$name = $last_name . $first_name;
			$formatted = trim( (string) $order->get_formatted_shipping_full_name() );
			return ! empty( $formatted ) ? $formatted : $name;
		}

		$billing_last  = trim( (string) $order->get_billing_last_name() );
		$billing_first = trim( (string) $order->get_billing_first_name() );
		if ( ! empty( $billing_last ) || ! empty( $billing_first ) ) {
			$formatted = trim( (string) $order->get_formatted_billing_full_name() );
			return ! empty( $formatted ) ? $formatted : ( $billing_last . $billing_first );
		}

		return '-';
	}

	/**
	 * 取得收件人電話 (若無 shipping 則取 billing)
	 *
	 * @param WC_Order $order 訂單物件.
	 * @return string
	 */
	private function get_recipient_phone( WC_Order $order ): string {
		$phone = trim( (string) $order->get_shipping_phone() );
		if ( empty( $phone ) ) {
			$phone = trim( (string) $order->get_billing_phone() );
		}
		return ! empty( $phone ) ? $phone : '-';
	}

	/**
	 * 取得顧客 Email
	 *
	 * @param WC_Order $order 訂單物件.
	 * @return string
	 */
	private function get_customer_email( WC_Order $order ): string {
		$email = trim( (string) $order->get_billing_email() );
		return ! empty( $email ) ? $email : '-';
	}

	/**
	 * 取得收件地址 (超商顯示門市地址，宅配顯示格式化地址)
	 *
	 * @param WC_Order $order  訂單物件.
	 * @param bool     $is_711 是否為 7-11 超取.
	 * @return string
	 */
	private function get_recipient_address( WC_Order $order, bool $is_711 ): string {
		if ( $is_711 ) {
			$store_address = trim( (string) $order->get_meta( CCATPAY_Gateway_Abstract::META_STORE_ADDRESS ) );
			if ( ! empty( $store_address ) ) {
				return $store_address;
			}
		}

		$postcode  = trim( (string) ( $order->get_shipping_postcode() ?: $order->get_billing_postcode() ) );
		$state     = trim( (string) ( $order->get_shipping_state() ?: $order->get_billing_state() ) );
		$city      = trim( (string) ( $order->get_shipping_city() ?: $order->get_billing_city() ) );
		$address_1 = trim( (string) ( $order->get_shipping_address_1() ?: $order->get_billing_address_1() ) );
		$address_2 = trim( (string) ( $order->get_shipping_address_2() ?: $order->get_billing_address_2() ) );

		// 處理台灣縣市代碼轉換 (例如 TPE => 台北市).
		if ( function_exists( 'WC' ) && WC()->countries ) {
			$states = WC()->countries->get_states( 'TW' );
			if ( is_array( $states ) && isset( $states[ $state ] ) ) {
				$state = $states[ $state ];
			}
		}

		$full_address = trim( $postcode . ' ' . $state . $city . $address_1 . ' ' . $address_2 );
		$full_address = preg_replace( '/\s+/', ' ', $full_address );

		return ! empty( $full_address ) ? $full_address : '-';
	}

	/**
	 * 取得 7-11 門市資訊
	 *
	 * @param WC_Order $order  訂單物件.
	 * @param bool     $is_711 是否為 7-11 超取.
	 * @return string
	 */
	private function get_store_info( WC_Order $order, bool $is_711 ): string {
		if ( ! $is_711 ) {
			return '-';
		}

		$store_id   = trim( (string) $order->get_meta( CCATPAY_Gateway_Abstract::META_STORE_ID ) );
		$store_name = trim( (string) $order->get_meta( CCATPAY_Gateway_Abstract::META_STORE_NAME ) );

		if ( ! empty( $store_id ) && ! empty( $store_name ) ) {
			return sprintf( '[%s] %s', $store_id, $store_name );
		} elseif ( ! empty( $store_name ) ) {
			return $store_name;
		} elseif ( ! empty( $store_id ) ) {
			return $store_id;
		}

		return '-';
	}

	/**
	 * 取得黑貓託運單號
	 *
	 * @param WC_Order $order 訂單物件.
	 * @return string
	 */
	private function get_obt_number( WC_Order $order ): string {
		$obt_number = trim( (string) $order->get_meta( CCATPAY_Shipping_Display::META_OBT_NUMBER ) );
		return ! empty( $obt_number ) ? $obt_number : '-';
	}

	/**
	 * 取得配送方式及溫層資訊
	 *
	 * @param WC_Order $order 訂單物件.
	 * @return array{method_id: string, method_title: string, is_ccat: bool, is_711: bool, thermosphere: string}
	 */
	private function get_shipping_method_info( WC_Order $order ): array {
		$shipping_method_id = '';
		$shipping_methods   = $order->get_shipping_methods();
		foreach ( $shipping_methods as $shipping_method ) {
			$shipping_method_id = (string) $shipping_method->get_method_id();
			break;
		}

		$method_title = trim( (string) $order->get_shipping_method() );
		if ( empty( $method_title ) ) {
			$method_title = '-';
		}

		$is_ccat = ( false !== strpos( $shipping_method_id, 'ccatpay_shipping' ) );
		$is_711  = ( false !== strpos( $shipping_method_id, '711' ) );

		$thermosphere = '-';
		if ( $is_ccat ) {
			if ( false !== strpos( $shipping_method_id, 'refrigerated' ) ) {
				$thermosphere = __( '冷藏', 'ccat-for-woocommerce' );
			} elseif ( false !== strpos( $shipping_method_id, 'frozen' ) ) {
				$thermosphere = __( '冷凍', 'ccat-for-woocommerce' );
			} else {
				$thermosphere = __( '常溫', 'ccat-for-woocommerce' );
			}
		}

		return array(
			'method_id'    => $shipping_method_id,
			'method_title' => $method_title,
			'is_ccat'      => $is_ccat,
			'is_711'       => $is_711,
			'thermosphere' => $thermosphere,
		);
	}

	/**
	 * 取得日期與時段資訊 (預計出貨日、希望配達日、希望配達時段、託運單狀態)
	 *
	 * @param WC_Order $order      訂單物件.
	 * @param bool     $is_711     是否為 7-11 超取.
	 * @param string   $obt_number 託運單號.
	 * @param bool     $is_ccat    是否為黑貓物流.
	 * @return array{shipment_date: string, delivery_date: string, delivery_time: string, shipping_status: string}
	 */
	private function get_dates_and_time_info( WC_Order $order, bool $is_711, string $obt_number, bool $is_ccat ): array {
		$shipment_date = (string) $order->get_meta( CCATPAY_Shipping_Display::META_SHIPMENT_DATE );
		$delivery_date = (string) $order->get_meta( CCATPAY_Shipping_Display::META_DELIVERY_DATE );
		$delivery_time = (string) $order->get_meta( CCATPAY_Shipping_Display::META_DELIVERY_TIME );

		$time_slot_map = array(
			'01' => __( '13時前', 'ccat-for-woocommerce' ),
			'02' => __( '14-18時', 'ccat-for-woocommerce' ),
			'04' => __( '不指定', 'ccat-for-woocommerce' ),
		);
		$delivery_time_text = isset( $time_slot_map[ $delivery_time ] ) ? $time_slot_map[ $delivery_time ] : ( ! empty( $delivery_time ) ? $delivery_time : '-' );

		$has_printed = ( 'yes' === $order->get_meta( CCATPAY_Shipping_Display::META_PRINTED ) ) || ( '-' !== $obt_number && ! empty( $obt_number ) );

		// 如果尚未記錄出貨日但已產生託運單，以訂單更新/建立時間推算.
		if ( empty( $shipment_date ) && $has_printed ) {
			$date_obj = $order->get_date_modified() ?: $order->get_date_created();
			if ( $date_obj ) {
				$shipment_date = $date_obj->date( 'Y-m-d' );
			}
		}

		// 如果尚未記錄配達日且為黑貓宅配已建立託運單，依黑貓規則 (隔天，若遇週日順延至週一) 推算.
		if ( empty( $delivery_date ) && ! $is_711 && $has_printed && ! empty( $shipment_date ) && '-' !== $shipment_date ) {
			try {
				$ship_dt = new DateTime( $shipment_date, new DateTimeZone( CCATPAY_Shipping_Display::TAIPEI_TIMEZONE ) );
				$ship_dt->add( new DateInterval( 'P1D' ) );
				if ( '0' === $ship_dt->format( 'w' ) ) {
					$ship_dt->add( new DateInterval( 'P1D' ) );
				}
				$delivery_date = $ship_dt->format( 'Y-m-d' );
			} catch ( Exception $e ) {
				$delivery_date = '-';
			}
		}

		// 7-11 超商取貨無指定希望配達日與時段.
		if ( $is_711 ) {
			$delivery_date      = '-';
			$delivery_time_text = '-';
		}

		if ( empty( $shipment_date ) ) {
			$shipment_date = '-';
		}
		if ( empty( $delivery_date ) ) {
			$delivery_date = '-';
		}

		$shipping_status = '-';
		if ( $is_ccat ) {
			$shipping_status = $has_printed ? __( '已建立託運單', 'ccat-for-woocommerce' ) : __( '尚未建立', 'ccat-for-woocommerce' );
		}

		return array(
			'shipment_date'   => $shipment_date,
			'delivery_date'   => $delivery_date,
			'delivery_time'   => $delivery_time_text,
			'shipping_status' => $shipping_status,
		);
	}

	/**
	 * 從報表項目提取訂單 ID
	 *
	 * @param mixed $item 報表項目 (陣列或物件).
	 * @return int
	 */
	private function extract_order_id_from_item( $item ): int {
		if ( is_array( $item ) ) {
			if ( isset( $item['order_id'] ) ) {
				return absint( $item['order_id'] );
			}
			if ( isset( $item['id'] ) ) {
				return absint( $item['id'] );
			}
		} elseif ( is_object( $item ) ) {
			if ( isset( $item->order_id ) ) {
				return absint( $item->order_id );
			}
			if ( isset( $item->id ) ) {
				return absint( $item->id );
			}
		}
		return 0;
	}
}
