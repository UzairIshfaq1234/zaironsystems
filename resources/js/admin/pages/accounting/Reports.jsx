import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import useFetch from '../../hooks/useFetch';
import { dateOnly, isoDate, money } from '../../lib/format';
import { Badge, Button, Card, ErrorNote, Input, PageHeader, Select, Skeleton, Table, cx } from '../../ui';
import Icon from '../../ui/Icon';
import { PeriodPicker, TYPE_LABELS, Tabs, defaultPeriod, downloadCsv, fiscalYear, useAccounting } from './shared';

const REPORTS = [
    { key: 'pl', label: 'Did we make money?' },
    { key: 'bs', label: 'What we own & owe' },
    { key: 'tb', label: 'Advanced: check totals' },
    { key: 'ar', label: 'Who owes me' },
    { key: 'ap', label: 'Bills to pay' },
];

/** As-of date with quick picks: today, end of last month, end of last financial year. */
function AsOfPicker({ startMonth, value, onChange }) {
    const now = new Date();
    const lastFyEnd = isoDate(new Date(new Date(`${fiscalYear(startMonth).start}T00:00:00`).getTime() - 86400000));
    const picks = [
        { value: isoDate(now), label: 'Today' },
        { value: isoDate(new Date(now.getFullYear(), now.getMonth(), 0)), label: 'End of last month' },
        { value: lastFyEnd, label: `End of ${fiscalYear(startMonth, new Date(`${lastFyEnd}T00:00:00`)).label}` },
    ];
    const match = picks.find((p) => p.value === value)?.value ?? 'custom';
    return (
        <>
            <Select label="As of" value={match} onChange={(e) => e.target.value !== 'custom' && onChange(e.target.value)} options={[...picks, { value: 'custom', label: 'Custom date' }]} />
            <Input label="Date" type="date" value={value} max={isoDate(now)} onChange={(e) => onChange(e.target.value)} />
        </>
    );
}

function BalancedBadge({ ok, yes, no }) {
    return (
        <Badge tone={ok ? 'green' : 'accent'} dot>
            {ok ? yes : no}
        </Badge>
    );
}

/** Statement section: heading row, account rows (linking to their ledger), total row. */
function Section({ title, section, base, period, negativeIsBad }) {
    return (
        <tbody>
            <tr className="a-report-section">
                <th colSpan={2} scope="colgroup">
                    {title}
                </th>
            </tr>
            {section.rows.length === 0 && (
                <tr>
                    <td colSpan={2} className="a-muted">
                        Nothing in this period.
                    </td>
                </tr>
            )}
            {section.rows.map((r) => (
                <tr key={r.id ?? r.name}>
                    <td>
                        {r.id ? (
                            <Link className="a-report-link" to={`/accounting/accounts/${r.id}${period ? `?from=${period.from}&to=${period.to}` : ''}`}>
                                <span className="a-code">{r.code}</span> {r.name}
                            </Link>
                        ) : (
                            <i>{r.name}</i>
                        )}
                    </td>
                    <td className={cx('a-num', negativeIsBad && r.amount < 0 && 'a-negative')}>{money(r.amount, base)}</td>
                </tr>
            ))}
            <tr className="a-report-total">
                <th scope="row">Total {title.toLowerCase()}</th>
                <td className="a-num">{money(section.total, base)}</td>
            </tr>
        </tbody>
    );
}

const statementCsv = (sections) => [['Section', 'Code', 'Account', 'Amount'], ...sections.flatMap(([title, s]) => [...s.rows.map((r) => [title, r.code, r.name, r.amount]), [title, '', `Total ${title.toLowerCase()}`, s.total]])];

function ProfitAndLoss({ acc }) {
    const [period, setPeriod] = useState(() => defaultPeriod(acc.fyStart));
    const { data, error, loading } = useFetch('/accounting/reports/profit-and-loss', { from: period.from, to: period.to });
    const r = data?.data;
    return (
        <Report
            filters={<PeriodPicker startMonth={acc.fyStart} value={period} onChange={setPeriod} />}
            error={error}
            loading={loading}
            title={`Profit & loss · ${dateOnly(period.from)} – ${dateOnly(period.to)}`}
            onCsv={r && (() => downloadCsv(`profit-and-loss-${period.from}-to-${period.to}.csv`, [...statementCsv([['Income', r.income], ['Expenses', r.expenses]]), ['', '', 'Net profit', r.net_profit]]))}
        >
            {r && (
                <table className="a-table a-report">
                    <Section title="Income" section={r.income} base={acc.base} period={period} />
                    <Section title="Expenses" section={r.expenses} base={acc.base} period={period} />
                    <tfoot>
                        <tr className={cx('a-report-grand', r.net_profit < 0 && 'is-loss')}>
                            <th scope="row">{r.net_profit < 0 ? 'Net loss' : 'Net profit'}</th>
                            <td className="a-num">{money(r.net_profit, acc.base)}</td>
                        </tr>
                    </tfoot>
                </table>
            )}
        </Report>
    );
}

