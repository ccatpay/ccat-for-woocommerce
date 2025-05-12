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
abstract class WC_Shipping_CCat_Abstract extends WC_Shipping_Method {
	/**
	 * 是否需要付款
	 *
	 * @var bool
	 */
	protected $requires_payment = true;

	/**
	 * 是否需要選擇超商
	 *
	 * @var bool
	 */
	protected $requires_store_selection = false;

	/**
	 * 商店選擇URL
	 *
	 * @var string
	 */
	protected $store_selection_url = '';

	/**
	 * 建構函數
	 *
	 * @param int $instance_id 運送方式實例ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->instance_id = absint( $instance_id );
		$this->supports    = array(
			'shipping-zones',
			'instance-settings',
		);

		// 載入設定.
		$this->init_form_fields();
		$this->init_settings();

		// 儲存設定.
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
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

	/**
	 * 檢查是否需要付款
	 *
	 * @return bool
	 */
	public function requires_payment() {
		return $this->requires_payment;
	}

	/**
	 * 檢查是否需要選擇超商
	 *
	 * @return bool
	 */
	public function requires_store_selection() {
		return $this->requires_store_selection;
	}

	/**
	 * 獲取商店選擇URL
	 *
	 * @return string
	 */
	public function get_store_selection_url() {
		return $this->store_selection_url;
	}
}