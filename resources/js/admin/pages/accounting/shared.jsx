import { NavLink } from 'react-router-dom';
import useFetch from '../../hooks/useFetch';
import { isoDate, money } from '../../lib/format';
import { Badge, Input, Select, cx } from '../../ui';
import Icon from '../../ui/Icon';

/** Accounting settings + current financial year, shared by every accounting page. */
export function useAccounting() {
    const { data, error, reload } = useFetch('/accounting/settings');
    return {
        ready: !!data,
        error,
        reload,
        base: data?.data.base_currency ?? 'PKR',
        fyStart: data?.data.fiscal_year_start ?? 7,
        lockDate: data?.data.lock_date ?? null,
        currencies: data?.currencies ?? {},
        months: data?.months ?? {},
        baseLocked: data?.base_locked ?? false,
        settings: data?.data,
    };
}

export const currencyOptions = (currencies) => Object.entries(currencies).map(([value, label]) => ({ value, label: `${value} — ${label}` }));

/** The financial year containing `date` (start month 1–12), e.g. { start: '2026-07-01', end: '2027-06-30', label: 'FY 2026–27' }. */
export function fiscalYear(startMonth, date = new Date()) {
    const y = date.getMonth() + 1 >= startMonth ? date.getFullYear() : date.getFullYear() - 1;
    return {
        start: isoDate(new Date(y, startMonth - 1, 1)),
        end: isoDate(new Date(y + 1, startMonth - 1, 0)),
        label: startMonth === 1 ? `FY ${y}` : `FY ${y}–${String(y + 1).slice(-2)}`,
    };
}

/** Date-range presets built around the financial year. */
export function periodPresets(startMonth) {
    const now = new Date();
    const today = isoDate(now);
    const fy = fiscalYear(startMonth, now);
    const last = fiscalYear(startMonth, new Date(now.getFullYear() - 1, now.getMonth(), now.getDate()));
    const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
    // Quarters of the financial year (Q1 = first three months).
    const offset = (now.getMonth() - (startMonth - 1) + 12) % 12;
    const qStart = new Date(now.getFullYear(), now.getMonth() - (offset % 3), 1);
    return [
        { key: 'fy', label: `${fy.label} to date`, from: fy.start, to: today },
        { key: 'last-fy', label: last.label, from: last.start, to: last.end },
        { key: 'quarter', label: `This quarter (Q${Math.floor(offset / 3) + 1})`, from: isoDate(qStart), to: today },
        { key: 'month', label: 'This month', from: isoDate(monthStart), to: today },
        { key: 'last-month', label: 'Last month', from: isoDate(new Date(now.getFullYear(), now.getMonth() - 1, 1)), to: isoDate(new Date(now.getFullYear(), now.getMonth(), 0)) },
        { key: 'custom', label: 'Custom…' },
    ];
}

/** Preset select plus from/to dates. `value`: { preset, from, to }. */
export function PeriodPicker({ startMonth, value, onChange }) {
    const presets = periodPresets(startMonth);
    const pick = (key) => {
        const p = presets.find((x) => x.key === key);
        onChange(p.from ? { preset: key, from: p.from, to: p.to } : { ...value, preset: key });
    };
    return (
        <>
            <Select label="Period" value={value.preset} onChange={(e) => pick(e.target.value)} options={presets.map((p) => ({ value: p.key, label: p.label }))} />
            <Input label="From" type="date" value={value.from} max={value.to} onChange={(e) => onChange({ ...value, preset: 'custom', from: e.target.value })} />
            <Input label="To" type="date" value={value.to} min={value.from} onChange={(e) => onChange({ ...value, preset: 'custom', to: e.target.value })} />
        </>
    );
}

export const defaultPeriod = (startMonth) => {
    const p = periodPresets(startMonth)[0];
    return { preset: p.key, from: p.from, to: p.to };
};

/** Base-currency amount; negatives keep their minus sign. */
export const Amount = ({ value, currency, strong }) => {
    const text = money(value, currency);
    return strong ? <b>{text}</b> : text;
};

/** Spreadsheet-safe CSV download (cells starting with = + - @ are neutralised, except plain numbers). */
export function downloadCsv(filename, rows) {
    const cell = (v) => {
        let s = v == null ? '' : String(v);
        if (/^[=+\-@\t\r]/.test(s) && !/^-?\d+(\.\d+)?$/.test(s)) s = `'${s}`;
        return /[",\n\r]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
    };
    const csv = '﻿' + rows.map((r) => r.map(cell).join(',')).join('\r\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const a = Object.assign(document.createElement('a'), { href: url, download: filename });
    document.body.append(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

const EXPENSE_TONE = { paid: 'green', unpaid: 'amber', overdue: 'accent' };
export const ExpenseStatus = ({ status }) => (
    <Badge tone={EXPENSE_TONE[status]} dot>
        {status[0].toUpperCase() + status.slice(1)}
    </Badge>
);

export const TYPE_LABELS = { asset: 'Asset', liability: 'Liability', equity: 'Equity', income: 'Income', expense: 'Expense' };

/** Segmented tabs driven by links (keeps the URL shareable). */
export function Tabs({ items, label }) {
    return (
        <nav className="a-tabs" aria-label={label}>
            {items.map((t) => (
                <NavLink key={t.to} to={t.to} end className={({ isActive }) => cx('a-tab', (t.active ?? isActive) && 'active')}>
                    {t.label}
                </NavLink>
            ))}
        </nav>
    );
}

/** Banner when the books are closed up to a date. */
export function LockNote({ lockDate }) {
    if (!lockDate) return null;
    return (
        <p className="a-lock-note">
            <Icon name="lock" /> Books closed up to <b>{new Intl.DateTimeFormat('en', { dateStyle: 'medium' }).format(new Date(`${lockDate}T00:00:00`))}</b> — entries on or before that date can&apos;t change.
        </p>
    );
}
