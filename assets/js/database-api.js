(() => {
    'use strict';

    const config = window.lumiCineApiConfig;

    window.lumiCineApi = async (resource, options = {}) => {
        const method = options.method || 'GET';
        const url = new URL(config.url, window.location.href);
        url.searchParams.set('resource', resource);
        Object.entries(options.query || {}).forEach(([key, value]) => {
            url.searchParams.set(key, String(value));
        });

        const headers = { Accept: 'application/json' };
        const request = { method, headers, credentials: 'same-origin' };
        if (method !== 'GET') {
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-Token'] = config.csrfToken;
            request.body = JSON.stringify(options.body || {});
        }

        const response = await fetch(url, request);
        let payload;
        try {
            payload = await response.json();
        } catch (error) {
            throw new Error('Phản hồi từ máy chủ không hợp lệ.');
        }
        if (!response.ok) {
            throw new Error(payload.error || `Yêu cầu thất bại (${response.status}).`);
        }
        return payload;
    };
})();
