<?php
/**
 * CCATPAY_Gateway_Cvs_Atm class
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
 * @class    CCATPAY_Gateway_Cvs_Atm
 * @version  1.0
 */
class CCATPAY_Gateway_Cvs_Atm extends CCATPAY_Gateway_Cvs_Abstract {

	/**
	 * Unique id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'ccat_payment_cvs_atm';

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		$this->title       = __( '黑貓Pay - ATM繳款', 'ccat-for-woocommerce');
		$this->description = __( '使用黑貓Pay ATM，付款更安心。', 'ccat-for-woocommerce');
		add_action(
			'woocommerce_thankyou',
			array(
				$this,
				'display_virtual_account_details',
			)
		);
		add_action(
			'woocommerce_view_order',
			array(
				$this,
				'display_virtual_account_details',
			)
		);
		add_action(
			'woocommerce_admin_order_data_after_order_details',
			array(
				$this,
				'display_virtual_account_details',
			)
		);

		parent::__construct();
	}

	/**
	 * Displays the virtual account details for an order.
	 *
	 * @param mixed $order_id The ID of the order for which to display the virtual account details.
	 *
	 * @return void
	 */
	public function display_virtual_account_details( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order->get_payment_method() !== $this->id ) {
			return;
		}

		$bank_id          = $order->get_meta( self::ATM_BANK_ID );
		$virtual_account  = $order->get_meta( self::ATM_VIRTUAL_ACCOUNT );
		$payment_deadline = $order->get_meta( self::ATM_EXPIRE_DATA );
		$bill_amount      = $order->get_meta( self::ATM_BILL_AMOUNT );

		if ( $virtual_account && $payment_deadline ) {
			$html           = '';
			$current_action = current_filter();
			if ( 'woocommerce_admin_order_data_after_order_details' !== $current_action ) {
				$html .= '<h2>' . esc_html__( '感謝訂購 請到ATM繳款', 'ccat-for-woocommerce') . '</h2>';
			}

			$html .= '<p>' . esc_html( sprintf( __( '銀行代號: %s', 'ccat-for-woocommerce'), $bank_id ) ) . '</p>';
			$html .= '<p>' . esc_html( sprintf( __( '轉帳帳號: %s', 'ccat-for-woocommerce'), $virtual_account ) ) . '</p>';
			$html .= '<p>' . esc_html( sprintf( __( '付款期限: %s', 'ccat-for-woocommerce'), $payment_deadline ) ) . '</p>';
			$html .= '<p>' . esc_html( sprintf( __( '繳款金額: %d', 'ccat-for-woocommerce'), $bill_amount ) ) . '</p>';
			echo $html;
		}
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
				'default'     => __( '黑貓Pay - ATM繳款', 'ccat-for-woocommerce'),
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
		return '1';
	}

	/**
	 * Retrieves the configured acquirer type for the payment gateway.
	 *
	 * @return string The acquirer type as configured in the gateway settings.
	 */
	public function acquirer_type(): string {
		return '3';
	}
}
