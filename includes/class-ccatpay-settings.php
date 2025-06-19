<?php
/**
 * 黑貓物流
 *
 * @package WooCommerceCCatGateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * 定義 WC_CCat_Settings 類別，處理外掛的 WooCommerce 設定頁面
 */
class CCATPAY_Settings {

	/**
	 * 在 WooCommerce 設定頁新增自訂頁籤
	 */
	public static function init() {
		add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_ccat', array( __CLASS__, 'output_settings' ) );
		add_action( 'woocommerce_update_options_ccat', array( __CLASS__, 'save_settings' ) );

		if ( 'yes' !== get_option( CCATPAYMENTS_PREFIX . '_migration_completed' ) ) {
			self::migrate_settings();
			update_option( CCATPAYMENTS_PREFIX . '_migration_completed', 'yes' );
		}
	}

	/**
	 * 新增頁籤名稱到設定 Tabs 列表
	 *
	 * @param array $tabs 已定義的 WooCommerce 設定區分頁.
	 *
	 * @return array
	 */
	public static function add_settings_tab( array $tabs ): array {
		$tabs['ccat'] = __( '黑貓Pay', 'ccat-for-woocommerce');

		return $tabs;
	}

	/**
	 * 定義頁籤的設定欄位
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		return array(
			'section_title' => array(
				'name' => __( '黑貓Pay設定', 'ccat-for-woocommerce'),
				'type' => 'title',
				'desc' => '',
				'id'   => 'wc_ccat_settings_section_title',
			),
			array(
				'name'    => __( '啟用黑貓Pay', 'ccat-for-woocommerce'),
				'type'    => 'checkbox',
				'desc'    => __( '啟用或停用黑貓Pay功能。', 'ccat-for-woocommerce'),
				'id'      => CCATPAYMENTS_PREFIX . '_enable',
				'default' => 'yes',
			),
			array(
				'name'    => __( '啟用電子發票', 'ccat-for-woocommerce'),
				'type'    => 'checkbox',
				'desc'    => __( '啟用或停用電子發票功能。', 'ccat-for-woocommerce'),
				'id'      => CCATPAYMENTS_PREFIX . '_invoice_enable',
				'default' => 'no',
			),
			array(
				'name'    => __( '啟用黑貓物流', 'ccat-for-woocommerce'),
				'type'    => 'checkbox',
				'desc'    => __( '啟用或停用黑貓物流功能。', 'ccat-for-woocommerce'),
				'id'      => CCATPAYMENTS_PREFIX . '_shipping_enable',
				'default' => 'yes',
			),
			array(
				'name'    => __( '測試模式', 'ccat-for-woocommerce'),
				'type'    => 'checkbox',
				'desc'    => __( '啟用測試模式以使用黑貓Pay測試環境，關閉則使用正式環境。', 'ccat-for-woocommerce'),
				'id'      => CCATPAYMENTS_PREFIX . '_test_mode',
				'default' => 'no',
			),
			array(
				'name' => __( '金流代號', 'ccat-for-woocommerce'),
				'type' => 'text',
				'desc' => __( '輸入您的黑貓Pay 金流代號。', 'ccat-for-woocommerce'),
				'id'   => CCATPAYMENTS_PREFIX . '_merchant_id',
			),
			array(
				'name' => __( 'API密碼', 'ccat-for-woocommerce'),
				'type' => 'password',
				'desc' => __( '輸入黑貓Pay 的 API 密碼。', 'ccat-for-woocommerce'),
				'id'   => CCATPAYMENTS_PREFIX . '_api_key',
			),
			array(
				'name' => __( '檢核碼', 'ccat-for-woocommerce'),
				'type' => 'password',
				'desc' => __( '輸入API檢核碼(hash_base)，信用卡線上刷卡使用。', 'ccat-for-woocommerce'),
				'id'   => CCATPAYMENTS_PREFIX . '_chk_code',
			),
			array(
				'name' => __( '測試模式金流代號', 'ccat-for-woocommerce'),
				'type' => 'text',
				'desc' => __( '測試環境用的金流代號，啟用測試模式時有效。', 'ccat-for-woocommerce'),
				'id'   => CCATPAYMENTS_PREFIX . '_test_merchant_id',
			),
			array(
				'name' => __( '測試模式 API 密碼', 'ccat-for-woocommerce'),
				'type' => 'password',
				'desc' => __( '測試環境用的 API 密碼，啟用測試模式時有效。', 'ccat-for-woocommerce'),
				'id'   => CCATPAYMENTS_PREFIX . '_test_api_key',
			),
			array(
				'name' => __( '測試模式檢核碼', 'ccat-for-woocommerce'),
				'type' => 'password',
				'desc' => __( '測試環境用的 API 檢核碼(hash_base)，信用卡線上刷卡使用。', 'ccat-for-woocommerce'),
				'id'   => CCATPAYMENTS_PREFIX . '_test_chk_code',
			),
			array(
				'name' => __( '寄件人姓名', 'ccat-for-woocommerce'),
				'type' => 'text',
				'desc' => __( '請輸入寄件人姓名', 'ccat-for-woocommerce'),
				'id'   => CCATPAYMENTS_PREFIX . '_sender_name',
			),
			array(
				'name' => __( '寄件人電話', 'ccat-for-woocommerce'),
				'type' => 'text',
				'desc' => __( '請輸入寄件人市話', 'ccat-for-woocommerce'),
				'id'   => CCATPAYMENTS_PREFIX . '_sender_tel',
			),
			array(
				'name' => __( '寄件人手機', 'ccat-for-woocommerce'),
				'type' => 'text',
				'desc' => __( '請輸入寄件人手機號碼', 'ccat-for-woocommerce'),
				'id'   => CCATPAYMENTS_PREFIX . '_sender_mobile',
			),
			array(
				'name' => __( '寄件人地址', 'ccat-for-woocommerce'),
				'type' => 'text',
				'desc' => __( '請輸入寄件人詳細地址', 'ccat-for-woocommerce'),
				'id'   => CCATPAYMENTS_PREFIX . '_sender_address',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wc_ccat_settings_section_end',
			),
		);
	}

	/**
	 * 輸出設定欄位
	 */
	public static function output_settings() {
		woocommerce_admin_fields( self::get_settings() );
	}

