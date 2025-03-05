const ccatAtmPaymentConfig = window.wc.wcSettings.getSetting('ccat_payment_cvs_atm_data', {});
const ccatAtmPaymentLabel = window.wp.htmlEntities.decodeEntities(ccatAtmPaymentConfig.title) || window.wp.i18n.__('ATM', 'ATM');
const ccatAtmPaymentContent = () => {
    return window.wp.htmlEntities.decodeEntities(ccatAtmPaymentConfig.description);
};
const ccatAtmPayment = {
    name: 'ccat_payment_cvs_atm',
    label: ccatAtmPaymentLabel,
    content: Object(window.wp.element.createElement)(ccatAtmPaymentContent, null),
    edit: Object(window.wp.element.createElement)(ccatAtmPaymentContent, null),
    canMakePayment: () => true,
    ariaLabel: ccatAtmPaymentLabel,
    supports: {
        features: ccatAtmPaymentConfig.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(ccatAtmPayment);