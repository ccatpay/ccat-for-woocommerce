const ccatPayuniPaymentConfig = window.wc.wcSettings.getSetting('ccat_payment_pay_uni_data', {});
const ccatPayuniPaymentLabel = window.wp.htmlEntities.decodeEntities(ccatPayuniPaymentConfig.title) || window.wp.i18n.__('統一金流', '統一金流');
const ccatPayuniPaymentContent = () => {
    return window.wp.htmlEntities.decodeEntities(ccatPayuniPaymentConfig.description);
};
const ccatPayuniPayment = {
    name: 'ccat_payment_pay_uni',
    label: ccatPayuniPaymentLabel,
    content: Object(window.wp.element.createElement)(ccatPayuniPaymentContent, null),
    edit: Object(window.wp.element.createElement)(ccatPayuniPaymentContent, null),
    canMakePayment: () => true,
    ariaLabel: ccatPayuniPaymentLabel,
    supports: {
        features: ccatPayuniPaymentConfig.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(ccatPayuniPayment);