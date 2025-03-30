<?php
/**
 * WC_Gateway_CCat class
 *
 * @author   sakilu <brian@sakilu.com>
 * @package  WooCommerce CCat Payments Gateway
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'class-wc-gateway-ccat-abstract.php';

/**
 * CCat Gateway.
 *
 * @class    WC_Gateway_CCat
 * @version  1.10.0
 */
abstract class WC_Gateway_CCat_Cvs_Abstract extends WC_Gateway_CCat_Abstract {
	const ATM_VIRTUAL_ACCOUNT = '_virtual_account';  // phpcs:ignore Generic.Formatting.MultipleStatementAlignment
	const ATM_EXPIRE_DATA = '_expire_date';  // phpcs:ignore Generic.Formatting.MultipleStatementAlignment
	const ATM_BILL_AMOUNT = '_bill_amount';  // phpcs:ignore Generic.Formatting.MultipleStatementAlignment

	const ATM_BILL_BARCODE_1 = '_bar_code_1';  // phpcs:ignore Generic.Formatting.MultipleStatementAlignment
	const ATM_BILL_BARCODE_2 = '_bar_code_2';  // phpcs:ignore Generic.Formatting.MultipleStatementAlignment
	const ATM_BILL_BARCODE_3 = '_bar_code_3';  // phpcs:ignore Generic.Formatting.MultipleStatementAlignment

	const CMD_COCS_ORDER_APPEND = 'CvsOrderAppend';  // phpcs:ignore Generic.Formatting.MultipleStatementAlignment

