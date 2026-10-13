// src/js/ccat-block/index.js
import {Block} from './block'
import metadata from './block.json'
import {registerBlockType} from '@wordpress/blocks';

// 在區塊編輯器中註冊區塊，向下相容傳入完整 metadata 配置
registerBlockType(metadata.name, {
    ...metadata,
    edit: Block,
    save: () => null,
});