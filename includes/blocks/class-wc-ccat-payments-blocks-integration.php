<?php

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

define( 'ORDD_BLOCK_VERSION', '1.0.0' );

class WC_Gateway_CCat_Blocks_Integration implements IntegrationInterface {

	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'date-field';
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {
		$this->register_block_frontend_scripts();
		$this->register_block_editor_scripts();
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'checkout-block-frontend' ); // Updated script handle
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array( 'date-field-block-editor' ); // Updated script handle
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return array();
	}

	/**
	 * Register scripts for date field block editor.
	 *
	 * @return void
	 */
	public function register_block_editor_scripts() {
		$script_path       = '/build/index.js';
		$script_url        = WC_CCat_Payments::plugin_url() . $script_path;
		$script_asset_path = WC_CCat_Payments::plugin_url() . '/build/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_asset_path ),
			);

		wp_register_script(
			'date-field-block-editor', // Updated script handle
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
		$script_path       = '/build/checkout-block-frontend.js';
		$script_url        = WC_CCat_Payments::plugin_url() . $script_path;
		$script_asset_path = WC_CCat_Payments::plugin_url() . '/build/checkout-block-frontend.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_asset_path ),
			);

		wp_register_script(
			'checkout-block-frontend',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
	}

	/**
	 * Get the file modified time as a cache buster if we're in dev mode.
	 *
	 * @param string $file Local path to the file.
	 *
	 * @return string The cache buster value to use for the given file.
	 */
	protected function get_file_version( $file ) {
		return uniqid();

//        if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG && file_exists($file)) {
//            return filemtime($file);
//        }
//        return ORDD_BLOCK_VERSION;
	}

}