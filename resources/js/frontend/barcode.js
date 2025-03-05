const ccatBarcodePaymentConfig = window.wc.wcSettings.getSetting('ccat_payment_cvs_barcode_data', {});
const ccatBarcodePaymentLabel = window.wp.htmlEntities.decodeEntities(ccatBarcodePaymentConfig.title) || window.wp.i18n.__('統一金流', '統一金流');
const ccatBarcodePaymentContent = () => {
    return window.wp.htmlEntities.decodeEntities(ccatBarcodePaymentConfig.description);
};
const ccatBarcodePayment = {
    name: 'ccat_payment_cvs_barcode',
    label: ccatBarcodePaymentLabel,
    content: Object(window.wp.element.createElement)(ccatBarcodePaymentContent, null),
    edit: Object(window.wp.element.createElement)(ccatBarcodePaymentContent, null),
    canMakePayment: () => true,
    ariaLabel: ccatBarcodePaymentLabel,
    supports: {
        features: ccatBarcodePaymentConfig.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(ccatBarcodePayment);