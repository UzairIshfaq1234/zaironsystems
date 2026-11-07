const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });
const UNITS = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
];

/** "3 hours ago", "yesterday", "just now". */
export function timeAgo(iso) {
    if (!iso) return '—';
    const seconds = (new Date(iso).getTime() - Date.now()) / 1000;
    for (const [unit, size] of UNITS) {
        if (Math.abs(seconds) >= size) return rtf.format(Math.round(seconds / size), unit);
    }
    return 'just now';
}

export const dateTime = (iso) =>
    iso ? new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso)) : '—';

export const shortDate = (iso) => new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric' }).format(new Date(iso));

export const compact = (n) => new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(n);

export const initials = (name = '') =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join('') || '?';

export const STATUSES = [
    { value: 'new', label: 'New' },
    { value: 'contacted', label: 'Contacted' },
    { value: 'in_progress', label: 'In progress' },
    { value: 'won', label: 'Won' },
    { value: 'lost', label: 'Lost' },
];
export const APPLICATION_STATUSES = [
    { value: 'new', label: 'New' },
    { value: 'reviewing', label: 'Reviewing' },
    { value: 'shortlisted', label: 'Shortlisted' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'hired', label: 'Hired' },
];
export const OPENING_STATUSES = [
    { value: 'open', label: 'Open' },
    { value: 'closed', label: 'Closed' },
];
export const statusLabel = (s) => [...STATUSES, ...APPLICATION_STATUSES, ...OPENING_STATUSES].find((x) => x.value === s)?.label ?? s;

// ISO 4217 currencies without minor units. Everything else shows 2 decimals, matching how amounts are
// stored (hundredths) and the server's Billing::format, whatever the browser's locale data says (e.g. PKR).
const ZERO_DECIMAL = new Set(['BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF']);
const formatters = new Map();

/** 1250.5, 'USD' → "$1,250.50" */
export const money = (amount, currency = 'USD') => {
    if (!formatters.has(currency)) {
        const digits = ZERO_DECIMAL.has(currency) ? 0 : 2;
        formatters.set(currency, new Intl.NumberFormat('en', { style: 'currency', currency, minimumFractionDigits: digits, maximumFractionDigits: digits }));
    }
    return formatters.get(currency).format(Number(amount) || 0);
};

export const isoDate = (d = new Date()) => {
    const local = new Date(d.getTime() - d.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
};

export const addDays = (days, from = new Date()) => isoDate(new Date(from.getTime() + days * 86400000));

export const dateOnly = (iso) => (iso ? new Intl.DateTimeFormat('en', { dateStyle: 'medium' }).format(new Date(`${iso.slice(0, 10)}T00:00:00`)) : '—');

export function greeting() {
    const h = new Date().getHours();
    return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
}
