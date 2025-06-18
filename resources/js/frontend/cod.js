// 一般黑貓貨到付款
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

// 711貨到付款
const ccatCod711Config = window.wc.wcSettings.getSetting('ccat_cod_711_data', {});
const ccatCod711Label = window.wp.htmlEntities.decodeEntities(ccatCod711Config.title) || window.wp.i18n.__('超商711貨到付款', '黑貓貨到付款');
const ccatCod711Content = () => {
    return window.wp.htmlEntities.decodeEntities(ccatCod711Config.description);
};
const ccatCod711Payment = {
    name: 'ccat_cod_711',
    label: ccatCod711Label,
    content: Object(window.wp.element.createElement)(ccatCod711Content, null),
    edit: Object(window.wp.element.createElement)(ccatCod711Content, null),
    canMakePayment: () => true,
    ariaLabel: ccatCod711Label,
    supports: {
        features: ccatCod711Config.supports,
    },
};

// 現金貨到付款
const ccatCodCashConfig = window.wc.wcSettings.getSetting('ccat_cod_cash_data', {});
const ccatCodCashLabel = window.wp.htmlEntities.decodeEntities(ccatCodCashConfig.title) || window.wp.i18n.__('宅配貨到付款(現金)', '黑貓貨到付款');
const ccatCodCashContent = () => {
    return window.wp.htmlEntities.decodeEntities(ccatCodCashConfig.description);
};
const ccatCodCashPayment = {
    name: 'ccat_cod_cash',
    label: ccatCodCashLabel,
    content: Object(window.wp.element.createElement)(ccatCodCashContent, null),
    edit: Object(window.wp.element.createElement)(ccatCodCashContent, null),
    canMakePayment: () => true,
    ariaLabel: ccatCodCashLabel,
    supports: {
        features: ccatCodCashConfig.supports,
    },
};

// 刷卡貨到付款
const ccatCodCardConfig = window.wc.wcSettings.getSetting('ccat_cod_card_data', {});
const ccatCodCardLabel = window.wp.htmlEntities.decodeEntities(ccatCodCardConfig.title) || window.wp.i18n.__('宅配貨到付款(刷卡)', '黑貓貨到付款');
const ccatCodCardContent = () => {
    return window.wp.htmlEntities.decodeEntities(ccatCodCardConfig.description);
};
const ccatCodCardPayment = {
    name: 'ccat_cod_card',
    label: ccatCodCardLabel,
    content: Object(window.wp.element.createElement)(ccatCodCardContent, null),
    edit: Object(window.wp.element.createElement)(ccatCodCardContent, null),
    canMakePayment: () => true,
    ariaLabel: ccatCodCardLabel,
    supports: {
        features: ccatCodCardConfig.supports,
    },
};

// 手機支付貨到付款
const ccatCodMobileConfig = window.wc.wcSettings.getSetting('ccat_cod_mobile_data', {});
const ccatCodMobileLabel = window.wp.htmlEntities.decodeEntities(ccatCodMobileConfig.title) || window.wp.i18n.__('宅配貨到付款(手機支付)', '黑貓貨到付款');
const ccatCodMobileContent = () => {
    return window.wp.htmlEntities.decodeEntities(ccatCodMobileConfig.description);
};
const ccatCodMobilePayment = {
    name: 'ccat_cod_mobile',
    label: ccatCodMobileLabel,
    content: Object(window.wp.element.createElement)(ccatCodMobileContent, null),
    edit: Object(window.wp.element.createElement)(ccatCodMobileContent, null),
    canMakePayment: () => true,
    ariaLabel: ccatCodMobileLabel,
    supports: {
        features: ccatCodMobileConfig.supports,
    },
};

// 註冊所有付款方式
window.wc.wcBlocksRegistry.registerPaymentMethod(ccatCodePayment);
window.wc.wcBlocksRegistry.registerPaymentMethod(ccatCod711Payment);
window.wc.wcBlocksRegistry.registerPaymentMethod(ccatCodCashPayment);
window.wc.wcBlocksRegistry.registerPaymentMethod(ccatCodCardPayment);
window.wc.wcBlocksRegistry.registerPaymentMethod(ccatCodMobilePayment);