<?php
/**
 * Plugin Name: ccatpay Payment for WooCommerce
 * Plugin URI: https://github.com/ccatpay/ccat-for-woocommerce
 * Description: Adds the CCat Payments gateway to your WooCommerce website.
 * Version: 2.5
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
if ( ! defined( 'CCATPAYMENTS_VERSION' ) ) {
	define( 'CCATPAYMENTS_VERSION', '2.5' );
}
if ( ! defined( 'CCATPAYMENTS_DOMAIN' ) ) {
	define( 'CCATPAYMENTS_DOMAIN', 'ccat-for-woocommerce' );
}
if ( ! defined( 'CCATPAYMENTS_PREFIX' ) ) {
	define( 'CCATPAYMENTS_PREFIX', 'ccatpay-for-woocommerce' );
}
if ( ! defined( 'CCATPAYMENTS_JS_PREFIX' ) ) {
    define( 'CCATPAYMENTS_JS_PREFIX', 'ccatpay_for_woocommerce' );
}

/**
 * WC CCat Payment gateway plugin class.
 *
 * @class WC_CCat_Payments
 */
class CCATPAY_Payments {
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
		require_once self::plugin_abspath() . 'includes/shipping/class-ccatpay-shipping-payment-coordinator.php';
		CCATPAY_Shipping_Payment_Coordinator::init();
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
			$methods['ccatpay_shipping_cod']         = 'CCATPAY_Shipping_COD';
			$methods['ccatpay_shipping_711_cod']     = 'CCATPAY_Shipping_711_COD';
			$methods['ccatpay_shipping_prepaid']     = 'CCATPAY_Shipping_Prepaid';
			$methods['ccatpay_shipping_711_prepaid'] = 'CCATPAY_Shipping_711_Prepaid';

			// 冷藏物流方法.
			$methods['ccatpay_shipping_cod_refrigerated']         = 'CCATPAY_Shipping_COD_Refrigerated';
			$methods['ccatpay_shipping_711_cod_refrigerated']     = 'CCATPAY_Shipping_711_COD_Refrigerated';
			$methods['ccatpay_shipping_prepaid_refrigerated']     = 'CCATPAY_Shipping_Prepaid_Refrigerated';
			$methods['ccatpay_shipping_711_prepaid_refrigerated'] = 'CCATPAY_Shipping_711_Prepaid_Refrigerated';