function BalanceSheet({ acc }) {
    const [asOf, setAsOf] = useState(isoDate());
    const { data, error, loading } = useFetch('/accounting/reports/balance-sheet', { as_of: asOf });
    const r = data?.data;
    return (
        <Report
            filters={<AsOfPicker startMonth={acc.fyStart} value={asOf} onChange={setAsOf} />}
            error={error}
            loading={loading}
            title={`Balance sheet · as of ${dateOnly(asOf)}`}
            badge={r && <BalancedBadge ok={r.balanced} yes="Assets = liabilities + equity" no="Does not balance" />}
            onCsv={r && (() => downloadCsv(`balance-sheet-${asOf}.csv`, statementCsv([['Assets', r.assets], ['Liabilities', r.liabilities], ['Equity', r.equity]])))}
        >
            {r && (
                <table className="a-table a-report">
                    <Section title="Assets" section={r.assets} base={acc.base} />
                    <Section title="Liabilities" section={r.liabilities} base={acc.base} />
                    <Section title="Equity" section={r.equity} base={acc.base} />
                    <tfoot>
                        <tr className="a-report-grand">
                            <th scope="row">Liabilities + equity</th>
                            <td className="a-num">{money(r.liabilities.total + r.equity.total, acc.base)}</td>
                        </tr>
                    </tfoot>
                </table>
            )}
        </Report>
    );
}

