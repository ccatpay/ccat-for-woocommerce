<?php
/**
 * 黑貓物流
 *
 * @package WooCommerceCCatGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_CCat_Shipping_Display class handles the display of electronic invoice information
 * both on the frontend and backend order detail pages.
 */
class CCATPAY_Shipping_Display
{

    /**
     * 黑貓物流訂單託運單號元數據鍵值
     */
    const META_OBT_NUMBER = '_ccat_shipping_obt_number';

    /**
     * 黑貓物流訂單檔案編號元數據鍵值
     */
    const META_FILE_NO = '_ccat_shipping_file_no';

    /**
     * 黑貓物流訂單列印狀態元數據鍵值
     */
    const META_PRINTED = '_ccat_shipping_printed';


    /**
     * 台北時區名稱
     */
    const TAIPEI_TIMEZONE = 'Asia/Taipei';

    /**
     * 初始化 hooks
     */
    public function __construct()
    {
        // 後台訂單詳細頁面.
        add_action(
            'woocommerce_admin_order_data_after_shipping_address',
            array(
                $this,
                'display_admin_shipping_info',
            )
        );

        // 註冊和加載後台 JS 和 CSS.
        add_action('admin_enqueue_scripts', array($this, 'register_admin_scripts'));

        // 註冊變更門市的 Ajax 處理.
        add_action('wp_ajax_' . CCATPAYMENTS_PREFIX . '_store_selection_url', array($this, 'handle_store_selection_url'));

        // 註冊儲存門市的 Ajax 處理.
        add_action('wp_ajax_' . CCATPAYMENTS_PREFIX . '_save_store_ajax', array($this, 'handle_save_store_ajax'));

        // 註冊建立物流訂單的 Ajax 處理.
        add_action('wp_ajax_' . CCATPAYMENTS_PREFIX . '_create_logistics_order', array($this, 'handle_create_logistics_order'));

        // 註冊下載託運單的 Ajax 處理.
        add_action('wp_ajax_' . CCATPAYMENTS_PREFIX . '_download_shipping_label', array($this, 'handle_download_shipping_label'));
    }

    /**
     * 處理建立物流訂單的 AJAX 請求
     */
    public function handle_create_logistics_order()
    {
        // 驗證 nonce.
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ccat-logistics-nonce')) {
            wp_send_json_error(
                array(
                    'message' => esc_html__('安全驗證失敗', 'ccat-for-woocommerce'),
                )
            );
            wp_die();
        }

        // 獲取訂單 ID.
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(array('message' => __('無效的訂單 ID', 'ccat-for-woocommerce')));

            return;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(array('message' => __('找不到此訂單', 'ccat-for-woocommerce')));