			// 冷凍物流方法.
			$methods['ccatpay_shipping_cod_frozen']         = 'CCATPAY_Shipping_COD_Frozen';
			$methods['ccatpay_shipping_711_cod_frozen']     = 'CCATPAY_Shipping_711_COD_Frozen';
			$methods['ccatpay_shipping_prepaid_frozen']     = 'CCATPAY_Shipping_Prepaid_Frozen';
			$methods['ccatpay_shipping_711_prepaid_frozen'] = 'CCATPAY_Shipping_711_Prepaid_Frozen';
		}

		return $methods;
	}

	/**
	 * Determines if the CCat payment gateway is enabled via settings.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public static function is_ccat_enabled(): bool {
		$is_enabled = get_option( CCATPAYMENTS_PREFIX . '_enable', 'yes' );

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
			'ccatpay_shipping_cod',
			'ccatpay_shipping_711_cod',
			'ccatpay_shipping_prepaid',
			'ccatpay_shipping_711_prepaid',
			'ccatpay_shipping_cod_refrigerated',
			'ccatpay_shipping_cod_frozen',
			'ccatpay_shipping_prepaid_refrigerated',
			'ccatpay_shipping_prepaid_frozen',
			'ccatpay_shipping_711_cod_refrigerated',
			'ccatpay_shipping_711_cod_frozen',
			'ccatpay_shipping_711_prepaid_refrigerated',
			'ccatpay_shipping_711_prepaid_frozen',
		);

		if ( $taiwan_zone_exists ) {
			// 如果台灣區域已存在，獲取該區域.
			$zone = new WC_Shipping_Zone( $taiwan_zone_id );

			// 獲取現有的物流方法.
			$existing_methods    = $zone->get_shipping_methods();
			$existing_method_ids = array();

			// 收集現有物流方法的 ID.
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

			// 添加所有物流方法.
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
		$is_enabled = get_option( CCATPAYMENTS_PREFIX . '_shipping_enable', 'yes' );

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
			$gateways[] = 'CCATPAY_Gateway_Credit_Card';
			$gateways[] = 'CCATPAY_Gateway_Chinatrust';
			$gateways[] = 'CCATPAY_Gateway_Payuni';
			$gateways[] = 'CCATPAY_Gateway_Cvs_Ibon';
			$gateways[] = 'CCATPAY_Gateway_Cvs_Atm';
			// $gateways[] = 'CCATPAY_Gateway_App_Opw';
			// $gateways[] = 'CCATPAY_Gateway_App_Icash';
			// 新增黑貓貨到付款閘道.
			$gateways[] = 'CCATPAY_Gateway_COD_Cash';
			// $gateways[] = 'CCATPAY_Gateway_COD_Mobile';
			$gateways[] = 'CCATPAY_Gateway_COD_711';
			$gateways[] = 'CCATPAY_Gateway_COD_Card';
		}

		return $gateways;
	}

	/**
	 * Plugin includes.
	 */
	public static function includes(): void {
		$is_invoice_enabled = 'yes' === get_option( CCATPAYMENTS_PREFIX . '_invoice_enable', 'no' );
		if ( $is_invoice_enabled ) {
			require_once 'ccat-checkout-block/ccatpay-block-integration-checkout.php';
			require_once 'includes/class-ccatpay-invoice-display.php';
			new CCATPAY_Invoice_Display();
			add_action(
				'woocommerce_blocks_checkout_block_registration',
				function ( $integration_registry ) {
					$integration_registry->register( new CCATPAY_Blocks_Integration() );
				}
			);

			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => CheckoutSchema::IDENTIFIER,
					'namespace'       => CCATPAYMENTS_DOMAIN,
					'data_callback'   => array( __CLASS__, 'get_invoice_data' ),
					'schema_callback' => array( __CLASS__, 'get_invoice_schema' ),
				)
			);
		}
		if ( self::is_shipping_enabled() ) {
			require_once '711-checkout-block/class-ccatpay-711-blocks-integration.php';
			require_once 'includes/class-ccatpay-shipping-display.php';
			new CCATPAY_Shipping_Display();
			add_action(
				'woocommerce_blocks_checkout_block_registration',
				function ( $integration_registry ) {
					$integration_registry->register( new CCATPAY_711_Blocks_Integration() );
				}
			);
		}

		if ( class_exists( 'WC_Payment_Gateway' ) && self::is_ccat_enabled() ) {
			require_once 'includes/class-ccatpay-gateway-abstract.php';
			require_once 'includes/class-ccatpay-gateway-cvs-abstract.php';
			require_once 'includes/class-ccatpay-gateway-credit-card.php';
			require_once 'includes/class-ccatpay-gateway-chinatrust.php';
			require_once 'includes/class-ccatpay-gateway-payuni.php';
			require_once 'includes/class-ccatpay-gateway-cvs-ibon.php';
			require_once 'includes/class-ccatpay-gateway-cvs-atm.php';
			require_once 'includes/class-ccatpay-gateway-app-opw.php';
			require_once 'includes/class-ccatpay-gateway-app-icash.php';
			// 新增黑貓貨到付款閘道.
			require_once 'includes/class-ccatpay-gateway-cod-abstract.php';
			require_once 'includes/class-ccatpay-gateway-cod-cash.php';
			require_once 'includes/class-ccatpay-gateway-cod-card.php';
			require_once 'includes/class-ccatpay-gateway-cod-mobile.php';
			require_once 'includes/class-ccatpay-gateway-cod-711.php';
		}

		// 載入黑貓物流相關類別.
		if ( class_exists( 'WC_Shipping_Method' ) && self::is_shipping_enabled() ) {
			require_once 'includes/shipping/class-ccatpay-shipping-abstract.php';
			require_once 'includes/shipping/class-ccatpay-shipping-cod.php';
			require_once 'includes/shipping/class-ccatpay-shipping-711-cod.php';
			require_once 'includes/shipping/class-ccatpay-shipping-prepaid.php';
			require_once 'includes/shipping/class-ccatpay-shipping-711-prepaid.php';

			// 引入宅配先付款不同溫度類型.
			require_once 'includes/shipping/class-ccatpay-shipping-prepaid-refrigerated.php';
			require_once 'includes/shipping/class-ccatpay-shipping-prepaid-frozen.php';

			// 引入宅配貨到付款不同溫度類型.
			require_once 'includes/shipping/class-ccatpay-shipping-cod-refrigerated.php';
			require_once 'includes/shipping/class-ccatpay-shipping-cod-frozen.php';

			// 引入7-11先付款不同溫度類型.
			require_once 'includes/shipping/class-ccatpay-shipping-711-prepaid-refrigerated.php';
			require_once 'includes/shipping/class-ccatpay-shipping-711-prepaid-frozen.php';

			// 引入7-11貨到付款不同溫度類型.
			require_once 'includes/shipping/class-ccatpay-shipping-711-cod-refrigerated.php';
			require_once 'includes/shipping/class-ccatpay-shipping-711-cod-frozen.php';
		}

		require_once 'includes/class-ccatpay-settings.php';
		CCATPAY_Settings::init();
	}

	public static function get_invoice_data(  ) {
		// todo: 電子發票廠商回呼
		return [];
	}

	public static function get_invoice_schema(  ) {
		// todo: 電子發票廠商回呼
		return [];
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
			require_once 'includes/blocks/class-ccatpay-payments-credit-card-blocks.php';
			require_once 'includes/blocks/class-ccatpay-payments-chinatrust-blocks.php';
			require_once 'includes/blocks/class-ccatpay-payments-payuni-blocks.php';
			require_once 'includes/blocks/class-ccatpay-payments-ibon-blocks.php';
			require_once 'includes/blocks/class-ccatpay-payments-atm-blocks.php';
			require_once 'includes/blocks/class-ccatpay-payments-opw-blocks.php';
			require_once 'includes/blocks/class-ccatpay-payments-icash-blocks.php';
			require_once 'includes/blocks/class-ccatpay-payments-cod-blocks.php';

			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new CCATPAY_Gateway_Credit_Card_Blocks_Support() );
					$payment_method_registry->register( new CCATPAY_Gateway_Chinatrust_Blocks_Support() );
					$payment_method_registry->register( new CCATPAY_Gateway_Payuni_Blocks_Support() );
					$payment_method_registry->register( new CCATPAY_Gateway_Ibon_Blocks_Support() );
					$payment_method_registry->register( new CCATPAY_Gateway_Atm_Blocks_Support() );
					// $payment_method_registry->register( new CCATPAY_Gateway_Opw_Blocks_Support() );
					// $payment_method_registry->register( new CCATPAY_Gateway_Icash_Blocks_Support() );
					$payment_method_registry->register( new CCATPAY_Gateway_COD_Blocks_Support() );
				}
			);
		}
	}
}

CCATPAY_Payments::init();
