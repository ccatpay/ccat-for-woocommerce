<?php
/**
 * 黑貓物流與支付協調器
 *
 * @package WooCommerceCCatGateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 黑貓物流與支付協調器類別
 */
class CCATPAY_Shipping_Payment_Coordinator {
	/**
	 * 初始化協調器
	 */
	public static function init() {
		// 添加過濾付款方式的鉤子.
		add_filter(
			'woocommerce_available_payment_gateways',
			array(
				__CLASS__,
				'filter_payment_gateways_by_shipping',
			),
		);
		add_filter(
			'woocommerce_rest_api_get_setting_payment_gateways',
			array(
				__CLASS__,
				'filter_api_payment_gateways',
			),
		);
		add_filter(
			'woocommerce_payment_gateways_available',
			array(
				__CLASS__,
				'filter_api_payment_gateways',
			),
		);
	}

	/**
	 * 根據已選擇的物流方式過濾付款閘道
	 *
	 * @param array $available_gateways 可用的付款閘道.
	 *
	 * @return array 過濾後的付款閘道
	 */
	public static function filter_payment_gateways_by_shipping( array $available_gateways ): array {
		// 檢查 WC 是否已初始化.
		if ( ! function_exists( 'WC' ) || ! isset( WC()->session ) ) {
			return $available_gateways;
		}

		// 獲取已選擇的物流方式.
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( empty( $chosen_shipping_methods ) ) {
			return $available_gateways;
		}

		// 檢查選擇的物流方式是否為貨到付款類型.
		$is_cod_shipping = self::is_cod_shipping_selected( $chosen_shipping_methods );

		// 根據物流方式類型過濾支付方式.
		if ( $is_cod_shipping ) {
			// 檢查是否選擇了711的物流方式.
			$is_711_shipping = false;
			foreach ( $chosen_shipping_methods as $method ) {
				if ( false !== strpos( $method, '711' ) ) {
					$is_711_shipping = true;
					break;
				}
			}

			if ( $is_711_shipping ) {
				// 如果選擇了711相關物流，只允許使用711貨到付款支付方式.
				foreach ( $available_gateways as $id => $gateway ) {
					if ( 'ccat_cod_711' !== $id ) {
						unset( $available_gateways[ $id ] );
					}
				}
			} else {
				// 如果選擇了非711的貨到付款物流，允許使用其他貨到付款支付方式.
				foreach ( $available_gateways as $id => $gateway ) {
					if ( ! in_array( $id, array( 'ccat_cod_card', 'ccat_cod_cash', 'ccat_cod_mobile' ), true ) ) {
						unset( $available_gateways[ $id ] );
					}
				}
			}
		} else {
			// 如果選擇了非貨到付款物流，排除所有貨到付款支付方式.
			foreach ( $available_gateways as $id => $gateway ) {
				if ( strpos( $id, 'cod' ) !== false ) {
					unset( $available_gateways[ $id ] );
				}
			}
		}

		return $available_gateways;
	}

	/**
	 * 過濾 API 響應中的支付方式
	 *
	 * @param array $payment_gateways 支付方式 ID 列表.
	 *
	 * @return array 過濾後的支付方式 ID 列表
	 */
	public static function filter_api_payment_gateways( array $payment_gateways ): array {
		// 檢查 WC 是否已初始化.
		if ( ! function_exists( 'WC' ) || ! isset( WC()->session ) ) {
			return $payment_gateways;
		}

		// 獲取已選擇的物流方式.
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( empty( $chosen_shipping_methods ) ) {
			return $payment_gateways;
		}

		// 檢查選擇的物流方式是否為貨到付款類型.
		$is_cod_shipping = self::is_cod_shipping_selected( $chosen_shipping_methods );
		if ( $is_cod_shipping ) {
			$is_711_shipping = false;
			foreach ( $chosen_shipping_methods as $method ) {
				if ( false !== strpos( $method, '711' ) ) {
					$is_711_shipping = true;
					break;
				}
			}

			if ( $is_711_shipping ) {
				// 如果選擇了711相關物流，只允許使用711貨到付款支付方式.
				return array_filter(
					$payment_gateways,
					function ( $gateway ) {
						return 'ccat_cod_711' === $gateway;
					}
				);
			} else {
				// 如果選擇了非711的貨到付款物流，允許使用其他貨到付款支付方式.
				return array_filter(
					$payment_gateways,
					function ( $gateway ) {
						return in_array( $gateway, array( 'ccat_cod_card', 'ccat_cod_cash', 'ccat_cod_mobile' ), true );
					}
				);
			}
		} else {
			// 如果選擇了非貨到付款物流，排除所有貨到付款支付方式.
			return array_filter(
				$payment_gateways,
				function ( $gateway ) {
					return strpos( $gateway, 'cod' ) === false;
				}
			);
		}
	}

	/**
	 * 檢查是否選擇了貨到付款物流方式
	 *
	 * @param array $chosen_methods 已選擇的物流方式.
	 *
	 * @return bool 是否選擇了貨到付款物流
	 */
	private static function is_cod_shipping_selected( array $chosen_methods ): bool {
		foreach ( $chosen_methods as $method ) {
			if ( false !== strpos( $method, 'cod' ) ) {
				return true;
			}
		}

		return false;
	}
}
