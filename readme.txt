=== ccat-for-woocommerce ===
Contributors: ccatpay
Tags: woocommerce, payment gateway, credit card, cvs payment, taiwan
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 8.3
Stable tag: 2.0.2
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

1. 上傳 `ccat-for-woocommerce` 資料夾至 `/wp-content/plugins/` 目錄
2. 在後台啟用插件
3. 前往 WooCommerce > 設定 > 黑貓Pay 設定您的金流

== Frequently Asked Questions ==

= 支援哪些 WooCommerce 版本？ =
本插件支援 WooCommerce 9.8 以上版本。

= 如何設定電子發票？ =
前往 WooCommerce > 設定 > 黑貓Pay > 發票設定 啟用並設定。

== Changelog ==

= 1.10.0 =

* 新增：支援最新版 WordPress 6.6
* 改進：電子發票整合功能
* 修正：結帳區塊顯示問題

= 1.10.1 =

* 新增：線上刷卡區分玉山銀、中信銀、統一金流

= 1.10.2 =

* 修正：文字用詞改善

= 1.10.3 =

* 新增：支援最新版 WordPress 6.7
* 修正：ATM、超商三段式條碼服務暫時中止

= 1.10.4 =

* 修正：線上刷卡請款完成後，不將訂單改為完成，由使用者自行判斷是否出貨並調整狀態。

= 1.11.0 =

* 新增：支援統一金流ATM繳款方式。

= 1.11.1 =

* 修正：改善一些程式語法

= 2.0.0 =

* 新增：黑貓宅配、快速到店

= 2.0.1 =

* 修正：使用黑貓物流時，電話為必填。選擇7-11取貨的時候，將門市地址自動填上地址表單。

= 2.0.2 =

* 新增：支援最新版 WordPress 6.8、WooCommerce 9.8

= 2.0.3 =

* 修正：提醒託運單格式，黑貓快速到店(711取貨)對應A4三模託運單，黑貓宅配則是對應A4二模託運單。

== Screenshots ==

1. 付款設定畫面
2. 結帳頁面展示
3. 電子發票設定
4. 運送方式設定畫面

== Support ==

如需技術支援請聯繫客服：02-8752-0688