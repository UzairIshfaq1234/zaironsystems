// Same-origin JSON API client with session auth and CSRF. Shared by the admin and the employee portal.
// `base` is the API prefix (e.g. /admin/api); `unauthorizedEvent` is dispatched on 401 so the app can sign out.

export class ApiError extends Error {
    constructor(status, data) {
        super(data?.message || `Request failed (${status})`);
        this.status = status;
        this.data = data;
        const entries = Object.entries(data?.errors ?? {});
        // Both forms: "items" (first error per field) and "items.2.description" (exact field).
        this.errors = {
            ...Object.fromEntries(entries.map(([k, v]) => [k.split('.')[0], v[0]])),
            ...Object.fromEntries(entries.map(([k, v]) => [k, v[0]])),
        };
    }
}

export function createApi(base, unauthorizedEvent) {
    let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const setCsrf = (token) => {
        if (token) csrf = token;
    };

    async function request(method, path, body, retried = false) {
        const multipart = body instanceof FormData;
        const response = await fetch(base + path, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                // FormData sets its own multipart boundary.
                ...(body !== undefined && !multipart && { 'Content-Type': 'application/json' }),
            },
            body: body === undefined || multipart ? body : JSON.stringify(body),
        });

        // Expired CSRF token (long-open tab): refresh it once via /me and retry.
        if (response.status === 419 && !retried) {
            const me = await fetch(`${base}/me`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (me.ok) setCsrf((await me.json()).csrf);
            return request(method, path, body, true);
        }

        if (response.status === 204) return null;
        const data = await response.json().catch(() => ({}));
        if (data?.csrf) setCsrf(data.csrf);

        if (!response.ok) {
            if (response.status === 401 && unauthorizedEvent) window.dispatchEvent(new Event(unauthorizedEvent));
            throw new ApiError(response.status, data);
        }
        return data;
    }

    const api = {
        get: (path, params) => {
            const query = params ? new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v != null)).toString() : '';
            return request('GET', query ? `${path}?${query}` : path);
        },
        post: (path, body = {}) => request('POST', path, body),
        put: (path, body = {}) => request('PUT', path, body),
        patch: (path, body = {}) => request('PATCH', path, body),
        del: (path) => request('DELETE', path),
        /** Multipart POST (file uploads). Null becomes '' (the server reads it as null), booleans 1/0; undefined is skipped. */
        upload: (path, fields) => {
            const form = new FormData();
            Object.entries(fields).forEach(([k, v]) => {
                if (v === undefined) return;
                form.append(k, v === null ? '' : typeof v === 'boolean' ? (v ? '1' : '0') : v);
            });
            return request('POST', path, form);
        },
    };

    /** Direct URL for file downloads (the session cookie authorises them). */
    const fileUrl = (path) => base + path;

    return { api, setCsrf, fileUrl };
}
