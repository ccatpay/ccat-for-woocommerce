import {registerPlugin} from '@wordpress/plugins'

const render = () => {
}

registerPlugin('ccat-block', {
    render,
    scope: 'woocommerce-checkout',
});