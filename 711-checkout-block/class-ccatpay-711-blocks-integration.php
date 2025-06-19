<?php
/**
 * CCATPAY_711_Blocks_Integration class
 *
 * @author   sakilu <brian@sakilu.com>
 * @package  WooCommerce CCat Payments Gateway
 * @since    1.0.0
 */

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration class for CCAT711 blocks.
 */
class CCATPAY_711_Blocks_Integration implements IntegrationInterface {

	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'ccat711-block';
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {
		$this->register_block_frontend_scripts();
		$this->register_block_editor_scripts();
		$this->register_main_integration();
	}

	/**
	 * Adds custom query variables to the list of public query variables.
	 *
	 * @param array $vars An array of public query variables.
	 *
	 * @return array The modified array of public query variables.
	 */
	public function ccat711_add_query_vars( array $vars ): array {
		$vars[] = 'action';

		return $vars;
	}

	/**
	 * Adds a custom rewrite rule to handle specific URL patterns and map them to a query action.
	 *
	 * @return void
	 */
	public function ccat711_add_rewrite_rules() {
		add_rewrite_rule(
			'^cvs-callback/?$',
			'index.php?action=ccat711_store_callback',
			'top'
		);
		flush_rewrite_rules();
	}

	/**
	 * Registers the main integration script for the plugin.
	 *
	 * This method registers and localizes the script used for the plugin's integration,
	 * ensuring proper dependency management and dynamic data localization, such as nonce values.
	 *
	 * @return void
	 */
	public function register_main_integration() {
		$script_path       = '/build/index.js';
		$script_url        = plugins_url( $script_path, __FILE__ );
		$script_asset_path = __DIR__ . '/build/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version(),
			);
		wp_register_script(
            CCATPAYMENTS_JS_PREFIX.'ccat711-blocks-integration', // Updated script handle.
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		// 將數據本地化到腳本中，確保 nonce 可用.
		wp_localize_script(
            CCATPAYMENTS_JS_PREFIX.'ccat711-blocks-integration',
            CCATPAYMENTS_JS_PREFIX.'ccat711BlockData',
			$this->get_script_data()
		);
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles(): array {
		return array( CCATPAYMENTS_JS_PREFIX.'ccat711-blocks-integration', CCATPAYMENTS_JS_PREFIX.'ccat711-blocks-frontend' );
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles(): array {
		return array( CCATPAYMENTS_JS_PREFIX.'ccat711-blocks-integration', CCATPAYMENTS_JS_PREFIX.'ccat711-block-editor' );
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data(): array {
		return array(
			'nonce'    => wp_create_nonce( 'ccat711_store_selection_nonce' ),
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		);
	}

	/**
	 * Constructor for the integration.
	 */
	public function __construct() {
		add_action( 'wp_ajax_'.CCATPAYMENTS_PREFIX.'_711_store_selection_url', array( $this, 'ajax_get_711_store_selection_url' ) );
		add_action( 'wp_ajax_nopriv_'.CCATPAYMENTS_PREFIX.'_711_store_selection_url', array( $this, 'ajax_get_711_store_selection_url' ) );
		add_action( 'template_redirect', array( $this, 'handle_store_callback' ) );
		add_action( 'init', array( $this, 'ccat711_add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'ccat711_add_query_vars' ) );
	}

	/**
	 * 獲取 API 授權 token.
	 *
	 * @return array 授權 token.
	 * @throws Exception 如果無法獲取 token.
	 */
	public static function get_api_data(): array {
		// 使用 WC_Gateway_CCat_Abstract 中的 get_payment_api_token 方法.

		// 從 WooCommerce 獲取啟用的支付閘道.
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

		// 嘗試獲取任何 CCat 支付閘道的實例.
		$gateway = null;
		foreach ( $available_gateways as $gateway_id => $available_gateway ) {
			if ( $available_gateway instanceof CCATPAY_Gateway_Abstract ) {
				$gateway = $available_gateway;
				break;
			}
		}

		if ( ! $gateway ) {
			$all_gateways = WC()->payment_gateways()->payment_gateways();
			foreach ( $all_gateways as $gateway_id => $available_gateway ) {
				if ( $available_gateway instanceof CCATPAY_Gateway_Abstract ) {
					$gateway = $available_gateway;
					break;
				}
			}
		}

		// 如果找到支付閘道，使用它來獲取 token.
		if ( $gateway ) {
			$token      = $gateway->get_payment_api_token();
			$url        = $gateway->get_base_url();
			$service_id = $gateway->get_account();

			return array(
				$token,
				$url,
				$service_id,
			);
		}

		// 如果無法使用 WC_Gateway_CCat_Abstract 獲取 token，拋出異常.
		throw new Exception( esc_html__( '無法獲取 API token，請確認已啟用 CCat 支付閘道', 'ccat-for-woocommerce') );
	}

	/**
	 * Handles AJAX request to retrieve the 7-11 store selection URL.
	 *
	 * Processes posted shipping method details, constructs an API request
	 * to fetch a URL for the 7-11 store map, and returns it via a JSON response.
	 * Temporary data is stored for use during callback handling.
	 *
	 * @return void Outputs JSON response containing the URL or error details.
	 * @throws Exception Exception.
	 */
	public function ajax_get_711_store_selection_url() {
		// 驗證 nonce.
		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'ccat711_store_selection_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( '安全驗證失敗', 'ccat-for-woocommerce'),
				)
			);
			wp_die();
		}

		// 獲取運送方式.
		$shipping_method = isset( $_POST['shipping_method'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_method'] ) ) : '';
		$store_category  = isset( $_POST['store_category'] ) ? sanitize_text_field( wp_unslash( $_POST['store_category'] ) ) : '';

		self::openMapForStore( $store_category, $shipping_method );

		wp_die();
	}

	/**
	 * Register scripts for the date field block editor.
	 *
	 * @return void
	 */
	public function register_block_editor_scripts() {
		$script_path       = '/build/ccat-block.js';
		$script_url        = plugins_url( $script_path, __FILE__ );
		$script_asset_path = __DIR__ . '/build/ccat-block.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version(),
			);
		wp_register_script(
            CCATPAYMENTS_JS_PREFIX.'ccat711-blocks-editor', // Updated script handle.
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		// 將數據本地化到腳本中，確保 nonce 可用.
		wp_localize_script(
            CCATPAYMENTS_JS_PREFIX.'ccat711-blocks-frontend',
            CCATPAYMENTS_JS_PREFIX.'ccat711BlockData',
			$this->get_script_data()
		);
	}

	/**
	 * Register scripts for frontend block.
	 *
	 * @return void
	 */
	public function register_block_frontend_scripts() {
		$script_path       = '/build/ccat-block-frontend.js';
		$script_url        = plugins_url( $script_path, __FILE__ );
		$script_asset_path = __DIR__ . '/build/ccat-block-frontend.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version(),
			);
		wp_register_script(
			CCATPAYMENTS_JS_PREFIX.'ccat711-blocks-frontend', // Updated script handle.
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		// 將數據本地化到腳本中，確保 nonce 可用.
		wp_localize_script(
            CCATPAYMENTS_JS_PREFIX.'ccat711-blocks-frontend',
            CCATPAYMENTS_JS_PREFIX.'ccat711BlockData',
			$this->get_script_data()
		);
	}

	/**
	 * Get the file modified time as a cache buster if we're in dev mode.
	 *
	 * @return string The cache buster value to use for the given file.
	 */
	protected function get_file_version(): string {
		return CCATPAYMENTS_VERSION;
	}

	/**
	 * 處理從門市選擇頁面返回的回調
	 */
	public function handle_store_callback() {
		if ( ! isset( $_GET['action'] ) || 'ccat711_store_callback' !== $_GET['action'] ) {  // phpcs:ignore WordPress
			return;
		}
		$temp_var = isset( $_POST['TempVar'] ) ? sanitize_text_field( wp_unslash( $_POST['TempVar'] ) ) : '';  // phpcs:ignore WordPress
		$data     = get_option( CCATPAYMENTS_PREFIX . 'ccat_temp_var_' . $temp_var );

		// check nonce myself.
		if ( empty( $data ) ) {
			wp_die( esc_html__( '安全驗證失敗', 'ccat-for-woocommerce') );
		}
		// 獲取臨時變數和門市資訊.
		$store_name    = isset( $_POST['storename'] ) ? sanitize_text_field( wp_unslash( $_POST['storename'] ) ) : ''; // phpcs:ignore WordPress
		$store_id      = isset( $_POST['storeid'] ) ? sanitize_text_field( wp_unslash( $_POST['storeid'] ) ) : '';  // phpcs:ignore WordPress
		$store_address = isset( $_POST['storeaddress'] ) ? sanitize_text_field( wp_unslash( $_POST['storeaddress'] ) ) : '';  // phpcs:ignore WordPress
		$outside       = isset( $_POST['outside'] ) ? sanitize_text_field( wp_unslash( $_POST['outside'] ) ) : '0'; //  // phpcs:ignore WordPress
		$ship          = isset( $_POST['ship'] ) ? sanitize_text_field( wp_unslash( $_POST['ship'] ) ) : '1111111'; //  // phpcs:ignore WordPress

		if ( empty( $temp_var ) ) {
			wp_die( esc_html__( '缺少識別參數', 'ccat-for-woocommerce') );
		}

		// 獲取保存的資訊.
		$stored_data = get_option( CCATPAYMENTS_PREFIX . 'ccat_temp_var_' . $temp_var );
		if ( empty( $stored_data ) ) {
			wp_die( esc_html__( '無效的識別參數', 'ccat-for-woocommerce') );
		}

		// 刪除臨時資料.
		delete_option( CCATPAYMENTS_PREFIX . 'ccat_temp_var_' . $temp_var );

		// 準備 JavaScript 回調.
		$store_data = wp_json_encode(
			array(
				'tempVar'      => $temp_var,      // 原本傳入之Key值，作為資訊識別用.
				'storeId'      => $store_id,      // 門市店號.
				'storeName'    => $store_name,    // 門市名稱.
				'storeAddress' => $store_address, // 門市地址.
				'outside'      => $outside,       // 判斷是否為離島 (0為本島，1為外島).
				'ship'         => $ship,          // 配送週期 (0：不配送, 1：配送) 順序為 (日一二三四五六).
			)
		);

		// 輸出 HTML 和 JavaScript 導回結帳頁面.
		echo '<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>' . esc_html__( '門市選擇完成', 'ccat-for-woocommerce') . '</title>
			<script type="text/javascript">
				window.onload = function() {
					var storeData = ' . $store_data . ';
					
					// 嘗試將門市資料存儲到 localStorage 以防止資料丟失
					try {
						localStorage.setItem("selectedCvsStore", JSON.stringify(storeData));
					} catch(e) {
						console.error("無法保存門市資料到 localStorage:", e);
					}
					
					if (window.opener && !window.opener.closed) {
						try {
							// 嘗試調用父窗口的回調函數
							window.opener.setSelectedCvsStore(storeData);
							document.getElementById("success-message").style.display = "block";
							// 成功傳遞數據後關閉窗口
							setTimeout(function() {
								window.close();
							}, 1000);
						} catch(e) {
							window.alert("調用父窗口函數失敗");
							console.error("調用父窗口函數失敗:", e);
							document.getElementById("manual-redirect").style.display = "block";
						}
					} else {
						// 父窗口不存在或已關閉，顯示自動重定向訊息
						document.getElementById("auto-redirect").style.display = "block";
						setTimeout(function() {
							window.location.href = "' . esc_url( wc_get_checkout_url() ) . '";
						}, 3000);
					}
				};
			</script>
			<style>
				body {
					font-family: Arial, sans-serif;
					text-align: center;
					padding: 20px;
					background-color: #f8f8f8;
					color: #333;
				}
				h1 {
					color: #2a9d38;
					margin-bottom: 10px;
				}
				.store-info {
					background-color: #fff;
					border-radius: 5px;
					padding: 15px;
					margin: 20px auto;
					max-width: 400px;
					box-shadow: 0 2px 5px rgba(0,0,0,0.1);
				}
				.message {
					display: none;
					margin: 20px 0;
					padding: 15px;
					border-radius: 5px;
				}
				#success-message {
					background-color: #e8f5e9;
					color: #2a9d38;
				}
				#auto-redirect {
					background-color: #e3f2fd;
					color: #1976d2;
				}
				#manual-redirect {
					background-color: #fff3e0;
					color: #e65100;
				}
				.button {
					display: inline-block;
					padding: 10px 20px;
					background: #2a9d38;
					color: white;
					text-decoration: none;
					border-radius: 5px;
					margin-top: 15px;
					border: none;
					cursor: pointer;
				}
				.loading {
					display: inline-block;
					width: 20px;
					height: 20px;
					margin-right: 10px;
					border: 3px solid #f3f3f3;
					border-top: 3px solid #3498db;
					border-radius: 50%;
					animation: spin 1s linear infinite;
				}
				@keyframes spin {
					0% { transform: rotate(0deg); }
					100% { transform: rotate(360deg); }
				}
			</style>
		</head>
		<body>
			<h1>' . esc_html__( '門市選擇完成', 'ccat-for-woocommerce') . '</h1>
			
			<div class="store-info">
				<p><strong>' . esc_html__( '選擇門市：', 'ccat-for-woocommerce') . '</strong> ' . esc_html( $store_name ) . '</p>
				<p><strong>' . esc_html__( '門市代號：', 'ccat-for-woocommerce') . '</strong> ' . esc_html( $store_id ) . '</p>
				<p><strong>' . esc_html__( '門市地址：', 'ccat-for-woocommerce') . '</strong> ' . esc_html( $store_address ) . '</p>
				<p><strong>' . esc_html__( '位置類型：', 'ccat-for-woocommerce') . '</strong> ' . esc_html( intval( $outside ) ? '本島' : '外島' ) . '</p>
			</div>
			
			<div id="success-message" class="message">
				<div class="loading"></div>
				<p>' . esc_html__( '資料已成功傳送，正在關閉視窗...', 'ccat-for-woocommerce') . '</p>
			</div>
			
			<div id="manual-redirect" class="message">
				<p>' . esc_html__( '無法自動返回原始頁面，請點擊下方按鈕手動返回結帳', 'ccat-for-woocommerce') . '</p>
				<a href="' . esc_url( wc_get_checkout_url() ) . '" class="button">' . esc_html__( '返回結帳頁面', 'ccat-for-woocommerce') . '</a>
			</div>
			
			<div id="auto-redirect" class="message">
				<div class="loading"></div>
				<p>' . esc_html__( '正在自動返回結帳頁面...', 'ccat-for-woocommerce') . '</p>
			</div>
		</body>
		</html>';

		exit;
	}


	/**
	 * Opens a map interface for selecting a store based on the provided store category and shipping method.
	 * Sends an API request to retrieve a map URL and handles various stages, including callback setup
	 * and temporary storage of relevant data.
	 *
	 * @param string $store_category The category of the store to filter the map selection (e.g., convenience store type).
	 * @param string $shipping_method The shipping method associated with the store selection.
	 *
	 * @return void void
	 */
	public static function openMapForStore( string $store_category, string $shipping_method ): void {
		try {

			// 創建回調網址，用於從地圖選擇頁面返回.
			$base_id      = uniqid();
			$random       = wp_rand( 1000000, 9999999 );
			$temp_var     = $base_id . $random;
			$callback_url = add_query_arg(
				array(
					'action' => 'ccat711_store_callback',
				),
				site_url( 'cvs-callback' ) // 使用自定義 URL.
			);
			try {
				// 獲取 API token.
				$api_data = self::get_api_data();

				// API 端點.
				$api_url = $api_data[1] . 'api/Logistics/OpenMap';

				// 準備請求資料.
				$request_data = array(
					'ServiceId'     => $api_data[2],
					'ReturnUrl'     => $callback_url,
					'TempVar'       => $temp_var,
					'StoreCategory' => $store_category,
				);

				// 發送 API 請求.
				$response = wp_remote_post(
					$api_url,
					array(
						'headers' => array(
							'Content-Type'  => 'application/json',
							'Authorization' => 'Bearer ' . $api_data[0],
						),
						'body'    => wp_json_encode( $request_data ),
						'timeout' => 30,
					)
				);

				// 檢查回應.
				if ( is_wp_error( $response ) ) {
					throw new Exception( $response->get_error_message() );
				}

				$body = wp_remote_retrieve_body( $response );
				$url  = json_decode( $body, true );

				// 檢查回應是否包含地圖 URL.
				if ( empty( $url ) ) {
					throw new Exception( esc_html__( '取得超商網址錯誤', 'ccat-for-woocommerce') );
				}

				// 儲存臨時變數，以便在回調時使用.
				update_option(
					CCATPAYMENTS_PREFIX . 'ccat_temp_var_' . $temp_var,
					array(
						'shipping_method' => $shipping_method,
						'order_id'        => isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '',
						// phpcs:ignore WordPress
						'created_at'      => time(),
					),
					false
				);

				// 返回地圖 URL.
				wp_send_json_success(
					array(
						'url' => $url,
					)
				);

			} catch ( Exception $e ) {
				throw new Exception( esc_html__( 'API 請求失敗：', 'ccat-for-woocommerce') . $e->getMessage() );
			}
		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}
}
