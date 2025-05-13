import {useState, useEffect} from '@wordpress/element';
import {__} from '@wordpress/i18n';

// 超商選擇器區塊
export const Block = ({checkoutExtensionData}) => {
    console.log('超商選擇器區塊組件渲染', checkoutExtensionData);

    // 獲取配置
    const settings = window.wc?.wcSettings?.getSetting('ccat--store-selector', {
        shippingMethods: {},
        labels: {
            selectStore: '選擇7-11超商門市',
            selectedStore: '已選擇門市',
            changeStore: '變更超商門市',
            storeAddress: '地址'
        }
    });

    // 如果設定中沒有運送方法，使用測試資料
    if (!settings.shippingMethods || Object.keys(settings.shippingMethods).length === 0) {
        settings.shippingMethods = {
            'wc_shipping_ccat__cod': {
                url: 'https://emap.pcsc.com.tw/EMapSDK.aspx'
            },
            'wc_shipping_ccat__prepaid': {
                url: 'https://emap.pcsc.com.tw/EMapSDK.aspx'
            }
        };
    }

    // 獲取已選擇的店鋪資訊
    const [selectedStore, setSelectedStore] = useState(() => {
        // 嘗試從localStorage讀取已存的資料
        try {
            const savedStore = localStorage.getItem('ccat_selected_store');
            return savedStore ? JSON.parse(savedStore) : null;
        } catch (e) {
            console.error('讀取儲存的超商資訊時出錯:', e);
            return null;
        }
    });

    // 檢查是否為7-11運送方式
    const isShipping = true; // 為了測試，先設為true，實際環境需根據選擇的運送方式判斷

    // 處理超商選擇
    const handleStoreSelection = (storeInfo) => {
        // 保存選擇的超商資訊
        setSelectedStore(storeInfo);

        // 儲存到localStorage
        try {
            localStorage.setItem('ccat_selected_store', JSON.stringify(storeInfo));
        } catch (e) {
            console.error('儲存超商資訊到localStorage時出錯:', e);
        }

        // 提交到結帳數據
        if (checkoutExtensionData && checkoutExtensionData.setExtensionData) {
            checkoutExtensionData.setExtensionData('ccat--store-selector', {
                storeCode: storeInfo.storeCode,
                storeName: storeInfo.storeName,
                storeAddress: storeInfo.storeAddress
            });
        }
    };

    // 打開超商選擇視窗
    const openStoreSelector = () => {
        // 使用第一個可用的運送方法
        const methodConfig = Object.values(settings.shippingMethods)[0];
        if (!methodConfig || !methodConfig.url) {
            console.error('找不到運送方式的配置');
            return;
        }

        // 打開新視窗進行超商選擇
        const popup = window.open(
            methodConfig.url,
            'storeSelection',
            'width=800,height=600'
        );

        // 設置視窗訊息監聽
        const handleMessage = (event) => {
            try {
                if (event.data && event.data.storeInfo) {
                    handleStoreSelection(event.data.storeInfo);

                    // 關閉選擇視窗
                    if (popup) {
                        popup.close();
                    }

                    // 移除監聽器
                    window.removeEventListener('message', handleMessage);
                }
            } catch (error) {
                console.error('處理超商選擇訊息時出錯:', error);
            }
        };

        window.addEventListener('message', handleMessage);
    };

    // 如果不是7-11運送方式，則不顯示
    if (!isShipping) {
        return null;
    }

    return (
        <div className="wc-block-components-panel">
            <div className="wc-block-components-panel__content">
                <div className="ccat--store-selector-container">
                    <h3 className="wc-block-components-title">7-11 超商取貨</h3>

                    <button
                        className="wc-block-components-button wp-element-button"
                        onClick={openStoreSelector}
                        style={{width: '100%', marginBottom: '10px'}}
                    >
                        {selectedStore ? settings.labels.changeStore : settings.labels.selectStore}
                    </button>

                    {selectedStore && (
                        <div className="ccat-selected-store-info" style={{
                            padding: '10px',
                            backgroundColor: '#f8f8f8',
                            border: '1px solid #ddd',
                            borderRadius: '4px'
                        }}>
                            <div>
                                <strong>{settings.labels.selectedStore}:</strong>
                                {selectedStore.storeName} ({selectedStore.storeCode})
                            </div>
                            <div>
                                <strong>{settings.labels.storeAddress}:</strong>
                                {selectedStore.storeAddress}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

// 添加視窗郵件處理函數
window.handleStoreSelection = (storeInfo) => {
    if (window.opener) {
        window.opener.postMessage({storeInfo}, '*');
    }
};