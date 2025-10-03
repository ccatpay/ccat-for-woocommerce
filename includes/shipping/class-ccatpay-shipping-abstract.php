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

    public $cost;
    public $cost_requires;
    public $min_amount;
    public $coupon_check;

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

        $this->title         = $this->get_option('title');
        $this->tax_status    = $this->get_option('tax_status');
        $this->cost          = $this->get_option('cost');
        $this->cost_requires = $this->get_option('cost_requires');
        $this->min_amount    = $this->get_option('min_amount', 0);

        $this->coupon_check  = ['coupon', 'either', 'both'];

		// 儲存設定.
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		parent::__construct( $instance_id );
	}

	/**
	 * 初始化表單欄位
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(
			'title' => [
        'title' => __('Title', 'woocommerce'),
        'type' => 'text',
        'default' => $this->method_title,
        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
        'desc_tip' => true
    ],
    'tax_status' => [
        'title' => __('Tax status', 'woocommerce'),
        'type' => 'select',
        'class' => 'wc-enhanced-select',
        'default' => 'none',
        'options' => [
            'taxable' => __('Taxable', 'woocommerce'),
            'none' => _x('None', 'Tax status', 'woocommerce')
        ],
    ],
    'cost' => [
        'title' => __('運送費用', 'ccat-for-woocommerce'),
        'type' => 'number',
        'default' => 0,
        'min' => 0,
        'step' => 1,
        'desc_tip' => true
    ],
    'cost_requires' => [
        'title'   => __( 'Free shipping requires...', 'woocommerce' ),
        'type'    => 'select',
        'class'   => 'wc-enhanced-select',
        'default' => '',
        'options' => [
            ''           => __('N/A', 'woocommerce'),
            'coupon'     => __('A valid free shipping coupon', 'woocommerce'),
            'min_amount' => __('A minimum order amount', 'woocommerce'),
            'either'     => __('A minimum order amount OR a coupon', 'woocommerce'),
            'both'       => __('A minimum order amount AND a coupon', 'woocommerce'),
        ]
    ],
    'min_amount' => [
        'title' => __('A minimum order amount', 'woocommerce'),
        'type' => 'price',
        'default' => 0,
        'placeholder' => wc_format_localized_price(0),
        'description' => __('Users will need to spend this amount to get free shipping (if enabled above).', 'woocommerce'),
        'desc_tip' => true
    ]
		);
	}

	/**
	 * 計算運費
	 *
	 * @param array $package 包裹資訊.
	 */
	public function calculate_shipping( $package = array() ) {
        $rate = [
            'id'      => $this->get_rate_id(),
            'label'   => $this->title,
            'cost'    => $this->cost,
            'package' => $package,
        ];

        if ($this->check_free_shipping()) {
            $rate['cost'] = 0;
        }

        $this->add_rate($rate);
        do_action('woocommerce_' . $this->id . '_shipping_add_rate', $this, $rate);
	}

    private function check_free_shipping()
    {
        $has_coupon = $this->has_coupon();

        $total = WC()->cart->get_displayed_subtotal();
        if ('incl' === WC()->cart->get_tax_price_display_mode) {
            $total = round($total - (WC()->cart->get_cart_discount_total() + WC()->cart->get_cart_discount_tax_total()), wc_get_price_decimals());
        } else {
            $total = round($total - WC()->cart->get_cart_discount_total(), wc_get_price_decimals());
        }

        $min_amount_condition = ($total >= $this->min_amount);

        if ($this->cost_requires == 'coupon') {
            return $has_coupon;
        }

        if ($this->cost_requires == 'min_amount') {
            return $min_amount_condition;
        }

        if ($this->cost_requires == 'either') {
            return $has_coupon || $min_amount_condition;
        }

        if ($this->cost_requires == 'both') {
            return $has_coupon && $min_amount_condition;
        }

        return false;
    }

    private function has_coupon()
    {
        if (!in_array($this->cost_requires, $this->coupon_check)) {
            return false;
        }

        $coupons = WC()->cart->get_coupons();
        if (empty($coupons)) {
            return false;
        }

        foreach ($coupons as $coupon) {
            if ($coupon->is_valid() && $coupon->get_free_shipping()) {
                return true;
            }
        }

        return false;
    }
}
