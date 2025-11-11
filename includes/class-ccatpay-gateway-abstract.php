<?php
/**
 * CCATPAY_Gateway_Abstract class
 *
 * @author   sakilu <brian@sakilu.com>
 * @package  WooCommerce CCat Payments Gateway
 * @since    1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for CCat payment gateways, extending WooCommerce payment gateway functionality.
 */
abstract class CCATPAY_Gateway_Abstract extends WC_Payment_Gateway {
	const CMD_COCS_ORDER_APPEND = 'CocsOrderAppend';  // phpcs:ignore Generic.Formatting.MultipleStatementAlignment
	const ROUTE_NAMESPACE = 'ccat/v1'; // phpcs:ignore Generic.Formatting.MultipleStatementAlignment
	const META_APN = '_received_apn_notifications'; // phpcs:ignore Generic.Formatting.MultipleStatementAlignment
	const META_RESPONSE_DATA = '_received_data'; // phpcs:ignore Generic.Formatting.MultipleStatementAlignment
	const CODE_OK = 'OK'; // phpcs:ignore Generic.Formatting.MultipleStatementAlignment
	const ORDER_NO = '_final_order_no'; // phpcs:ignore Generic.Formatting.MultipleStatementAlignment
	const META_PROCESSING             = '_meta_processing';
	const META_INVOICE_DISCOUNT       = '_invoice_discount';
	const META_INVOICE_APN            = '_invoice_apn';
	const META_INVOICE_NO             = '_invoice_no';
	const META_INVOICE_DATE           = '_invoice_date';
	const META_INVOICE_RANDOM         = '_invoice_random';
	const META_INVOICE_ORDER_API_DATA = '_invoice_order_api_data';

	// 711超商門市資訊相關常數.
	const META_STORE_ID      = '_ccat_store_id';       // 門市代號.
	const META_STORE_NAME    = '_ccat_store_name';     // 門市名稱.
	const META_STORE_ADDRESS = '_ccat_store_address';  // 門市地址.
	const META_OUTSIDE       = '_ccat_outside';        // 外島註記.
	const META_SHIP          = '_ccat_ship';           // 運送資訊.


	/**
	 * Title
	 *
	 * @var string Title
	 */
	public $title;

	/**
	 * Description
	 *
	 * @var string Description
	 */
	public $description;

	/**
	 * Supports
	 *
	 * @var array $supports
	 */
	public $supports = array(
		'products',
	);


	/**
	 * Unique id for the gateway.
	 *
	 * @var string
	 */
	public $id = '';

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		$this->has_fields         = false;
		$this->method_title       = $this->title;
		$this->method_description = $this->description;

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title                    = $this->get_option( 'title' );
		$this->description              = $this->get_option( 'description' );
		$this->instructions             = $this->get_option( 'instructions', $this->description );
		$this->hide_for_non_admin_users = $this->get_option( 'hide_for_non_admin_users' );

