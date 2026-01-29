# 黑貓Pay for WooCommerce 系統規格書

## 1. 專案概述

本外掛程式 `ccat-for-woocommerce` (黑貓Pay Payment for WooCommerce) 旨在為 WooCommerce 商店提供完整的黑貓支付與物流整合方案。它整合了多元支付方式（信用卡、ATM轉帳、超商代碼等）以及黑貓物流服務（宅配、超商取貨），並支援電子發票開立。

### 1.1 基本資訊

- **外掛名稱**: ccatpay Payment for WooCommerce
- **版本**: 2.3.10
- **最低 WordPress/WooCommerce 需求**:
  - WordPress: 6.6+
  - WooCommerce: (需確認，通常與插件版本相依)
  - PHP: 8.3+
- **授權**: GNU General Public License v3.0

## 2. 系統架構

### 2.1 目錄結構

專案遵循標準的 WordPress 外掛結構：

```
/
├── ccat-for-woocommerce.php   # 主要入口檔案，負責初始化與掛鉤註冊
├── 711-checkout-block/        # 7-11 取貨結帳區塊整合
├── ccat-checkout-block/       # 支付結帳區塊整合
├── includes/                  # 核心邏輯類別庫
│   ├── blocks/                # WooCommerce Blocks 支援
│   ├── shipping/              # 物流運送方法定義
│   ├── class-ccatpay-gateway-*.php    # 各類支付閘道實作
│   ├── class-ccatpay-settings.php     # 後台設定頁面邏輯
│   ├── class-ccatpay-shipping-display.php # 物流單顯示與操作邏輯
│   └── ...
├── resources/                 # 前端資源 (JS, CSS)
└── ...
```

### 2.2 核心類別

- **`CCATPAY_Payments`** (`ccat-for-woocommerce.php`):
  - 外掛單例核心，負責加載相依檔案。
  - 註冊支付閘道 (`woocommerce_payment_gateways`)。
  - 註冊物流方法 (`woocommerce_shipping_methods`)。
  - 初始化 WooCommerce Blocks 支援。
  - 管理物流區域 (自動新增「台灣」區域與相關運送方法)。

- **`CCATPAY_Settings`** (`includes/class-ccatpay-settings.php`):
  - 管理 WooCommerce 設定頁面中的「黑貓Pay」分頁。
  - 提供 API 金鑰、測試模式、寄件人資訊等全域設定。

- **`CCATPAY_Shipping_Payment_Coordinator`** (`includes/shipping/class-ccatpay-shipping-payment-coordinator.php`):
  - **物流與支付連動控制**: 根據使用者選擇的物流方式（如7-11取貨），動態過濾可用的支付方式（如限制只能用貨到付款）。這是此插件的關鍵邏輯之一。

- **`CCATPAY_Shipping_Display`** (`includes/class-ccatpay-shipping-display.php`):
  - 後台訂單詳情的物流操作介面。
  - 功能包含：建立物流訂單 (API)、列印/下載託運單、變更 7-11 取貨門市。

## 3. 功能模組

### 3.1 支付閘道 (Payment Gateways)

外掛支援多種支付方式，每種方式對應一個獨立的閘道類別：

1.  **信用卡 (Credit Card)**
    - 類別: `CCATPAY_Gateway_Credit_Card`
    - 代碼: `CCATPAY_Gateway_Credit_Card`
2.  **中國信託 (Chinatrust)**
    - 類別: `CCATPAY_Gateway_Chinatrust`
3.  **PayUni**
    - 類別: `CCATPAY_Gateway_Payuni`
4.  **超商代碼 (CVS iBon/ATM)**
    - 類別: `CCATPAY_Gateway_Cvs_Ibon`, `CCATPAY_Gateway_Cvs_Atm`
5.  **行動支付 (APP)**
    - 類別: `CCATPAY_Gateway_App_Opw` (OpenWallet), `CCATPAY_Gateway_App_Icash`
6.  **貨到付款 (COD)**
    - 支援多種情境：現金 (Cash)、刷卡 (Card)、行動支付 (Mobile)、7-11取貨付款。
    - 類別: `CCATPAY_Gateway_COD_*`

### 3.2 物流運送 (Shipping Methods)

支援黑貓宅配與統一超商 (7-11) 取貨，並細分為不同溫層與付款類型：

- **常溫**:
  - 宅配: `ccatpay_shipping_prepaid` (先付款), `ccatpay_shipping_cod` (貨到付款)
  - 7-11: `ccatpay_shipping_711_prepaid`, `ccatpay_shipping_711_cod`
- **冷藏 (Refrigerated)**:
  - 宅配: `..._refrigerated`
  - 7-11: `..._711_..._refrigerated`
- **冷凍 (Frozen)**:
  - 宅配: `..._frozen`
  - 7-11: `..._711_..._frozen`

每種物流方法繼承自 `CCATPAY_Shipping_Abstract`。

### 3.3 電子發票 (E-Invoice)

- 由 `CCATPAY_Invoice_Display` 與 `CCATPAY_Blocks_Integration` 處理。
- 提供發票開立選項與相關欄位整合。

## 4. API 整合與數據流

### 4.1 物流 API 串接

- 主要透過 `CCATPAY_Shipping_Display` 類別處理。
- **建立託運單**:
  - **宅配**: POST `api/Logistics/PrintOBT`
  - **7-11**: POST `api/Logistics/PrintOBTByB2S`
- **資料傳輸**: 包含訂單編號、收件人/寄件人資訊、溫層、規格、付款金額等。
- **回傳處理**: 成功後將託運單號 (`_ccat_shipping_obt_number`) 與檔案編號 (`_ccat_shipping_file_no`) 寫入訂單 Meta。

### 4.2 API 設定

於後台設定頁面配置：

- Merchant ID (金流代號)
- Hash Key/IV (API 密碼/檢核碼)
- 支援測試環境切換 (`test_mode`)

## 5. 前端整合

- **WooCommerce Blocks**: 完整支援新版區塊結帳流程，各支付與物流方式均有對應的 Block Integration 類別 (`includes/blocks/`)。
- **JavaScript**: 位於 `resources/js/frontend/`，處理前端互動（如選擇門市、驗證欄位）。
  - `cod.js`: 處理貨到付款相關邏輯。
  - 其他對應各支付方式的 JS 檔。

## 6. 資料庫儲存

- **Options 表**: 儲存全域設定 (prefix: `ccatpay-for-woocommerce_` 或 `wc_ccat_`)。
- **Post Meta 表**: 儲存訂單層級資訊，如託運單號、門市資訊、發票資訊。
