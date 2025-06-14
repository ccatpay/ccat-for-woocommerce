<?php
/**
 * 黑貓物流抽象類別
 *
 * @package WooCommerceCCatGateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 黑貓物流抽象類別
 */
abstract class CCATPAY_Shipping_Abstract extends WC_Shipping_Method {
	/**
	 * 是否需要付款
	 *
	 * @var bool
	 */
	protected bool $requires_payment = true;

	/**
	 * 商店選擇URL
	 *
	 * @var string
	 */
	protected string $store_selection_url = '';

	/**
	 * 溫度類型
	 *
	 * @var string
	 */
	protected string $temperature_type = 'normal';

	/**
	 * 建構函數
	 *
	 * @param int $instance_id 運送方式實例ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->instance_id = absint( $instance_id );
		$this->id          = strtolower( static::class );
		$this->supports    = array(
			'shipping-zones',
			'instance-settings',
		);

		// 載入設定.
		$this->init_form_fields();
		$this->init_settings();

		// 儲存設定.
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		parent::__construct( $instance_id );
	}

	/**
	 * 初始化表單欄位
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(
			'title' => array(
				'title'       => __( '運送方式名稱', 'ccat-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( '顧客看到的名稱', 'ccat-for-woocommerce' ),
				'default'     => $this->method_title,
			),
			'cost'  => array(
				'title'       => __( '運費', 'ccat-for-woocommerce' ),
				'type'        => 'price',
				'default'     => '0',
				'description' => __( '運送費用', 'ccat-for-woocommerce' ),
			),
		);
	}

	/**
	 * 計算運費
	 *
	 * @param array $package 包裹資訊.
	 */
	public function calculate_shipping( $package = array() ) {
		$rate = array(
			'id'    => $this->get_rate_id(),
			'label' => $this->title,
			'cost'  => $this->get_option( 'cost' ),
		);

		$this->add_rate( $rate );
	}
}