	/**
	 * 儲存設定欄位
	 */
	public static function save_settings() {
		woocommerce_update_options( self::get_settings() );
	}

	/**
	 * 遷移黑貓Pay設定值從舊ID到新ID
	 *
	 * @return void
	 */
	public static function migrate_settings() {
		$settings_mapping = array(
			CCATPAYMENTS_PREFIX . '_enable'           => 'wc_ccat_enable',
			CCATPAYMENTS_PREFIX . '_invoice_enable'   => 'wc_ccat_invoice_enable',
			CCATPAYMENTS_PREFIX . '_shipping_enable'  => 'wc_ccat_shipping_enable',
			CCATPAYMENTS_PREFIX . '_test_mode'        => 'wc_ccat_test_mode',
			CCATPAYMENTS_PREFIX . '_merchant_id'      => 'wc_ccat_merchant_id',
			CCATPAYMENTS_PREFIX . '_api_key'          => 'wc_ccat_api_key',
			CCATPAYMENTS_PREFIX . '_chk_code'         => 'wc_ccat_chk_code',
			CCATPAYMENTS_PREFIX . '_test_merchant_id' => 'wc_ccat_test_merchant_id',
			CCATPAYMENTS_PREFIX . '_test_api_key'     => 'wc_ccat_test_api_key',
			CCATPAYMENTS_PREFIX . '_test_chk_code'    => 'wc_ccat_test_chk_code',
			CCATPAYMENTS_PREFIX . '_sender_name'      => 'woocommerce_ccat_sender_name',
			CCATPAYMENTS_PREFIX . '_ccat_sender_tel'  => 'woocommerce_ccat_sender_tel',
			CCATPAYMENTS_PREFIX . '_sender_mobile'    => 'woocommerce_ccat_sender_mobile',
			CCATPAYMENTS_PREFIX . '_sender_address'   => 'woocommerce_ccat_sender_address',
		);

		// 遍歷每個對應關係，檢查並遷移設定值.
		foreach ( $settings_mapping as $new_id => $old_id ) {
			$old_value = get_option( $old_id );

			if ( false !== $old_value ) {
				$new_value = get_option( $new_id );

				// 如果新值不存在或為空，則遷移舊值到新ID.
				if ( false === $new_value || '' === $new_value ) {
					update_option( $new_id, $old_value );
				}
			}
		}
	}
}
