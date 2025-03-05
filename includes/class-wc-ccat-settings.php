<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * 定義 WC_CCat_Settings 類別，處理外掛的 WooCommerce 設定頁面
 */
class WC_CCat_Settings
{

    /**
     * 在 WooCommerce 設定頁新增自訂頁籤
     */
    public static function init()
    {
        add_filter('woocommerce_settings_tabs_array', array(__CLASS__, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_ccat', array(__CLASS__, 'output_settings'));
        add_action('woocommerce_update_options_ccat', array(__CLASS__, 'save_settings'));
    }

    /**
     * 新增頁籤名稱到設定 Tabs 列表
     *
     * @param array $tabs 已定義的 WooCommerce 設定區分頁.
     *
     * @return array
     */
    public static function add_settings_tab(array $tabs): array
    {
        $tabs['ccat'] = __('黑貓Pay', 'woocommerce-gateway-ccat');

        return $tabs;
    }

    /**
     * 定義頁籤的設定欄位
     *
     * @return array
     */
    public static function get_settings(): array
    {
        return array(
            'section_title' => array(
                'name' => __('黑貓Pay設定', 'woocommerce-gateway-ccat'),
                'type' => 'title',
                'desc' => '',
                'id' => 'wc_ccat_settings_section_title',
            ),
            array(
                'name' => __('啟用黑貓Pay', 'woocommerce-gateway-ccat'),
                'type' => 'checkbox',
                'desc' => __('啟用或停用黑貓Pay功能。', 'woocommerce-gateway-ccat'),
                'id' => 'wc_ccat_enable',
                'default' => 'yes',
            ),
            array(
                'name' => __('啟用電子發票', 'woocommerce-gateway-ccat'),
                'type' => 'checkbox',
                'desc' => __('啟用或停用電子發票功能。', 'woocommerce-gateway-ccat'),
                'id' => 'wc_ccat_invoice_enable',
                'default' => 'no',
            ),
            array(
                'name' => __('測試模式', 'woocommerce-gateway-ccat'),
                'type' => 'checkbox',
                'desc' => __('啟用測試模式以使用測試環境設定（商家 ID 和 API 密鑰將會視為測試環境資料）。', 'woocommerce-gateway-ccat'),
                'id' => 'wc_ccat_test_mode',
                'default' => 'no',
            ),
            array(
                'name' => __('商家 ID', 'woocommerce-gateway-ccat'),
                'type' => 'text',
                'desc' => __('輸入您的商家 ID。', 'woocommerce-gateway-ccat'),
                'id' => 'wc_ccat_merchant_id',
            ),
            array(
                'name' => __('API 密鑰', 'woocommerce-gateway-ccat'),
                'type' => 'password',
                'desc' => __('輸入黑貓Pay的 API 密鑰。', 'woocommerce-gateway-ccat'),
                'id' => 'wc_ccat_api_key',
            ),
            array(
                'name' => __('檢核碼', 'woocommerce-gateway-ccat'),
                'type' => 'password',
                'desc' => __('輸入 API 檢核碼。', 'woocommerce-gateway-ccat'),
                'id' => 'wc_ccat_chk_code',
            ),
            array(
                'name' => __('測試模式商家 ID', 'woocommerce-gateway-ccat'),
                'type' => 'text',
                'desc' => __('測試環境用的商家 ID。啟用測試模式時有效。', 'woocommerce-gateway-ccat'),
                'id' => 'wc_ccat_test_merchant_id',
            ),
            array(
                'name' => __('測試模式 API 密鑰', 'woocommerce-gateway-ccat'),
                'type' => 'password',
                'desc' => __('測試環境用的 API 密鑰。啟用測試模式時有效。', 'woocommerce-gateway-ccat'),
                'id' => 'wc_ccat_test_api_key',
            ),
            array(
                'name' => __('測試模式檢核碼', 'woocommerce-gateway-ccat'),
                'type' => 'password',
                'desc' => __('測試環境用的 API 檢核碼。', 'woocommerce-gateway-ccat'),
                'id' => 'wc_ccat_test_chk_code',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'wc_ccat_settings_section_end',
            ),
        );
    }

    /**
     * 輸出設定欄位
     */
    public static function output_settings()
    {
        woocommerce_admin_fields(self::get_settings());
    }

    /**
     * 儲存設定欄位
     */
    public static function save_settings()
    {
        woocommerce_update_options(self::get_settings());
    }
}