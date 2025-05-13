import {useState, useEffect} from '@wordpress/element';
import {__} from '@wordpress/i18n';
import {useSelect, useDispatch} from '@wordpress/data';
import fetchInterceptor from '../../../../fetch-interceptor';


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
        // 假設這裡會從某個 API 獲取超商資訊
        // 這只是模擬資料
        const selectedStoreInfo = {
            storeName: '7-11 測試門市',
            storeId: 'TW12345',
            storeAddress: '台北市信義區松高路123號'
        };

        setStoreInfo(selectedStoreInfo);

        // 更新送件地址
        // updateShippingAddress({
        //     address_1: `${selectedStoreInfo.storeName} (${selectedStoreInfo.storeId})`,
        //     address_2: selectedStoreInfo.storeAddress,
        //     city: '台北市',
        //     state: '台北市',
        //     postcode: '11050',
        //     country: 'TW'
        // });
    };

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
                }
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