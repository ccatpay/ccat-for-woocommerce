const ccatCodePaymentConfig = window.wc.wcSettings.getSetting('ccat_cod_data', {});
const ccatCodePaymentLabel = window.wp.htmlEntities.decodeEntities(ccatCodePaymentConfig.title) || window.wp.i18n.__('黑貓貨到付款', '黑貓貨到付款');
const ccatCodePaymentContent = () => {
    return window.wp.htmlEntities.decodeEntities(ccatCodePaymentConfig.description);
};
const ccatCodePayment = {
    name: 'ccat_cod',
    label: ccatCodePaymentLabel,
    content: Object(window.wp.element.createElement)(ccatCodePaymentContent, null),
    edit: Object(window.wp.element.createElement)(ccatCodePaymentContent, null),
    canMakePayment: () => true,
    ariaLabel: ccatCodePaymentLabel,
    supports: {
        features: ccatCodePaymentConfig.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(ccatCodePayment);