function TrialBalance({ acc }) {
    const navigate = useNavigate();
    const [asOf, setAsOf] = useState(isoDate());
    const { data, error, loading } = useFetch('/accounting/reports/trial-balance', { as_of: asOf });
    const r = data?.data;
    const m = (v) => (v ? money(v, acc.base) : '');
    return (
        <Report
            filters={<AsOfPicker startMonth={acc.fyStart} value={asOf} onChange={setAsOf} />}
            error={error}
            loading={loading}
            title={`Trial balance · as of ${dateOnly(asOf)}`}
            badge={r && <BalancedBadge ok={r.balanced} yes="Debits = credits" no="Out of balance" />}
            onCsv={r && (() => downloadCsv(`trial-balance-${asOf}.csv`, [['Code', 'Account', 'Type', 'Debit', 'Credit'], ...r.rows.map((x) => [x.code, x.name, TYPE_LABELS[x.type], x.debit || '', x.credit || '']), ['', 'Total', '', r.totals.debit, r.totals.credit]]))}
            note={r && `Income and expense accounts show ${r.fiscal_year.label} only; earlier years' profit is inside Retained earnings.`}
        >
            {r && (
                <div className="a-table-wrap">
                    <table className="a-table a-report">
                        <thead>
                            <tr>
                                <th scope="col">Account</th>
                                <th scope="col" className="a-hide-sm">Type</th>
                                <th scope="col" className="a-num">Debit</th>
                                <th scope="col" className="a-num">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            {r.rows.map((x) => (
                                <tr key={x.id} className="clickable" tabIndex={0} onClick={() => navigate(`/accounting/accounts/${x.id}`)} onKeyDown={(e) => e.key === 'Enter' && navigate(`/accounting/accounts/${x.id}`)}>
                                    <td>
                                        <span className="a-code">{x.code}</span> {x.name}
                                    </td>
                                    <td className="a-hide-sm a-muted">{TYPE_LABELS[x.type]}</td>
                                    <td className="a-num">{m(x.debit)}</td>
                                    <td className="a-num">{m(x.credit)}</td>
                                </tr>
                            ))}
                            {r.rows.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="a-muted">
                                        No postings yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                        <tfoot>
                            <tr className="a-report-grand">
                                <th scope="row">Total</th>
                                <td className="a-hide-sm" />
                                <td className="a-num">{money(r.totals.debit, acc.base)}</td>
                                <td className="a-num">{money(r.totals.credit, acc.base)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}
        </Report>
    );
}

function Aging({ acc, kind }) {
    const navigate = useNavigate();
    const receivable = kind === 'ar';
    const { data, error, loading } = useFetch(`/accounting/reports/${receivable ? 'receivables' : 'payables'}`);
    const r = data?.data;
    const open = (row) => navigate(receivable ? `/billing/invoices/${row.id}` : `/accounting/expenses?open=${row.id}`);
    return (
        <Report
            error={error}
            loading={loading}
            title={`${receivable ? 'Receivables' : 'Payables'} aging · today`}
            note={receivable ? 'Unpaid invoices by how long they are past their due date.' : 'Unpaid expenses by how long they are past their due date (bill date if none).'}
            onCsv={r && (() => downloadCsv(`${receivable ? 'receivables' : 'payables'}-aging-${isoDate()}.csv`, [['Number', receivable ? 'Client' : 'Vendor', 'Due', 'Days overdue', 'Bucket', 'Currency', 'Amount', `Amount (${acc.base})`], ...r.rows.map((x) => [x.number, x.party, x.due_date, x.days_overdue, r.buckets[x.bucket], x.currency, x.amount, x.base_amount])]))}
        >
            {r && (
                <>
                    <ul className="a-buckets">
                        {Object.entries(r.buckets).map(([key, label]) => (
                            <li key={key} className={cx(key !== 'current' && r.totals[key] > 0 && 'is-late')}>
                                <span>{label}</span>
                                <b>{money(r.totals[key], acc.base)}</b>
                            </li>
                        ))}
                        <li className="is-total">
                            <span>Total</span>
                            <b>{money(r.total, acc.base)}</b>
                        </li>
                    </ul>
                    <Table
                        rows={r.rows}
                        onRowClick={open}
                        columns={[
                            { key: 'number', label: 'Number', render: (x) => <span className="a-two-line"><b>{x.party}</b><small>{x.number}</small></span> },
                            { key: 'due_date', label: 'Due', className: 'a-nowrap a-muted', render: (x) => dateOnly(x.due_date) },
                            { key: 'days', label: 'Overdue', render: (x) => (x.days_overdue ? <Badge tone={x.days_overdue > 60 ? 'accent' : 'amber'}>{x.days_overdue} days</Badge> : <Badge tone="green">Not yet due</Badge>) },
                            { key: 'amount', label: 'Amount', className: 'a-num a-hide-sm', render: (x) => money(x.amount, x.currency) },
                            { key: 'base', label: acc.base, className: 'a-num', render: (x) => <b>{money(x.base_amount, acc.base)}</b> },
                        ]}
                        empty={<p className="a-muted a-pad">{receivable ? 'No unpaid invoices. Everything has been collected.' : 'No unpaid expenses.'}</p>}
                    />
                </>
            )}
        </Report>
    );
}

/** Shared frame: filters row, card with title/actions, print + CSV. */
function Report({ filters, title, badge, note, error, loading, onCsv, children }) {
    return (
        <>
            {filters && <div className="a-toolbar a-report-filters">{filters}</div>}
            <ErrorNote error={error} />
            <Card
                padded={false}
                className={cx('a-report-card', loading && 'is-loading')}
                title={
                    <span className="a-title-status">
                        {title} {badge}
                    </span>
                }
                subtitle={note}
                actions={
                    <>
                        <Button variant="ghost" size="sm" icon="printer" onClick={() => window.print()}>
                            Print
                        </Button>
                        <Button variant="ghost" size="sm" icon="download" onClick={onCsv} disabled={!onCsv}>
                            CSV
                        </Button>
                    </>
                }
            >
                {children ?? <Skeleton height={320} />}
            </Card>
        </>
    );
}

export default function Reports() {
    const acc = useAccounting();
    const [params] = useSearchParams();
    const current = REPORTS.some((r) => r.key === params.get('r')) ? params.get('r') : 'pl';

    if (acc.error) return <ErrorNote error={acc.error} onRetry={acc.reload} />;

    return (
        <>
            <PageHeader title="Financial reports" subtitle={`Built live from the journal, in ${acc.base}. Financial year starts in ${acc.months[acc.fyStart] ?? 'July'}.`} />
            <Tabs label="Reports" items={REPORTS.map((r) => ({ to: `/accounting/reports?r=${r.key}`, label: r.label, active: r.key === current }))} />
            {!acc.ready ? (
                <Skeleton height={400} />
            ) : (
                <div className="a-report-body" key={current}>
                    {current === 'pl' && <ProfitAndLoss acc={acc} />}
                    {current === 'bs' && <BalanceSheet acc={acc} />}
                    {current === 'tb' && <TrialBalance acc={acc} />}
                    {(current === 'ar' || current === 'ap') && <Aging acc={acc} kind={current} />}
                </div>
            )}
            <p className="a-print-only a-muted a-small">
                <Icon name="file" className="a-inline-icon" />
                Printed {new Intl.DateTimeFormat('en', { dateStyle: 'long', timeStyle: 'short' }).format(new Date())}
            </p>
        </>
    );
}
