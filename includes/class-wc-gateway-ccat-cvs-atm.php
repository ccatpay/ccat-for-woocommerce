<?php
/**
 * WC_Gateway_CCat class
 *
 * @author   sakilu <brian@sakilu.com>
 * @package  WooCommerce CCat Payments Gateway
 * @since    1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

require_once 'class-wc-gateway-ccat-abstract.php';

/**
 * CCat Gateway.
 *
 * @class    WC_Gateway_CCat_Cvs_Ibon
 * @version  1.0
 */
class WC_Gateway_CCat_Cvs_Atm extends WC_Gateway_CCat_Cvs_Abstract
{

    /**
     * Unique id for the gateway.
     *
     * @var string
     */
    public $id = 'ccat_payment_cvs_atm';

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {

        $this->title = __('黑貓Pay - ATM繳款', 'woocommerce-gateway-ccat');
        $this->description = __('使用黑貓Pay ATM，付款更安心。', 'woocommerce-gateway-ccat');
        add_action('woocommerce_thankyou', array(
            $this,
            'display_virtual_account_details',
        ));
        add_action('woocommerce_view_order', array(
            $this,
            'display_virtual_account_details',
        ));
        add_action('woocommerce_admin_order_data_after_order_details',
            array(
                $this,
                'display_virtual_account_details',
            ));

        parent::__construct();
    }

    public function display_virtual_account_details($order_id)
    {
        $order = wc_get_order($order_id);
        if ($order->get_payment_method() !== $this->id) {
            return;
        }

        $virtual_account = $order->get_meta(self::ATM_VIRTUAL_ACCOUNT);
        $payment_deadline = $order->get_meta(self::ATM_EXPIRE_DATA);
        $bill_amount = $order->get_meta(self::ATM_BILL_AMOUNT);

        if ($virtual_account && $payment_deadline) {
            $html = '';
            $current_action = current_filter();
            if ($current_action !== 'woocommerce_admin_order_data_after_order_details') {
                $html .= '<h2>' . esc_html__('感謝訂購 請到ATM繳款', 'woocommerce-gateway-ccat') . '</h2>';
            }

            $html .= '<p>' . esc_html(sprintf(__('銀行代號: 玉山銀行 %s', 'woocommerce-gateway-ccat'), '808')) . '</p>';
            $html .= '<p>' . esc_html(sprintf(__('轉帳帳號: %s', 'woocommerce-gateway-ccat'), $virtual_account)) . '</p>';
            $html .= '<p>' . esc_html(sprintf(__('付款期限: %s', 'woocommerce-gateway-ccat'), $payment_deadline)) . '</p>';
            $html .= '<p>' . esc_html(sprintf(__('繳款金額: %d', 'woocommerce-gateway-ccat'), $bill_amount)) . '</p>';
            echo $html;
        }
    }


    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('啟用', 'woocommerce-gateway-ccat'),
                'type' => 'checkbox',
                'label' => __('啟用', 'woocommerce-gateway-ccat'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('付款標題', 'woocommerce-gateway-ccat'),
                'type' => 'text',
                'description' => __('使用者選擇付款時顯示的文字', 'woocommerce-gateway-ccat'),
                'default' => __('黑貓Pay - ATM繳款', 'woocommerce-gateway-ccat'),
                'desc_tip' => true,
            ),
        );
    }

    /**
     * Retrieves the configured payment type for the payment gateway.
     *
     * @return string The payment type as configured in the gateway settings.
     */
    public function payment_type(): string
    {
        return '1';
    }

    /**
     * Retrieves the configured acquirer type for the payment gateway.
     *
     * @return string The acquirer type as configured in the gateway settings.
     */
    public function acquirer_type(): string
    {
        return '0';
    }
}
