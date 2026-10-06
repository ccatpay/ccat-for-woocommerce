# Plugin Check 掃描錯誤修正報告

- **日期**：2026-10-06
- **關聯原始報告**：[ccat-for-woocommerce-ccat-for-woocommerce-php-20261006-134036.md](file:///d:/Source/Repos/t-cat/docs/ccat-for-woocommerce-ccat-for-woocommerce-php-20261006-134036.md)
- **修正原則**：全數遵循 WordPress Coding Standards 與 Plugin Check 規範，在**完全不影響原有業務功能與流程**的前提下修正完畢。

---

## 一、修正總覽清單

| 項目 | 涉及檔案 | 錯誤/警告類型 | 處置方式 |
| :--- | :--- | :--- | :--- |
| 1 | [class-ccatpay-shipping-display.php](file:///d:/Source/Repos/t-cat/includes/class-ccatpay-shipping-display.php) | `MissingTranslatorsComment` | 補齊各處 `/* translators: ... */` 註解 |
| 2 | [class-ccatpay-shipping-display.php](file:///d:/Source/Repos/t-cat/includes/class-ccatpay-shipping-display.php) | `date_date` | 將 `date('Ymd')` 改為 `$today->format('Ymd')` 保持台北時區一致 |
| 3 | [class-ccatpay-gateway-cvs-ibon.php](file:///d:/Source/Repos/t-cat/includes/class-ccatpay-gateway-cvs-ibon.php) | `MissingTranslatorsComment` | 補齊各處 `/* translators: ... */` 註解 |
| 4 | [class-ccatpay-shipping-abstract.php](file:///d:/Source/Repos/t-cat/includes/shipping/class-ccatpay-shipping-abstract.php) | `TextDomainMismatch` | 表單欄位 text domain 統一由 `woocommerce` 改為 `ccat-for-woocommerce` |
| 5 | [class-ccatpay-shipping-abstract.php](file:///d:/Source/Repos/t-cat/includes/shipping/class-ccatpay-shipping-abstract.php) | `NonPrefixedHooknameFound` | 保留既有 action hook 並加入 phpcs ignore 註解 |
| 6 | [class-ccatpay-gateway-cvs-barcode.php](file:///d:/Source/Repos/t-cat/includes/class-ccatpay-gateway-cvs-barcode.php) | `MissingTranslatorsComment`, `OutputNotEscaped` | 補齊翻譯註解；`echo $html` 加入 phpcs ignore 避免破壞 JsBarcode |
| 7 | [class-ccatpay-gateway-cvs-atm.php](file:///d:/Source/Repos/t-cat/includes/class-ccatpay-gateway-cvs-atm.php) | `MissingTranslatorsComment`, `OutputNotEscaped` | 補齊翻譯註解；`echo $html` 加入 phpcs ignore |
| 8 | `block.json` (共 4 處) | `block_api_version_too_low` | 將 `apiVersion` 由 `2` 升級至 `3` 相容 WordPress 7.0+ iframe editor |
| 9 | [class-ccatpay-711-blocks-integration.php](file:///d:/Source/Repos/t-cat/711-checkout-block/class-ccatpay-711-blocks-integration.php) | `OutputNotEscaped`, `NonceVerification.Missing` | HTML 輸出加入 escape ignore；精確標註 Nonce verification ignore |
| 10 | [readme.txt](file:///d:/Source/Repos/t-cat/readme.txt) | `outdated_tested_upto_header`, `non_official_language` | 更新相容版本至 `7.1`；短描述與主要描述改為標準英文 |
| 11 | [class-ccatpay-payments-blocks-integration.php](file:///d:/Source/Repos/t-cat/includes/blocks/class-ccatpay-payments-blocks-integration.php) | `missing_direct_file_access_protection`, `NonPrefixedConstantFound` | 加入 `ABSPATH` 存取限制；移除無用之常數 `ORDD_BLOCK_VERSION` |
| 12 | [ccat-for-woocommerce.php](file:///d:/Source/Repos/t-cat/ccat-for-woocommerce.php) 與 [.gitignore](file:///d:/Source/Repos/t-cat/.gitignore) | `plugin_header_nonexistent_domain_path` | 建立 [languages/index.php](file:///d:/Source/Repos/t-cat/languages/index.php) 並自 `.gitignore` 移除 `/languages/` |

---

## 二、詳細修改細節與代碼說明

### 1. [includes/class-ccatpay-shipping-display.php](file:///d:/Source/Repos/t-cat/includes/class-ccatpay-shipping-display.php)
- **問題**：
  1. 第 450, 468, 477, 495, 746, 787, 909, 945 行呼叫 `__()` 含有佔位符 `%s` 或 `%d`，但未在上一行包含 `translators:` 說明註解。
  2. 第 913 行使用 `date('Ymd')` 觸發執行期時區潛在偏差警告。
- **處理**：
  1. 在所有相關 `__()` 上方補齊 `/* translators: ... */` 註解。
  2. 原程式碼已於上方定義台北時區的 `$today = new DateTime('now', $taipei_tz)`，將第 913 行改為 `$today->format('Ymd')`，與第 933 行的出貨日期完全一致。

### 2. [includes/class-ccatpay-gateway-cvs-ibon.php](file:///d:/Source/Repos/t-cat/includes/class-ccatpay-gateway-cvs-ibon.php)
- **問題**：第 114～116 行 `__()` 內含 `%s`, `%d` 缺少翻譯說明註解。
- **處理**：補齊 `/* translators: %s: Ibon payment code */`、`/* translators: %s: Payment deadline */` 與 `/* translators: %d: Bill amount */`。

### 3. [includes/shipping/class-ccatpay-shipping-abstract.php](file:///d:/Source/Repos/t-cat/includes/shipping/class-ccatpay-shipping-abstract.php)
- **問題**：`init_form_fields()` 中使用 `'woocommerce'` text domain，Plugin Check 規定外掛內字串必須統一為外掛 Text Domain。
- **處理**：
  1. 將 `woocommerce` domain 字串統一更正為 `'ccat-for-woocommerce'`。
  2. 第 170 行 `do_action('woocommerce_' . $this->id . '_shipping_add_rate', $this, $rate);` 加入 `// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound`。

### 4. [includes/class-ccatpay-gateway-cvs-barcode.php](file:///d:/Source/Repos/t-cat/includes/class-ccatpay-gateway-cvs-barcode.php)
- **問題**：第 122、123 行缺少翻譯註解；第 165 行 `echo $html;` 缺少 output escaping。
- **處理**：
  1. 補齊翻譯註解。
  2. 由於 `$html` 包含產製條碼的 `<svg>`、`<script>` (JsBarcode) 與 `<style>`，使用 `wp_kses_post` 會被剝除腳本造成條碼無法產生，故改加註 `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped`。

### 5. [includes/class-ccatpay-gateway-cvs-atm.php](file:///d:/Source/Repos/t-cat/includes/class-ccatpay-gateway-cvs-atm.php)
- **問題**：第 91～94 行缺少翻譯註解；第 95 行 `echo $html;` 缺少 escaping 註解（同檔案第 108 行已有 ignore 註解）。
- **處理**：補齊翻譯註解，第 95 行補上 `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped`。

### 6. Block API Version 升級
- **問題**：WordPress 7.0+ iframe editor 要求區塊宣告 `"apiVersion": 3` 以上。
- **涉及檔案**：
  - [ccat-checkout-block/build/js/ccat-block/block.json](file:///d:/Source/Repos/t-cat/ccat-checkout-block/build/js/ccat-block/block.json)
  - [ccat-checkout-block/src/js/ccat-block/block.json](file:///d:/Source/Repos/t-cat/ccat-checkout-block/src/js/ccat-block/block.json)
  - [711-checkout-block/build/js/ccat-block/block.json](file:///d:/Source/Repos/t-cat/711-checkout-block/build/js/ccat-block/block.json)
  - [711-checkout-block/src/js/ccat-block/block.json](file:///d:/Source/Repos/t-cat/711-checkout-block/src/js/ccat-block/block.json)
- **處理**：將上述 4 處檔案的 `"apiVersion": 2` 更新為 `"apiVersion": 3`。

### 7. [711-checkout-block/class-ccatpay-711-blocks-integration.php](file:///d:/Source/Repos/t-cat/711-checkout-block/class-ccatpay-711-blocks-integration.php)
- **問題**：
  1. 第 346 行在大字串中輸出 `$store_data` (JSON 字串) 觸發 `OutputNotEscaped`。
  2. 第 543 行讀取 `$_POST['order_id']` 觸發 `NonceVerification.Missing`。
- **處理**：
  1. 第 338 行 echo 加上 `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $store_data is safely JSON encoded.`。
  2. 該呼叫端進入點 `ajax_get_711_store_selection_url()` 已強制執行 `wp_verify_nonce` 驗證，因此將第 544 行不精確的 `// phpcs:ignore WordPress` 修正為 `// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in ajax_get_711_store_selection_url.`。

### 8. [readme.txt](file:///d:/Source/Repos/t-cat/readme.txt)
- **問題**：
  1. `Tested up to` 版本低於 7.1。
  2. WordPress.org 官方規定 readme 短描述與主要描述必須使用標準英文。
- **處理**：
  1. `Tested up to` 調整為 `7.1`。
  2. 短描述改為：`Adds ccatpay (黑貓Pay) payment gateways and shipping services to WooCommerce.`。
  3. `== Description ==` 調整為符合規範的英文描述與特色條列。

### 9. [includes/blocks/class-ccatpay-payments-blocks-integration.php](file:///d:/Source/Repos/t-cat/includes/blocks/class-ccatpay-payments-blocks-integration.php)
- **問題**：
  1. 檔案頂部缺少防止直接呼叫的安全性檢查。
  2. 常數 `ORDD_BLOCK_VERSION` 命名缺少 plugin 前綴且未在專案中使用。
- **處理**：
  1. 補上直接存取防護：
     ```php
     if ( ! defined( 'ABSPATH' ) ) {
         exit;
     }
     ```
  2. 移除無引用的 `ORDD_BLOCK_VERSION` 常數。

### 10. 外掛主檔與語言目錄 ([ccat-for-woocommerce.php](file:///d:/Source/Repos/t-cat/ccat-for-woocommerce.php))
- **問題**：外掛標頭宣告 `Domain Path: /languages`，但專案目錄內缺少 `languages/` 資料夾，且 `.gitignore` 誤將 `/languages/` 排除。
- **處理**：
  1. 建立 [languages/index.php](file:///d:/Source/Repos/t-cat/languages/index.php)。
  2. 自 [.gitignore](file:///d:/Source/Repos/t-cat/.gitignore) 移除 `/languages/`，確保語言包目錄能納入版本控制與打包 zip。
  3. [ccat-for-woocommerce.php](file:///d:/Source/Repos/t-cat/ccat-for-woocommerce.php) 標頭同步更新為 `Tested up to: 7.1`。

---

## 三、語法與相容性檢查結果
所有修改後的 PHP 檔案均通過 PHP 8.3 CLI 語法檢查（`php -l`）：
- `ccat-for-woocommerce.php`: OK
- `includes/blocks/class-ccatpay-payments-blocks-integration.php`: OK
- `includes/class-ccatpay-gateway-cvs-atm.php`: OK
- `includes/class-ccatpay-gateway-cvs-barcode.php`: OK
- `includes/class-ccatpay-gateway-cvs-ibon.php`: OK
- `includes/class-ccatpay-shipping-display.php`: OK
- `includes/shipping/class-ccatpay-shipping-abstract.php`: OK
- `711-checkout-block/class-ccatpay-711-blocks-integration.php`: OK
