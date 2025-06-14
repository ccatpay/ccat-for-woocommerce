<?php
/**
 * CCATPAY_Gateway_Payuni class
 *
 * @author   sakilu <brian@sakilu.com>
 * @package  WooCommerce CCat Payments Gateway
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'class-ccatpay-gateway-abstract.php';

/**
 * CCat Gateway.
 *
 * @class    CCATPAY_Gateway_Payuni
 * @version  1.10.0
 */
class CCATPAY_Gateway_Payuni extends CCATPAY_Gateway_Abstract {


	/**
	 * Payment gateway instructions.
	 *
	 * @var string
	 */
	protected string $instructions;

	/**
	 * Whether the gateway is visible for non-admin users.
	 *
	 * @var boolean
	 */
	protected $hide_for_non_admin_users;

	/**
	 * Supports
	 *
	 * @var array $supports
	 */
	public $supports = array(
		'products',
		'refunds',
	);

	/**
	 * Unique id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'ccat_payment_pay_uni';

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		$this->title       = __( '黑貓Pay - 信用卡(統一金流)', WC_CCAT_PAYMENTS_DOMAIN );
		$this->description = __( '使用黑貓Pay信用卡(統一金流)付款，付款更安心。', WC_CCAT_PAYMENTS_DOMAIN );
		parent::__construct();
	}


	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( '啟用', WC_CCAT_PAYMENTS_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => __( '啟用', WC_CCAT_PAYMENTS_DOMAIN ),
				'default' => 'yes',
			),
			'title'   => array(
				'title'       => __( '付款標題', WC_CCAT_PAYMENTS_DOMAIN ),
				'type'        => 'text',
				'description' => __( '使用者選擇付款時顯示的文字', WC_CCAT_PAYMENTS_DOMAIN ),
				'default'     => __( '黑貓Pay - 信用卡(統一金流)', WC_CCAT_PAYMENTS_DOMAIN ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Retrieves the configured acquirer type for the payment gateway.
	 *
	 * @return string The acquirer type as configured in the gateway settings.
	 */
	public function acquirer_type(): string {
		return 'payuni';
	}
}