		// Actions.
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);
		add_action(
			'woocommerce_scheduled_subscription_payment_ccat',
			array(
				$this,
				'process_subscription_payment',
			),
			10,
			2
		);
		add_action(
			'wc_pre_orders_process_pre_order_completion_payment_' . $this->id,
			array(
				$this,
				'process_pre_order_release_payment',
			),
		);

		add_action( 'rest_api_init', array( $this, 'register_apn_endpoint' ) );
	}

	/**
	 * Registers the APN endpoint for payment callback functionality.
	 *
	 * This method registers a custom REST API endpoint for handling payment callback notifications
	 * from external services. The endpoint is configured to accept POST requests and is linked
	 * to a specific callback handler for processing incoming data.
	 *
	 * @return void
	 */
	public function register_apn_endpoint(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/' . $this->id . '/payment-callback',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_payment_callback' ),
				'permission_callback' => '__return_true',
				'show_in_index'       => false,
			)
		);
	}

	/**
	 * Generates the APN (Apple Push Notification) callback URL for the payment gateway.
	 *
	 * @return string The full REST URL for the payment callback.
	 */
	public function get_apn_url(): string {
		return rest_url( self::ROUTE_NAMESPACE . '/' . $this->id . '/payment-callback' );
	}

	/**
	 * Checks whether the payment gateway is in test mode.
	 *
	 * @return bool True if in test mode, False otherwise.
	 */
	public function is_test_mode(): bool {
		return get_option( CCATPAYMENTS_PREFIX . '_test_mode', 'no' ) === 'yes';
	}

	/**
	 * Get the payment API base URL based on the test mode setting.
	 *
	 * @return string The base URL for API calls.
	 */
	public function get_base_url(): string {
		return $this->is_test_mode()
			? 'https://test.4128888card.com.tw/app/'
			: 'https://cocs.4128888card.com.tw/';
	}

	/**
	 * 取得檢核碼
	 * 根據測試模式狀態返回對應的檢核碼
	 *
	 * @return string 檢核碼
	 */
	protected function get_chk_code(): string {
		$is_test_mode = 'yes' === get_option( CCATPAYMENTS_PREFIX . '_test_mode' );
		if ( $is_test_mode ) {
			return get_option( CCATPAYMENTS_PREFIX . '_test_chk_code', '' );
		}

		return get_option( CCATPAYMENTS_PREFIX . '_chk_code', '' );
	}


	/**
	 * Retrieves the API access token for payment requests.
	 *
	 * This method checks for a cached API token; if unavailable, it requests a new token
	 * from the payment gateway's API based on stored credentials. The token is cached for reuse.
	 *
	 * @return string|null Returns the access token in an associative array if successful, or null in case of failure.
	 */
	public function get_payment_api_token(): ?string {

		$cached_token = get_transient( CCATPAYMENTS_PREFIX. 'api_access_token' );

		if ( $cached_token ) {
			return $cached_token;
		}

		$api_endpoint = $this->get_base_url() . 'token';

		$body = array(
			'grant_type' => 'password',
			'username'   => $this->get_account(),
			'password'   => $this->get_password(),
		);

		$headers = array(
			'Content-Type' => 'application/x-www-form-urlencoded',
		);

		$response = wp_remote_post(
			$api_endpoint,
			array(
				'method'  => 'POST',
				'timeout' => 45,
				'headers' => $headers,
				'body'    => http_build_query( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$logger = wc_get_logger();
			$logger->error(
				'API Token request failed: ' . $response->get_error_message(),
				array( 'source' => 'api-token' )
			);

			return null;
		}

		$response_body = wp_remote_retrieve_body( $response );

		if ( empty( $response_body ) ) {
			$logger = wc_get_logger();
			$logger->error(
				'API Token response body is empty.',
				array( 'source' => 'api-token' )
			);

			return null;
		}

		$decoded_response = json_decode( $response_body, true );

		if ( isset( $decoded_response['error'] ) ) {
			$logger = wc_get_logger();
			$logger->error(
				'API Token error: ' . $decoded_response['error'],
				array( 'source' => 'api-token' )
			);

			return null;
		}

		$access_token = $decoded_response['access_token'];
		$expires_at   = isset( $decoded_response['.expires'] ) ? strtotime( $decoded_response['.expires'] ) : 0;

		$current_time = time();
		$expires_in   = $expires_at ? ( $expires_at - $current_time ) : 0;

		if ( $access_token && $expires_in > 0 ) {
			set_transient( CCATPAYMENTS_PREFIX. 'api_access_token', $access_token, $expires_in - 60 );
		}

		return $access_token;
	}

	/**
	 * Retrieves the API account identifier from the plugin options.
	 *
	 * @return string The API account ID configured in the WooCommerce settings.
	 */
	public function get_account(): string {
		return $this->is_test_mode()
			? get_option( CCATPAYMENTS_PREFIX . '_test_merchant_id', '' )
			: get_option( CCATPAYMENTS_PREFIX . '_merchant_id', '' );
	}

	/**
	 * Retrieves the API password from the gateway settings.
	 *
	 * @return string The API password configured in the WooCommerce settings.
	 */
	public function get_password(): string {
		return $this->is_test_mode()
			? get_option( CCATPAYMENTS_PREFIX . '_test_api_key', '' )
			: get_option( CCATPAYMENTS_PREFIX . '_api_key', '' );
	}

	/**
	 * 檢查電話號碼是否為有效的台灣電話格式
	 *
	 * @param string $phone 電話號碼.
	 *
	 * @return bool 是否為有效的台灣電話格式
	 */
	protected function is_valid_taiwan_phone( string $phone ): bool {
		// 移除所有非數字字符（如空格、破折號等）.
		$phone = preg_replace( '/[^0-9]/', '', $phone );

		// 檢查是否為空.
		if ( empty( $phone ) ) {
			return false;
		}

		// 台灣手機格式驗證：必須是 09 開頭的 10 位數字.
		if ( preg_match( '/^09\d{8}$/', $phone ) ) {
			return true;
		}

		if ( strlen( $phone ) >= 7 ) {
			return true;
		}

		return false;
	}


	/**
	 * 加入並驗證發票資料
	 *
	 * @param array    $order_data 訂單資料.
	 * @param WC_Order $wc_order 訂單.
	 *
	 * @throws Exception Exception.
	 */
	protected function add_shipping_data( array $order_data, WC_Order $wc_order ) {
		// 取得 JSON 資料.
		if ( ! CCATPAY_Payments::is_shipping_enabled() ) {
			return null;
		}

		$shipping_method_id = '';
		$shipping_methods   = $wc_order->get_shipping_methods();
		foreach ( $shipping_methods as $shipping_method ) {
			$shipping_method_id = $shipping_method->get_method_id();
			break; // 只取第一個運送方法.
		}
		if ( false !== stripos( $shipping_method_id, 'ccat' ) ) {
			$phone = $wc_order->get_billing_phone();

			// 沒有billing_phone就改用shipping_phone
			if ( empty( $phone ) ) {
				$phone = $wc_order->get_shipping_phone();
			}

			// 檢查電話是否為空.
			if ( empty( $phone ) ) {
				throw new Exception( esc_html__( '使用黑貓物流時電話為必填', 'ccat-for-woocommerce') );
			}

			// 檢查電話格式是否有效.
			if ( ! $this->is_valid_taiwan_phone( $phone ) ) {
				throw new Exception( esc_html__( '請輸入有效的台灣手機號碼(09開頭)或市話號碼', 'ccat-for-woocommerce') );
			}
		}

		if ( false === stripos( $shipping_method_id, '711' ) ) {
			return null;
		}
		$raw_data   = file_get_contents( 'php://input' );
		$post_data  = json_decode( $raw_data, true );
		$store_info = $post_data['extensions']['ccat_711_store_info'] ?? array();

		if ( empty( $store_info ) || empty( $store_info['storeId'] ) ) {
			throw new Exception( esc_html__( '請選取超商門市', 'ccat-for-woocommerce') );
		}
		// 將超商資訊儲存到訂單中.
		$wc_order->update_meta_data( self::META_STORE_ID, sanitize_text_field( $store_info['storeId'] ) );
		$wc_order->update_meta_data( self::META_STORE_NAME, sanitize_text_field( $store_info['storeName'] ) );
		$wc_order->update_meta_data( self::META_STORE_ADDRESS, sanitize_text_field( $store_info['storeAddress'] ) );
		$wc_order->update_meta_data( self::META_OUTSIDE, sanitize_text_field( $store_info['outside'] ) );
		$wc_order->update_meta_data( self::META_SHIP, sanitize_text_field( $store_info['ship'] ) );
		$wc_order->save();
		return null;
	}

	/**
	 * 加入並驗證發票資料
	 *
	 * @param array    $order_data 訂單資料.
	 * @param WC_Order $wc_order 訂單.
	 *
	 * @return array 處理後的訂單資料.
	 * @throws Exception Exception.
	 */
	protected function add_invoice_data( array $order_data, WC_Order $wc_order ): array {
		// 取得 JSON 資料.
		$raw_data         = file_get_contents( 'php://input' );
		$post_data        = json_decode( $raw_data, true );
		$invoice_data_raw = $post_data['extensions']['ccat_invoice_data'] ?? array();
		if ( ! empty( $invoice_data_raw ) ) {
			$billing_data = $post_data['billing_address'] ?? array();

			// 初始化發票資料.
			$invoice_data = array(
				'b2c'            => '1', // 預設開立電子發票.
				'print_invoice'  => '0', // 預設不列印.
				'donate_invoice' => '0', // 預設不捐贈.
				'product_name'   => wp_strip_all_tags( $this->get_order_items_name( $wc_order ) ),
				'payer_name'     => sanitize_text_field( $billing_data['first_name'] . ' ' . $billing_data['last_name'] ),
				'payer_postcode' => sanitize_text_field( $billing_data['postcode'] ),
				'payer_address'  => sanitize_text_field( $billing_data['address_1'] . ' ' . $billing_data['address_2'] ),
				'payer_mobile'   => sanitize_text_field( $billing_data['phone'] ),
				'payer_email'    => sanitize_email( $billing_data['email'] ),
			);

			// 基本驗證：發票類型.
			$vehicle_type = sanitize_text_field( $invoice_data_raw['vehicle_type'] ?? '' );
			if ( empty( $vehicle_type ) ) {
				throw new Exception( esc_html( __( '請選擇發票類型', 'ccat-for-woocommerce') ) );
			}

			// 依發票類型處理.
			switch ( $vehicle_type ) {
				case '1': // 個人雲端發票.
					$cloud_type = sanitize_text_field( $invoice_data_raw['cloud_invoice_type'] ?? '' );
					if ( empty( $cloud_type ) ) {
						throw new Exception( esc_html( __( '請選擇載具類型', 'ccat-for-woocommerce') ) );
					}

					// 轉換載具類型為 API 格式.
					switch ( $cloud_type ) {
						case 'member':
							$invoice_data['vehicle_type'] = '1';
							break;
						case 'mobile':
							$invoice_data['vehicle_type'] = '2';
							$barcode                      = sanitize_text_field( $invoice_data_raw['vehicle_barcode'] ?? '' );
							if ( ! preg_match( '/^\/[0-9A-Z.+\-]{7}$/', $barcode ) ) {
								throw new Exception( esc_html( __( '手機條碼格式不正確，應為 "/" 開頭加上7碼英數字', 'ccat-for-woocommerce') ) );
							}
							$invoice_data['vehicle_barcode'] = $barcode;
							break;
						case 'certificate':
							$invoice_data['vehicle_type'] = '3';
							$cert_number                  = sanitize_text_field( $invoice_data_raw['certificate_number'] ?? '' );
							if ( ! preg_match( '/^[A-Z]{2}[0-9]{14}$/', $cert_number ) ) {
								throw new Exception( esc_html( __( '自然人憑證格式不正確', 'ccat-for-woocommerce') ) );
							}
							$invoice_data['vehicle_barcode'] = $cert_number;
							break;
					}
					break;

				case '2': // 發票捐贈.
					$invoice_data['donate_invoice'] = '1';
					$love_code                      = sanitize_text_field( $invoice_data_raw['love_code'] ?? '919' ); // 預設創世基金會.
					if ( ! empty( $love_code ) && ! preg_match( '/^[0-9]{3,7}$/', $love_code ) ) {
						throw new Exception( esc_html( __( '請輸入有效的愛心碼', 'ccat-for-woocommerce') ) );
					}
					$invoice_data['love_code'] = $love_code;
					break;

				case '3': // 公司發票.
					$bill_no = sanitize_text_field( $invoice_data_raw['buyer_bill_no'] ?? '' );
					if ( ! $this->validate_tax_number( $bill_no ) ) {
						throw new Exception( esc_html( __( '統一編號格式不正確', 'ccat-for-woocommerce') ) );
					}

					$title = sanitize_text_field( $invoice_data_raw['buyer_invoice_title'] ?? '' );
					if ( empty( $title ) || mb_strlen( $title ) < 2 ) {
						throw new Exception( esc_html( __( '請輸入發票抬頭', 'ccat-for-woocommerce') ) );
					}

					$invoice_data['buyer_bill_no']       = $bill_no;
					$invoice_data['buyer_invoice_title'] = $title;
					break;

				default:
					throw new Exception( esc_html( __( '無效的發票類型', 'ccat-for-woocommerce') ) );
			}
			$wc_order->update_meta_data( self::META_INVOICE_ORDER_API_DATA, $invoice_data );
		} else {
			$saved_invoice_data = $wc_order->get_meta( self::META_INVOICE_ORDER_API_DATA );
			if ( ! empty( $saved_invoice_data ) ) {
				$invoice_data = $saved_invoice_data;
			}
		}

		return ! empty( $invoice_data ) ? array_merge( $order_data, $invoice_data ) : $order_data;
	}


	/**
	 * Retrieves the concatenated names and quantities of items in an order.
	 *
	 * @param WC_Order $w_c_order The order object containing the items.
	 *
	 * @return string A string containing the names and quantities of the items, truncated if exceeding 60 characters.
	 */
	protected function get_order_items_name( WC_Order $w_c_order ): string {
		$names = array();
		foreach ( $w_c_order->get_items() as $item ) {
			$names[] = $item->get_name() . 'x' . $item->get_quantity();
		}
		$result = implode( '、', $names );

		return mb_strlen( $result ) > 60 ? mb_substr( $result, 0, 57 ) . '...' : $result;
	}

	/**
	 * 驗證統一編號.
	 *
	 * @param string $number 統一編號.
	 *
	 * @return bool
	 */
	private function validate_tax_number( string $number ): bool {
		if ( ! preg_match( '/^[0-9]{8}$/', $number ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Processes the payment for a given order.
	 *
	 * @param int $order_id The ID of the order for which the payment is being processed.
	 *
	 * @return array An array containing the result of the payment and the redirect URL upon success; otherwise, throws an exception on failure.
	 * @throws Exception If the payment fails, an exception is thrown with an appropriate error message.
	 */
	public function process_payment( $order_id ): array {
		$datetime = new DateTime( 'now', new DateTimeZone( 'Asia/Taipei' ) );
		$order    = wc_get_order( $order_id );

		try {

			$api_url   = $this->get_base_url() . 'api/Collect';
			$api_token = $this->get_payment_api_token();
			$order_no  = $this->generate_unique_order_number( $order );
			$post_data = array(
				'cmd'              => self::CMD_COCS_ORDER_APPEND,
				'cust_id'          => $this->get_account(),
				'cust_order_no'    => $order_no,
				'order_amount'     => $order->get_total(),
				'order_detail'     => 'Order #' . $order->get_id(),
				'acquirer_type'    => $this->acquirer_type(),
				'limit_product_id' => '',
				// phpcs:ignore Generic.Functions.DateTime.DiscouragedFunction
				'send_time'        => $datetime->format( 'Y-m-d H:i:s' ),
				'success_url'      => '',
				'apn_url'          => $this->get_apn_url(),
				'b2c'              => '0',
			);
			if ( 'yes' === get_option( CCATPAYMENTS_PREFIX . '_invoice_enable', 'no' ) ) {
				$post_data = $this->add_invoice_data( $post_data, $order );
			}
			if ( CCATPAY_Payments::is_shipping_enabled() ) {
				$this->add_shipping_data( $post_data, $order );
			}
			$args     = array(
				'body'    => wp_json_encode( $post_data ),
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_token,
				),
				'method'  => 'POST',
				'timeout' => 45,
			);
			$response = wp_remote_post( $api_url, $args );
			if ( is_wp_error( $response ) ) {
				$logger = wc_get_logger();
				$logger->error(
					'API call failed: ' . $response->get_error_message(),
					array( 'source' => 'api-error' )
				);

				throw new Exception( esc_html__( 'Api error. try again later.', 'ccat-for-woocommerce') );
			}

			$response_body = wp_remote_retrieve_body( $response );
			$response_data = json_decode( $response_body, true );

			$order->update_meta_data( self::META_RESPONSE_DATA, $response_body );
			$order->update_meta_data( $response_data['cust_order_no'], $response_data['cust_order_no'] );

			if ( isset( $response_data['status'] ) && self::CODE_OK === $response_data['status'] ) {
				$this->add_apn_notification( $order, $response_data );
				$order->update_status( Automattic\WooCommerce\Enums\OrderStatus::PENDING, esc_html__( 'Awaiting payment', 'ccat-for-woocommerce') );
				WC()->cart->empty_cart();

				return array(
					'result'   => 'success',
					'redirect' => $response_data['url'],
				);
			} else {
				$error_message = $response_data['msg'] ?? esc_html__( 'Unknown Error.', 'ccat-for-woocommerce');
				delete_transient( CCATPAYMENTS_PREFIX. 'api_access_token' );
				return array(
					'result'   => 'failure',
					'messages' => $error_message,
				);
			}
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			return array(
				'result'   => 'failure',
				'messages' => $e->getMessage(),
			);
		}
	}

	/**
	 * Handle the payment callback from the payment gateway.
	 *
	 * @param WP_REST_Request $request The incoming request object containing payment callback data.
	 *
	 * @return WP_REST_Response A response object indicating the status of the callback handling.
	 */
	public function handle_payment_callback( WP_REST_Request $request ): WP_REST_Response {
		$validate = $this->handle_payment_validation( $request );
		if ( $validate instanceof WP_Error ) {
			return new WP_REST_Response( $validate->get_error_message(), $validate->get_error_code() );
		}

		$data  = json_decode( $request->get_body(), true );
		$order = $this->get_order( $data['order_no'] );
		$this->add_apn_notification( $order, $data );
		$status = strtoupper( $data['status'] );

		switch ( $status ) {
			case 'B':
				$note = esc_html__( '授權完成', 'ccat-for-woocommerce');
				$order->set_payment_method( $this );
				$order->update_meta_data( self::ORDER_NO, $data['order_no'] );
				$order->save();
				$order->payment_complete();
				break;

			case 'O':
				$note = esc_html__( '請款作業中', 'ccat-for-woocommerce');
				$order->update_meta_data( self::META_PROCESSING, 1 );
				$order->save();
				break;

			case 'E':
				$note = esc_html__( '請款完成', 'ccat-for-woocommerce');
				break;

			case 'F':
				$note = esc_html__( '授權失敗', 'ccat-for-woocommerce');
				$order->update_status( Automattic\WooCommerce\Enums\OrderStatus::FAILED );
				break;

			case 'D':
				$note = esc_html__( '訂單已逾期', 'ccat-for-woocommerce');
				break;

			case 'P':
				$note = esc_html__( '請款失敗', 'ccat-for-woocommerce');
				$order->update_status( Automattic\WooCommerce\Enums\OrderStatus::FAILED );
				break;

			case 'M':
				$note = esc_html__( '取消交易完成', 'ccat-for-woocommerce');
				$order->update_status( Automattic\WooCommerce\Enums\OrderStatus::CANCELLED );
				break;

			case 'N':
				$note = esc_html__( '取消交易失敗', 'ccat-for-woocommerce');
				break;

			case 'Q':
				$note = esc_html__( '取消授權完成', 'ccat-for-woocommerce');
				$order->update_status( Automattic\WooCommerce\Enums\OrderStatus::CANCELLED );
				break;

			case 'R':
				$note = esc_html__( '取消授權失敗', 'ccat-for-woocommerce');
				break;

			case 'I':
				$note = esc_html__( '開立發票通知', 'ccat-for-woocommerce');
				break;

			case 'J':
				$note = esc_html__( '開立發票折讓通知', 'ccat-for-woocommerce');
				break;

			default:
				$note = esc_html__( '未知狀態碼: ', 'ccat-for-woocommerce') . esc_html( $status );
				break;
		}

		$order->add_order_note( $note );

		// 處理發票資訊，有發票號碼才寫入紀錄
		if ( isset( $data['invoice_no'] ) && ! empty( $data['invoice_no'] ) ) {
			// 基本發票資訊.
			$order->update_meta_data( self::META_INVOICE_NO, sanitize_text_field( $data['invoice_no'] ) );
			$order->update_meta_data( self::META_INVOICE_DATE, sanitize_text_field( $data['invoice_date'] ) );
			$order->update_meta_data( self::META_INVOICE_RANDOM, sanitize_text_field( $data['random_number'] ) );

			// 儲存完整發票資料.
			$invoice_data = array(
				'print_invoice'   => sanitize_text_field( $data['print_invoice'] ?? '0' ),
				'vehicle_type'    => sanitize_text_field( $data['vehicle_type'] ?? '' ),
				'vehicle_barcode' => sanitize_text_field( $data['vehicle_barcode'] ?? '' ),
				'donate_invoice'  => sanitize_text_field( $data['donate_invoice'] ?? '0' ),
				'love_code'       => sanitize_text_field( $data['love_code'] ?? '' ),
				'invoice_no'      => sanitize_text_field( $data['invoice_no'] ),
				'invoice_date'    => sanitize_text_field( $data['invoice_date'] ),
				'random_number'   => sanitize_text_field( $data['random_number'] ),
				'created_at'      => current_time( 'mysql' ),
			);

			// 如果有折讓資訊.
			if ( ! empty( $data['invoice_discount_no'] ) ) {
				$invoice_data['invoice_discount_no'] = sanitize_text_field( $data['invoice_discount_no'] );
				$order->update_meta_data( self::META_INVOICE_DISCOUNT, $data['invoice_discount_no'] );
			}

			$order->update_meta_data( self::META_INVOICE_APN, $invoice_data );

			$note = sprintf(
			/* translators: %1$s: Invoice number, %2$s: Invoice date, %3$s: Random code. */
				__( '已收到電子發票通知，發票號碼：%1$s，開立日期：%2$s，隨機碼：%3$s', 'ccat-for-woocommerce'),
				$data['invoice_no'],
				$data['invoice_date'],
				$data['random_number']
			);
			$order->add_order_note( $note );
			$order->save();
		}

		return new WP_REST_Response( 'OK', 200 );
	}

	/**
	 * Retrieves a WooCommerce order based on the given order number.
	 *
	 * @param string $order_no The custom order number to search for.
	 *
	 * @return wc_order|null The WooCommerce order object if found, or null if no order matches the given number.
	 */
	protected function get_order( string $order_no ): ?wc_order {
		$args   = array(
			'meta_key'   => $order_no,  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => $order_no, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'limit'      => 1,
		);
		$orders = wc_get_orders( $args );
		if ( empty( $orders ) ) {
			return null;
		}

		return $orders[0];
	}


	/**
	 * Extracts and validates the request data.
	 *
	 * @param WP_REST_Request $request .
	 *
	 * @return null|WP_Error
	 */
	protected function handle_payment_validation( WP_REST_Request $request ): ?WP_Error {
		$body = $request->get_body();
		$data = json_decode( $body, true );

		if ( $this->is_test_mode() ) {
			CCATPAY_Payments::log( 'Payment callback received: ' . $body );
		}
		if ( ! $data ) {
			return new WP_Error( 400, 'Invalid JSON' );
		}

		$required_fields = array( 'api_id', 'trans_id', 'amount', 'status', 'nonce', 'checksum' );

		foreach ( $required_fields as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return new WP_Error( 400, "Missing field: $field" );
			}
		}

		$calculated_checksum = md5(
			$data['api_id'] . ':' .
			$data['trans_id'] . ':' .
			$data['amount'] . ':' .
			$data['status'] . ':' .
			$data['nonce']
		);

		if ( $calculated_checksum !== $data['checksum'] ) {
			return new WP_Error( 400, 'Checksum verification failed' );
		}

		return null;
	}


	/**
	 * Saves an APN to the order metadata.
	 *
	 * @param WC_Order $order The WooCommerce order to which the APN data will be saved.
	 * @param array    $data The APN data to be stored, typically received from a notification.
	 *
	 * @return void
	 */
	public function add_apn_notification( WC_Order $order, array $data ): void {
		if ( $this->is_test_mode() ) {
			CCATPAY_Payments::log( 'add_apn_notification: ' . wp_json_encode( $data ) );
		}
		$received_notifications = $order->get_meta( self::META_APN );
		if ( ! is_array( $received_notifications ) ) {
			$received_notifications = array();
		}

		$received_notifications[] = array(
			'time' => current_time( 'mysql' ),
			'data' => $data,
		);

		$order->update_meta_data( self::META_APN, $received_notifications );
		$order->save();
	}

	/**
	 * Generates a unique order number based on the current date, order ID, and payment attempt count.
	 *
	 * @param WC_Order $order The order object for which the unique order number is being generated.
	 *
	 * @return string A unique order number string combining the date, order ID, and attempt count.
	 */
	protected function generate_unique_order_number( WC_Order $order ): string {
		$attempt = (int) $order->get_meta( '_payment_attempt_count' );
		$order->update_meta_data( '_payment_attempt_count', $attempt + 1 );
		$order->save();

		$datetime = new DateTime( 'now', new DateTimeZone( 'Asia/Taipei' ) );

		return sprintf(
			'CCAT%s%06d%02d',
			$datetime->format( 'Ymd' ),
			$order->get_id(),
			$attempt
		);
	}

	/**
	 * 實作退款處理方法
	 *
	 * @param int    $order_id 訂單編號.
	 * @param float  $amount 退款金額（可能為 null）.
	 * @param string $reason 退款原因.
	 *
	 * @return bool|WP_Error  成功返回 true，失敗返回錯誤物件
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', esc_html__( '找不到訂單', 'ccat-for-woocommerce') );
		}

		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', esc_html__( '無效的退款金額', 'ccat-for-woocommerce') );
		}

		$api_token  = $this->get_payment_api_token();
		$order_no   = $order->get_meta( self::ORDER_NO );
		$processing = $order->get_meta( self::META_PROCESSING, 1 );
		try {
			if ( empty( $processing ) && ( is_null( $amount ) || empty( $order->get_remaining_refund_amount() ) ) ) {
				$request_data = array(
					'cmd'           => 'CocsOrderCancel',
					'cust_id'       => $this->get_account(),
					'cust_order_no' => $order_no,
					'order_amount'  => $order->get_total(),
					'acquirer_type' => $this->acquirer_type(),
					'send_time'     => current_time( 'Y-m-d H:i:s' ),
				);

				$response = wp_remote_post(
					$this->get_base_url() . '/api/Collect',
					array(
						'method'  => 'POST',
						'timeout' => 30,
						'headers' => array(
							'Content-Type'  => 'application/json',
							'Authorization' => 'Bearer ' . $api_token,
						),
						'body'    => wp_json_encode( $request_data ),
					)
				);

				if ( is_wp_error( $response ) ) {
					return new WP_Error( 'api_connection_error', __( 'API 請求失敗: ', 'ccat-for-woocommerce') . $response->get_error_message() );
				}
				$body = wp_remote_retrieve_body( $response );
				if ( $this->is_test_mode() ) {
					CCATPAY_Payments::log( 'Refund request data: ' . wp_json_encode( $request_data ) );
					CCATPAY_Payments::log( 'Refund response data: ' . $body );
				}
				$response_data = json_decode( $body, true );
				if ( empty( $response_data ) ) {
					return new WP_Error( 'api_response_error', esc_html__( 'Json Decode Fail.', 'ccat-for-woocommerce') );
				}
				$status = $response_data['status'] ?? null;
				$msg    = $response_data['msg'] ?? '';
				if ( 'OK' !== $status ) {
					return new WP_Error( 'api_response_error', __( 'API error: ', 'ccat-for-woocommerce') . $msg );
				}

				return true;
			}

			if ( empty( $processing ) ) {
				$request_data = array(
					'cmd'           => 'CocsCashRequest',
					'timeout'       => 30,
					'cust_id'       => $this->get_account(),
					'cust_order_no' => $order_no,
					'order_amount'  => $order->get_total(),
					'cr_amount'     => $order->get_remaining_refund_amount(),
					'send_time'     => current_time( 'Y-m-d H:i:s' ),
				);

				$response = wp_remote_post(
					$this->get_base_url() . '/api/Collect',
					array(
						'method'  => 'POST',
						'headers' => array(
							'Content-Type'  => 'application/json',
							'Authorization' => 'Bearer ' . $api_token,
						),
						'body'    => wp_json_encode( $request_data ),
					)
				);

				if ( is_wp_error( $response ) ) {
					return new WP_Error( 'api_connection_error', __( 'API 請求失敗: ', 'ccat-for-woocommerce') . $response->get_error_message() );
				}
				$body = wp_remote_retrieve_body( $response );
				if ( $this->is_test_mode() ) {
					CCATPAY_Payments::log( 'Refund request data: ' . wp_json_encode( $request_data ) );
					CCATPAY_Payments::log( 'Refund response data: ' . $body );
				}
				$response_data = json_decode( $body, true );
				if ( empty( $response_data ) ) {
					return new WP_Error( 'api_response_error', __( 'Json Decode Fail.', 'ccat-for-woocommerce') );
				}
				$status = $response_data['status'] ?? null;
				$msg    = $response_data['msg'] ?? '';
				if ( 'OK' !== $status ) {
					return new WP_Error( 'api_response_error', __( 'API error: ', 'ccat-for-woocommerce') . $msg );
				}

				return true;
			}

			if ( ! empty( $order_no ) ) {
				$request_data = array(
					'cmd'           => 'CocsOrderRefund',
					'cust_id'       => $this->get_account(),
					'cust_order_no' => $order_no,
					'order_amount'  => $order->get_total(),
					'refund_amount' => $amount,
					'acquirer_type' => $this->acquirer_type(),
					'send_time'     => current_time( 'Y-m-d H:i:s' ),
				);

				$response = wp_remote_post(
					$this->get_base_url() . '/api/Collect',
					array(
						'method'  => 'POST',
						'timeout' => 30,
						'headers' => array(
							'Content-Type'  => 'application/json',
							'Authorization' => 'Bearer ' . $api_token,
						),
						'body'    => wp_json_encode( $request_data ),
					)
				);

				if ( is_wp_error( $response ) ) {
					return new WP_Error( 'api_connection_error', esc_html__( 'API 請求失敗: ', 'ccat-for-woocommerce') . esc_html( $response->get_error_message() ) );
				}
				$body = wp_remote_retrieve_body( $response );
				if ( $this->is_test_mode() ) {
					CCATPAY_Payments::log( 'Refund request data: ' . wp_json_encode( $request_data ) );
					CCATPAY_Payments::log( 'Refund response data: ' . $body );
				}
				$response_data = json_decode( $body, true );
				if ( empty( $response_data ) ) {
					return new WP_Error( 'api_response_error', esc_html__( 'JSON Decode Fail.', 'ccat-for-woocommerce') );
				}

				$status = $response_data['status'] ?? null;
				if ( 'OK' !== $status ) {
					$msg = $response_data['msg'] ?? '';

					return new WP_Error( 'api_response_error', esc_html__( 'API error: ', 'ccat-for-woocommerce') . esc_html( $msg ) );
				}

				return true;
			}

			return new WP_Error( 'refund_failed', esc_html__( '退款處理失敗', 'ccat-for-woocommerce') );
		} catch ( Exception $e ) {
			return new WP_Error( 'refund_error', $e->getMessage() );
		}
	}

	/**
	 * Retrieves the type of acquirer associated with the implementation.
	 *
	 * @return string A string representing the acquirer type.
	 */
	abstract public function acquirer_type(): string;
}
