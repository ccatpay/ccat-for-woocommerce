import {registerPlugin} from '@wordpress/plugins'

const render = () => {
}

registerPlugin('ccat711-block', {
    render,
    scope: 'woocommerce-checkout',
});