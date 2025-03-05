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
 * @class    WC_Gateway_CCat_Cvs_Barcode
 * @version  1.0
 */
class WC_Gateway_CCat_Cvs_Barcode extends WC_Gateway_CCat_Cvs_Abstract
{

    /**
     * Unique id for the gateway.
     *
     * @var string
     */
    public $id = 'ccat_payment_cvs_barcode';

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {

        $this->title = __('黑貓Pay - 三段式條碼', 'woocommerce-gateway-ccat');
        $this->description = __('使用黑貓Pay 三段式條碼，付款更安心。', 'woocommerce-gateway-ccat');
        // 在結帳感謝頁面和訂單詳情中附加條碼相關訊息
        add_action('woocommerce_thankyou', array($this, 'display_barcode_details'));
        add_action('woocommerce_view_order', array($this, 'display_barcode_details'));

        // 在 WooCommerce 後台訂單詳情顯示條碼資料
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'display_barcode_details'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_barcode_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_barcode_scripts'));

        parent::__construct();
    }

    public function enqueue_barcode_scripts()
    {
        if (is_order_received_page() || is_view_order_page() || is_admin()) {
            wp_enqueue_script(
                'jsbarcode',
                'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js',
                array(),
                '3.11.6',
                true
            );
        }
    }

    /**
     * 顯示條碼付款資訊
     */
    public function display_barcode_details($order_id)
    {
        $order = wc_get_order($order_id);

        if ($order->get_payment_method() !== $this->id) {
            return;
        }

        $barcode1 = $order->get_meta(self::ATM_BILL_BARCODE_1);
        $barcode2 = $order->get_meta(self::ATM_BILL_BARCODE_2);
        $barcode3 = $order->get_meta(self::ATM_BILL_BARCODE_3);
        $payment_deadline = $order->get_meta(self::ATM_EXPIRE_DATA);
        $bill_amount = $order->get_meta(self::ATM_BILL_AMOUNT);
        if ($barcode1 && $barcode2 && $barcode3 && $payment_deadline && $bill_amount) {
            $html = '';
            $current_action = current_filter();

            if ($current_action !== 'woocommerce_admin_order_data_after_order_details') {
                $html .= '<h2>' . esc_html__('感謝您的訂購，請使用三段條碼付款', 'woocommerce-gateway-ccat') . '</h2>';
            }

            // 條碼容器
            $html .= '<div class="barcode-container">';
            $html .= '<div class="barcode-item">';
            $html .= '<p>' . esc_html__('條碼 1:', 'woocommerce-gateway-ccat') . '</p>';
            $html .= '<svg id="barcode1" data-value="' . esc_attr($barcode1) . '"></svg>';
            $html .= '</div>';

            $html .= '<div class="barcode-item">';
            $html .= '<p>' . esc_html__('條碼 2:', 'woocommerce-gateway-ccat') . '</p>';
            $html .= '<svg id="barcode2" data-value="' . esc_attr($barcode2) . '"></svg>';
            $html .= '</div>';

            $html .= '<div class="barcode-item">';
            $html .= '<p>' . esc_html__('條碼 3:', 'woocommerce-gateway-ccat') . '</p>';
            $html .= '<svg id="barcode3" data-value="' . esc_attr($barcode3) . '"></svg>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '<p>' . esc_html(sprintf(__('付款期限: %s', 'woocommerce-gateway-ccat'), $payment_deadline)) . '</p>';
            $html .= '<p>' . esc_html(sprintf(__('繳款金額: %d 元', 'woocommerce-gateway-ccat'), $bill_amount)) . '</p>';

            // 加入條碼生成的 JavaScript
            $html .= '<script type="text/javascript">
            jQuery(document).ready(function($) {
                if (typeof JsBarcode !== "undefined") {
                    JsBarcode("#barcode1", $("#barcode1").data("value"), {
                        format: "code128",
                        width: 2,
                        height: 100,
                        displayValue: true
                    });
                    JsBarcode("#barcode2", $("#barcode2").data("value"), {
                        format: "code128",
                        width: 2,
                        height: 100,
                        displayValue: true
                    });
                    JsBarcode("#barcode3", $("#barcode3").data("value"), {
                        format: "code128",
                        width: 2,
                        height: 100,
                        displayValue: true
                    });
                }
            });
        </script>';

            // 加入樣式
            $html .= '<style>
            .barcode-container {
                margin: 20px 0;
            }
            .barcode-item {
                margin-bottom: 15px;
            }
            .barcode-item svg {
                max-width: 100%;
                height: auto;
            }
        </style>';

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
                'default' => __('黑貓Pay - 三段式條碼', 'woocommerce-gateway-ccat'),
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
        return '2';
    }
}