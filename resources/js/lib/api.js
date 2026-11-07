export class ApiError extends Error {
    constructor(status, data) {
        super(data?.message || `Request failed (${status})`);
        this.status = status;
        this.data = data;
    }
}

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

/** POST JSON or FormData to a same-origin Laravel endpoint with the CSRF token attached. */
export async function post(url, body) {
    const isForm = body instanceof FormData;
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...(isForm ? {} : { 'Content-Type': 'application/json' }),
        },
        body: isForm ? body : JSON.stringify(body),
    });

    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new ApiError(response.status, data);
    return data;
}

/** Laravel returns { field: [messages] } and "field.0" for array items; keep the first message per field. */
export function fieldErrors(error) {
    if (!(error instanceof ApiError) || error.status !== 422) return {};
    return Object.fromEntries(
        Object.entries(error.data?.errors ?? {}).map(([key, messages]) => [key.split('.')[0], messages[0]]),
    );
}

export function alertMessage(error) {
    if (!(error instanceof ApiError)) return 'We could not reach the server. Check your connection and try again.';
    switch (error.status) {
        case 422:
            return null;
        case 413:
            return 'That file is too large. Please attach a file under 10 MB.';
        case 419:
            return 'Your session expired. Please refresh the page and try again.';
        case 429:
            return 'Too many attempts. Please wait a minute and try again.';
        default:
            return 'Something went wrong on our side. Please try again, or email us directly.';
    }
}
