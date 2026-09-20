/**
 * api.js — fetch wrapper that auto-attaches the JWT Bearer token.
 * Requires auth.js to be loaded first.
 */

const Api = (() => {
    // From frontend/admin/ the API folder is two levels up.
    const BASE = '../../api';

    function headers(extra = {}) {
        const token = Auth.getToken();
        const h = { 'X-Requested-With': 'XMLHttpRequest', ...extra };
        if (token) h['Authorization'] = `Bearer ${token}`;
        return h;
    }

    async function handleResponse(res) {
        if (res.status === 401) {
            Auth.clear();
            window.location.href = 'login.html';
            throw new Error('Unauthorized');
        }
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch {
            throw new Error('Invalid JSON response: ' + text.substring(0, 200));
        }
    }

    return {
        /**
         * GET request.
         * @param {string} endpoint  e.g. 'teacher_api.php?action=list'
         */
        async get(endpoint) {
            const res = await fetch(`${BASE}/${endpoint}`, {
                method: 'GET',
                headers: headers()
            });
            return handleResponse(res);
        },

        /**
         * POST with JSON body.
         * @param {string} endpoint  e.g. 'teacher_api.php?action=update'
         * @param {object} data
         */
        async post(endpoint, data = {}) {
            const res = await fetch(`${BASE}/${endpoint}`, {
                method: 'POST',
                headers: headers({ 'Content-Type': 'application/json' }),
                body: JSON.stringify(data)
            });
            return handleResponse(res);
        },

        /**
         * POST a FormData object (file uploads, multipart).
         * @param {string}   endpoint
         * @param {FormData} formData
         */
        async postForm(endpoint, formData) {
            const res = await fetch(`${BASE}/${endpoint}`, {
                method: 'POST',
                headers: headers(),          // no Content-Type — browser sets boundary
                body: formData
            });
            return handleResponse(res);
        },

        /**
         * POST form fields as application/x-www-form-urlencoded.
         */
        async postUrlEncoded(endpoint, params = {}) {
            const body = new URLSearchParams(params).toString();
            const res = await fetch(`${BASE}/${endpoint}`, {
                method: 'POST',
                headers: headers({ 'Content-Type': 'application/x-www-form-urlencoded' }),
                body
            });
            return handleResponse(res);
        },

        /**
         * Download a file from a URL (e.g. backup download).
         * Creates a temporary anchor and clicks it.
         */
        downloadUrl(endpoint, filename = 'download') {
            const token = Auth.getToken();
            const url   = `${BASE}/${endpoint}`;
            // Use fetch to stream the file with auth header
            fetch(url, { headers: headers() })
                .then(res => res.blob())
                .then(blob => {
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(a.href);
                });
        }
    };
})();