            return;
        }
        $shipping_methods = $order->get_shipping_methods();
        foreach ($shipping_methods as $shipping_method) {
            $shipping_method_id = $shipping_method->get_method_id();
            break; // 只取第一個運送方法.
        }
        if (empty($shipping_method_id)) {
            wp_send_json_error(array('message' => __('找不到配送方式', 'ccat-for-woocommerce')));

            return;
        }
        // 呼叫 API 建立物流訂單.
        $delivery_time = isset($_POST['delivery_time']) ? sanitize_text_field(wp_unslash($_POST['delivery_time'])) : '04';
        $print_obt_type = isset($_POST['print_obt_type']) ? sanitize_text_field(wp_unslash($_POST['print_obt_type'])) : '01';
        $result = $this->create_logistics_order($order, $delivery_time, $print_obt_type);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));

            return;
        }

        // 處理 API 回應.
        if ('Y' === $result['IsOK']) {
            // 保存託運單號和檔案編號.
            $obt_number = $result['Data']['Orders'][0]['OBTNumber'];
            $file_no = $result['Data']['FileNo'];

            // 使用 update_post_meta 確保元數據正確保存.
            $order->update_meta_data(self::META_OBT_NUMBER, $obt_number);
            $order->update_meta_data(self::META_FILE_NO, $file_no);
            $order->update_meta_data(self::META_PRINTED, 'yes');
            $order->save();

            // 添加訂單備註.
            $order->add_order_note(
                sprintf(
                /* translators: %s: 黑貓物流託運單號 */
                    __('黑貓物流託運單已建立，單號: %s', 'ccat-for-woocommerce'),
                    $obt_number
                ),
                false, // 不顯示給客戶.
                true // 由系統新增.
            );

            wp_send_json_success(
                array(
                    'message' => __('物流託運單建立成功', 'ccat-for-woocommerce'),
                    'obt_number' => $obt_number,
                    'file_no' => $file_no,
                )
            );
        } else {
            wp_send_json_error(
                array(
                    'message' => sprintf(
                    /* translators: %s: API 錯誤訊息 */
                        __('建立物流託運單失敗: %s', 'ccat-for-woocommerce'),
                        $result['Message']
                    ),
                )
            );
        }
    }

    /**
     * 呼叫黑貓物流 API 建立物流訂單
     *
     * @param WC_Order $order 訂單物件.
     * @param string $delivery_time 希望配達時段 (宅配用)
     * @param string $print_obt_type 託運單類別
     *
     * @return array|WP_Error API 回應或錯誤
     */
    private function create_logistics_order(WC_Order $order, $delivery_time = '04', $print_obt_type = '01')
    {
        // 從設定獲取 API 資訊.
        $api_data = CCATPAY_711_Blocks_Integration::get_api_data();
        $service_id = $api_data[2];
        $api_token = $api_data[0];
        $api_url = $api_data[1];

        if (empty($service_id) || empty($api_token) || empty($api_url)) {
            return new WP_Error('invalid_api_settings', __('黑貓物流 API 設定不完整', 'ccat-for-woocommerce'));
        }

        // 組裝 API 請求資料.
        $shipping_method_id = '';
        $shipping_methods = $order->get_shipping_methods();
        foreach ($shipping_methods as $shipping_method) {
            $shipping_method_id = $shipping_method->get_method_id();
            break; // 只取第一個運送方法.
        }

        // 判斷是否為 7-11 取貨.
        $is_711 = strpos($shipping_method_id, '711') !== false;

        // 依據運送方式設定溫層.
        $thermosphere = '0001'; // 預設常溫.
        if (strpos($shipping_method_id, 'refrigerated') !== false) {
            $thermosphere = '0002'; // 冷藏.
        } elseif (strpos($shipping_method_id, 'frozen') !== false) {
            $thermosphere = '0003'; // 冷凍.
        }

        // 設定商品規格 (依據訂單總重量或體積).
        $spec = '0001'; // 預設 60cm.

        // 付款方式.
        $payment_method = $order->get_payment_method();
        $is_cod = strpos($payment_method, 'cod') !== false;
        $is_collection = $is_cod ? 'Y' : 'N';

        // 是否為代收貨款、代收金額.
        $is_freight = 'N';
        $collection_amount = 0;
        if ('Y' === $is_collection) {
            $collection_amount = $order->get_total();
        }

        // 收件人資訊.從shipping取得.
        $recipient_name = $order->get_shipping_last_name() . $order->get_shipping_first_name();
        $recipient_tel = $order->get_shipping_phone();
        $recipient_mobile = $order->get_shipping_phone();
        $recipient_city = $order->get_shipping_city();
        $recipient_state = $order->get_shipping_state();
        $recipient_postcode = $order->get_shipping_postcode();
        $recipient_address = $order->get_shipping_address_1() . $order->get_shipping_address_2();

        // 組合完整收件人地址
        $recipient_address = $recipient_postcode  . $recipient_state . $recipient_city . $recipient_address;

        // 寄件人資訊 (從店家設定取得).
        $sender_name = get_option(CCATPAYMENTS_PREFIX . '_sender_name', '');
        $sender_tel = get_option(CCATPAYMENTS_PREFIX . '_sender_tel', '');
        $sender_mobile = get_option(CCATPAYMENTS_PREFIX . '_sender_mobile', '');
        $sender_address = get_option(CCATPAYMENTS_PREFIX . '_sender_address', '');

        // 設定台北時區.
        $taipei_tz = new DateTimeZone(self::TAIPEI_TIMEZONE);
        $today = new DateTime('now', $taipei_tz);

        // 出貨日期和希望配達日期 (使用台北時區).
        $day_after_tomorrow = clone $today;
        $day_after_tomorrow->add(new DateInterval('P1D'));

        // 如果是星期日，則順延到星期一
        if ('0' === $day_after_tomorrow->format('w')) {
            $day_after_tomorrow->add(new DateInterval('P1D'));
        }

        // 取得訂單商品資訊.
        $product_name = '';
        $items = $order->get_items();
        if (!empty($items)) {
            $first_item = reset($items);
            $product_name = $first_item->get_name();
            // 限制商品名稱長度為 20 字.
            if (mb_strlen($product_name) > 20) {
                $product_name = mb_substr($product_name, 0, 19) . '…';
            }
        }

        // 組裝訂單資料.
        if ($is_711) {
            // 獲取收件門市資訊.
            $store_id = $order->get_meta(CCATPAY_Gateway_Abstract::META_STORE_ID);
            if (empty($store_id)) {
                return new WP_Error('missing_store_id', __('找不到 7-11 門市資訊', 'ccat-for-woocommerce'));
            }

            // 7-11 到店託運單資料
            $order_data = array(
                'OBTNumber' => '',
                'OrderId' => $order->get_order_number(),
                'Thermosphere' => $thermosphere,
                'Spec' => $spec,
                'ReceiveStoreId' => $store_id,
                'RecipientName' => $recipient_name,
                'RecipientTel' => $recipient_tel,
                'RecipientMobile' => $recipient_mobile,
                'SenderName' => $sender_name,
                'SenderTel' => $sender_tel,
                'SenderMobile' => $sender_mobile,
                'SenderAddress' => $sender_address,
                'IsCollection' => $is_collection,
                'CollectionAmount' => intval($collection_amount),
                'FBName' => substr(get_bloginfo('name'), 0, 6),
                /* translators: %s: 訂單編號 */
                'Memo' => sprintf(__('訂單編號: %s', 'ccat-for-woocommerce'), $order->get_order_number()),
            );

            // API 請求資料.
            $request_data = array(
                'ServiceId' => $service_id,
                'PrintOBTType' => $print_obt_type, // 使用傳入的託運單類別
                'Orders' => array($order_data),
            );

            // 發送 API 請求 - 使用 7-11 到店託運單 API.
            $response = wp_remote_post(
                $api_url . 'api/Logistics/PrintOBTByB2S',
                array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $api_token,
                    ),
                    'body' => wp_json_encode($request_data),
                    'timeout' => 120,
                )
            );
        } else {
            // 宅配託運單資料.
            $order_data = array(
                'OBTNumber' => '',
                'OrderId' => sprintf('TCAT%s%d', date('Ymd'), $order->get_order_number()),
                'Thermosphere' => $thermosphere,
                'Spec' => $spec,
                'RecipientName' => $recipient_name,
                'RecipientTel' => $recipient_tel,
                'RecipientMobile' => $recipient_mobile,
                'RecipientAddress' => $recipient_address,
                'SenderName' => $sender_name,
                'SenderTel' => $sender_tel,
                'SenderMobile' => $sender_mobile,
                'SenderAddress' => $sender_address,
                'ShipmentDate' => $today->format('Ymd'),
                'DeliveryDate' => $day_after_tomorrow->format('Ymd'),
                'DeliveryTime' => $delivery_time, // 使用傳入的希望配達時段
                'IsFreight' => $is_freight,
                'IsCollection' => $is_collection,
                'CollectionAmount' => $collection_amount,
                'IsSwipe' => strpos($shipping_method_id, 'card') !== false ? 'Y' : 'N',
                'IsMobilePay' => strpos($shipping_method_id, 'mobile') !== false ? 'Y' : 'N',
                'IsDeclare' => 'N',
                'DeclareAmount' => 0,
                'ProductTypeId' => '0015', // 一般食品.
                'ProductName' => $product_name,
                /* translators: %s: 訂單編號 */
                'Memo' => sprintf(__('訂單編號: %s', 'ccat-for-woocommerce'), $order->get_order_number()),
            );

            // API 請求資料.
            $request_data = array(
                'ServiceId' => $service_id,
                'PrintOBTType' => $print_obt_type, // 使用傳入的託運單類別
                'Orders' => array($order_data),
            );

            // 發送 API 請求 - 使用宅配託運單 API.
            $response = wp_remote_post(
                $api_url . 'api/Logistics/PrintOBT',
                array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $api_token,
                    ),
                    'body' => wp_json_encode($request_data),
                    'timeout' => 120,
                )
            );
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if (200 !== $response_code) {
            $response_body = wp_remote_retrieve_body($response);
            $error_message = '';

            // 嘗試解析錯誤訊息.
            if (!empty($response_body)) {
                $response_data = json_decode($response_body, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($response_data['Message'])) {
                    $error_message = $response_data['Message'];
                } else {
                    $error_message = $response_body;
                }
            }

            // 記錄請求數據以便除錯.
            $request_body = wp_json_encode($request_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            return new WP_Error(
                'api_error',
                sprintf(
                /* translators: %1$d: API 回應的狀態碼, %2$s: API 錯誤訊息, %3$s: 請求數據 */
                    __('API 請求失敗 (狀態碼: %1$d, 訊息: %2$s) 請求數據: %3$s', 'ccat-for-woocommerce'),
                    $response_code,
                    $error_message,
                    $request_body
                )
            );
        }

        $result = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('api_response_error', __('API 回應格式無效', 'ccat-for-woocommerce'));
        }

        return $result;
    }

    /**
     * 處理商店選擇跳轉 URL 請求
     *
     * 驗證請求的安全性，並通過 API 創建地圖選擇頁面跳轉的回調 URL。
     * 支援存儲臨時變數以供回調時使用。
     * 如果請求或 API 操作失敗，則返回錯誤響應
     */
    public function handle_store_selection_url()
    {
        // 驗證 nonce.
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ccat-logistics-nonce')) {
            wp_send_json_error(
                array(
                    'message' => esc_html__('安全驗證失敗', 'ccat-for-woocommerce'),
                )
            );
            wp_die();
        }

        // 獲取運送方式.
        $shipping_method = isset($_POST['shippingMethod']) ? sanitize_text_field(wp_unslash($_POST['shippingMethod'])) : '';
        $store_category = isset($_POST['storeCategory']) ? sanitize_text_field(wp_unslash($_POST['storeCategory'])) : '';

        CCATPAY_711_Blocks_Integration::openMapForStore($store_category, $shipping_method);
        wp_die();
    }


    /**
     * 顯示物流按鈕
     *
     * @param WC_Order $order 訂單資訊.
     */
    public function display_admin_shipping_info(WC_Order $order)
    {
        $shipping_method = $order->get_shipping_method();

        // 判斷是否為黑貓物流.
        if ($this->is_ccat_shipping($order)) {
            // 檢查是否已列印託運單.
            $has_printed = $order->get_meta(self::META_PRINTED) === 'yes';
            // 檢查付款方式是否為貨到付款.
            $payment_method = $order->get_payment_method();
            $is_cod = strpos($payment_method, 'cod') !== false;

            // 檢查訂單是否已付款（如果不是貨到付款）.
            $is_paid = $order->is_paid() || $is_cod;

            // 只有在貨到付款或已付款的情況下才顯示按鈕.
            echo '<div class="ccat-logistics-buttons">';

            if ($is_paid) {



                echo '<p class="ccat-logistics-notice">';
                // 尚未列印過，超商取貨顯示託運單類別；宅配顯示希望配達時段與託運單類別。
                if ($this->is_convenience_store_shipping($order) && !$has_printed) {
                    echo '<label for="ccat_print_obt_type">' . esc_html__('託運單類別', 'ccat-for-woocommerce') .
                        ' <select id="ccat_print_obt_type" class="ccat-print-obt-type-select">
                            <option value="01">' . esc_html__('A4三模B2S', 'ccat-for-woocommerce') . '</option>
                            <option value="02">' . esc_html__('熱轉印B2S', 'ccat-for-woocommerce') . '</option>
                            <option value="03">' . esc_html__('A4三模B2S_QRCode版面', 'ccat-for-woocommerce') . '</option>
                            <option value="04">' . esc_html__('熱轉印B2S_QRCode版面', 'ccat-for-woocommerce') . '</option>
                        </select></label>';

                } elseif(!$has_printed){
                    echo '<label for="ccat_print_obt_type">' . esc_html__('託運單類別', 'ccat-for-woocommerce') .
                        ' <select id="ccat_print_obt_type" class="ccat-print-obt-type-select">
                            <option value="01">' . esc_html__('A4二模宅配', 'ccat-for-woocommerce') . '</option>
                            <option value="02">' . esc_html__('A4三模宅配', 'ccat-for-woocommerce') . '</option>
                            <option value="03">' . esc_html__('熱轉印宅配', 'ccat-for-woocommerce') . '</option>
                        </select></label>';
                    echo '<label for="ccat_delivery_time" style="margin-right:8px;">' . esc_html__('希望配達時段', 'ccat-for-woocommerce') .
                        ' <select id="ccat_delivery_time" class="ccat-delivery-time-select" style="min-width:120px; margin-left:4px;">
                            <option value="04">' . esc_html__('不指定', 'ccat-for-woocommerce') . '</option>
                            <option value="01">' . esc_html__('13時前', 'ccat-for-woocommerce') . '</option>
                            <option value="02">' . esc_html__('14-18時', 'ccat-for-woocommerce') . '</option>
                        </select></label>';

                }
                echo '</p>';
                
                // 超商取貨且尚未列印過，顯示變更門市按鈕.
                if ($this->is_convenience_store_shipping($order) && !$has_printed) {
                    echo '<button type="button" class="button change-store" data-order-id="' . esc_attr($order->get_id()) . '">' .
                        esc_html__('變更門市', 'ccat-for-woocommerce') .
                        '</button>';
                }
                
                // 尚未列印過，顯示建立物流訂單按鈕
                if (!$has_printed) {
                    echo '<button type="button" class="button create-logistics-order" data-order-id="' . esc_attr($order->get_id()) . '">' .
                        esc_html__('建立物流託運單', 'ccat-for-woocommerce') .
                        '</button>';
                }

                // 顯示下載託運單按鈕.
                if ($has_printed) {
                    echo '<button type="button" class="button download-shipping-label" data-order-id="' . esc_attr($order->get_id()) . '">' .
                        esc_html__('下載託運單', 'ccat-for-woocommerce') .
                        '</button>';
                }

                // 提醒託運單格式
				if ( $this->is_convenience_store_shipping( $order ) ) {
					echo '<p class="ccat-logistics-notice">' .
						esc_html__( '黑貓快速到店(7-11取貨)，支援A4三模、熱轉印格式。', 'ccat-for-woocommerce' ) .
						'</p>';
				} else {
					echo '<p class="ccat-logistics-notice">' .
						esc_html__( '黑貓宅配，支援A4二模、A4三模及熱轉印格式，不支援撿貨明細。', 'ccat-for-woocommerce' ) .
						'</p>';
				}

                echo '</div>';
            } else {
                // 顯示未付款提示訊息.
                echo '<p class="ccat-logistics-notice">' .
                    esc_html__('請完成付款後，系統將自動開放物流託運單建立功能。', 'ccat-for-woocommerce') .
                    '</p>';
            }
            echo '</div>';
        }
    }

    /**
     * 判斷訂單是否使用黑貓物流
     *
     * @param WC_Order $order 訂單物件.
     *
     * @return bool 是否為黑貓物流.
     */
    private function is_ccat_shipping(WC_Order $order): bool
    {
        $shipping_methods = $order->get_shipping_methods();

        foreach ($shipping_methods as $shipping_method) {
            $method_id = $shipping_method->get_method_id();

            // 檢查運送方式ID是否包含"wc_shipping_ccat".
            if (strpos($method_id, 'ccatpay_shipping') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判斷是否為超商取貨
     *
     * @param WC_Order $order 訂單物件.
     *
     * @return bool 是否為超商取貨.
     */
    private function is_convenience_store_shipping(WC_Order $order): bool
    {
        $shipping_methods = $order->get_shipping_methods();

        foreach ($shipping_methods as $shipping_method) {
            $method_id = $shipping_method->get_method_id();

            // 檢查是否為7-11超商取貨.
            if (strpos($method_id, '711') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 註冊和加載後台 JS 和 CSS
     */
    public function register_admin_scripts()
    {
        $screen = get_current_screen();

        if ($screen && 'woocommerce_page_wc-orders' === $screen->id) {
            if (!isset($_GET['id'])) { // phpcs:ignore WordPress
                return;
            }
            $order_id = absint($_GET['id']); // phpcs:ignore WordPress

            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }
            $shipping_methods = $order->get_shipping_methods();
            foreach ($shipping_methods as $shipping_method_obj) {
                $shipping_method = $shipping_method_obj->get_method_id();
                break; // 只取第一個運送方法.
            }
            if (empty($shipping_method)) {
                return;
            }
            // 根據運送方式類型決定門市類別.
            if (false !== strpos($shipping_method, 'refrigerated')) {
                $store_category = '15'; // 冷藏.
            } elseif (false !== strpos($shipping_method, 'frozen')) {
                $store_category = '14'; // 冷凍.
            } else {
                $store_category = '13'; // 常溫.
            }
            // 註冊並加載 JS.
            wp_register_script(
                CCATPAYMENTS_PREFIX.'ccat-logistics-buttons',
                CCATPAY_Payments::plugin_url() . '/logistics-buttons.js',
                array('jquery'),
                time(),
                true
            );

            // 將必要的變數傳遞給 JS.
            wp_localize_script(
                CCATPAYMENTS_PREFIX.'ccat-logistics-buttons',
                CCATPAYMENTS_JS_PREFIX.'ccat_logistics_params',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('ccat-logistics-nonce'),
                    'store_category' => $store_category,
                    'shipping_method' => $shipping_method,
                    'order_id' => $order_id,
                )
            );

            // 加載 JS.
            wp_enqueue_script(CCATPAYMENTS_PREFIX.'ccat-logistics-buttons');

            // 加載 CSS.
            wp_add_inline_style(
                'woocommerce_admin_styles', // 使用 WooCommerce 的管理樣式.
                '
				.ccat-logistics-buttons {
					margin-top: 10px;
				}
				.ccat-logistics-buttons .button {
					margin-right: 5px;
					margin-bottom: 5px;
				}
				.ccat-store-modal {
					display: none;
					position: fixed;
					z-index: 1000;
					left: 0;
					top: 0;
					width: 100%;
					height: 100%;
					overflow: auto;
					background-color: rgba(0,0,0,0.4);
				}
				.ccat-store-modal-content {
					background-color: #fefefe;
					margin: 5% auto;
					padding: 20px;
					border: 1px solid #888;
					width: 80%;
					max-width: 960px;
					border-radius: 5px;
					box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2);
				}
				.ccat-store-close {
					color: #aaa;
					float: right;
					font-size: 28px;
					font-weight: bold;
					cursor: pointer;
				}
				.ccat-store-close:hover,
				.ccat-store-close:focus {
					color: black;
					text-decoration: none;
					cursor: pointer;
				}
				.ccat-store-search {
					margin-bottom: 20px;
				}
				.ccat-store-search-fields {
					display: flex;
					flex-wrap: wrap;
					gap: 10px;
					margin-bottom: 10px;
				}
				.ccat-store-search-field {
					flex: 1;
					min-width: 150px;
				}
				.ccat-store-search-button {
					display: block;
					width: 100%;
					text-align: center;
				}
				.ccat-store-list {
					max-height: 400px;
					overflow-y: auto;
					border: 1px solid #ddd;
					margin-bottom: 20px;
				}
				.ccat-store-list table {
					width: 100%;
					border-collapse: collapse;
				}
				.ccat-store-list th, 
				.ccat-store-list td {
					padding: 8px;
					text-align: left;
					border-bottom: 1px solid #ddd;
				}
				.ccat-store-list th {
					background-color: #f2f2f2;
				}
				.ccat-store-list tr:hover {
					background-color: #f5f5f5;
				}
				.ccat-store-list tr.selected {
					background-color: #e7f7e7;
				}
				.ccat-store-actions {
					text-align: right;
				}
				.ccat-store-loading {
					text-align: center;
					padding: 20px;
				}
				'
            );
        }
    }


    /**
     * 處理儲存門市的 Ajax 請求
     */
    public function handle_save_store_ajax()
    {
        check_ajax_referer('ccat-logistics-nonce', 'nonce');

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(array('message' => __('無效的訂單 ID', 'ccat-for-woocommerce')));

            return;
        }
        // 獲取臨時變數和門市資訊.
        $store_name = isset($_POST['storename']) ? sanitize_text_field(wp_unslash($_POST['storename'])) : ''; // phpcs:ignore WordPress
        $store_id = isset($_POST['storeid']) ? sanitize_text_field(wp_unslash($_POST['storeid'])) : '';  // phpcs:ignore WordPress
        $store_address = isset($_POST['storeaddress']) ? sanitize_text_field(wp_unslash($_POST['storeaddress'])) : '';  // phpcs:ignore WordPress
        $outside = isset($_POST['outside']) ? sanitize_text_field(wp_unslash($_POST['outside'])) : '0'; //  // phpcs:ignore WordPress
        $ship = isset($_POST['ship']) ? sanitize_text_field(wp_unslash($_POST['ship'])) : '1111111'; //  // phpcs:ignore WordPress
        $city = isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : ''; // phpcs:ignore WordPress
        $district = isset($_POST['district']) ? sanitize_text_field(wp_unslash($_POST['district'])) : ''; // phpcs:ignore WordPress
        $postcode = isset($_POST['postcode']) ? sanitize_text_field(wp_unslash($_POST['postcode'])) : ''; // phpcs:ignore WordPress

        if (empty($store_id) || empty($store_name) || empty($store_address)) {
            wp_send_json_error(array('message' => __('門市資訊不完整', 'ccat-for-woocommerce')));

            return;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(array('message' => __('找不到此訂單', 'ccat-for-woocommerce')));

            return;
        }

        // 更新門市資訊.
        $order->update_meta_data(CCATPAY_Gateway_Abstract::META_STORE_ID, $store_id);
        $order->update_meta_data(CCATPAY_Gateway_Abstract::META_STORE_NAME, $store_name);
        $order->update_meta_data(CCATPAY_Gateway_Abstract::META_STORE_ADDRESS, $store_address);
        $order->update_meta_data(CCATPAY_Gateway_Abstract::META_OUTSIDE, $outside);
        $order->update_meta_data(CCATPAY_Gateway_Abstract::META_SHIP, $ship);

        // 更新訂單的運送地址.
        $shipping_address = array(
            'first_name' => $order->get_shipping_first_name(),
            'last_name' => $order->get_shipping_last_name(),
            'company' => $order->get_shipping_company(),
            'address_1' => $store_name . ' (' . $store_id . ')',
            'address_2' => $store_address,
            'city' => $city,
            'state' => $district,
            'postcode' => $postcode,
            'country' => $order->get_shipping_country(),
        );

        // 更新訂單的發貨地址.
        $order->set_address($shipping_address, 'shipping');

        $order->save();

        // 添加訂單備註.
        $order->add_order_note(
            sprintf(
            /* translators: %1$s: 門市名稱, %2$s: 門市編號, %3$s: 門市地址 */
                __('門市已變更為: %1$s (%2$s) %3$s', 'ccat-for-woocommerce'),
                $store_name,
                $store_id,
                $store_address
            ),
            false, // 不顯示給客戶.
            true // 由系統新增.
        );

        wp_send_json_success(
            array(
                'message' => __('門市變更成功', 'ccat-for-woocommerce'),
                'store_id' => $store_id,
                'store_name' => $store_name,
                'store_address' => $store_address,
            )
        );
    }

    /**
     * 處理下載託運單的 AJAX 請求
     */
    public function handle_download_shipping_label()
    {
        // 驗證 nonce.
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ccat-logistics-nonce')) {
            wp_send_json_error(
                array(
                    'message' => esc_html__('安全驗證失敗', 'ccat-for-woocommerce'),
                ),
                400
            );
            wp_die();
        }

        // 獲取參數.
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        // 驗證參數.
        if (!$order_id) {
            wp_send_json_error(
                array(
                    'message' => esc_html__('缺少必要參數', 'ccat-for-woocommerce'),
                ),
                400
            );
            wp_die();
        }

        // 檢查訂單是否存在.
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(
                array(
                    'message' => esc_html__('找不到此訂單', 'ccat-for-woocommerce'),
                ),
                400
            );
            wp_die();
        }
        $file_no = $order->get_meta(self::META_FILE_NO);
        $obt_number = $order->get_meta(self::META_OBT_NUMBER);
        // 從設定獲取 API 資訊.
        $api_data = CCATPAY_711_Blocks_Integration::get_api_data();
        $service_id = $api_data[2];
        $api_token = $api_data[0];
        $api_url = $api_data[1];

        if (empty($service_id) || empty($api_token) || empty($api_url)) {
            wp_send_json_error(
                array(
                    'message' => esc_html__('黑貓物流 API 設定不完整', 'ccat-for-woocommerce'),
                ),
                400
            );
            wp_die();
        }

        // 組裝 API 請求資料.
        $request_data = array(
            'ServiceId' => $service_id,
            'FileNo' => $file_no,
            'Orders' => array(
                array(
                    'OBTNumber' => $obt_number,
                ),
            ),
        );

        // 設定請求頭.
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_token,
            ),
            'body' => wp_json_encode($request_data),
            'timeout' => 30,
            'sslverify' => false, // 在開發環境可能需要關閉 SSL 驗證.
            'stream' => true, // 直接輸出響應.
            'filename' => get_temp_dir() . 'shipping_label_' . $obt_number . '.pdf',
        );

        // 發送 API 請求.
        $response = wp_remote_post($api_url . 'api/Logistics/DownloadOBT', $args);

        // 檢查是否有錯誤.
        if (is_wp_error($response)) {
            wp_send_json_error(
                array(
                    'message' => $response->get_error_message(),
                ),
                400
            );
            wp_die();
        }

        // 獲取 HTTP 狀態碼.
        $http_code = wp_remote_retrieve_response_code($response);

        if (200 !== $http_code) {
            // 處理非 200 的響應.
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            $error_message = $error_data['Message'] ?? __('下載託運單失敗', 'ccat-for-woocommerce');

            wp_send_json_error(
                array(
                    'message' => $error_message,
                ),
                $http_code
            );
            wp_die();
        }

        // 獲取文件路徑.
        $file_path = $response['filename'];
        if (!file_exists($file_path)) {
            wp_send_json_error(
                array(
                    'message' => __('下載託運單失敗：檔案未能正確儲存', 'ccat-for-woocommerce'),
                ),
                500
            );
            wp_die();
        }

        // 初始化 WP_Filesystem.
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        // 讀取檔案內容.
        $file_content = $wp_filesystem->get_contents($file_path);
        if (false === $file_content) {
            wp_send_json_error(
                array(
                    'message' => __('下載託運單失敗：無法讀取檔案', 'ccat-for-woocommerce'),
                ),
                500
            );
            wp_die();
        }

        // 設定 header 以輸出 PDF 檔案.
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="shipping_label_' . $obt_number . '.pdf"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // 輸出檔案內容.
        echo $file_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        // 刪除暫存檔案.
        wp_delete_file($file_path);

        // 結束執行.
        wp_die();
    }
}
