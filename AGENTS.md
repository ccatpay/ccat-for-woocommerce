# ccatpay Payment for WooCommerce - AI Agent 專案指南

這份指南提供給 AI 程式設計助手（例如 Cursor, Copilot 等），以便快速理解本專案的架構、業務邏輯與開發規範。

## 1. 專案概述
- **專案名稱**: ccatpay Payment for WooCommerce (黑貓Pay for WooCommerce)
- **目的**: 整合黑貓支付（信用卡、ATM轉帳、超商代碼等）與黑貓物流服務（宅配、7-11超商取貨），並支援電子發票開立。
- **技術環境**: PHP 8.3+, WordPress 6.6+, WooCommerce 9.8+

## 2. 目錄結構與核心模組
專案遵循標準的 WordPress 外掛目錄結構。在協助開發或除錯時，請優先參考以下檔案分佈：

- `ccat-for-woocommerce.php`: 外掛主入口檔，負責初始化單例 `CCATPAY_Payments`，加載相依檔案並註冊支付與物流方法。
- `includes/`: 核心後端邏輯。
  - `class-ccatpay-gateway-*.php`: 支付閘道類別（包含信用卡、中國信託、PayUni、超商代碼等）。
  - `shipping/`: 物流方法（常溫/冷藏/冷凍之宅配與 7-11 取貨），以及物流與支付的連動協調器 (`CCATPAY_Shipping_Payment_Coordinator`)。
  - `class-ccatpay-settings.php`: WooCommerce 後台設定頁面管理。
  - `class-ccatpay-shipping-display.php`: 處理物流 API 串接（建立託運單）與後台訂單操作。
  - `blocks/`: 處理 WooCommerce Blocks 的伺服器端註冊。
- `711-checkout-block/` & `ccat-checkout-block/`: 新版 WooCommerce 結帳區塊 (Checkout Blocks) 的前端與整合邏輯。
- `resources/`: 前端資源 (JS, CSS)。
  - `js/frontend/`: 處理傳統結帳流程或特定前端互動（如門市選擇、貨到付款）。
- `docs/`: 規格文件，如 `spec.md` 包含詳細的系統架構與 API 設計。

## 3. 重要業務邏輯與開發規範
1. **物流與支付的連動**:
   這是一個核心機制。使用者選擇特定的物流方式（例如：7-11 取貨付款）時，系統會動態過濾並限制可用的支付方式（例如：只能使用貨到付款）。若要調整結帳邏輯，務必檢查 `CCATPAY_Shipping_Payment_Coordinator` 的過濾規則。
2. **WooCommerce Blocks 支援**:
   專案已全面支援 WooCommerce Checkout Blocks。新增或修改結帳欄位（如電子發票、取貨門市）時，需確保同時相容傳統 Shortcode 結帳與 Block 結帳流程。
3. **資料庫與 Meta 儲存**:
   - 選項設定前綴為 `ccatpay-for-woocommerce_` 或 `wc_ccat_`。
   - 訂單相關資訊（如託運單號 `_ccat_shipping_obt_number`、門市資料）儲存於 Post Meta。
4. **程式碼風格**:
   - 專案使用 `phpcs.xml` 定義了代碼規範，請遵循 WordPress PHP Coding Standards。
   - 類別名稱統一使用 `CCATPAY_` 前綴。
   - 語系字串處理請確保遵守 `I18n` 規範，避免 `NonSingularStringLiteralDomain` 問題。

## 4. 給 AI 的提示 (Prompting Instructions)
- **追蹤問題**: 如果要追蹤結帳或付款的錯誤，請先釐清使用者是使用傳統結帳頁面還是 Blocks 結帳，然後再往對應的 `includes/` 或 `*-checkout-block/` 尋找。
- **修改 API 邏輯**: 黑貓物流與託運單 API 相關都在 `CCATPAY_Shipping_Display` 中處理；若需新增列印選項，請確保對應 `shipping/` 資料夾裡的溫層設定。
- **參考文件**: 若有功能設計上的疑問，請隨時回頭閱讀 `docs/spec.md` 與 `readme.txt` 以確保沒有偏離官方規格。
