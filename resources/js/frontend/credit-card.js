const ccatPaymentConfig = window.wc.wcSettings.getSetting('ccat_payment_credit_card_data', {});
const ccatPaymentLabel = window.wp.htmlEntities.decodeEntities(ccatPaymentConfig.title) || window.wp.i18n.__('黑貓Pay', '黑貓Pay');
const ccatPaymentContent = () => {
    return window.wp.htmlEntities.decodeEntities(ccatPaymentConfig.description);
};
const ccatPayment = {
    name: 'ccat_payment_credit_card',
    label: ccatPaymentLabel,
    content: Object(window.wp.element.createElement)(ccatPaymentContent, null),
    edit: Object(window.wp.element.createElement)(ccatPaymentContent, null),
    canMakePayment: () => true,
    ariaLabel: ccatPaymentLabel,
    supports: {
        features: ccatPaymentConfig.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(ccatPayment);