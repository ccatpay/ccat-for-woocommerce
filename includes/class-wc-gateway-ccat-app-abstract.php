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
 * @class    WC_Gateway_CCat
 * @version  1.10.0
 */
abstract class WC_Gateway_CCat_App_Abstract extends WC_Gateway_CCat_Abstract
{
    const CMD_COCS_ORDER_APPEND = 'DphOrderAppend';  // phpcs:ignore Generic.Formatting.MultipleStatementAlignment

    /**
     * Processes the payment for a given order.
     *
     * @param int $order_id The ID of the order for which the payment is being processed.
     *
     * @return array An array containing the result of the payment and the redirect URL upon success; otherwise, throws an exception on failure.
     * @throws Exception If the payment fails, an exception is thrown with an appropriate error message.
     */
    public function process_payment($order_id): array
    {
        $datetime = new DateTime('now', new DateTimeZone('Asia/Taipei'));
        $order = wc_get_order($order_id);

        $api_url = $this->get_base_url() . 'api/Collect';
        $api_token = $this->get_payment_api_token();

        try {
            $payer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            $payer_email = $order->get_billing_email();

            // 基本驗證
            if (!filter_var($payer_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception(esc_html__('信箱格式錯誤或沒有填寫', 'woocommerce-gateway-ccat'));
            }

            // 生成訂單編號
            $order_no = $this->generate_unique_order_number($order);

            // 準備新的 API 參數
            $post_data = array(
                'cmd' => self::CMD_COCS_ORDER_APPEND,  // 固定值
                'cust_id' => $this->get_account(),
                'cust_order_no' => $order_no,
                'order_amount' => $order->get_total(),
                'order_detail' => substr('Order #' . $order->get_id(), 0, 500),  // 限制 500 字元
                'payer_name' => $payer_name,
                'acquirer_type' => $this->payment_type(),  // 可根據需求改為 'icp'
                'send_time' => $datetime->format('Y-m-d H:i:s'),
                'success_url' => '',
                'apn_url' => $this->get_apn_url(),
            );
            if ('yes' === get_option('wc_ccat_invoice_enable', 'no')) {
                $post_data = $this->add_invoice_data($post_data, $order);
            }
            $args = array(
                'body' => wp_json_encode($post_data),
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $api_token,
                ),
                'method' => 'POST',
                'timeout' => 45,
            );

            $response = wp_remote_post($api_url, $args);
            if ($this->is_test_mode()) {
                WC_CCat_Payments::log('request:' . print_r($post_data, true));
                WC_CCat_Payments::log('response:' . $response);
            }
            if (is_wp_error($response)) {
                throw new Exception(esc_html__('Api error. Try again later.', 'woocommerce-gateway-ccat'));
            }

            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);

            if (empty($response_data['status']) || self::CODE_OK !== $response_data['status']) {
                $logger = wc_get_logger();
                $logger->error(
                    'Token:' . $api_token,
                    array('source' => 'api-error')
                );
                $logger->error(
                    'Post:' . print_r($post_data, true) . ' Response:' . $response_body,
                    array('source' => 'api-error')
                );
                throw new Exception($response_data['msg'] ?? __('Unknown Error.', 'woocommerce-gateway-ccat'));
            }

            $redirect = !empty($response_data['url']) ? $response_data['url'] : $this->get_return_url($order);

            // 儲存回應資料
            $order->update_meta_data(self::META_RESPONSE_DATA, $response_body);
            $order->update_meta_data($order_no, $order_no);
            $order->save();
            // 處理回應
            return array(
                'result' => 'success',
                'redirect' => $redirect,
            );

        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            return array(
                'result' => 'failure',
                'messages' => $e->getMessage(),
            );
        }
    }

    /**
     * Retrieves the configured payment type for the payment gateway.
     *
     * @return string The payment type as configured in the gateway settings.
     */
    abstract public function payment_type(): string;

    /**
     * Extracts and validates the request data.
     *
     * @param WP_REST_Request $request
     * @return null|WP_Error
     */
    protected function handle_payment_validation(WP_REST_Request $request): ?WP_Error
    {
        $body = $request->get_body();
        $data = json_decode($body, true);

        if ($this->is_test_mode()) {
            WC_CCat_Payments::log('Payment callback received: ' . $body);
        }

        if (!$data) {
            return new WP_Error(400, 'Invalid JSON');
        }

        $required_fields = array(
            'ret',
            'cust_order_no',
            'order_amount',
            'send_time',
            'acquire_time',
            'auth_code',
            'card_no',
            'notify_time',
            'chk'
        );

        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                return new WP_Error(400, "Missing field: {$field}");
            }
        }

        $hash_base = $this->get_chk_code();

        // 組合檢核字串
        $check_string = implode('$', [
            $hash_base,
            $data['order_amount'],
            $data['send_time'],
            $data['ret'],
            $data['acquire_time'],
            $data['auth_code'],
            $data['card_no'],
            $data['notify_time'],
            $data['cust_order_no']
        ]);

        $calculated_chk = md5($check_string);

        if ($calculated_chk !== $data['chk']) {
            WC_CCat_Payments::log('Checksum verification failed. Expected: ' . $calculated_chk . ', Received: ' . $data['chk']);
            return new WP_Error(400, 'Checksum verification failed');
        }

        if ($data['ret'] !== 'OK') {
            return new WP_Error(400, 'Transaction not successful');
        }

        return null;
    }

    /**
     * 實作退款處理方法
     *
     * @param int $order_id 訂單編號
     * @param float $amount 退款金額（可能為 null）
     * @param string $reason 退款原因
     * @return bool|WP_Error  成功返回 true，失敗返回錯誤物件
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('invalid_order', '找不到訂單');
        }

        if ($amount != $order->get_total()) {
            return new WP_Error('invalid_amount', '統一金流只支援全額退款');
        }

        $api_token = $this->get_payment_api_token();
        $order_no = $order->get_meta(self::ORDER_NO);
        $processing = $order->get_meta(self::META_PROCESSING, 1);
        try {
            if (empty($processing) && (is_null($amount) || empty($order->get_remaining_refund_amount()))) {
                $request_data = array(
                    'cmd' => 'DphOrderCancel',
                    'cust_id' => $this->get_account(),
                    'cust_order_no' => $order_no,
                    'order_amount' => $order->get_total(),
                    'acquirer_type' => $this->payment_type(),
                    'send_time' => current_time('Y-m-d H:i:s'),
                );

                $response = wp_remote_post($this->get_base_url() . '/api/Collect', array(
                    'method' => 'POST',
                    'timeout' => 30,
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $api_token,
                    ),
                    'body' => wp_json_encode($request_data),
                ));

                if (is_wp_error($response)) {
                    return new WP_Error('api_connection_error', __('API 請求失敗: ', 'woocommerce-gateway-ccat') . $response->get_error_message());
                }
                $body = wp_remote_retrieve_body($response);
                if ($this->is_test_mode()) {
                    WC_CCat_Payments::log('Refund request data: ' . wp_json_encode($request_data));
                    WC_CCat_Payments::log('Refund response data: ' . $body);
                }
                $response_data = json_decode($body, true);
                if (empty($response_data)) {
                    return new WP_Error('api_response_error', __('Json Decode Fail.', 'woocommerce-gateway-ccat'));
                }
                $status = $response_data['status'] ?? null;
                $msg = $response_data['msg'] ?? '';
                if ('OK' !== $status) {
                    return new WP_Error('api_response_error', __('API error: ', 'woocommerce-gateway-ccat') . $msg);
                }
                return true;
            }

            if (!empty($order_no)) {
                $request_data = array(
                    'cmd' => 'DphOrderRefund',
                    'cust_id' => $this->get_account(),
                    'cust_order_no' => $order_no,
                    'order_amount' => $order->get_total(),
                    'acquirer_type' => $this->payment_type(),
                    'send_time' => current_time('Y-m-d H:i:s'),
                );

                $response = wp_remote_post($this->get_base_url() . '/api/Collect', array(
                    'method' => 'POST',
                    'timeout' => 30,
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $api_token,
                    ),
                    'body' => wp_json_encode($request_data),
                ));

                if (is_wp_error($response)) {
                    return new WP_Error('api_connection_error', __('API 請求失敗: ', 'woocommerce-gateway-ccat') . $response->get_error_message());
                }
                $body = wp_remote_retrieve_body($response);
                if ($this->is_test_mode()) {
                    WC_CCat_Payments::log('Refund request data: ' . wp_json_encode($request_data));
                    WC_CCat_Payments::log('Refund response data: ' . $body);
                }
                $response_data = json_decode($body, true);
                if (empty($response_data)) {
                    return new WP_Error('api_response_error', __('JSON Decode Fail.', 'woocommerce-gateway-ccat'));
                }

                $status = $response_data['status'] ?? null;
                if ('OK' !== $status) {
                    $msg = $response_data['msg'] ?? '';
                    return new WP_Error('api_response_error', __('API error: ', 'woocommerce-gateway-ccat') . $msg);
                }
                return true;
            }

            return new WP_Error('refund_failed', '退款處理失敗');
        } catch (Exception $e) {
            return new WP_Error('refund_error', $e->getMessage());
        }
    }

    public function acquirer_type(): string
    {
        return $this->payment_type();
    }
}
