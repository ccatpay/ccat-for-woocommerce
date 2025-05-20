// fetch-interceptor.js
// 使用 IIFE 和閉包來實現單例模式
const fetchInterceptor = (function () {
    function createInterceptor() {
        // 原始的 fetch 函數
        const originalFetch = window.fetch;

        // 攔截器列表
        const interceptors = [];

        // 攔截的 fetch 實現
        async function interceptedFetch(...args) {
            let [resource, config] = args;

            console.log('攔截到請求:', resource, config);

            // 確保 config 存在
            config = config || {};

            // 依序執行所有攔截器
            for (const interceptor of interceptors) {
                try {
                    console.log('執行攔截器:', interceptor.name || '匿名攔截器');

                    const result = await interceptor(resource, config);
                    if (result) {
                        [resource, config] = result;
                        console.log('攔截器修改後的資源和配置:', resource, config);
                    } else {
                        console.log('攔截器未修改請求');
                    }
                } catch (error) {
                    console.error('執行攔截器時發生錯誤:', error);
                }
            }

            // 如果是 checkout 請求，記錄最終請求內容
            if (resource.includes('/wc/store/v1/checkout') && config.body) {
                try {
                    const bodyObj = JSON.parse(config.body);
                    console.log('最終結帳請求資料:', bodyObj);
                    console.log('extensions 內容:', bodyObj.extensions);
                } catch (e) {
                    console.error('無法解析請求體:', e);
                }
            }

            console.log('調用原始 fetch 方法');

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
                    console.log('Fetch 攔截器已初始化');
                }
                return this;
            },

            // 註冊新攔截器
            register: function (interceptor) {
                if (typeof interceptor !== 'function') {
                    console.error('攔截器必須是函數');
                    return;
                }

                interceptors.push(interceptor);
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

window.fetchInterceptor = fetchInterceptor.getInstance();