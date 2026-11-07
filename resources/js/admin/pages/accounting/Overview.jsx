import { Link } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import useFetch from '../../hooks/useFetch';
import { dateOnly, money } from '../../lib/format';
import { Button, Card, ErrorNote, PageHeader, Skeleton, cx } from '../../ui';
import { GroupedBars } from '../../ui/charts';
import Icon from '../../ui/Icon';

const MONTH = new Intl.DateTimeFormat('en', { month: 'short' });
const MONTH_YEAR = new Intl.DateTimeFormat('en', { month: 'long', year: 'numeric' });

function Stat({ to, icon, label, value, note, alert }) {
    return (
        <Link to={to} className={cx('a-stat', alert && 'is-alert')}>
            <div className="a-stat-top">
                <span>{label}</span>
                <span className="a-stat-icon">
                    <Icon name={icon} />
                </span>
            </div>
            <strong>{value}</strong>
            {note && <span className="a-stat-note">{note}</span>}
        </Link>
    );
}

function Check({ ok, label, detail }) {
    return (
        <li className={cx('a-check-item', ok ? 'ok' : 'bad')}>
            <span className="a-check-icon">
                <Icon name={ok ? 'check' : 'alert'} />
            </span>
            <span>
                <b>{label}</b>
                <small>{detail}</small>
            </span>
        </li>
    );
}

export default function AccountingOverview() {
    const { can } = useAuth();
    const { data, error, reload } = useFetch('/accounting/overview');

    if (error) return <ErrorNote error={error} onRetry={reload} />;
    if (!data) {
        return (
            <>
                <Skeleton height={60} />
                <div className="a-stats">{[1, 2, 3, 4].map((i) => <Skeleton key={i} height={120} />)}</div>
                <Skeleton height={300} />
            </>
        );
    }

    const { kpis, base_currency: base, fiscal_year: fy, checks, counts } = data;
    const m = (v) => money(v, base);
    const months = data.months.map((row) => {
        const d = new Date(`${row.month}-01T00:00:00`);
        return { ...row, label: MONTH.format(d), title: MONTH_YEAR.format(d) };
    });
    const hasActivity = months.some((r) => r.income || r.expenses);

    return (
        <>
            <PageHeader
                title="Accounting"
                subtitle={`${fy.label} (${dateOnly(fy.start)} – ${dateOnly(fy.end)}) · books kept in ${base}`}
                actions={
                    <>
                        <Button variant="secondary" icon="scale" to="/accounting/reports">
                            Reports
                        </Button>
                        {can('accounting.manage') && (
                            <Button icon="plus" to="/accounting/expenses?new=1">
                                Add a bill
                            </Button>
                        )}
                    </>
                }
            />

            <div className="a-stats money">
                <Stat to="/accounting/accounts" icon="wallet" label="Money in the bank" value={m(kpis.cash)} note="Bank and cash together" />
                <Stat to="/accounting/reports?r=ar" icon="file" label="Clients owe you" value={m(kpis.receivable)} note={counts.overdue_invoices ? `${counts.overdue_invoices} invoice(s) are late` : 'Nothing is late'} alert={counts.overdue_invoices > 0} />
                <Stat to="/accounting/reports?r=ap" icon="receipt" label="You owe" value={m(kpis.payable)} note={`${counts.unpaid_expenses} bill(s) not paid yet`} />
                <Stat to="/accounting/reports?r=pl" icon="trend" label={`Profit this year (${fy.label})`} value={m(kpis.profit)} note={`${m(kpis.income)} came in · ${m(kpis.expenses)} went out`} alert={kpis.profit < 0} />
            </div>

            <div className="a-grid-main a-align-start">
                <Card title="Money in vs money out" subtitle={`By month, ${fy.label}`}>
                    {hasActivity ? (
                        <GroupedBars
                            rows={months}
                            label={`Income and expenses per month, ${fy.label}, in ${base}`}
                            format={m}
                            series={[
                                { key: 'income', label: 'Income', color: 'var(--a-series)' },
                                { key: 'expenses', label: 'Expenses', color: 'var(--a-series-2)' },
                            ]}
                        />
                    ) : (
                        <p className="a-muted a-bar-empty">Nothing posted this financial year yet. Sent invoices, payments and expenses appear here automatically.</p>
                    )}
                </Card>
                <div className="a-stack">
                    <Card title="Is everything correct?">
                        <ul className="a-checks">
                            <Check ok={checks.trial_balance} label="Totals match" detail={checks.trial_balance ? 'Every entry has both sides' : 'Something is missing a matching entry'} />
                            <Check ok={checks.balance_sheet} label="Balances add up" detail={checks.balance_sheet ? 'What we own equals what we owe plus our own money' : 'Something does not add up. Ask your accountant'} />
                            <Check ok={!checks.missing_rates} label="Currency rates" detail={checks.missing_rates ? `${checks.missing_rates} foreign invoice(s) need an exchange rate` : 'All foreign invoices have a rate'} />
                            <Check ok label="Closed months" detail={data.lock_date ? `Locked up to ${dateOnly(data.lock_date)}` : 'Nothing is locked'} />
                        </ul>
                    </Card>
                    <Card title="Bank & cash" padded={false}>
                        <ul className="a-balances">
                            {data.money_accounts.map((a) => (
                                <li key={a.id}>
                                    <Link to={`/accounting/accounts/${a.id}`}>
                                        <span>
                                            <b>{a.name}</b>
                                            <small>{a.code}</small>
                                        </span>
                                        <span className="a-num">{m(a.balance)}</span>
                                    </Link>
                                </li>
                            ))}
                            <li>
                                <Link to="/accounting/reports?r=bs">
                                    <span>
                                        <b>Sales tax to pay</b>
                                        <small>Collected on invoices, owed to the tax authority</small>
                                    </span>
                                    <span className="a-num">{m(kpis.tax_payable)}</span>
                                </Link>
                            </li>
                        </ul>
                    </Card>
                </div>
            </div>
        </>
    );
}
