/**
 * Webpack Configuration for BITCORE Core
 *
 * Modern asset build configuration
 * Based on ai-services webpack.config.js
 *
 * @package BITCORE
 */

const path = require('path');

module.exports = {
    mode: process.env.NODE_ENV || 'development',
    entry: {
        'bitcore-admin': path.resolve(__dirname, '../assets/js/bitcore-admin.js'),
        'bitcore-realtime': path.resolve(__dirname, '../assets/js/bitcore-realtime.js'),
    },
    output: {
        path: path.resolve(__dirname, '../assets/dist'),
        filename: '[name].bundle.js',
    },
    module: {
        rules: [
            {
                test: /\.tsx?$/,
                use: 'ts-loader',
                exclude: /node_modules/,
            },
            {
                test: /\.js$/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env'],
                    },
                },
            },
            {
                test: /\.css$/,
                use: ['style-loader', 'css-loader'],
            },
        ],
    },
    resolve: {
        extensions: ['.tsx', '.ts', '.js'],
    },
    externals: {
        jquery: 'jQuery',
        lodash: 'lodash',
    },
    devtool: 'source-map',
};
