const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const WooCommerceDependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');

module.exports = {
    ...defaultConfig,
    devtool: 'source-map',
    entry: {
        index: path.resolve(process.cwd(), 'src', 'js', 'index.js'),
        'ccat-block':
            path.resolve(
                process.cwd(),
                'src',
                'js',
                'ccat-block',
                'index.js'
            ),
        'ccat-block-frontend':
            path.resolve(
                process.cwd(),
                'src',
                'js',
                'ccat-block',
                'frontend.js'
            ),
    },
    plugins: [
        ...defaultConfig.plugins.filter(
            (plugin) =>
                plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
        ),
        new WooCommerceDependencyExtractionWebpackPlugin(),
    ],
};