	/**
	 * Processes the payment for a given order.
	 *
	 * @param int $order_id The ID of the order for which the payment is being processed.
	 *
	 * @return array An array containing the result of the payment and the redirect URL upon success; otherwise, throws an exception on failure.
	 * @throws Exception If the payment fails, an exception is thrown with an appropriate error message.
	 */
	public function process_payment( $order_id ): array {
		$datetime = new DateTime( 'now', new DateTimeZone( 'Asia/Taipei' ) );
		$order    = wc_get_order( $order_id );

		$api_url   = $this->get_base_url() . 'api/Collect';
		$api_token = $this->get_payment_api_token();

		try {
			$payer_name     = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
			$payer_postcode = $order->get_billing_postcode();
			$payer_address  = $order->get_billing_address_1();
			$payer_mobile   = $order->get_billing_phone();
			$payer_email    = $order->get_billing_email();

			if ( empty( $payer_name ) ) {
				throw new Exception( esc_html__( '姓名為必填', 'ccat-for-woocommerce' ) );
			}
			if ( empty( $payer_postcode ) ) {
				throw new Exception( esc_html__( '郵遞區號為必填', 'ccat-for-woocommerce' ) );
			}
			if ( empty( $payer_address ) ) {
				throw new Exception( esc_html__( '地址為必填', 'ccat-for-woocommerce' ) );
			}
			if ( empty( $payer_mobile ) ) {
				throw new Exception( esc_html__( '手機為必填', 'ccat-for-woocommerce' ) );
			}
			if ( ! filter_var( $payer_email, FILTER_VALIDATE_EMAIL ) ) {
				throw new Exception( esc_html__( '信箱格式錯誤或沒有填寫', 'ccat-for-woocommerce' ) );
			}
			$order_no  = $this->generate_unique_order_number( $order );
			$post_data = array(
				'cmd'                  => self::CMD_COCS_ORDER_APPEND,
				'cust_id'              => $this->get_account(),
				'cust_order_no'        => $order_no,
				'order_amount'         => $order->get_total(),
				'expire_date'          => $datetime->modify( '+7 days' )->format( 'Y-m-d' ),
				'payer_name'           => $payer_name,
				'payer_postcode'       => $payer_postcode,
				'payer_address'        => $payer_address,
				'payer_mobile'         => $payer_mobile,
				'payer_email'          => $payer_email,
				'payment_type'         => $this->payment_type(),
				'payment_acquirerType' => $this->acquirer_type(),
				'apn_url'              => $this->get_apn_url(),
				'order_detail'         => substr( 'Order #' . $order->get_id(), 0, 150 ),
			);
			if ( 'yes' === get_option( 'wc_ccat_invoice_enable', 'no' ) ) {
				$post_data = $this->add_invoice_data( $post_data, $order );
			}
			$args = array(
				'body'    => wp_json_encode( $post_data ),
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_token,
				),
				'method'  => 'POST',
				'timeout' => 45,
			);

			$response = wp_remote_post( $api_url, $args );

			if ( is_wp_error( $response ) ) {
				throw new Exception( esc_html( __( 'Api error. Try again later.', 'ccat-for-woocommerce' ) ) );
			}

			$response_body = wp_remote_retrieve_body( $response );
			$response_data = json_decode( $response_body, true );
			$order->update_meta_data( self::META_RESPONSE_DATA, $response_body );
			$order->update_meta_data( $order_no, $order_no );

			if ( $this->payment_type() == '1' ) {
				$virtual_account = sanitize_text_field( $response_data['virtual_account'] );
				$expire_date     = sanitize_text_field( $response_data['expire_date'] );
				$bill_amount     = intval( $response_data['bill_amount'] );

				// 儲存到訂單 Meta Data
				$order->update_meta_data( self::ATM_VIRTUAL_ACCOUNT, $virtual_account );
				$order->update_meta_data( self::ATM_EXPIRE_DATA, $expire_date );
				$order->update_meta_data( self::ATM_BILL_AMOUNT, $bill_amount );
			}

			if ( $this->payment_type() == '2' ) {
				$order->update_meta_data( self::ATM_BILL_BARCODE_1, $response_data['st_barcode1'] );
				$order->update_meta_data( self::ATM_BILL_BARCODE_2, $response_data['st_barcode2'] );
				$order->update_meta_data( self::ATM_BILL_BARCODE_3, $response_data['st_barcode3'] );
				$order->update_meta_data( self::ATM_BILL_AMOUNT, intval( $response_data['bill_amount'] ) );
			}

			if ( isset( $response_data['status'] ) && self::CODE_OK === $response_data['status'] ) {
				$this->add_apn_notification( $order, $response_data );
				$order->update_status(
					Automattic\WooCommerce\Enums\OrderStatus::PENDING,
					__( 'Awaiting payment', 'ccat-for-woocommerce' )
				);
				$order->save();
				WC()->cart->empty_cart();

				$redirect = ! empty( $response_data['short_url'] ) ? $response_data['short_url'] : $this->get_return_url( $order );

				return array(
					'result'   => 'success',
					'redirect' => $redirect,
				);
			} else {
				$error_message = $response_data['msg'] ?? __( 'Unknown Error.', 'ccat-for-woocommerce' );
				delete_transient( 'api_access_token' );
				throw new Exception( $error_message );
			}
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			throw new Exception( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Retrieves the configured payment type for the payment gateway.
	 *
	 * @return string The payment type as configured in the gateway settings.
	 */
	abstract public function payment_type(): string;

	/**
	 * Handle the payment callback from the payment gateway.
	 *
	 * @param WP_REST_Request $request The incoming request object containing payment callback data.
	 *
	 * @return WP_REST_Response A response object indicating the status of the callback handling.
	 */
	public function handle_payment_callback( WP_REST_Request $request ): WP_REST_Response {
		$validate = $this->handle_payment_validation( $request );
		if ( $validate instanceof WP_Error ) {
			return new WP_REST_Response( $validate->get_error_message(), $validate->get_error_code() );
		}

		$data  = json_decode( $request->get_body(), true );
		$order = $this->get_order( $data['order_no'] );
		$this->add_apn_notification( $order, $data );
		$status = strtoupper( $data['status'] );

		switch ( $status ) {
			case 'A':
				$note = __( '等待繳款人繳納', 'ccat-for-woocommerce' );
				$order->update_status( 'pending' );
				break;

			case 'B':
				$note = __( '繳款人已繳納', 'ccat-for-woocommerce' );
				$order->set_payment_method( $this );
				$order->payment_complete();
				break;

			case 'C':
				$note = __( '契客註定註銷', 'ccat-for-woocommerce' );
				$order->update_status( Automattic\WooCommerce\Enums\OrderStatus::CANCELLED );
				break;

			case 'D':
				$note = __( '已過期繳款單', 'ccat-for-woocommerce' );
				$order->update_status( Automattic\WooCommerce\Enums\OrderStatus::CANCELLED );
				break;

			case 'E':
				$note = __( '已預約撥款給契客', 'ccat-for-woocommerce' );
				break;

			case 'I':
				$note = __( '開立發票通知', 'ccat-for-woocommerce' );
				break;

			case 'J':
				$note = __( '開立發票折讓單號通知', 'ccat-for-woocommerce' );
				break;

			default:
				$note = __( '未知狀態碼: ', 'ccat-for-woocommerce' ) . $status;
				break;
		}

		$order->add_order_note( $note );

		return new WP_REST_Response( 'OK', 200 );
	}

	public function acquirer_type(): string {
		return '0';
	}
}
