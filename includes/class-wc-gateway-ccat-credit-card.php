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
class WC_Gateway_CCat_Credit_Card extends WC_Gateway_CCat_Abstract {

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
	public $id = 'ccat_payment_credit_card';

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

		$this->title       = __( '黑貓Pay - 信用卡(玉山銀行)', 'ccat-for-woocommerce' );
		$this->description = __( '使用黑貓Pay信用卡(玉山銀行)付款，付款更安心。', 'ccat-for-woocommerce' );
		parent::__construct();
	}


	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( '啟用', 'ccat-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( '啟用', 'ccat-for-woocommerce' ),
				'default' => 'yes',
			),
			'title'   => array(
				'title'       => __( '付款標題', 'ccat-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( '使用者選擇付款時顯示的文字', 'ccat-for-woocommerce' ),
				'default'     => __( '黑貓Pay - 信用卡(玉山銀行)', 'ccat-for-woocommerce' ),
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
		return 'esun';
	}

}
