// fetch-interceptor.js
// 使用 IIFE 和閉包來實現單例模式，並安全支援 Request / URL 物件以避免與其他外掛衝突
const fetchInterceptor = (function () {
    function getResourceUrl(resource) {
        if (typeof resource === 'string') {
            return resource;
        }
        if (resource && typeof resource.url === 'string') {
            return resource.url;
        }
        if (resource && typeof resource.toString === 'function') {
            return resource.toString();
        }
        return '';
    }

    function createInterceptor() {
        // 原始的 fetch 函數
        const originalFetch = window.fetch.bind(window);

        // 攔截器列表
        const interceptors = [];

        // 攔截的 fetch 實現
        async function interceptedFetch(...args) {
            let [resource, config] = args;

            // 確保 config 存在
            config = config || {};

            // 依序執行所有攔截器
            for (const interceptor of interceptors) {
                try {
                    const result = await interceptor(resource, config);
                    if (result) {
                        [resource, config] = result;
                    }
                } catch (error) {
                    console.error('執行攔截器時發生錯誤:', error);
                }
            }

            // 如果是 checkout 請求，驗證最終請求內容
            const resourceUrl = getResourceUrl(resource);
            if (resourceUrl.includes('/wc/store/v1/checkout') && config && config.body && typeof config.body === 'string') {
                try {
                    JSON.parse(config.body);
                } catch (e) {
                    console.error('無法解析請求體:', e);
                }
            }

            // 呼叫原始 fetch
            return originalFetch(resource, config);
        }

        // 檢查是否已經初始化過
        let isInitialized = false;

        return {
            // 初始化攔截器 - 只會執行一次
            init: function () {
                if (!isInitialized) {
                    // 替換全局 fetch
                    window.fetch = interceptedFetch;
                    isInitialized = true;
                }
                return this;
            },

            // 註冊新攔截器
            register: function (interceptor) {
                if (typeof interceptor !== 'function') {
                    console.error('攔截器必須是函數');
                    return;
                }

                if (!interceptors.includes(interceptor)) {
                    interceptors.push(interceptor);
                }
            },

            // 取消註冊攔截器
            unregister: function (interceptor) {
                const index = interceptors.indexOf(interceptor);
                if (index !== -1) {
                    interceptors.splice(index, 1);
                    return true;
                }
                return false;
            },

            getResourceUrl: getResourceUrl,
        };
    }

    // 返回獲取實例的方法
    return {
        // 獲取單例實例
        getInstance: function () {
            if (!window.ccatInstance) {
                window.ccatInstance = createInterceptor();
                window.ccatInstance.init(); // 自動初始化
            }
            return window.ccatInstance;
        }
    };
})();

window.ccatFetchInterceptor = fetchInterceptor.getInstance();
if (!window.fetchInterceptor) {
    window.fetchInterceptor = window.ccatFetchInterceptor;
}
export default window.ccatFetchInterceptor;
