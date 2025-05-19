import {useState, useEffect} from '@wordpress/element';
import {__} from '@wordpress/i18n';
import {useSelect, useDispatch} from '@wordpress/data';
import fetchInterceptor from '../../../../fetch-interceptor';

// 獲取全局設置的腳本數據
const ccat711BlockData = window.ccat711BlockData || {};

export const Block = ({checkoutExtensionData, extensions}) => {
    const [showBlock, setShowBlock] = useState(false);
    const [storeInfo, setStoreInfo] = useState({
        storeName: '',
        storeId: '',
        storeAddress: ''
    });

    const shippingRates = useSelect((select) => {
        const store = select('wc/store/cart');
        return store.getCartData().shippingRates;
    });


    const getActiveShippingRates = (shippingRates) => {
        if (!shippingRates.length) {
            return [];
        }

        let activeRates = [];
        for (let i = 0; i < shippingRates.length; i++) {
            if (!shippingRates[i].shipping_rates) {
                continue;
            }
            for (let j = 0; j < shippingRates[i].shipping_rates.length; j++) {
                activeRates.push(shippingRates[i].shipping_rates[j]);
            }
        }

        return activeRates;
    };

    useEffect(() => {
        // 建立攔截器函數
        const cvsInterceptor = async (resource, config) => {
            // 檢查是否是結帳請求
            if (resource.includes('/wc/store/v1/checkout') && config.body && showBlock && storeInfo.storeName) {
                // 修改請求資料
                const body = JSON.parse(config.body);

                // 添加超商資訊到請求
                body.extensions = {
                    ...body.extensions,
                    'ccat_cvs_store_info': storeInfo
                };

                // 如果需要，也可以修改地址資訊
                if (body.shipping_address) {
                    body.shipping_address = {
                        ...body.shipping_address,
                        address_1: `${storeInfo.storeName} (${storeInfo.storeId})`,
                        address_2: storeInfo.storeAddress,
                        city: '台北市',
                        state: '台北市',
                        postcode: '11050',
                        country: 'TW'
                    };
                }

                config.body = JSON.stringify(body);
            }

            return [resource, config];
        };

        // 註冊攔截器並獲取取消函數
        const unregister = fetchInterceptor.register(cvsInterceptor);

        // 組件卸載時取消註冊
        return () => {
            unregister();
        };
    }, [showBlock, storeInfo]); // 當這些狀態變更時重新註冊攔截器


    // 處理超商選擇
    const handleStoreSelect = (event) => {
        // 顯示載入中狀態
        const buttonEl = event.target;
        const originalText = buttonEl.textContent;
        buttonEl.disabled = true;
        buttonEl.textContent = __('載入中...', 'your-text-domain');

        // 使用 AJAX 獲取選擇門市的 URL
        fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'get_711_store_selection_url',
                security: ccat711BlockData?.nonce || '',
                shipping_method: 'wc_shipping_ccat_711_prepaid'
            })
        })
            .then(response => response.json())
            .then(data => {
                // 恢復按鈕狀態
                buttonEl.disabled = false;
                buttonEl.textContent = originalText;

                if (data.success && data.data.url) {
                    // 在新窗口中打開選擇門市頁面
                    const selectionWindow = window.open(data.data.url, '_blank', 'width=800,height=700');

                    // 創建並顯示提示用戶操作的信息
                    const pendingMessage = document.createElement('div');
                    pendingMessage.className = 'wc-block-components-notice-banner is-info';
                    pendingMessage.style.marginTop = '10px';
                    pendingMessage.innerHTML = `<span>${__('請在新開啟的視窗中選擇門市...', 'your-text-domain')}</span>`;

                    const noticeContainer = buttonEl.closest('.wc-block-components-shipping-cvs-selector__content');
                    const existingNotice = noticeContainer.querySelector('.wc-block-components-notice-banner');

                    if (existingNotice) {
                        noticeContainer.removeChild(existingNotice);
                    }

                    noticeContainer.insertBefore(pendingMessage, noticeContainer.querySelector('.wc-block-components-shipping-cvs-info') || null);

                    // 顯示成功訊息的函數
                    const showSuccessMessage = (container) => {
                        // 移除進行中訊息
                        const existingNotice = container.querySelector('.wc-block-components-notice-banner');
                        if (existingNotice) {
                            container.removeChild(existingNotice);
                        }

                        // 建立成功訊息
                        const successMessage = document.createElement('div');
                        successMessage.className = 'wc-block-components-notice-banner is-success';
                        successMessage.style.marginTop = '10px';
                        successMessage.innerHTML = `<span>${__('已成功選擇門市', 'your-text-domain')}</span>`;

                        container.insertBefore(successMessage, container.querySelector('.wc-block-components-shipping-cvs-info') || null);

                        // 2秒後移除通知
                        setTimeout(() => {
                            if (successMessage.parentNode) {
                                successMessage.parentNode.removeChild(successMessage);
                            }
                        }, 2000);
                    };

                    // 設置一個全局回調函數，讓選擇門市頁面可以調用
                    window.setSelectedCvsStore = (storeData) => {
                        if (storeData && storeData.storeName && storeData.storeId) {
                            const selectedStoreInfo = {
                                storeName: storeData.storeName || '',
                                storeId: storeData.storeId || '',
                                storeAddress: storeData.storeAddress || ''
                            };

                            // 將選擇的門市資訊儲存到 localStorage 作為備份
                            try {
                                localStorage.setItem('selectedCvsStore', JSON.stringify(selectedStoreInfo));
                            } catch (e) {
                                console.error('無法將門市資訊儲存到 localStorage:', e);
                            }

                            setStoreInfo(selectedStoreInfo);

                            // 如果選擇窗口還打開著，關閉它
                            if (selectionWindow && !selectionWindow.closed) {
                                selectionWindow.close();
                            }

                            // 顯示成功訊息
                            showSuccessMessage(noticeContainer);
                        }
                    };

                    // 監聽窗口關閉事件
                    const checkWindowClosed = setInterval(() => {
                        if (selectionWindow && selectionWindow.closed) {
                            clearInterval(checkWindowClosed);

                            // 檢查 localStorage 是否有保存的數據
                            setTimeout(() => {
                                try {
                                    const savedStore = localStorage.getItem('selectedCvsStore');
                                    if (savedStore) {
                                        const storeData = JSON.parse(savedStore);

                                        // 確認資料是否已通過 window.setSelectedCvsStore 設置
                                        // 避免重複設置 (比較目前的 storeInfo 和從 localStorage 讀取的資料)
                                        if (!storeInfo.storeId || storeInfo.storeId !== storeData.storeId) {
                                            setStoreInfo(storeData);
                                            showSuccessMessage(noticeContainer);
                                        }

                                        // 使用後從 localStorage 中清除，避免下次自動載入
                                        localStorage.removeItem('selectedCvsStore');
                                    } else {
                                        // 如果 localStorage 沒有資料且現有 storeInfo 為空，表示用戶沒有選擇門市
                                        if (!storeInfo.storeId) {
                                            // 移除進行中的提示訊息
                                            const existingNotice = noticeContainer.querySelector('.wc-block-components-notice-banner');
                                            if (existingNotice) {
                                                noticeContainer.removeChild(existingNotice);
                                            }
                                        }
                                    }
                                } catch (e) {
                                    console.error('讀取門市資料時發生錯誤:', e);
                                }
                            }, 500); // 給予足夠時間讓回調處理完成
                        }
                    }, 500);

                } else {
                    console.error('無法獲取門市選擇網址:', data.data?.message || '未知錯誤');
                    alert('無法獲取門市選擇網址，請稍後再試');
                }
            })
            .catch(error => {
                // 恢復按鈕狀態
                buttonEl.disabled = false;
                buttonEl.textContent = originalText;

                console.error('請求門市選擇網址時發生錯誤:', error);
                alert('請求門市選擇網址時發生錯誤，請稍後再試');
            });
    };

    // 在組件初始化時檢查 localStorage 中是否有保存的門市資訊
    useEffect(() => {
        try {
            const savedStore = localStorage.getItem('selectedCvsStore');
            if (savedStore) {
                const storeData = JSON.parse(savedStore);
                if (storeData && storeData.storeName && storeData.storeId) {
                    setStoreInfo(storeData);
                    // 不要立即刪除，等到確認運送方式後再決定是否使用這個資料
                }
            }
        } catch (e) {
            console.error('無法讀取保存的門市資訊:', e);
        }
    }, []);

    useEffect(() => {
        setShowBlock(false);
        if (shippingRates.length) {
            const activeRates = getActiveShippingRates(shippingRates);
            for (let i = 0; i < activeRates.length; i++) {
                if (!activeRates[i].rate_id) {
                    continue;
                }
                if (activeRates[i].rate_id.includes("wc_shipping_ccat_711") && activeRates[i].selected) {
                    setShowBlock(true);

                    // 如果切換到不同運送方式時，確認是否要保留或清除 localStorage 資料
                    if (!showBlock) {
                        // 檢查有沒有預存的門市資訊且目前狀態沒有
                        const savedStore = localStorage.getItem('selectedCvsStore');
                        if (savedStore && !storeInfo.storeId) {
                            try {
                                const storeData = JSON.parse(savedStore);
                                if (storeData && storeData.storeName && storeData.storeId) {
                                    setStoreInfo(storeData);
                                    // 資料已使用，從 localStorage 移除
                                    localStorage.removeItem('selectedCvsStore');
                                }
                            } catch (e) {
                                console.error('無法解析保存的門市資訊:', e);
                            }
                        }
                    }
                }
            }
        }

        // 如果不再顯示 711 取貨區塊，但 localStorage 中仍有資料，則移除
        if (!showBlock) {
            try {
                localStorage.removeItem('selectedCvsStore');
            } catch (e) {
                // 忽略可能的錯誤
            }
        }
    }, [
        shippingRates
    ]);

    if (!showBlock) {
        return <></>
    }

    return (
        <div className="wc-block-components-shipping-cvs-selector">
            <h4>{__('選擇 7-11 取貨門市', 'your-text-domain')}</h4>
            <div className="wc-block-components-shipping-cvs-selector__content">
                <button type="button"
                        className="wc-block-components-button"
                        onClick={handleStoreSelect}
                >
                    {__('選擇門市', 'your-text-domain')}
                </button>

                {storeInfo.storeName && (
                    <div className="wc-block-components-shipping-cvs-info">
                        <p><strong>{__('已選擇門市：', 'your-text-domain')}</strong> {storeInfo.storeName}</p>
                        <p><strong>{__('門市代號：', 'your-text-domain')}</strong> {storeInfo.storeId}</p>
                        <p><strong>{__('門市地址：', 'your-text-domain')}</strong> {storeInfo.storeAddress}</p>
                    </div>
                )}
            </div>
        </div>
    );
};