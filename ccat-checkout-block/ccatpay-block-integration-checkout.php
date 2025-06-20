<?php
/**
 * CCATPAY_Gateway_Abstract class
 *
 * @author   sakilu <brian@sakilu.com>
 * @package  WooCommerce CCat Payments Gateway
 * @since    1.0.0
 */

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * A class responsible for integrating custom blocks into WordPress.
 *
 * This class handles the registration, initialization, and setup of scripts
 * required for managing custom blocks in both the editor and frontend contexts.
 */
class CCATPAY_Blocks_Integration implements IntegrationInterface {

	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'ccat-block';
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
	 * Registers the main integration script for the plugin in WordPress.
	 *
	 * This function sets up the script required for the plugin's integration,
	 * including its dependencies, versioning, and loading in the footer.
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
            CCATPAYMENTS_JS_PREFIX.'ccat-blocks-integration', // Updated script handle.
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles(): array {
		return array( CCATPAYMENTS_JS_PREFIX.'ccat-blocks-integration', CCATPAYMENTS_JS_PREFIX.'ccat-blocks-frontend' );
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles(): array {
		return array( CCATPAYMENTS_JS_PREFIX.'ccat-blocks-integration', CCATPAYMENTS_JS_PREFIX.'ccat-block-editor' );
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data(): array {
		return array(
			'ccat_block-active' => true,
		);
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
            CCATPAYMENTS_JS_PREFIX.'ccat-blocks-editor', // Updated script handle.
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
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
            CCATPAYMENTS_JS_PREFIX.'ccat-blocks-frontend', // Updated script handle.
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		// 將數據本地化到腳本中，確保 nonce 可用.
		wp_localize_script(
            CCATPAYMENTS_JS_PREFIX.'ccat711-blocks-integration',
			'ccat711BlockData',
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
}
