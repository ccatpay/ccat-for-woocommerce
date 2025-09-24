<?php
/**
 * CCATPAY_Gateway_Chinatrust class
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
 * @class    CCATPAY_Gateway_Chinatrust
 * @version  1.10.0
 */
class CCATPAY_Gateway_Chinatrust extends CCATPAY_Gateway_Abstract {

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
	 * Unique id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'ccat_payment_chinatrust';

	/**
	 * Title
	 *
	 * @var string Title
	 */
	public $title;

	/**
	 * Description
	 *
	 * @var string Description
	 */
	public $description;


	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		$this->title       = __( '黑貓Pay - 信用卡(中國信託)', 'ccat-for-woocommerce');
		$this->description = __( '使用黑貓Pay信用卡(中國信託)付款，付款更安心。', 'ccat-for-woocommerce');
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
				'default'     => __( '黑貓Pay - 信用卡(中國信託)', 'ccat-for-woocommerce'),
				'desc_tip'    => true,
			),
			'description'   => array(
				'title'       => __( '付款說明', 'ccat-for-woocommerce'),
				'type'        => 'textarea',
			),
		);
	}

	/**
	 * Retrieves the configured acquirer type for the payment gateway.
	 *
	 * @return string The acquirer type as configured in the gateway settings.
	 */
	public function acquirer_type(): string {
		return 'chinatrust';
	}
}
