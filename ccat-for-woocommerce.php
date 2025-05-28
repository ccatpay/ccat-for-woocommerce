<?php
/**
<<<<<<< HEAD
 * Plugin Name: ccat for WooCommerce
 * Plugin URI: https://github.com/ccatpay/ccat-for-woocommerce
=======
 * Plugin Name: ccat-for-woocommerce
 * Plugin URI: https://www.ccat.com.tw/
>>>>>>> parent of d47d7c2 (更名ccatpay Payment for WooCommerce)
 * Description: Adds the CCat Payments gateway to your WooCommerce website.
 * Version: 2.0.2
 * Author: ccatpay
 * Text Domain: ccat-for-woocommerce
 *
 * Requires at least: 6.6
 * Tested up to: 6.8
 * Requires PHP: 8.3
 * Requires Plugins: woocommerce
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WooCommerceCCatGateway
 */

use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'WC_CCAT_PAYMENTS_VERSION' ) ) {
	define( 'WC_CCAT_PAYMENTS_VERSION', '2.0.2' );
}

/**
 * WC CCat Payment gateway plugin class.
 *
 * @class WC_CCat_Payments
 */
class WC_CCat_Payments {
	/**
	 * Plugin bootstrapping.
	 */
	public static function init(): void {
		// CCat Payments gateway class.
		add_action( 'plugins_loaded', array( __CLASS__, 'includes' ), 0 );

		// Make the CCat Payments gateway available to WC.
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );

		// 註冊黑貓物流方法.
		add_filter( 'woocommerce_shipping_methods', array( __CLASS__, 'add_shipping_methods' ) );

		// Registers WooCommerce Blocks integration.
		add_action(
			'woocommerce_blocks_loaded',
			array(
				__CLASS__,
				'woocommerce_gateway_ccat_woocommerce_block_support',
			)
		);

