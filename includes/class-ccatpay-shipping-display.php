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
     * 黑貓物流批次列印單次上限 (黑貓 API 限制最多 100 筆)
     */
    const MAX_BATCH_LIMIT = 100;

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

        // 註冊訂單列表批次操作 (支援傳統 CPT 與 HPOS).
        add_filter('bulk_actions-edit-shop_order', array($this, 'register_bulk_actions'));
        add_filter('bulk_actions-woocommerce_page_wc-orders', array($this, 'register_bulk_actions'));

        // 處理批次操作執行.
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_batch_print_obt'), 10, 3);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', array($this, 'handle_batch_print_obt'), 10, 3);

        // 後台管理員提示與自動下載.
        add_action('admin_notices', array($this, 'display_bulk_action_admin_notices'));

        // 批次 PDF 下載 Endpoint.
        add_action('admin_post_' . CCATPAYMENTS_PREFIX . '_download_batch_pdf', array($this, 'handle_download_batch_pdf'));
    }

    /**
     * 註冊 WooCommerce 訂單列表批次操作
     *
     * @param array $bulk_actions 批次操作陣列.
     * @return array
     */
    public function register_bulk_actions($bulk_actions)
    {
        $bulk_actions['ccat_batch_print_obt'] = __('黑貓Pay - 批次列印託運單', 'ccat-for-woocommerce');
        return $bulk_actions;
    }

    /**
     * 處理 WooCommerce 訂單列表批次列印託運單操作
     *
     * @param string $redirect_to 重導向 URL.
     * @param string $action      執行的批次動作.
     * @param array  $order_ids   選取的訂單 ID 列表.
     * @return string
     */
    public function handle_batch_print_obt($redirect_to, $action, $order_ids)
    {
        if ('ccat_batch_print_obt' !== $action) {
            return $redirect_to;
        }

        if (!CCATPAY_Payments::is_shipping_enabled()) {
            return add_query_arg(
                array(
                    'ccat_bulk_disabled' => 1,
                ),
                $redirect_to
            );
        }

        $processed_count = 0;
        $skipped_count   = 0;
        $failed_count    = 0;
        $download_tokens = array();

        $home_delivery_orders = array();
        $cvs_711_orders       = array();

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            // 1. 檢查是否使用黑貓物流.
            if (!$this->is_ccat_shipping($order)) {
                $skipped_count++;
                continue;
            }

            // 2. 避免重複列印：若已列印過則略過.
            if ('yes' === $order->get_meta(self::META_PRINTED)) {
                $skipped_count++;
                continue;
            }

            // 3. 檢查付款狀態 (已付款或貨到付款).
            $payment_method = $order->get_payment_method();
            $is_cod = strpos($payment_method, 'cod') !== false;
            $is_paid = $order->is_paid() || $is_cod;
            if (!$is_paid) {
                $skipped_count++;
                continue;
            }

            // 4. 依配送方式分組.
            if ($this->is_convenience_store_shipping($order)) {
                $store_id = $order->get_meta(CCATPAY_Gateway_Abstract::META_STORE_ID);
                if (empty($store_id)) {
                    $failed_count++;
                    $order->add_order_note(
                        __('黑貓物流批次列印失敗：缺少 7-11 門市資訊', 'ccat-for-woocommerce'),
                        false,
                        true
                    );
                    continue;
                }
                $cvs_711_orders[] = $order;
            } else {
                $home_delivery_orders[] = $order;
            }
        }

        $batch_id = 'BATCH_' . gmdate('YmdHis');

        // 5. 批次發送宅配訂單 API.
        if (!empty($home_delivery_orders)) {
            $res = $this->send_batch_logistics_api($home_delivery_orders, false, $batch_id);
            $processed_count += $res['success'];
            $failed_count    += $res['failed'];
            if (!empty($res['download_token'])) {
                $download_tokens[] = $res['download_token'];
            }
        }

        // 6. 批次發送 7-11 訂單 API.
        if (!empty($cvs_711_orders)) {
            $res = $this->send_batch_logistics_api($cvs_711_orders, true, $batch_id);
            $processed_count += $res['success'];
            $failed_count    += $res['failed'];
            if (!empty($res['download_token'])) {
                $download_tokens[] = $res['download_token'];
            }
        }

        // 移除可能存在的舊參數.
        $redirect_to = remove_query_arg(
            array(
                'ccat_bulk_processed',
                'ccat_bulk_skipped',
                'ccat_bulk_failed',
                'ccat_download_tokens',
                'ccat_bulk_disabled',
            ),
            $redirect_to
        );

        $query_args = array(
            'ccat_bulk_processed' => $processed_count,
            'ccat_bulk_skipped'   => $skipped_count,
            'ccat_bulk_failed'    => $failed_count,
        );

        if (!empty($download_tokens)) {
            $query_args['ccat_download_tokens'] = implode(',', $download_tokens);
        }

        return add_query_arg($query_args, $redirect_to);
    }

    /**
     * 發送批次物流訂單 API (具備單次最多 100 筆上限防護與自動分批機制)
     *
     * @param array  $orders   WC_Order 陣列.
     * @param bool   $is_711   是否為 7-11.
     * @param string $batch_id 批次編號.
     * @return array
     */
    private function send_batch_logistics_api(array $orders, bool $is_711, string $batch_id): array
    {
        // 若訂單超過 100 筆，自動切為每批 100 筆分批發送，符合黑貓 API 單次上限規範.
        if (count($orders) > self::MAX_BATCH_LIMIT) {
            $chunks = array_chunk($orders, self::MAX_BATCH_LIMIT);
            $total_success = 0;
            $total_failed = 0;
            $download_tokens = array();

            foreach ($chunks as $chunk_index => $chunk_orders) {
                $sub_batch_id = sprintf('%s_%d', $batch_id, $chunk_index + 1);
                $res = $this->send_batch_logistics_api($chunk_orders, $is_711, $sub_batch_id);
                $total_success += $res['success'];
                $total_failed  += $res['failed'];
                if (!empty($res['download_token'])) {
                    $download_tokens[] = $res['download_token'];
                }
            }

            return array(
                'success'        => $total_success,
                'failed'         => $total_failed,
                'download_token' => implode(',', $download_tokens),
                'error'          => '',
            );
        }

        try {
            $api_data = CCATPAY_711_Blocks_Integration::get_api_data();
            $service_id = $api_data[2];
            $api_token = $api_data[0];
            $api_url = $api_data[1];
        } catch (Exception $e) {
            return array(
                'success'        => 0,
                'failed'         => count($orders),
                'download_token' => '',
                'error'          => $e->getMessage(),
            );
        }

        if (empty($service_id) || empty($api_token) || empty($api_url)) {
            return array(
                'success'        => 0,
                'failed'         => count($orders),
                'download_token' => '',
                'error'          => __('黑貓物流 API 設定不完整', 'ccat-for-woocommerce'),
            );
        }

        $all_payloads = array();
        // 記錄 OrderId 與 WC_Order 對應.
        $order_map = array();

        foreach ($orders as $order) {
            $order_payload_items = $this->build_order_payload_items($order, $is_711, '04', 1, '01');
            foreach ($order_payload_items as $item) {
                $all_payloads[] = $item;
                $order_map[$item['OrderId']] = $order;
            }
        }

        $request_data = array(
            'ServiceId'    => $service_id,
            'PrintOBTType' => '01',
            'Orders'       => $all_payloads,
        );

        $endpoint = $is_711 ? 'api/Logistics/PrintOBTByB2S' : 'api/Logistics/PrintOBT';

        $response = wp_remote_post(
            $api_url . $endpoint,
            array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_token,
                ),
                'body'    => wp_json_encode($request_data),
                'timeout' => 120,
            )
        );

        if (is_wp_error($response)) {
            foreach ($orders as $order) {
                $order->add_order_note(
                    sprintf(__('黑貓批次列印 API 請求錯誤: %s', 'ccat-for-woocommerce'), $response->get_error_message()),
                    false,
                    true
                );
            }
            return array(
                'success'        => 0,
                'failed'         => count($orders),
                'download_token' => '',
                'error'          => $response->get_error_message(),
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if (200 !== $response_code) {
            CCATPAY_Gateway_Abstract::clear_payment_api_token_cache();
            $error_message = sprintf(__('API 請求失敗 (狀態碼: %d)', 'ccat-for-woocommerce'), $response_code);
            if (!empty($body)) {
                $err_data = json_decode($body, true);
                if (isset($err_data['Message'])) {
                    $error_message = $err_data['Message'];
                }
            }
            foreach ($orders as $order) {
                $order->add_order_note(
                    sprintf(__('黑貓批次列印失敗: %s', 'ccat-for-woocommerce'), $error_message),
                    false,
                    true
                );
            }
            return array(
                'success'        => 0,
                'failed'         => count($orders),
                'download_token' => '',
                'error'          => $error_message,
            );
        }

        $result = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($result) || 'Y' !== ($result['IsOK'] ?? '')) {
            $error_message = $result['Message'] ?? __('建立物流託運單失敗', 'ccat-for-woocommerce');
            foreach ($orders as $order) {
                $order->add_order_note(
                    sprintf(__('黑貓批次列印失敗: %s', 'ccat-for-woocommerce'), $error_message),
                    false,
                    true
                );
            }
            return array(
                'success'        => 0,
                'failed'         => count($orders),
                'download_token' => '',
                'error'          => $error_message,
            );
        }

        $file_no = $result['Data']['FileNo'] ?? '';
        $obt_orders_returned = $result['Data']['Orders'] ?? array();

        // 整理各訂單產生的 OBTNumber.
        $all_obt_numbers = array();
        $order_obt_map = array();

        foreach ($obt_orders_returned as $index => $item) {
            $obt_no = $item['OBTNumber'] ?? '';
            if (empty($obt_no)) {
                continue;
            }
            $all_obt_numbers[] = $obt_no;
            $ret_order_id = $item['OrderId'] ?? '';

            if (!empty($ret_order_id) && isset($order_map[$ret_order_id])) {
                $target_order = $order_map[$ret_order_id];
                $target_order_id = $target_order->get_id();
                if (!isset($order_obt_map[$target_order_id])) {
                    $order_obt_map[$target_order_id] = array();
                }
                $order_obt_map[$target_order_id][] = $obt_no;
            } elseif (isset($orders[$index])) {
                $target_order_id = $orders[$index]->get_id();
                if (!isset($order_obt_map[$target_order_id])) {
                    $order_obt_map[$target_order_id] = array();
                }
                $order_obt_map[$target_order_id][] = $obt_no;
            }
        }

        // 更新每筆訂單 Meta 與備註.
        foreach ($orders as $order) {
            $oid = $order->get_id();
            $obt_str = isset($order_obt_map[$oid]) ? implode(', ', $order_obt_map[$oid]) : '';
            if (empty($obt_str) && !empty($all_obt_numbers)) {
                $obt_str = implode(', ', $all_obt_numbers);
            }

            $order->update_meta_data(self::META_OBT_NUMBER, $obt_str);
            $order->update_meta_data(self::META_FILE_NO, $file_no);
            $order->update_meta_data(self::META_PRINTED, 'yes');
            $order->update_meta_data('_ccat_shipping_batch_id', $batch_id);
            $order->save();

            $order->add_order_note(
                sprintf(
                    /* translators: 1: 託運單號, 2: 檔案編號 */
                    __('黑貓物流託運單已批次建立，單號: %1$s，檔案編號: %2$s', 'ccat-for-woocommerce'),
                    $obt_str,
                    $file_no
                ),
                false,
                true
            );
        }

        // 呼叫 DownloadOBT API 下載批次 PDF 暫存.
        $download_token = '';
        if (!empty($file_no) && !empty($all_obt_numbers)) {
            $download_token = $this->download_batch_pdf_to_temp($service_id, $file_no, $all_obt_numbers, $api_token, $api_url);
        }

        return array(
            'success'        => count($orders),
            'failed'         => 0,
            'download_token' => $download_token,
        );
    }

    /**
     * 下載批次 PDF 並儲存至暫存檔，回傳下載 Token
     *
     * @param string $service_id  服務代號.
     * @param string $file_no     檔案編號.
     * @param array  $obt_numbers 託運單號列表.
     * @param string $api_token   API Token.
     * @param string $api_url     API URL.
     * @return string
     */
    private function download_batch_pdf_to_temp(string $service_id, string $file_no, array $obt_numbers, string $api_token, string $api_url): string
    {
        $orders_payload = array();
        foreach ($obt_numbers as $single_obt) {
            $orders_payload[] = array('OBTNumber' => $single_obt);
        }

        $request_data = array(
            'ServiceId' => $service_id,
            'FileNo'    => $file_no,
            'Orders'    => $orders_payload,
        );

        $temp_dir = get_temp_dir();

        // 清理超過 2 小時的舊批次暫存檔，避免累積硬碟空間.
        $old_temp_files = glob($temp_dir . 'ccat_batch_*.pdf');
        if (!empty($old_temp_files)) {
            $now = time();
            foreach ($old_temp_files as $old_file) {
                if (is_file($old_file) && ($now - filemtime($old_file) > 2 * HOUR_IN_SECONDS)) {
                    wp_delete_file($old_file);
                }
            }
        }

        $token = wp_generate_password(24, false);
        $temp_file = $temp_dir . 'ccat_batch_' . $token . '.pdf';

        $args = array(
            'headers'   => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_token,
            ),
            'body'      => wp_json_encode($request_data),
            'timeout'   => 60,
            'sslverify' => false,
            'stream'    => true,
            'filename'  => $temp_file,
        );

        $response = wp_remote_post($api_url . 'api/Logistics/DownloadOBT', $args);

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response) || !file_exists($temp_file)) {
            return '';
        }

        // 將暫存檔案路徑存入 Transient，有效期 1 小時 (期間內可隨時、重複下載).
        set_transient('ccat_batch_pdf_' . $token, $temp_file, HOUR_IN_SECONDS);

        // 記錄至 1 小時批次歷史清單.
        $history = get_transient('ccat_recent_batch_downloads') ?: array();
        $history = is_array($history) ? $history : array();
        $valid_history = array();
        $now = time();
        foreach ($history as $h_item) {
            $h_token = $h_item['token'] ?? '';
            $h_file = get_transient('ccat_batch_pdf_' . $h_token);
            if (!empty($h_file) && file_exists($h_file) && isset($h_item['time']) && ($now - $h_item['time'] < HOUR_IN_SECONDS)) {
                $valid_history[] = $h_item;
            }
        }
        array_unshift($valid_history, array(
            'token'        => $token,
            'time'         => $now,
            'display_time' => current_time('H:i:s'),
        ));
        set_transient('ccat_recent_batch_downloads', array_slice($valid_history, 0, 5), HOUR_IN_SECONDS);

        return $token;
    }

    /**
     * 處理批次 PDF 下載請求 (admin-post)
     */
    public function handle_download_batch_pdf()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('權限不足', 'ccat-for-woocommerce'), 403);
        }

        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if (empty($token) || !wp_verify_nonce($nonce, 'ccat_download_batch_pdf_' . $token)) {
            wp_die(esc_html__('下載連結已失效或安全驗證失敗', 'ccat-for-woocommerce'), 403);
        }

        $file_path = get_transient('ccat_batch_pdf_' . $token);
        if (empty($file_path) || !file_exists($file_path)) {
            wp_die(esc_html__('託運單檔案已過期或不存在，請至個別訂單內下載。', 'ccat-for-woocommerce'), 404);
        }

        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $file_content = $wp_filesystem->get_contents($file_path);
        if (false === $file_content) {
            wp_die(esc_html__('無法讀取託運單檔案', 'ccat-for-woocommerce'), 500);
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="ccat_batch_labels_' . gmdate('YmdHis') . '.pdf"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $file_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * 顯示批次操作管理員提示訊息 (包含 1 小時內的歷史下載按鈕)
     */
    public function display_bulk_action_admin_notices()
    {
        if (isset($_GET['ccat_bulk_disabled'])) { // phpcs:ignore WordPress.Security.NonceVerification
            echo '<div class="notice notice-error is-dismissible"><p>' .
                esc_html__('黑貓物流功能已於設定中停用，無法批次建立託運單。', 'ccat-for-woocommerce') .
                '</p></div>';
            return;
        }

        if (!isset($_GET['ccat_bulk_processed'])) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        $processed = isset($_GET['ccat_bulk_processed']) ? absint($_GET['ccat_bulk_processed']) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        $skipped   = isset($_GET['ccat_bulk_skipped']) ? absint($_GET['ccat_bulk_skipped']) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        $failed    = isset($_GET['ccat_bulk_failed']) ? absint($_GET['ccat_bulk_failed']) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        $tokens_str = isset($_GET['ccat_download_tokens']) ? sanitize_text_field(wp_unslash($_GET['ccat_download_tokens'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        $tokens = !empty($tokens_str) ? explode(',', $tokens_str) : array();

        $message = sprintf(
            /* translators: 1: 成功筆數, 2: 略過筆數, 3: 失敗筆數 */
            __('黑貓託運單批次列印完成：成功建立 %1$d 筆，略過 %2$d 筆（已列印/未付款/非黑貓），失敗 %3$d 筆。', 'ccat-for-woocommerce'),
            $processed,
            $skipped,
            $failed
        );

        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>' . esc_html($message) . '</strong></p>';

        // 本次有新產生 Token 則顯示本次下載按鈕.
        if (!empty($tokens)) {
            echo '<p style="margin-top: 5px;">';
            foreach ($tokens as $idx => $token) {
                $download_url = wp_nonce_url(
                    admin_url('admin-post.php?action=' . CCATPAYMENTS_PREFIX . '_download_batch_pdf&token=' . urlencode($token)),
                    'ccat_download_batch_pdf_' . $token
                );
                $btn_label = count($tokens) > 1
                    ? sprintf(__('下載第 %d 批託運單 PDF', 'ccat-for-woocommerce'), $idx + 1)
                    : __('點此下載整批託運單 PDF', 'ccat-for-woocommerce');

                echo '<a href="' . esc_url($download_url) . '" class="button button-primary ccat-auto-download-btn" style="margin-right: 8px;" target="_blank">' .
                    esc_html($btn_label) .
                    '</a>';
            }
            echo '</p>';

            // 自動觸發下載腳本.
            echo '<script type="text/javascript">
                jQuery(document).ready(function($) {
                    $(".ccat-auto-download-btn").each(function() {
                        var url = $(this).attr("href");
                        if (url) {
                            var iframe = document.createElement("iframe");
                            iframe.style.display = "none";
                            iframe.src = url;
                            document.body.appendChild(iframe);
                        }
                    });
                });
            </script>';
        } else {
            // 本次成功 0 筆時，讀取 1 小時內的有效歷史批次列印按鈕.
            $history = get_transient('ccat_recent_batch_downloads') ?: array();
            $valid_history_buttons = array();
            $now = time();

            if (is_array($history)) {
                foreach ($history as $h_item) {
                    $h_token = $h_item['token'] ?? '';
                    $h_file = get_transient('ccat_batch_pdf_' . $h_token);
                    if (!empty($h_file) && file_exists($h_file) && isset($h_item['time']) && ($now - $h_item['time'] < HOUR_IN_SECONDS)) {
                        $download_url = wp_nonce_url(
                            admin_url('admin-post.php?action=' . CCATPAYMENTS_PREFIX . '_download_batch_pdf&token=' . urlencode($h_token)),
                            'ccat_download_batch_pdf_' . $h_token
                        );
                        $d_time = $h_item['display_time'] ?? '';
                        $valid_history_buttons[] = array(
                            'url'   => $download_url,
                            'label' => sprintf(__('下載 1 小時內建立的託運單 PDF (%s 建立)', 'ccat-for-woocommerce'), $d_time),
                        );
                    }
                }
            }

            if (!empty($valid_history_buttons)) {
                echo '<p style="margin-top: 8px;">';
                echo '<span style="font-weight: 500; margin-right: 8px;">' . esc_html__('最近 1 小時內建立的託運單：', 'ccat-for-woocommerce') . '</span>';
                foreach ($valid_history_buttons as $h_btn) {
                    echo '<a href="' . esc_url($h_btn['url']) . '" class="button button-secondary" style="margin-right: 8px; margin-bottom: 4px;" target="_blank">' .
                        esc_html($h_btn['label']) .
                        '</a>';
                }
                echo '</p>';
            }
        }

        echo '</div>';
    }

    /**
     * 組裝單筆訂單的黑貓 API Payload 項目陣列 (支援多件)
     *
     * @param WC_Order $order          訂單物件.
     * @param bool     $is_711         是否為 7-11 超商取貨.
     * @param string   $delivery_time  希望配達時段.
     * @param int      $obt_count      託運單數量.
     * @param string   $print_obt_type 託運單類別.
     * @return array
     */
    public function build_order_payload_items(WC_Order $order, bool $is_711, string $delivery_time = '04', int $obt_count = 1, string $print_obt_type = '01'): array
    {
        $shipping_method_id = '';
        $shipping_methods = $order->get_shipping_methods();
        foreach ($shipping_methods as $shipping_method) {
            $shipping_method_id = $shipping_method->get_method_id();
            break;
        }

        // 溫層設定.
        $thermosphere = '0001'; // 常溫.
        if (strpos($shipping_method_id, 'refrigerated') !== false) {
            $thermosphere = '0002'; // 冷藏.
        } elseif (strpos($shipping_method_id, 'frozen') !== false) {
            $thermosphere = '0003'; // 冷凍.
        }

        $spec = '0001'; // 預設 60cm.

        // 付款方式與代收金額.
        $payment_method = $order->get_payment_method();
        $is_cod = strpos($payment_method, 'cod') !== false;
        $is_freight = 'N';
        $total_collection_amount = $is_cod ? intval($order->get_total()) : 0;

        // 收件人資訊 (優先取 shipping，無則取 billing).
        $first_name = $order->get_shipping_first_name() ?: $order->get_billing_first_name();
        $last_name = $order->get_shipping_last_name() ?: $order->get_billing_last_name();
        $recipient_name = $last_name . $first_name;
        $recipient_phone = $order->get_shipping_phone() ?: $order->get_billing_phone();
        $recipient_city = $order->get_shipping_city() ?: $order->get_billing_city();
        $recipient_state = $order->get_shipping_state() ?: $order->get_billing_state();
        $recipient_postcode = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
        $recipient_address_1 = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
        $recipient_address_2 = $order->get_shipping_address_2() ?: $order->get_billing_address_2();

        $recipient_full_address = $recipient_postcode . $recipient_state . $recipient_city . $recipient_address_1 . $recipient_address_2;

        // 寄件人資訊.
        $sender_name    = get_option(CCATPAYMENTS_PREFIX . '_sender_name', '');
        $sender_tel     = get_option(CCATPAYMENTS_PREFIX . '_sender_tel', '');
        $sender_mobile  = get_option(CCATPAYMENTS_PREFIX . '_sender_mobile', '');
        $sender_address = get_option(CCATPAYMENTS_PREFIX . '_sender_address', '');

        // 台北時區.
        $taipei_tz = new DateTimeZone(self::TAIPEI_TIMEZONE);
        $today = new DateTime('now', $taipei_tz);
        $day_after_tomorrow = clone $today;
        $day_after_tomorrow->add(new DateInterval('P1D'));
        if ('0' === $day_after_tomorrow->format('w')) {
            $day_after_tomorrow->add(new DateInterval('P1D'));
        }

        // 商品名稱.
        $product_name = '';
        $items = $order->get_items();
        if (!empty($items)) {
            $first_item = reset($items);
            $product_name = $first_item->get_name();
            if (mb_strlen($product_name) > 20) {
                $product_name = mb_substr($product_name, 0, 19) . '…';
            }
        }

        $items_payload = array();

        if ($is_711) {
            $store_id = $order->get_meta(CCATPAY_Gateway_Abstract::META_STORE_ID);
            $base_order_number = $order->get_order_number();

            for ($i = 1; $i <= $obt_count; $i++) {
                $item_order_id = $obt_count > 1 ? sprintf('%s-%d', $base_order_number, $i) : (string)$base_order_number;
                $item_is_collection = ($is_cod && 1 === $i) ? 'Y' : 'N';
                $item_collection_amount = ($is_cod && 1 === $i) ? $total_collection_amount : 0;

                $items_payload[] = array(
                    'OBTNumber'        => '',
                    'OrderId'          => $item_order_id,
                    'Thermosphere'     => $thermosphere,
                    'Spec'             => $spec,
                    'ReceiveStoreId'   => $store_id,
                    'RecipientName'    => $recipient_name,
                    'RecipientTel'     => $recipient_phone,
                    'RecipientMobile'  => $recipient_phone,
                    'SenderName'       => $sender_name,
                    'SenderTel'        => $sender_tel,
                    'SenderMobile'     => $sender_mobile,
                    'SenderAddress'    => $sender_address,
                    'IsCollection'     => $item_is_collection,
                    'CollectionAmount' => $item_collection_amount,
                    'FBName'           => substr(get_bloginfo('name'), 0, 6),
                    'Memo'             => sprintf(__('訂單編號: %s', 'ccat-for-woocommerce'), $item_order_id),
                );
            }
        } else {
            $base_order_number = sprintf('TCAT%s%d', date('Ymd'), $order->get_order_number());

            for ($i = 1; $i <= $obt_count; $i++) {
                $item_order_id = $obt_count > 1 ? sprintf('%s-%d', $base_order_number, $i) : $base_order_number;
                $item_is_collection = ($is_cod && 1 === $i) ? 'Y' : 'N';
                $item_collection_amount = ($is_cod && 1 === $i) ? $total_collection_amount : 0;

                $items_payload[] = array(
                    'OBTNumber'        => '',
                    'OrderId'          => $item_order_id,
                    'Thermosphere'     => $thermosphere,
                    'Spec'             => $spec,
                    'RecipientName'    => $recipient_name,
                    'RecipientTel'     => $recipient_phone,
                    'RecipientMobile'  => $recipient_phone,
                    'RecipientAddress' => $recipient_full_address,
                    'SenderName'       => $sender_name,
                    'SenderTel'        => $sender_tel,
                    'SenderMobile'     => $sender_mobile,
                    'SenderAddress'    => $sender_address,
                    'ShipmentDate'     => $today->format('Ymd'),
                    'DeliveryDate'     => $day_after_tomorrow->format('Ymd'),
                    'DeliveryTime'     => $delivery_time,
                    'IsFreight'        => $is_freight,
                    'IsCollection'     => $item_is_collection,
                    'CollectionAmount' => $item_collection_amount,
                    'IsSwipe'          => strpos($shipping_method_id, 'card') !== false ? 'Y' : 'N',
                    'IsMobilePay'      => strpos($shipping_method_id, 'mobile') !== false ? 'Y' : 'N',
                    'IsDeclare'        => 'N',
                    'DeclareAmount'    => 0,
                    'ProductTypeId'    => apply_filters('ccatpay_shipping_product_type_id', '0015', $order), // 0015: 其他.
                    'ProductName'      => $product_name,
                    'Memo'             => sprintf(__('訂單編號: %s', 'ccat-for-woocommerce'), $item_order_id),
                );
            }
        }

        return $items_payload;
    }

    /**
     * 呼叫黑貓物流 API 建立物流訂單
     *
     * @param WC_Order $order          訂單物件.
     * @param string   $delivery_time  希望配達時段 (宅配用)
     * @param string   $print_obt_type 託運單類別
     * @param int      $obt_count      託運單數量
     *
     * @return array|WP_Error API 回應或錯誤
     */
    private function create_logistics_order(WC_Order $order, $delivery_time = '04', $print_obt_type = '01', $obt_count = 1)
    {
        // 從設定獲取 API 資訊.
        try {
            $api_data = CCATPAY_711_Blocks_Integration::get_api_data();
            $service_id = $api_data[2];
            $api_token = $api_data[0];
            $api_url = $api_data[1];
        } catch (Exception $e) {
            return new WP_Error('invalid_api_settings', $e->getMessage());
        }

        if (empty($service_id) || empty($api_token) || empty($api_url)) {
            return new WP_Error('invalid_api_settings', __('黑貓物流 API 設定不完整', 'ccat-for-woocommerce'));
        }

        $is_711 = $this->is_convenience_store_shipping($order);
        if ($is_711) {
            $store_id = $order->get_meta(CCATPAY_Gateway_Abstract::META_STORE_ID);
            if (empty($store_id)) {
                return new WP_Error('missing_store_id', __('找不到 7-11 門市資訊', 'ccat-for-woocommerce'));
            }
        }

        $orders_payload = $this->build_order_payload_items($order, $is_711, $delivery_time, $obt_count, $print_obt_type);

        $request_data = array(
            'ServiceId'    => $service_id,
            'PrintOBTType' => $print_obt_type,
            'Orders'       => $orders_payload,
        );

        $endpoint = $is_711 ? 'api/Logistics/PrintOBTByB2S' : 'api/Logistics/PrintOBT';

        $response = wp_remote_post(
            $api_url . $endpoint,
            array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_token,
                ),
                'body'    => wp_json_encode($request_data),
                'timeout' => 120,
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if (200 !== $response_code) {
            CCATPAY_Gateway_Abstract::clear_payment_api_token_cache();
            $response_body = wp_remote_retrieve_body($response);
            $error_message = '';

            if (!empty($response_body)) {
                $response_data = json_decode($response_body, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($response_data['Message'])) {
                    $error_message = $response_data['Message'];
                } else {
                    $error_message = $response_body;
                }
            }

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
            $is_shipping_enabled = CCATPAY_Payments::is_shipping_enabled();
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
                if (!$is_shipping_enabled && !$has_printed) {
                    echo '<p class="ccat-logistics-notice" style="color: #d63638; font-weight: bold;">' .
                        esc_html__('黑貓物流功能已於黑貓Pay設定中停用，無法建立新託運單。', 'ccat-for-woocommerce') .
                        '</p>';
                } else {
                    if (!$has_printed) {
                        echo '<div class="ccat-logistics-controls">';

                        // 託運單類別.
                        echo '<div class="ccat-control-group">';
                        echo '<label for="ccat_print_obt_type">' . esc_html__('託運單類別', 'ccat-for-woocommerce') . '</label>';
                        echo '<select id="ccat_print_obt_type" class="ccat-print-obt-type-select">';
                        if ($this->is_convenience_store_shipping($order)) {
                            echo '<option value="01">' . esc_html__('A4三模B2S', 'ccat-for-woocommerce') . '</option>
                                  <option value="02">' . esc_html__('熱轉印B2S', 'ccat-for-woocommerce') . '</option>
                                  <option value="03">' . esc_html__('A4三模B2S_QRCode版面', 'ccat-for-woocommerce') . '</option>
                                  <option value="04">' . esc_html__('熱轉印B2S_QRCode版面', 'ccat-for-woocommerce') . '</option>';
                        } else {
                            echo '<option value="01">' . esc_html__('A4二模宅配', 'ccat-for-woocommerce') . '</option>
                                  <option value="02">' . esc_html__('A4三模宅配', 'ccat-for-woocommerce') . '</option>
                                  <option value="03">' . esc_html__('熱轉印宅配', 'ccat-for-woocommerce') . '</option>';
                        }
                        echo '</select></div>';

                        // 希望配達時段 (宅配).
                        if (!$this->is_convenience_store_shipping($order)) {
                            echo '<div class="ccat-control-group">';
                            echo '<label for="ccat_delivery_time">' . esc_html__('希望配達時段', 'ccat-for-woocommerce') . '</label>';
                            echo '<select id="ccat_delivery_time" class="ccat-delivery-time-select">
                                    <option value="04">' . esc_html__('不指定', 'ccat-for-woocommerce') . '</option>
                                    <option value="01">' . esc_html__('13時前', 'ccat-for-woocommerce') . '</option>
                                    <option value="02">' . esc_html__('14-18時', 'ccat-for-woocommerce') . '</option>
                                </select></div>';
                        }

                        // 託運單數量.
                        echo '<div class="ccat-control-group">';
                        echo '<label for="ccat_print_obt_numbers">' . esc_html__('託運單數量', 'ccat-for-woocommerce') . '</label>';
                        echo '<input type="number" id="ccat_print_obt_numbers" class="ccat-print-obt-numbers-input" min="1" max="100" value="1" data-is-cod="' . ($is_cod ? '1' : '0') . '">';
                        echo '</div>';

                        echo '</div>'; // .ccat-logistics-controls

                        // 若為貨到付款，顯示多張託運單警示提示（預設隱藏）.
                        if ($is_cod) {
                            echo '<p class="ccat-cod-multi-notice" style="display:none; color: #d63638; font-weight: bold; margin: 5px 0 10px 0;">' .
                                esc_html__('請注意!! 代收金額只會放在第1張託運單上，其餘的託運單則都是不收款。', 'ccat-for-woocommerce') .
                                '</p>';
                        }
                    }

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
                    if ($this->is_convenience_store_shipping($order)) {
                        echo '<p class="ccat-logistics-notice">' .
                            esc_html__('黑貓快速到店(7-11取貨)，支援A4三模、熱轉印格式。', 'ccat-for-woocommerce') .
                            '</p>';
                    } else {
                        echo '<p class="ccat-logistics-notice">' .
                            esc_html__('黑貓宅配，支援A4二模、A4三模及熱轉印格式，不支援撿貨明細。', 'ccat-for-woocommerce') .
                            '</p>';
                    }
                }
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
        $order_id = 0;

        if ($screen) {
            if ('woocommerce_page_wc-orders' === $screen->id && isset($_GET['id'])) { // phpcs:ignore WordPress.Security.NonceVerification
                $order_id = absint($_GET['id']); // phpcs:ignore WordPress.Security.NonceVerification
            } elseif (('shop_order' === $screen->id || 'post' === $screen->id) && isset($_GET['post'])) { // phpcs:ignore WordPress.Security.NonceVerification
                $order_id = absint($_GET['post']); // phpcs:ignore WordPress.Security.NonceVerification
            }
        }

        if (!$order_id) {
            return;
        }

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
				.ccat-logistics-controls {
					display: flex;
					flex-wrap: wrap;
					align-items: center;
					gap: 12px;
					margin: 10px 0;
				}
				.ccat-control-group {
					display: flex;
					align-items: center;
					gap: 6px;
				}
				.ccat-control-group label {
					font-weight: 500;
					white-space: nowrap;
				}
				.ccat-control-group select,
				.ccat-control-group input[type="number"] {
					min-height: 32px;
				}
				.ccat-control-group input[type="number"] {
					width: 70px;
				}
				.ccat-logistics-buttons .button {
					margin-right: 5px;
					margin-bottom: 5px;
				}
				@media screen and (max-width: 782px) {
					.ccat-logistics-controls {
						flex-direction: column;
						align-items: stretch;
						gap: 8px;
					}
					.ccat-control-group {
						justify-content: space-between;
					}
					.ccat-control-group select,
					.ccat-control-group input[type="number"] {
						flex: 1;
						max-width: 60%;
					}
					.ccat-logistics-buttons .button {
						width: 100%;
						margin-right: 0;
						margin-bottom: 8px;
						text-align: center;
					}
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
        try {
            $api_data = CCATPAY_711_Blocks_Integration::get_api_data();
            $service_id = $api_data[2];
            $api_token = $api_data[0];
            $api_url = $api_data[1];
        } catch ( Exception $e ) {
            wp_send_json_error(
                array(
                    'message' => $e->getMessage(),
                ),
                400
            );
            wp_die();
        }

        if (empty($service_id) || empty($api_token) || empty($api_url)) {
            wp_send_json_error(
                array(
                    'message' => esc_html__('黑貓物流 API 設定不完整', 'ccat-for-woocommerce'),
                ),
                400
            );
            wp_die();
        }

        // 組裝多單號 Orders 陣列.
        $obt_list = array_filter(array_map('trim', explode(',', $obt_number)));
        $orders_payload = array();
        foreach ($obt_list as $single_obt) {
            $orders_payload[] = array('OBTNumber' => $single_obt);
        }
        if (empty($orders_payload)) {
            $orders_payload[] = array('OBTNumber' => $obt_number);
        }

        // 組裝 API 請求資料.
        $request_data = array(
            'ServiceId' => $service_id,
            'FileNo' => $file_no,
            'Orders' => $orders_payload,
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
