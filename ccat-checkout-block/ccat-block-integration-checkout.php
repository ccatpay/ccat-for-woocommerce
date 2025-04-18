<?php

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

class Ccat_Blocks_Integration implements IntegrationInterface {

	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
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
			'ccat-blocks-integration', // Updated script handle
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
//        wp_set_script_translations(
//            'ccat-blocks-integration',
//            'nx_ccat_block',
//            __DIR__ . '/languages'
//        );
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'ccat-blocks-integration', 'ccat-blocks-frontend' );
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array( 'ccat-blocks-integration', 'ccat-block-editor' );
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return array(
			'ccat_block-active' => true,
		);
	}

	/**
	 * Register scripts for date field block editor.
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
			'ccat-blocks-editor', // Updated script handle
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
//        wp_set_script_translations(
//            'ccat-blocks-editor',
//            'nx_ccat_block',
//            __DIR__ . '/languages'
//        );
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
			'ccat-blocks-frontend', // Updated script handle
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
//        wp_set_script_translations(
//            'ccat-blocks-frontend',
//            'nx_ccat_block',
//            __DIR__ . '/languages'
//        );
	}

	/**
	 * Get the file modified time as a cache buster if we're in dev mode.
	 *
	 * @param string $file Local path to the file.
	 *
	 * @return string The cache buster value to use for the given file.
	 */
	protected function get_file_version() {
		return '1.0.0';
	}
}