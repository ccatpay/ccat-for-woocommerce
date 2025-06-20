<?php
/**
 * 黑貓電子發票
 *
 * @package WooCommerceCCatGateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_CCat_Invoice_Display class handles the display of electronic invoice information
 * both on the frontend and backend order detail pages.
 */
class CCATPAY_Invoice_Display {

	/**
	 * 初始化 hooks
	 */
	public function __construct() {
		// 前台訂單詳細頁面.
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_invoice_info' ) );

		// 後台訂單詳細頁面.
		add_action(
			'woocommerce_admin_order_data_after_billing_address',
			array(
				$this,
				'display_admin_invoice_info',
			)
		);
	}

	/**
	 * 顯示前台發票資訊
	 *
	 * @param WC_Order $order 訂單資訊.
	 */
	public function display_invoice_info( WC_Order $order ) {
		if ( 'yes' !== get_option( CCATPAYMENTS_PREFIX . '_invoice_enable', 'no' ) ) {
			return;
		}

		$invoice_no = $order->get_meta( CCATPAY_Gateway_Abstract::META_INVOICE_NO );
		if ( empty( $invoice_no ) ) {
			return;
		}

		$invoice_data = $order->get_meta( CCATPAY_Gateway_Abstract::META_INVOICE_APN );

		?>
		<h2><?php esc_html_e( '電子發票資訊', 'ccat-for-woocommerce'); ?></h2>
		<table class="woocommerce-table invoice-details">
			<tbody>
			<tr>
				<th><?php esc_html_e( '發票號碼：', 'ccat-for-woocommerce'); ?></th>
				<td><?php echo esc_html( $invoice_no ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( '開立日期：', 'ccat-for-woocommerce'); ?></th>
				<td><?php echo esc_html( $invoice_data['invoice_date'] ?? '' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( '隨機碼：', 'ccat-for-woocommerce'); ?></th>
				<td><?php echo esc_html( $invoice_data['random_number'] ?? '' ); ?></td>
			</tr>
			<?php if ( ! empty( $invoice_data['invoice_discount_no'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( '折讓單號：', 'ccat-for-woocommerce'); ?></th>
					<td><?php echo esc_html( $invoice_data['invoice_discount_no'] ); ?></td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}


	/**
	 * Displays electronic invoice information on the admin order details page in WooCommerce.
	 *
	 * @param WC_Order $order The WooCommerce order object containing the order details.
	 *
	 * @return void This method does not return a value.
	 */
	public function display_admin_invoice_info( WC_Order $order ) {
		if ( 'yes' !== get_option( CCATPAYMENTS_PREFIX . '_invoice_enable', 'no' ) ) {
			return;
		}

		$invoice_data = $order->get_meta( CCATPAY_Gateway_Abstract::META_INVOICE_APN );
		if ( empty( $invoice_data ) ) {
			return;
		}

		?>
		<div class="order_data_column">
			<h3><?php esc_html_e( '電子發票資訊', 'ccat-for-woocommerce'); ?></h3>
			<div class="address">
				<p>
					<strong><?php esc_html_e( '發票號碼：', 'ccat-for-woocommerce'); ?></strong>
					<?php echo esc_html( $invoice_data['invoice_no'] ); ?><br/>

					<strong><?php esc_html_e( '開立日期：', 'ccat-for-woocommerce'); ?></strong>
					<?php echo esc_html( $invoice_data['invoice_date'] ); ?><br/>

					<strong><?php esc_html_e( '隨機碼：', 'ccat-for-woocommerce'); ?></strong>
					<?php echo esc_html( $invoice_data['random_number'] ); ?><br/>

					<?php if ( ! empty( $invoice_data['vehicle_type'] ) ) : ?>
						<strong><?php esc_html_e( '載具類型：', 'ccat-for-woocommerce'); ?></strong>
						<?php
						$vehicle_types = array(
							'1' => '會員載具',
							'2' => '手機條碼',
							'3' => '自然人憑證',
						);
						echo esc_html( $vehicle_types[ $invoice_data['vehicle_type'] ] ?? '' );
						?>
						<br/>
					<?php endif; ?>

					<?php if ( ! empty( $invoice_data['vehicle_barcode'] ) ) : ?>
						<strong><?php esc_html_e( '載具條碼：', 'ccat-for-woocommerce'); ?></strong>
						<?php echo esc_html( $invoice_data['vehicle_barcode'] ); ?><br/>
					<?php endif; ?>

					<?php if ( ! empty( $invoice_data['love_code'] ) ) : ?>
						<strong><?php esc_html_e( '愛心碼：', 'ccat-for-woocommerce'); ?></strong>
						<?php echo esc_html( $invoice_data['love_code'] ); ?><br/>
					<?php endif; ?>

					<?php if ( ! empty( $invoice_data['invoice_discount_no'] ) ) : ?>
						<strong><?php esc_html_e( '折讓單號：', 'ccat-for-woocommerce'); ?></strong>
						<?php echo esc_html( $invoice_data['invoice_discount_no'] ); ?>
					<?php endif; ?>
				</p>
			</div>
		</div>
		<?php
	}
}
