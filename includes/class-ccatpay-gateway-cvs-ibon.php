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

require_once 'class-ccatpay-gateway-cvs-abstract.php';

/**
 * CCat Gateway.
 *
 * @class    CCATPAY_Gateway_Cvs_Ibon
 * @version  1.0
 */
class CCATPAY_Gateway_Cvs_Ibon extends CCATPAY_Gateway_Cvs_Abstract {

	/**
	 * Unique id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'ccat_payment_cvs_ibon';

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		$this->title       = __( '黑貓Pay - Ibon繳款', 'ccat-for-woocommerce');
		$this->description = __( '使用黑貓Pay Ibon，付款更安心。', 'ccat-for-woocommerce');
		add_action( 'woocommerce_thankyou', array( $this, 'display_payment_button' ) );
		add_action( 'woocommerce_view_order', array( $this, 'display_payment_button' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'display_payment_button' ) );

		parent::__construct();
	}


	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( '啟用', 'ccat-for-woocommerce'),
				'type'    => 'checkbox',
				'label'   => __( '啟用', 'ccat-for-woocommerce'),
				'default' => 'yes',
			),
			'title'   => array(
				'title'       => __( '付款標題', 'ccat-for-woocommerce'),
				'type'        => 'text',
				'description' => __( '使用者選擇付款時顯示的文字', 'ccat-for-woocommerce'),
				'default'     => __( '黑貓Pay - Ibon繳款', 'ccat-for-woocommerce'),
				'desc_tip'    => true,
			),
			'description'   => array(
				'title'       => __( '付款說明', 'ccat-for-woocommerce'),
				'type'        => 'textarea',
			),
		);
	}

	/**
	 * Retrieves the configured payment type for the payment gateway.
	 *
	 * @return string The payment type as configured in the gateway settings.
	 */
	public function payment_type(): string {
		return '0';
	}

	/**
	 * Retrieves the configured acquirer type for the payment gateway.
	 *
	 * @return string The acquirer type as configured in the gateway settings.
	 */
	public function acquirer_type(): string {
		return '2';
	}

	/**
	 * Displays the payment button for Ibon payment.
	 *
	 * @param int $order_id The ID of the order.
	 */
	public function display_payment_button( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			return;
		}

		$short_url        = $order->get_meta( '_ccat_short_url' );
		$ibon_code        = $order->get_meta( '_ccat_ibon_code' );
		$payment_deadline = $order->get_meta( self::ATM_EXPIRE_DATA );
		$bill_amount      = $order->get_meta( self::ATM_BILL_AMOUNT );

		$current_action = current_filter();
		$html           = '';

		if ( $ibon_code && $payment_deadline ) {
			if ( 'woocommerce_admin_order_data_after_order_details' !== $current_action ) {
				$html .= '<h2>' . esc_html__( '感謝訂購 請至 Ibon 機台繳款', 'ccat-for-woocommerce') . '</h2>';
			}

			$html .= '<p>' . esc_html( sprintf( __( 'Ibon 繳款代碼: %s', 'ccat-for-woocommerce'), $ibon_code ) ) . '</p>';
			$html .= '<p>' . esc_html( sprintf( __( '付款期限: %s', 'ccat-for-woocommerce'), $payment_deadline ) ) . '</p>';
			$html .= '<p>' . esc_html( sprintf( __( '繳款金額: %d', 'ccat-for-woocommerce'), $bill_amount ) ) . '</p>';
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( $short_url ) {
			if ( 'woocommerce_admin_order_data_after_order_details' !== $current_action ) {
				$html .= '<div class="ccat-pay-button-container" style="margin: 2em 0; padding: 2em; border-radius: 12px; background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%); border: 1px solid #e0e0e0; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center;">';
				$html .= '<h3 style="margin-top: 0; color: #333; font-weight: 600;">' . esc_html__( '訂單已建立，請完成後續繳費', 'ccat-for-woocommerce' ) . '</h3>';
				$html .= '<p style="color: #666; margin-bottom: 1.5em;">' . esc_html__( '點擊下方按鈕將開啟黑貓Pay支付頁面取得繳費代碼。', 'ccat-for-woocommerce' ) . '</p>';
				$html .= '<a href="' . esc_url( $short_url ) . '" class="button alt" style="display: inline-block; padding: 15px 40px; font-size: 1.1em; font-weight: 600; text-decoration: none; border-radius: 50px; background: #000; color: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.2);">' . esc_html__( '前往 Ibon 繳費', 'ccat-for-woocommerce' ) . '</a>';
				$html .= '</div>';
			} else {
				// Admin view.
				$html .= '<p class="form-field form-field-wide"><strong>' . esc_html__( 'Ibon 繳費連結:', 'ccat-for-woocommerce' ) . '</strong><br>';
				$html .= '<a href="' . esc_url( $short_url ) . '" target="_blank">' . esc_html( $short_url ) . '</a></p>';
			}

			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
