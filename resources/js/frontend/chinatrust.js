const ccatChinatrustPaymentConfig = window.wc.wcSettings.getSetting('ccat_payment_chinatrust_data', {});
const ccatChinatrustPaymentLabel = window.wp.htmlEntities.decodeEntities(ccatChinatrustPaymentConfig.title) || window.wp.i18n.__('中國信託', '中國信託');
const ccatChinatrustPaymentContent = () => {
    return window.wp.htmlEntities.decodeEntities(ccatChinatrustPaymentConfig.description);
};
const ccatChinatrustPayment = {
    name: 'ccat_payment_chinatrust',
    label: ccatChinatrustPaymentLabel,
    content: Object(window.wp.element.createElement)(ccatChinatrustPaymentContent, null),
    edit: Object(window.wp.element.createElement)(ccatChinatrustPaymentContent, null),
    canMakePayment: () => true,
    ariaLabel: ccatChinatrustPaymentLabel,
    supports: {
        features: ccatChinatrustPaymentConfig.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(ccatChinatrustPayment);