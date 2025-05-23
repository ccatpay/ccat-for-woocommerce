#!/bin/bash

# 設定變數
PLUGIN_NAME="ccat-for-woocommerce"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
OUTPUT_FILE="${PLUGIN_NAME}_${TIMESTAMP}.zip"

# 檢查當前目錄是否為外掛根目錄
if [ ! -d "711-checkout-block" ] || [ ! -d "ccat-checkout-block" ]; then
    echo "錯誤：請在 ccat-for-woocommerce 根目錄執行此腳本"
    exit 1
fi

# 先執行 npm run build 命令在兩個子目錄中
echo "構建 ccat-checkout-block..."
cd "ccat-checkout-block" || { echo "ccat-checkout-block 目錄不存在"; exit 1; }
npm run build
cd ..

echo "構建 711-checkout-block..."
cd "711-checkout-block" || { echo "711-checkout-block 目錄不存在"; exit 1; }
npm run build
cd ..

echo "開始壓縮程序..."

# 切換到上一層目錄以便可以壓縮當前目錄
cd ..

# 建立壓縮檔案
zip -r "$OUTPUT_FILE" "$PLUGIN_NAME" \
    -x "$PLUGIN_NAME/.git/*" \
    -x "$PLUGIN_NAME/.gitignore" \
    -x "$PLUGIN_NAME/package-plugin-zip.sh" \
    -x "$PLUGIN_NAME/phpcs.xml" \
    -x "$PLUGIN_NAME/**/src/*" \
    -x "$PLUGIN_NAME/**/node_modules/*" \
    -x "$PLUGIN_NAME/**/.DS_Store" \
    -x "$PLUGIN_NAME/**/.vscode/*" \
    -x "$PLUGIN_NAME/**/.idea/*" \
    -x "$PLUGIN_NAME/**/.svn/*" \
    -x "$PLUGIN_NAME/**/.github/*" \
    -x "$PLUGIN_NAME/**/.gitignore" \
    -x "$PLUGIN_NAME/**/.gitattributes" \
    -x "$PLUGIN_NAME/**/package-lock.json" \
    -x "$PLUGIN_NAME/**/composer.lock" \
    -x "$PLUGIN_NAME/**/phpunit.xml" \
    -x "$PLUGIN_NAME/**/phpcs.xml" \
    -x "$PLUGIN_NAME/**/README.md" \
    -x "$PLUGIN_NAME/**/package.json" \
    -x "$PLUGIN_NAME/**/webpack.config.js" \
    -x "$PLUGIN_NAME/**/LICENSE" \
    -x "$PLUGIN_NAME/**/tests/*" \
    -x "$PLUGIN_NAME/**/docs/*"

# 將壓縮檔移回原目錄
mv "$OUTPUT_FILE" "$PLUGIN_NAME/"

echo "外掛打包成功: $OUTPUT_FILE"
echo "壓縮檔案位於: $(pwd)/$PLUGIN_NAME/$OUTPUT_FILE"