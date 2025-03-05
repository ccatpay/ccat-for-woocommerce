const ccatIbonPaymentConfig = window.wc.wcSettings.getSetting('ccat_payment_cvs_ibon_data', {});
const ccatIbonPaymentLabel = window.wp.htmlEntities.decodeEntities(ccatIbonPaymentConfig.title) || window.wp.i18n.__('統一金流', '統一金流');
const ccatIbonPaymentContent = () => {
    return window.wp.htmlEntities.decodeEntities(ccatIbonPaymentConfig.description);
};
const ccatIbonPayment = {
    name: 'ccat_payment_cvs_ibon',
    label: ccatIbonPaymentLabel,
    content: Object(window.wp.element.createElement)(ccatIbonPaymentContent, null),
    edit: Object(window.wp.element.createElement)(ccatIbonPaymentContent, null),
    canMakePayment: () => true,
    ariaLabel: ccatIbonPaymentLabel,
    supports: {
        features: ccatIbonPaymentConfig.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(ccatIbonPayment);