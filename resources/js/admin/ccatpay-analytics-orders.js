/**
 * CCatPay WooCommerce Analytics Orders Report Extension
 *
 * 擴充 WooCommerce 後台 Analytics > Orders (分析 > 訂單) 網頁清單表格，
 * 網頁清單僅保留「配送方式」與「託運單號」欄位，詳細物流資訊則保留於 CSV 下載檔案中。
 *
 * @package WooCommerceCCatGateway
 */

(function () {
	'use strict';

	if (!window.wp || !window.wp.hooks) {
		return;
	}

	var addFilter = window.wp.hooks.addFilter;

	// 網頁清單僅顯示「配送方式」與「託運單號」
	var tableColumns = [
		{ key: 'ccat_shipping_method', label: '配送方式' },
		{ key: 'ccat_obt_number', label: '託運單號' }
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
				return header.key === 'ccat_shipping_method' || header.key === 'ccat_obt_number';
			});

			if (isAlreadyAdded) {
				return reportTableData;
			}

			// 附加「配送方式」與「託運單號」至表頭.
			var newHeaders = headers.concat(
				tableColumns.map(function (col) {
					return {
						label: col.label,
						key: col.key,
						required: false,
						isSortable: false,
					};
				})
			);

			// 附加每列資料對應儲存格.
			var rows = reportTableData.rows || [];
			var items = (reportTableData.items && reportTableData.items.data) || [];

			var newRows = rows.map(function (row, index) {
				var item = items[index] || {};
				var extraCells = tableColumns.map(function (col) {
					var rawValue = item[col.key];
					var displayValue =
						rawValue !== undefined && rawValue !== null && rawValue !== ''
							? String(rawValue)
							: '-';

					return {
						display: displayValue,
						value: displayValue,
					};
				});

				return row.concat(extraCells);
			});

			reportTableData.headers = newHeaders;
			reportTableData.rows = newRows;

			return reportTableData;
		}
	);
})();