		// 初始化物流與支付協調器.
		if ( self::is_shipping_enabled() ) {
			add_action( 'init', array( __CLASS__, 'init_shipping_payment_coordinator' ) );
			add_action( 'woocommerce_init', array( __CLASS__, 'check_and_add_taiwan_shipping_zone' ) );
		}
	}

	/**
	 * 初始化物流與支付協調器
	 */
	public static function init_shipping_payment_coordinator(): void {
		require_once self::plugin_abspath() . 'includes/shipping/class-wc-ccat-shipping-payment-coordinator.php';
		WC_CCat_Shipping_Payment_Coordinator::init();
	}

	/**
	 * 添加黑貓物流運送方式到WooCommerce
	 *
	 * @param array $methods 現有運送方式.
	 *
	 * @return array 包含黑貓物流的運送方式
	 */
	public static function add_shipping_methods( array $methods ): array {
		if ( self::is_shipping_enabled() ) {
			$methods['wc_shipping_ccat_cod']         = 'WC_Shipping_CCat_COD';
			$methods['wc_shipping_ccat_711_cod']     = 'WC_Shipping_CCat_711_COD';
			$methods['wc_shipping_ccat_prepaid']     = 'WC_Shipping_CCat_Prepaid';
			$methods['wc_shipping_ccat_711_prepaid'] = 'WC_Shipping_CCat_711_Prepaid';

			// 冷藏物流方法.
			$methods['wc_shipping_ccat_cod_refrigerated']         = 'WC_Shipping_CCat_COD_Refrigerated';
			$methods['wc_shipping_ccat_711_cod_refrigerated']     = 'WC_Shipping_CCat_711_COD_Refrigerated';
			$methods['wc_shipping_ccat_prepaid_refrigerated']     = 'WC_Shipping_CCat_Prepaid_Refrigerated';
			$methods['wc_shipping_ccat_711_prepaid_refrigerated'] = 'WC_Shipping_CCat_711_Prepaid_Refrigerated';

			// 冷凍物流方法.
			$methods['wc_shipping_ccat_cod_frozen']         = 'WC_Shipping_CCat_COD_Frozen';
			$methods['wc_shipping_ccat_711_cod_frozen']     = 'WC_Shipping_CCat_711_COD_Frozen';
			$methods['wc_shipping_ccat_prepaid_frozen']     = 'WC_Shipping_CCat_Prepaid_Frozen';
			$methods['wc_shipping_ccat_711_prepaid_frozen'] = 'WC_Shipping_CCat_711_Prepaid_Frozen';
		}

		return $methods;
	}

	/**
	 * Determines if the CCat payment gateway is enabled via settings.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public static function is_ccat_enabled(): bool {
		$is_enabled = get_option( 'wc_ccat_enable', 'yes' );

		return 'yes' === $is_enabled;
	}

	/**
	 * 檢查並新增台灣物流區域以及相關物流方法
	 */
	public static function check_and_add_taiwan_shipping_zone(): void {
		// 檢查台灣區域是否存在.
		$taiwan_zone_exists = false;
		$taiwan_zone_id     = null;
		$zones              = WC_Shipping_Zones::get_zones();

		foreach ( $zones as $zone ) {
			// 檢查區域名稱是否為Taiwan或台灣.
			if ( stripos( $zone['zone_name'], 'Taiwan' ) !== false || stripos( $zone['zone_name'], '台灣' ) !== false ) {
				$taiwan_zone_exists = true;
				$taiwan_zone_id     = $zone['id'];
				break;
			}

			// 檢查區域是否包含台灣的國家代碼.
			if ( isset( $zone['zone_locations'] ) && is_array( $zone['zone_locations'] ) ) {
				foreach ( $zone['zone_locations'] as $location ) {
					if ( 'country' === $location->type && 'TW' === $location->code ) {
						$taiwan_zone_exists = true;
						$taiwan_zone_id     = $zone['id'];
						break 2;
					}
				}
			}
		}

		// 定義所有需要添加的物流方法.
		$shipping_methods = array(
			'wc_shipping_ccat_cod',
			'wc_shipping_ccat_711_cod',
			'wc_shipping_ccat_prepaid',
			'wc_shipping_ccat_711_prepaid',
			'wc_shipping_ccat_cod_refrigerated',
			'wc_shipping_ccat_cod_frozen',
			'wc_shipping_ccat_prepaid_refrigerated',
			'wc_shipping_ccat_prepaid_frozen',
			'wc_shipping_ccat_711_cod_refrigerated',
			'wc_shipping_ccat_711_cod_frozen',
			'wc_shipping_ccat_711_prepaid_refrigerated',
			'wc_shipping_ccat_711_prepaid_frozen',
		);

		if ( $taiwan_zone_exists ) {
			// 如果台灣區域已存在，獲取該區域.
			$zone = new WC_Shipping_Zone( $taiwan_zone_id );

			// 獲取現有的物流方法.
			$existing_methods = $zone->get_shipping_methods();
			$existing_method_ids = array();

			// 收集現有物流方法的 ID
			foreach ( $existing_methods as $existing_method ) {
				$existing_method_ids[] = $existing_method->id;
			}

			// 添加尚未存在的物流方法.
			foreach ( $shipping_methods as $method_id ) {
				if ( ! in_array( $method_id, $existing_method_ids, true ) ) {
					$zone->add_shipping_method( $method_id );
				}
			}
		} else {
			// 如果台灣區域不存在，則創建新區域.
			$zone = new WC_Shipping_Zone();
			$zone->set_zone_name( '台灣' );
			$zone->add_location( 'TW', 'country' );
			$zone->save();

			// 添加所有物流方法
			foreach ( $shipping_methods as $method_id ) {
				$zone->add_shipping_method( $method_id );
			}
		}
	}

	/**
	 * Determines if the CCat payment gateway is enabled via settings.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public static function is_shipping_enabled(): bool {
		$is_enabled = get_option( 'wc_ccat_shipping_enable', 'yes' );

		return 'yes' === $is_enabled;
	}

	/**
	 * Adds the CCat payment gateway to the list of payment gateways.
	 *
	 * @param array $gateways List of available payment gateways.
	 *
	 * @return array Updated list of payment gateways including CCat gateway if conditions are met.
	 */
	public static function add_gateway( array $gateways ): array {
		if ( self::is_ccat_enabled() ) {
			$gateways[] = 'WC_Gateway_CCat_Credit_Card';
			$gateways[] = 'WC_Gateway_CCat_Chinatrust';
			$gateways[] = 'WC_Gateway_CCat_Payuni';
			$gateways[] = 'WC_Gateway_CCat_Cvs_Ibon';
			$gateways[] = 'WC_Gateway_CCat_Cvs_Atm';
			// $gateways[] = 'WC_Gateway_CCat_Cvs_Barcode';
			$gateways[] = 'WC_Gateway_CCat_App_Opw';
			$gateways[] = 'WC_Gateway_CCat_App_Icash';
			// 新增黑貓貨到付款閘道.
			$gateways[] = 'WC_Gateway_CCat_COD_Cash';
			$gateways[] = 'WC_Gateway_CCat_COD_Mobile';
			$gateways[] = 'WC_Gateway_CCat_COD_711';
			$gateways[] = 'WC_Gateway_CCat_COD_Card';
		}

		return $gateways;
	}

	/**
	 * Plugin includes.
	 */
	public static function includes(): void {
		$is_invoice_enabled = 'yes' === get_option( 'wc_ccat_invoice_enable', 'no' );
		if ( $is_invoice_enabled ) {
			require_once 'ccat-checkout-block/ccat-block-integration-checkout.php';
			require_once 'includes/class-wc-ccat-invoice-display.php';
			new WC_CCat_Invoice_Display();
			add_action(
				'woocommerce_blocks_checkout_block_registration',
				function ( $integration_registry ) {
					$integration_registry->register( new Ccat_Blocks_Integration() );
				}
			);
		}
		if ( self::is_shipping_enabled() ) {
			require_once '711-checkout-block/class-ccat711-blocks-integration.php';
			require_once 'includes/class-wc-ccat-shipping-display.php';
			new WC_CCat_Shipping_Display();
			add_action(
				'woocommerce_blocks_checkout_block_registration',
				function ( $integration_registry ) {
					$integration_registry->register( new Ccat711_Blocks_Integration() );
				}
			);
		}
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => CheckoutSchema::IDENTIFIER,
				'namespace'       => 'ccat-for-woocommerce',
				'data_callback'   => array( __CLASS__, 'get_invoice_data' ),
				'schema_callback' => array( __CLASS__, 'get_invoice_schema' ),
			)
		);

		// Make the WC_Gateway_CCat class available.
		if ( class_exists( 'WC_Payment_Gateway' ) && self::is_ccat_enabled() ) {
			require_once 'includes/class-wc-gateway-ccat-abstract.php';
			require_once 'includes/class-wc-gateway-ccat-cvs-abstract.php';
			require_once 'includes/class-wc-gateway-ccat-credit-card.php';
			require_once 'includes/class-wc-gateway-ccat-chinatrust.php';
			require_once 'includes/class-wc-gateway-ccat-payuni.php';
			require_once 'includes/class-wc-gateway-ccat-cvs-ibon.php';
			require_once 'includes/class-wc-gateway-ccat-cvs-atm.php';
			// require_once 'includes/class-wc-gateway-ccat-cvs-barcode.php';
			require_once 'includes/class-wc-gateway-ccat-app-opw.php';
			require_once 'includes/class-wc-gateway-ccat-app-icash.php';
			// 新增黑貓貨到付款閘道.
			require_once 'includes/class-wc-gateway-ccat-cod-abstract.php';
			require_once 'includes/class-wc-gateway-ccat-cod-cash.php';
			require_once 'includes/class-wc-gateway-ccat-cod-card.php';
			require_once 'includes/class-wc-gateway-ccat-cod-mobile.php';
			require_once 'includes/class-wc-gateway-ccat-cod-711.php';
		}

		// 載入黑貓物流相關類別.
		if ( class_exists( 'WC_Shipping_Method' ) && self::is_shipping_enabled() ) {
			require_once 'includes/shipping/class-wc-shipping-ccat-abstract.php';
			require_once 'includes/shipping/class-wc-shipping-ccat-cod.php';
			require_once 'includes/shipping/class-wc-shipping-ccat-711-cod.php';
			require_once 'includes/shipping/class-wc-shipping-ccat-prepaid.php';
			require_once 'includes/shipping/class-wc-shipping-ccat-711-prepaid.php';

			// 引入宅配先付款不同溫度類型.
			require_once 'includes/shipping/class-wc-shipping-ccat-prepaid-normal.php';
			require_once 'includes/shipping/class-wc-shipping-ccat-prepaid-refrigerated.php';
			require_once 'includes/shipping/class-wc-shipping-ccat-prepaid-frozen.php';

			// 引入宅配貨到付款不同溫度類型.
			require_once 'includes/shipping/class-wc-shipping-ccat-cod-normal.php';
			require_once 'includes/shipping/class-wc-shipping-ccat-cod-refrigerated.php';
			require_once 'includes/shipping/class-wc-shipping-ccat-cod-frozen.php';

			// 引入7-11先付款不同溫度類型.
			require_once 'includes/shipping/class-wc-shipping-ccat-711-prepaid-normal.php';
			require_once 'includes/shipping/class-wc-shipping-ccat-711-prepaid-refrigerated.php';
			require_once 'includes/shipping/class-wc-shipping-ccat-711-prepaid-frozen.php';

			// 引入7-11貨到付款不同溫度類型.
			require_once 'includes/shipping/class-wc-shipping-ccat-711-cod-normal.php';
			require_once 'includes/shipping/class-wc-shipping-ccat-711-cod-refrigerated.php';
			require_once 'includes/shipping/class-wc-shipping-ccat-711-cod-frozen.php';
		}

		require_once 'includes/class-wc-ccat-settings.php';
		WC_CCat_Settings::init();
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_url(): string {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Logs an error message using the WooCommerce logger.
	 *
	 * @param string $msg The error message to be logged.
	 *
	 * @return void
	 */
	public static function log( string $msg ): void {
		$logger = wc_get_logger();
		$logger->error(
			$msg,
			array( 'source' => 'api-token' ),
		);
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_abspath(): string {
		return trailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Registers WooCommerce Blocks integration.
	 */
	public static function woocommerce_gateway_ccat_woocommerce_block_support(): void {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) && self::is_ccat_enabled() ) {
			require_once 'includes/blocks/class-wc-ccat-payments-credit-card-blocks.php';
			require_once 'includes/blocks/class-wc-ccat-payments-chinatrust-blocks.php';
			require_once 'includes/blocks/class-wc-ccat-payments-payuni-blocks.php';
			require_once 'includes/blocks/class-wc-ccat-payments-ibon-blocks.php';
			require_once 'includes/blocks/class-wc-ccat-payments-atm-blocks.php';
			// require_once 'includes/blocks/class-wc-ccat-payments-barcode-blocks.php';
			require_once 'includes/blocks/class-wc-ccat-payments-opw-blocks.php';
			require_once 'includes/blocks/class-wc-ccat-payments-icash-blocks.php';
			require_once 'includes/blocks/class-wc-ccat-payments-cod-blocks.php';

			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new WC_Gateway_CCat_Credit_Card_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_CCat_Chinatrust_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_CCat_Payuni_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_CCat_Ibon_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_CCat_Atm_Blocks_Support() );
					// $payment_method_registry->register( new WC_Gateway_CCat_Barcode_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_CCat_Opw_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_CCat_Icash_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_CCat_COD_Blocks_Support() );
				}
			);
		}
	}
}

WC_CCat_Payments::init();
