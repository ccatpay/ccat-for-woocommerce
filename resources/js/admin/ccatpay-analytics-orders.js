/**
 * CCatPay WooCommerce Analytics Orders Report Extension
 *
 * 擴充 WooCommerce 後台 Analytics > Orders (分析 > 訂單) 網頁清單與 CSV 匯出：
 * 1. 網頁清單：視覺上僅保留「配送方式」與「託運單號」欄位（其餘欄位透過樣式隱藏）。
 * 2. 下載 CSV：無論是單頁前端即時下載或多頁背景下載，皆完整匯出所有 12 項黑貓物流資訊。
 *
 * @package WooCommerceCCatGateway
 */

(function () {
	'use strict';

	if (!window.wp || !window.wp.hooks) {
		return;
	}

	var addFilter = window.wp.hooks.addFilter;

	// 黑貓物流完整 12 欄定義 (hidden 為 true 者在網頁清單中隱藏，但完整保留供 CSV 下載)
	var allColumns = [
		{ key: 'ccat_shipping_method', label: '配送方式', hidden: false },
		{ key: 'ccat_obt_number', label: '託運單號', hidden: false },
		{ key: 'ccat_recipient_name', label: '收件人姓名', hidden: true },
		{ key: 'ccat_recipient_phone', label: '收件人電話', hidden: true },
		{ key: 'ccat_customer_email', label: '顧客 Email', hidden: true },
		{ key: 'ccat_thermosphere', label: '溫層', hidden: true },
		{ key: 'ccat_shipping_address', label: '收件地址', hidden: true },
		{ key: 'ccat_store_info', label: '7-11取貨門市', hidden: true },
		{ key: 'ccat_shipment_date', label: '預計出貨日', hidden: true },
		{ key: 'ccat_delivery_date', label: '希望配達日', hidden: true },
		{ key: 'ccat_delivery_time', label: '希望配達時段', hidden: true },
		{ key: 'ccat_shipping_status', label: '託運單狀態', hidden: true }
	];

	// 動態注入 CSS，確保 hidden 欄位在網頁表格上不顯示、不佔空間
	var styleId = 'ccatpay-analytics-table-style';
	if (!document.getElementById(styleId)) {
		var style = document.createElement('style');
		style.id = styleId;
		style.type = 'text/css';
		style.innerHTML = [
			'.ccat-analytics-hidden,',
			'th.ccat-analytics-hidden,',
			'td.ccat-analytics-hidden,',
			'.woocommerce-table th.ccat-analytics-hidden,',
			'.woocommerce-table td.ccat-analytics-hidden {',
			'    display: none !important;',
			'}'
		].join('\n');
		document.head.appendChild(style);
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
				return header.key === 'ccat_recipient_name' || (header.key === 'ccat_shipping_method' && headers.length > 15);
			});

			if (isAlreadyAdded) {
				return reportTableData;
			}

			// 如果舊版的只有 2 個欄位，先過濾掉舊的 headers.
			headers = headers.filter(function (header) {
				return header.key !== 'ccat_shipping_method' && header.key !== 'ccat_obt_number';
			});

			// 附加全部 12 個黑貓物流表頭 (供 CSV 下載讀取，hidden 欄位透過 className 隱藏).
			var newHeaders = headers.concat(
				allColumns.map(function (col) {
					return {
						label: col.label,
						key: col.key,
						required: false,
						isSortable: false,
						className: col.hidden ? 'ccat-analytics-hidden' : ''
					};
				})
			);

			// 附加每列資料對應儲存格.
			var rows = reportTableData.rows || [];
			var items = (reportTableData.items && reportTableData.items.data) || [];

			var newRows = rows.map(function (row, index) {
				// 若原本已有 2 欄舊自訂儲存格，先截斷保留原生儲存格
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
						value: displayValue,
						className: col.hidden ? 'ccat-analytics-hidden' : ''
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
