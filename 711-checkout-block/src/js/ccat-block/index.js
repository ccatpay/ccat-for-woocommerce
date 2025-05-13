// src/js/ccat-block/index.js
import {Block} from './block'
import metadata from './block.json'
import {registerBlockType} from '@wordpress/blocks';

// 在區塊編輯器中註冊區塊
registerBlockType(metadata.name, {
    edit: Block,
    save: () => null,
});