const ccatUniPayPaymentConfig = window.wc.wcSettings.getSetting('ccat_payment_uni_pay_data', {});
const ccatUniPayPaymentLabel = window.wp.htmlEntities.decodeEntities(ccatUniPayPaymentConfig.title) || window.wp.i18n.__('統一金流', '統一金流');
const ccatUniPayPaymentContent = () => {
    return window.wp.htmlEntities.decodeEntities(ccatUniPayPaymentConfig.description);
};
const ccatUniPayPayment = {
    name: 'ccat_payment_uni_pay',
    label: ccatUniPayPaymentLabel,
    content: Object(window.wp.element.createElement)(ccatUniPayPaymentContent, null),
    edit: Object(window.wp.element.createElement)(ccatUniPayPaymentContent, null),
    canMakePayment: () => true,
    ariaLabel: ccatUniPayPaymentLabel,
    supports: {
        features: ccatUniPayPaymentConfig.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(ccatUniPayPayment);