const ccatIcashPaymentConfig = window.wc.wcSettings.getSetting('ccat_payment_app_icash_data', {});
const ccatIcashPaymentLabel = window.wp.htmlEntities.decodeEntities(ccatIcashPaymentConfig.title) || window.wp.i18n.__('icash', 'icash');
const ccatIcashPaymentContent = () => {
    return window.wp.htmlEntities.decodeEntities(ccatIcashPaymentConfig.description);
};
const ccatIcashPayment = {
    name: 'ccat_payment_app_icash',
    label: ccatIcashPaymentLabel,
    content: Object(window.wp.element.createElement)(ccatIcashPaymentContent, null),
    edit: Object(window.wp.element.createElement)(ccatIcashPaymentContent, null),
    canMakePayment: () => true,
    ariaLabel: ccatIcashPaymentLabel,
    supports: {
        features: ccatIcashPaymentConfig.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(ccatIcashPayment);