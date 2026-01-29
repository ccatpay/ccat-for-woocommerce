<#
.SYNOPSIS
    此腳本用於在 Windows 環境下打包 ccat-for-woocommerce 外掛。
    功能等同於 package-plugin-zip.sh。

.DESCRIPTION
    1. 檢查目錄結構
    2. 執行 npm run build (針對 ccat-checkout-block 與 711-checkout-block)
    3. 使用 Robocopy 複製檔案到暫存區，並排除指定的檔案與目錄
    4. 壓縮成 zip 檔案至 download 目錄
#>

$ErrorActionPreference = "Stop"

# 設定變數
$PluginName = "ccat-for-woocommerce"
$Timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$OutputFile = "${PluginName}_${Timestamp}.zip"

# 設定輸出目錄為 download
$DownloadDir = Join-Path (Get-Location) "download"
if (-not (Test-Path $DownloadDir)) {
    New-Item -Path $DownloadDir -ItemType Directory -Force | Out-Null
}

# 檢查當前目錄是否為外掛根目錄
if (-not (Test-Path "711-checkout-block") -or -not (Test-Path "ccat-checkout-block")) {
    Write-Error "錯誤：請在 ccat-for-woocommerce 根目錄執行此腳本"
    exit 1
}

# 定義構建函數
function Build-Block {
    param([string]$Path)
    Write-Host "構建 $Path..." -ForegroundColor Cyan
    Push-Location $Path
    try {
        if (Test-Path "package.json") {
            # 檢測 node_modules，若無則自動安裝，避免 'wp-scripts' 找不到的錯誤
            if (-not (Test-Path "node_modules")) {
                Write-Host "偵測到 $Path 缺少 node_modules，正在自動執行 npm install..." -ForegroundColor Yellow
                cmd /c "npm install"
                if ($LASTEXITCODE -ne 0) { throw "npm install 失敗" }
            }

            # 執行 npm run build
            Write-Host "正在執行 npm run build..." -ForegroundColor Gray
            cmd /c "npm run build"
            if ($LASTEXITCODE -ne 0) { 
                throw "npm run build 失敗於目錄: $Path。若問題持續，請嘗試刪除 node_modules 後重新 npm install。" 
            }
        }
        else {
            Write-Warning "$Path 沒有 package.json，跳過構建"
        }
    }
    catch {
        Write-Error $_
        exit 1
    }
    finally {
        Pop-Location
    }
}

# 執行子目錄構建
Build-Block "ccat-checkout-block"
Build-Block "711-checkout-block"

Write-Host "開始壓縮程序..." -ForegroundColor Cyan

# 建立暫存目錄結構 (使用 Temp 資料夾避免汙染專案目錄)
$TempRoot = Join-Path $env:TEMP "ccat-pkg-$Timestamp"
$StageDir = Join-Path $TempRoot $PluginName

if (Test-Path $TempRoot) { Remove-Item $TempRoot -Recurse -Force }
New-Item -Path $StageDir -ItemType Directory -Force | Out-Null

# 定義排除清單 (參照 package-plugin-zip.sh 的排除規則)
# 排除目錄 (/XD)
$ExcludeDirs = @(
    ".git",
    "node_modules",
    "src",         # 對應 sh: **/src/*
    ".vscode",
    ".idea",
    ".svn",
    ".github",
    "tests",
    "docs"
)

# 排除檔案 (/XF)
$ExcludeFiles = @(
    ".gitignore",
    "package-plugin-zip.sh",
    "package-plugin-zip.ps1", # 排除本腳本
    "phpcs.xml",
    ".DS_Store",
    ".gitattributes",
    "package-lock.json",
    "composer.lock",
    "phpunit.xml",
    "README.md",
    "package.json",
    "webpack.config.js",
    "LICENSE"
)

# Robocopy 參數組裝
# /E :: 複製子目錄，包含空的
# /XD :: 排除目錄
# /XF :: 排除檔案
# /NFL /NDL ... :: 靜默模式
$RoboArgs = @(
    ".",                # 來源 (當前目錄)
    $StageDir,          # 目的 (暫存目錄)
    "/E",
    "/XD"
) + $ExcludeDirs + @(
    "/XF"
) + $ExcludeFiles + @(
    "/NFL", "/NDL", "/NJH", "/NJS", "/nc", "/ns", "/np"
)

Write-Host "正在複製檔案並套用排除規則..." -ForegroundColor Gray
# 執行 Robocopy
# Robocopy 的 ExitCode: 0-7 為成功 (單純複製、有複製檔案等)，>=8 為失敗
# 使用 & 運算子執行，並傳入參數陣列
$RoboProcess = Start-Process -FilePath "robocopy" -ArgumentList $RoboArgs -Wait -PassThru -NoNewWindow

if ($RoboProcess.ExitCode -ge 8) {
    Write-Error "檔案複製失敗 (Robocopy Exit Code: $($RoboProcess.ExitCode))"
    Remove-Item $TempRoot -Recurse -Force
    exit 1
}

# 壓縮檔案
Write-Host "正在建立壓縮檔 $OutputFile..." -ForegroundColor Cyan
$DestinationZipPath = Join-Path $DownloadDir $OutputFile

if (Test-Path $DestinationZipPath) { Remove-Item $DestinationZipPath -Force }

# 使用 Compress-Archive 壓縮暫存目錄中的資料夾
try {
    Compress-Archive -Path $StageDir -DestinationPath $DestinationZipPath -Force
}
catch {
    Write-Error "壓縮失敗: $_"
    Remove-Item $TempRoot -Recurse -Force
    exit 1
}

# 清理暫存
Remove-Item $TempRoot -Recurse -Force

Write-Host "外掛打包成功: $OutputFile" -ForegroundColor Green
Write-Host "壓縮檔案位於: $DestinationZipPath" -ForegroundColor Gray
