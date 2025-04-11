<?php

class WC_CCat_Invoice_Display {

	/**
	 * 初始化 hooks
	 */
	public function __construct() {
		// 前台訂單詳細頁面
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_invoice_info' ) );

		// 後台訂單詳細頁面
		add_action( 'woocommerce_admin_order_data_after_billing_address', array(
			$this,
			'display_admin_invoice_info'
		) );
	}

	/**
	 * 顯示前台發票資訊
	 *
	 * @param WC_Order $order
	 */
	public function display_invoice_info( $order ) {
		if ( 'yes' !== get_option( 'wc_ccat_invoice_enable', 'no' ) ) {
			return;
		}

		$invoice_no = $order->get_meta( WC_Gateway_CCat_Abstract::META_INVOICE_NO );
		if ( empty( $invoice_no ) ) {
			return;
		}

		$invoice_data = $order->get_meta( WC_Gateway_CCat_Abstract::META_INVOICE_APN );

		?>
        <h2><?php esc_html_e( '電子發票資訊', 'woocommerce' ); ?></h2>
        <table class="woocommerce-table invoice-details">
            <tbody>
            <tr>
                <th><?php esc_html_e( '發票號碼：', 'woocommerce' ); ?></th>
                <td><?php echo esc_html( $invoice_no ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( '開立日期：', 'woocommerce' ); ?></th>
                <td><?php echo esc_html( $invoice_data['invoice_date'] ?? '' ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( '隨機碼：', 'woocommerce' ); ?></th>
                <td><?php echo esc_html( $invoice_data['random_number'] ?? '' ); ?></td>
            </tr>
			<?php if ( ! empty( $invoice_data['invoice_discount_no'] ) ) : ?>
                <tr>
                    <th><?php esc_html_e( '折讓單號：', 'woocommerce' ); ?></th>
                    <td><?php echo esc_html( $invoice_data['invoice_discount_no'] ); ?></td>
                </tr>
			<?php endif; ?>
            </tbody>
        </table>
		<?php
	}

	/**
	 * 顯示後台發票資訊
	 *
	 * @param WC_Order $order
	 */
	public function display_admin_invoice_info( $order ) {
		if ( 'yes' !== get_option( 'wc_ccat_invoice_enable', 'no' ) ) {
			return;
		}

		$invoice_data = $order->get_meta( WC_Gateway_CCat_Abstract::META_INVOICE_APN );
		if ( empty( $invoice_data ) ) {
			return;
		}

		?>
        <div class="order_data_column">
            <h3><?php esc_html_e( '電子發票資訊', 'woocommerce' ); ?></h3>
            <div class="address">
                <p>
                    <strong><?php esc_html_e( '發票號碼：', 'woocommerce' ); ?></strong>
					<?php echo esc_html( $invoice_data['invoice_no'] ); ?><br/>

                    <strong><?php esc_html_e( '開立日期：', 'woocommerce' ); ?></strong>
					<?php echo esc_html( $invoice_data['invoice_date'] ); ?><br/>

                    <strong><?php esc_html_e( '隨機碼：', 'woocommerce' ); ?></strong>
					<?php echo esc_html( $invoice_data['random_number'] ); ?><br/>

					<?php if ( ! empty( $invoice_data['vehicle_type'] ) ) : ?>
                        <strong><?php esc_html_e( '載具類型：', 'woocommerce' ); ?></strong>
						<?php
						$vehicle_types = array(
							'1' => '會員載具',
							'2' => '手機條碼',
							'3' => '自然人憑證'
						);
						echo esc_html( $vehicle_types[ $invoice_data['vehicle_type'] ] ?? '' );
						?><br/>
					<?php endif; ?>

					<?php if ( ! empty( $invoice_data['vehicle_barcode'] ) ) : ?>
                        <strong><?php esc_html_e( '載具條碼：', 'woocommerce' ); ?></strong>
						<?php echo esc_html( $invoice_data['vehicle_barcode'] ); ?><br/>
					<?php endif; ?>

					<?php if ( ! empty( $invoice_data['love_code'] ) ) : ?>
                        <strong><?php esc_html_e( '愛心碼：', 'woocommerce' ); ?></strong>
						<?php echo esc_html( $invoice_data['love_code'] ); ?><br/>
					<?php endif; ?>

					<?php if ( ! empty( $invoice_data['invoice_discount_no'] ) ) : ?>
                        <strong><?php esc_html_e( '折讓單號：', 'woocommerce' ); ?></strong>
						<?php echo esc_html( $invoice_data['invoice_discount_no'] ); ?>
					<?php endif; ?>
                </p>
            </div>
        </div>
		<?php
	}
}