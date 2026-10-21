/**
 * CCatPay WooCommerce Analytics Orders Report Extension
 *
 * 擴充 WooCommerce 後台 Analytics > Orders (分析 > 訂單) 表格與 CSV 下載：
 * 1. 預設隱藏：所有新增的黑貓物流欄位預設不啟用 (hiddenByDefault: true)，避免版面雜亂。
 * 2. 隨選顯示：使用者可透過表格右上角的選單 (⋮) 依需求自行勾選開啟特定欄位。
 * 3. 完整匯出：點擊「下載」按鈕時，無論網頁畫面上是否勾選顯示，CSV 皆完整包含所有物流資訊。
 *
 * @package WooCommerceCCatGateway
 */

(function () {
	'use strict';

	if (!window.wp || !window.wp.hooks) {
		return;
	}

	var addFilter = window.wp.hooks.addFilter;

	// 黑貓物流完整 12 欄定義 (全部設定為 required: false 與 hiddenByDefault: true，初次安裝預設不啟用)
	var allColumns = [
		{ key: 'ccat_shipping_method', label: '配送方式' },
		{ key: 'ccat_obt_number', label: '託運單號' },
		{ key: 'ccat_recipient_name', label: '收件人姓名' },
		{ key: 'ccat_recipient_phone', label: '收件人電話' },
		{ key: 'ccat_customer_email', label: '顧客 Email' },
		{ key: 'ccat_thermosphere', label: '溫層' },
		{ key: 'ccat_shipping_address', label: '收件地址' },
		{ key: 'ccat_store_info', label: '7-11取貨門市' },
		{ key: 'ccat_shipment_date', label: '預計出貨日' },
		{ key: 'ccat_delivery_date', label: '希望配達日' },
		{ key: 'ccat_delivery_time', label: '希望配達時段' }
	];

	addFilter(
		'woocommerce_admin_report_table',
		'ccatpay-for-woocommerce',
		function (reportTableData) {
			if (!reportTableData || reportTableData.endpoint !== 'orders') {
				return reportTableData;
			}

			var headers = reportTableData.headers || [];

			// 檢查是否已加入自訂欄位，避免重複加入.
			var isAlreadyAdded = headers.some(function (header) {
				return header.key === 'ccat_recipient_name';
			});

			if (isAlreadyAdded) {
				return reportTableData;
			}

			// 若曾存在舊版部分欄位，先過濾乾淨.
			headers = headers.filter(function (header) {
				return !allColumns.some(function (col) {
					return col.key === header.key;
				});
			});

			// 附加全部 12 個黑貓物流表頭 (使用 WooCommerce 原生 hiddenByDefault: true 實現預設不啟用).
			var newHeaders = headers.concat(
				allColumns.map(function (col) {
					return {
						label: col.label,
						key: col.key,
						required: false,
						isSortable: false,
						hiddenByDefault: true
					};
				})
			);

			// 附加每列資料對應儲存格.
			var rows = reportTableData.rows || [];
			var items = (reportTableData.items && reportTableData.items.data) || [];

			var newRows = rows.map(function (row, index) {
				var originalRow = row.slice(0, headers.length);
				var item = items[index] || {};
				var extraCells = allColumns.map(function (col) {
					var rawValue = item[col.key];
					var displayValue =
						rawValue !== undefined && rawValue !== null && rawValue !== ''
							? String(rawValue)
							: '-';

					return {
						display: displayValue,
						value: displayValue
					};
				});

				return originalRow.concat(extraCells);
			});

			reportTableData.headers = newHeaders;
			reportTableData.rows = newRows;

			return reportTableData;
		}
	);
})();
