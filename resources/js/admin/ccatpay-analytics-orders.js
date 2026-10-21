/**
 * CCatPay WooCommerce Analytics Orders Report Extension
 *
 * 擴充 WooCommerce 後台 Analytics > Orders (分析 > 訂單) 表格，
 * 將黑貓物流相關欄位附加至前端報表表格中，同時支援前端即時單頁 CSV 下載。
 *
 * @package WooCommerceCCatGateway
 */

(function () {
	'use strict';

	if (!window.wp || !window.wp.hooks) {
		return;
	}

	var addFilter = window.wp.hooks.addFilter;
	var config = window.ccatpayAnalyticsData || {};
	var customColumns = config.columns || [];

	if (!customColumns.length) {
		return;
	}

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
				return header.key === customColumns[0].key;
			});

			if (isAlreadyAdded) {
				return reportTableData;
			}

			// 附加黑貓物流表頭.
			var newHeaders = headers.concat(
				customColumns.map(function (col) {
					return {
						label: col.label,
						key: col.key,
						required: false,
						isSortable: false,
					};
				})
			);

			// 附加黑貓物流每列資料儲存格.
			var rows = reportTableData.rows || [];
			var items = (reportTableData.items && reportTableData.items.data) || [];

			var newRows = rows.map(function (row, index) {
				var item = items[index] || {};
				var extraCells = customColumns.map(function (col) {
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
