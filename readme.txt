
=== ccatpay Payment for WooCommerce ===
Contributors: ccatpay
Tags: woocommerce, payment gateway, credit card, cvs payment, taiwan
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 8.3
Stable tag: 2.5
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

為您的 WooCommerce 網站添加 黑貓Pay 金流支付方式。

== Description ==

ccatpay Payment for WooCommerce 提供多種台灣本地支付方式：

* 信用卡支付
* 超商條碼支付 (ibon)
* 銀行虛擬帳號 (ATM)
* OPEN錢包行動支付 (OPW)
* 愛金卡行動支付 (iCash)

特色：

* 支援電子發票開立
* WooCommerce Blocks 整合支援
* 完整中文化介面

== Installation ==

= 自動安裝 =

1. 前往 [外掛]→[安裝外掛]。
2. 搜尋「ccatpay」或「黑貓Pay」。
3. 安裝並啟用 ccatpay Payment for WooCommerce 外掛。

= 手動安裝 =

1. 上傳 `ccat-for-woocommerce` 資料夾至 `/wp-content/plugins/` 目錄
2. 在後台啟用插件
3. 前往 WooCommerce > 設定 > 黑貓Pay 設定您的金流

== Frequently Asked Questions ==

= 支援哪些 WooCommerce 版本？ =
本插件支援 WooCommerce 9.8 以上版本。

= 如何設定電子發票？ =
前往 WooCommerce > 設定 > 黑貓Pay > 發票設定 啟用並設定。

== Changelog ==

= 1.0.0 =

* 新增：黑貓PAY金流服務

= 2.0.0 =

* 新增：黑貓宅配、快速到店

= 2.0.1 =

* 修正：使用黑貓物流時，電話為必填。選擇7-11取貨的時候，將門市地址自動填上地址表單。

= 2.0.2 =

* 新增：支援最新版 WordPress 6.8、WooCommerce 9.8

= 2.0.3 =

* 修正：提醒託運單格式，黑貓快速到店(711取貨)對應A4三模託運單，黑貓宅配則是對應A4二模託運單。

= 2.0.4 =

* 修正：文字調整，物流訂單 更改為 物流託運單。

= 2.1.0 =

* 修正：因應WordPress外掛商店上架調整程式碼。

= 2.1.1 =

* 修正：修正I18n.NonSingularStringLiteralDomain問題。

= 2.1.2 =

* 改進：依照官方審核要求調整變數名稱。

= 2.2.1 =

* 新增：後台列印黑貓宅配託運單時，可選擇希望配達時段

= 2.3.1 =

* 新增：後台列印運單時，可選擇託運單類別

= 2.3.2 =

* 修正：後台列印運單時，提醒託運單格式

= 2.3.3 =

* 修正：711電子地圖視窗大小調整

= 2.3.4 =

* 修正：訂購人電話改帶入get_billing_phone，沒有get_billing_phone才改用get_shipping_phone

= 2.3.5 =

* 修正： 未定義變數 $schema 錯誤

= 2.3.6 =

* 修正： get_invoice_data、get_invoice_schema錯誤

= 2.3.7 =

* 修正： 後台付款方式設定，增加付款說明欄位

= 2.3.8 =

* 修正： 後台運送方式設定，開放運費擴充欄位
* 修正： 調整消費者沒有填配送方式的聯絡電話時，印單時就改抓帳單的聯絡電話
* 改進： 收到APN資訊，有發票號碼才寫入訂單備註紀錄

= 2.3.9 =

* 修正： 列印黑貓宅配單，補上郵遞區號及縣市區資料

= 2.3.10 =

* 修正： 列印託運單的收件人資料，改成只從shipping中取得資料

= 2.4 =

* 修正： 關閉行動支付(OPW/iCash/宅配貨到手機支付)

= 2.5 =

* 修正： 列印託運單的希望配達日，如果計算結果當天是星期日，則順延到星期一

== Third-party Resources ==

1. JsBarcode - https://github.com/lindell/JsBarcode
   Version: 3.11.6
   License: MIT
   本地副本包含在 resources/js/frontend/JsBarcode.all.min.js

== Screenshots ==

1. 付款設定畫面
2. 結帳頁面展示
3. 電子發票設定
4. 運送方式設定畫面

== Support ==

* 使用手冊[下載](https://mpsdata.blob.core.windows.net/filedownload/ccatpay_UserManual.pdf "download")
* 如需技術支援請聯繫客服：02-8752-0688
