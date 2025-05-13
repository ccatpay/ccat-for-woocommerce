import {useState, useEffect} from '@wordpress/element';
import {__} from '@wordpress/i18n';
import fetchInterceptor from '../../../../fetch-interceptor';

let defaultInvoiceData = {
    vehicle_type: '1',
    cloud_invoice_type: 'member',
    vehicle_barcode: '',
    certificate_number: '',
    donate_invoice: '0',
    love_code: '',
    buyer_bill_no: '',
    buyer_invoice_title: ''
};
export const Block = ({checkoutExtensionData}) => {
    const [invoiceData, setInvoiceData] = useState(defaultInvoiceData);

    // 載具類型選項
    const vehicleTypes = [
        {value: '1', label: __('個人雲端發票', 'woocommerce')},
        {value: '2', label: __('發票捐贈', 'woocommerce')},
        {value: '3', label: __('公司發票', 'woocommerce')},
    ];

    const cloudInvoiceTypes = [
        {value: 'member', label: __('會員載具', 'woocommerce')},
        {value: 'mobile', label: __('手機條碼載具', 'woocommerce')},
        {value: 'certificate', label: __('自然人憑證載具', 'woocommerce')},
    ];


    const handleInputChange = (field, value) => {
        const newInvoiceData = {
            ...invoiceData,
            [field]: value
        };

        setInvoiceData(newInvoiceData);
        sessionStorage.setItem('wc_invoice_data', JSON.stringify(newInvoiceData));
    };

    return (
        <div className="wc-block-components-panel">
            <h2 className="wc-block-components-title">{__('電子發票資訊', 'woocommerce')}</h2>

            {/* 發票類型 */}
            <div className="wc-blocks-components-select">
                <div className="wc-blocks-components-select__container">
                    <label className="wc-blocks-components-select__label">{__('發票類型', 'woocommerce')}</label>
                    <select
                        name="vehicle_type" id="vehicle_type"
                        className="wc-blocks-components-select__select"
                        value={invoiceData.vehicle_type}
                        onChange={(e) => handleInputChange('vehicle_type', e.target.value)}
                    >
                        {vehicleTypes.map(type => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            {/* 個人雲端發票子選項 */}
            {invoiceData.vehicle_type === '1' && (
                <>
                    <div className="wc-blocks-components-select">
                        <div className="wc-blocks-components-select__container">
                            <label className="wc-blocks-components-select__label">
                                {__('載具類型', 'woocommerce')}
                            </label>
                            <select
                                name="cloud_invoice_type" id="cloud_invoice_type"
                                className="wc-blocks-components-select__select"
                                value={invoiceData.cloud_invoice_type}
                                onChange={(e) => handleInputChange('cloud_invoice_type', e.target.value)}
                            >
                                {cloudInvoiceTypes.map(type => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {/* 手機條碼載具輸入框 */}
                    {invoiceData.cloud_invoice_type === 'mobile' && (
                        <div className="wc-block-components-text-input is-active">
                            <input
                                name="vehicle_barcode" id="vehicle_barcode"
                                type="text"
                                maxLength="8"
                                value={invoiceData.vehicle_barcode}
                                onChange={(e) => handleInputChange('vehicle_barcode', e.target.value)}
                            />
                            <label>{__('手機條碼', 'woocommerce')}</label>
                        </div>
                    )}

                    {/* 自然人憑證載具輸入框 */}
                    {invoiceData.cloud_invoice_type === 'certificate' && (
                        <div className="wc-block-components-text-input is-active">
                            <input
                                name="certificate_number" id="certificate_number"
                                type="text"
                                maxLength="16"
                                value={invoiceData.certificate_number}
                                onChange={(e) => handleInputChange('certificate_number', e.target.value)}
                            />
                            <label>{__('自然人憑證號碼', 'woocommerce')}</label>
                        </div>
                    )}
                </>
            )}

            {/* 愛心碼 */}
            {invoiceData.vehicle_type === '2' && (
                <div className="wc-block-components-text-input is-active">
                    <input
                        name="love_code" id="love_code"
                        type="text"
                        maxLength="20"
                        placeholder="預設：創世基金會919"
                        value={invoiceData.love_code}
                        onChange={(e) => handleInputChange('love_code', e.target.value)}
                    />
                    <label>{__('愛心碼', 'woocommerce')}</label>
                </div>
            )}

            {invoiceData.vehicle_type === '3' && (
                <>
                    <div className="wc-block-components-text-input is-active">
                        <input
                            name="buyer_bill_no" id="buyer_bill_no"
                            type="text"
                            maxLength="8"
                            value={invoiceData.buyer_bill_no}
                            onChange={(e) => handleInputChange('buyer_bill_no', e.target.value)}
                        />
                        <label>{__('統一編號', 'woocommerce')}</label>
                    </div>

                    <div className="wc-block-components-text-input is-active">
                        <input
                            name="buyer_invoice_title" id="buyer_invoice_title"
                            type="text"
                            maxLength="161"
                            value={invoiceData.buyer_invoice_title}
                            onChange={(e) => handleInputChange('buyer_invoice_title', e.target.value)}
                        />
                        <label>{__('發票抬頭', 'woocommerce')}</label>
                    </div>
                </>
            )}
        </div>
    );
};
const invoiceInterceptor = async (resource, config) => {
    // 檢查是否是結帳請求
    if (resource.includes('/wc/store/v1/checkout') && config.body) {
        const invoiceData = sessionStorage.getItem('wc_invoice_data')
            ? JSON.parse(sessionStorage.getItem('wc_invoice_data'))
            : defaultInvoiceData;

        // 修改請求資料
        const body = JSON.parse(config.body);
        body.extensions = {
            ...body.extensions,
            'ccat_invoice_data': invoiceData
        };

        config.body = JSON.stringify(body);
    }

    return [resource, config];
};
fetchInterceptor.register(invoiceInterceptor);