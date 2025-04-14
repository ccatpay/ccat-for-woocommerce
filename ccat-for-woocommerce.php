<?php
/**
 * Plugin Name: ccat-for-woocommerce
 * Plugin URI: https://www.ccat.com.tw/
 * Description: Adds the CCat Payments gateway to your WooCommerce website.
 * Version: 1.10.3
 *
 * Text Domain: ccat-for-woocommerce
 *
 * Requires at least: 6.6
 * Tested up to: 6.7
 *
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
	define( 'WC_CCAT_PAYMENTS_VERSION', '1.10.3' );
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

		// Registers WooCommerce Blocks integration.
		add_action(
			'woocommerce_blocks_loaded',
			array(
				__CLASS__,
				'woocommerce_gateway_ccat_woocommerce_block_support',
			)
		);
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
			// $gateways[] = 'WC_Gateway_CCat_Cvs_Atm';
			// $gateways[] = 'WC_Gateway_CCat_Cvs_Barcode';
			$gateways[] = 'WC_Gateway_CCat_App_Opw';
			$gateways[] = 'WC_Gateway_CCat_App_Icash';
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
			// require_once 'includes/class-wc-gateway-ccat-cvs-atm.php';
			// require_once 'includes/class-wc-gateway-ccat-cvs-barcode.php';
			require_once 'includes/class-wc-gateway-ccat-app-opw.php';
			require_once 'includes/class-wc-gateway-ccat-app-icash.php';
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
			// require_once 'includes/blocks/class-wc-ccat-payments-atm-blocks.php';
			// require_once 'includes/blocks/class-wc-ccat-payments-barcode-blocks.php';
			require_once 'includes/blocks/class-wc-ccat-payments-opw-blocks.php';
			require_once 'includes/blocks/class-wc-ccat-payments-icash-blocks.php';

			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new WC_Gateway_CCat_Credit_Card_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_CCat_Chinatrust_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_CCat_Payuni_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_CCat_Ibon_Blocks_Support() );
					// $payment_method_registry->register( new WC_Gateway_CCat_Atm_Blocks_Support() );
					// $payment_method_registry->register( new WC_Gateway_CCat_Barcode_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_CCat_Opw_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_CCat_Icash_Blocks_Support() );
				}
			);
		}
	}
}

WC_CCat_Payments::init();
