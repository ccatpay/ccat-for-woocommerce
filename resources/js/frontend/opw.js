const ccatOpwPaymentConfig = window.wc.wcSettings.getSetting('ccat_payment_app_opw_data', {});
const ccatOpwPaymentLabel = window.wp.htmlEntities.decodeEntities(ccatOpwPaymentConfig.title) || window.wp.i18n.__('opw', 'opw');
const ccatOpwPaymentContent = () => {
    return window.wp.htmlEntities.decodeEntities(ccatOpwPaymentConfig.description);
};
const ccatOpwPayment = {
    name: 'ccat_payment_app_opw',
    label: ccatOpwPaymentLabel,
    content: Object(window.wp.element.createElement)(ccatOpwPaymentContent, null),
    edit: Object(window.wp.element.createElement)(ccatOpwPaymentContent, null),
    canMakePayment: () => true,
    ariaLabel: ccatOpwPaymentLabel,
    supports: {
        features: ccatOpwPaymentConfig.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(ccatOpwPayment);