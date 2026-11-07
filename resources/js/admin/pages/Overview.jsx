import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import useFetch from '../hooks/useFetch';
import { compact, greeting, money, statusLabel, timeAgo } from '../lib/format';
import { Avatar, Button, Card, ErrorNote, PageHeader, Skeleton, StatusBadge, Table } from '../ui';
import { AreaChart, BarList } from '../ui/charts';
import Icon from '../ui/Icon';

function StatTile({ label, value, icon, delta, note }) {
    return (
        <div className="a-stat">
            <div className="a-stat-top">
                <span>{label}</span>
                <span className="a-stat-icon">
                    <Icon name={icon} />
                </span>
            </div>
            <strong>{value == null ? <Skeleton height={34} width={80} /> : compact(value)}</strong>
            {delta != null ? (
                <span className={`a-delta ${delta > 0 ? 'up' : delta < 0 ? 'down' : ''}`}>
                    <Icon name="trend" />
                    {delta > 0 ? '+' : ''}
                    {delta} vs last week
                </span>
            ) : (
                <span className="a-stat-note">{note}</span>
            )}
        </div>
    );
}

/** Money tiles. Amounts are per currency and never added across currencies. */
function BillingStrip({ billing }) {
    const amount = (rows) => {
        const main = rows.find((r) => r.currency === billing.currency) ?? rows[0];
        const others = rows.filter((r) => r !== main);
        return (
            <>
                <strong>{main ? money(main.amount, main.currency) : money(0, billing.currency)}</strong>
                {others.length > 0 && <span className="a-stat-note">+ {others.map((r) => money(r.amount, r.currency)).join(' · ')}</span>}
            </>
        );
    };
    const count = (rows) => rows.reduce((n, r) => n + r.count, 0);

    return (
        <div className="a-stats money">
            <Link to="/billing/invoices?status=sent" className="a-stat">
                <div className="a-stat-top">
                    <span>Outstanding</span>
                    <span className="a-stat-icon">
                        <Icon name="file" />
                    </span>
                </div>
                {amount(billing.outstanding)}
                <span className="a-stat-note">{count(billing.outstanding)} unpaid invoice(s)</span>
            </Link>
            <Link to="/billing/invoices?status=overdue" className={`a-stat ${count(billing.overdue) ? 'is-alert' : ''}`}>
                <div className="a-stat-top">
                    <span>Overdue</span>
                    <span className="a-stat-icon">
                        <Icon name="activity" />
                    </span>
                </div>
                {amount(billing.overdue)}
                <span className="a-stat-note">{count(billing.overdue) ? `${count(billing.overdue)} need a reminder` : 'Nothing overdue'}</span>
            </Link>
            <Link to="/billing/invoices?status=paid" className="a-stat">
                <div className="a-stat-top">
                    <span>Paid this month</span>
                    <span className="a-stat-icon">
                        <Icon name="check" />
                    </span>
                </div>
                {amount(billing.paid_this_month)}
                <span className="a-stat-note">{count(billing.paid_this_month)} payment(s)</span>
            </Link>
            <Link to="/billing/quotes?status=sent" className="a-stat">
                <div className="a-stat-top">
                    <span>Open quotes</span>
                    <span className="a-stat-icon">
                        <Icon name="edit" />
                    </span>
                </div>
                <strong>{billing.open_quotes}</strong>
                <span className="a-stat-note">Awaiting the client&apos;s answer</span>
            </Link>
        </div>
    );
}

export default function Overview() {
    const { user, can } = useAuth();
    const navigate = useNavigate();
    const { data, error, reload } = useFetch('/overview');
    const s = data?.stats;

    return (
        <>
            <PageHeader
                title={`${greeting()}, ${user.name.split(' ')[0]}`}
                subtitle="Here's what's happening with ZAIRON Systems."
                actions={
                    can('enquiries.view') && (
                        <Button to="/enquiries?status=new" iconRight="arrow">
                            Review new enquiries
                        </Button>
                    )
                }
            />
            <ErrorNote error={error} onRetry={reload} />

            <div className="a-stats">
                <StatTile label="Enquiries this week" icon="inbox" value={s?.enquiries_week} delta={s ? s.enquiries_week - s.enquiries_prev_week : null} />
                <StatTile label="Awaiting reply" icon="mail" value={s?.enquiries_new} note="Enquiries with status New" />
                <StatTile label="Projects won" icon="spark" value={s?.won} note={s ? `of ${s.enquiries} enquiries in total` : ''} />
                <StatTile label="Subscribers" icon="users" value={s?.subscribers} note={s ? `+${s.subscribers_month} in the last 30 days` : ''} />
            </div>

            {data?.billing && <BillingStrip billing={data.billing} />}

            <div className="a-grid-main">
                <Card title="Enquiries" subtitle="New project enquiries per day, last 30 days">
                    {data ? <AreaChart series={data.series} label="Enquiries per day" /> : <Skeleton height={220} />}
                </Card>
                <Card title="Pipeline" subtitle="Enquiries by status">
                    {data ? (
                        <BarList
                            items={data.by_status.map((b) => ({ label: statusLabel(b.status), count: b.count, status: b.status }))}
                            marker={(item) => <span className={`a-dot status-${item.status}`} aria-hidden="true" />}
                            empty="No enquiries yet."
                        />
                    ) : (
                        <Skeleton height={180} />
                    )}
                </Card>
            </div>

            <div className="a-grid-split">
                {can('enquiries.view') && (
                    <Card
                        title="Latest enquiries"
                        padded={false}
                        actions={
                            <Button variant="ghost" size="sm" to="/enquiries" iconRight="arrow">
                                View all
                            </Button>
                        }
                    >
                        <Table
                            rows={data?.recent_enquiries}
                            loading={!data}
                            onRowClick={(row) => navigate(`/enquiries/${row.id}`)}
                            columns={[
                                {
                                    key: 'name',
                                    label: 'Client',
                                    render: (r) => (
                                        <span className="a-cell-user">
                                            <Avatar name={r.name} size={32} />
                                            <span>
                                                <b>{r.name}</b>
                                                <small>{r.company}</small>
                                            </span>
                                        </span>
                                    ),
                                },
                                { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
                                { key: 'created_at', label: 'Received', className: 'a-nowrap a-muted', render: (r) => timeAgo(r.created_at) },
                            ]}
                            empty={<p className="a-muted a-pad">No enquiries yet. They'll appear here as soon as someone uses the contact form.</p>}
                        />
                    </Card>
                )}
                <Card title="Top services" subtitle="Enquiries sent from service pages">
                    {data ? <BarList items={data.by_service} empty="No service-page enquiries yet." /> : <Skeleton height={180} />}
                </Card>
            </div>

            {can('activity.view') && (
                <Card
                    title="Recent activity"
                    actions={
                        <Button variant="ghost" size="sm" to="/activity" iconRight="arrow">
                            Full log
                        </Button>
                    }
                >
                    {data?.recent_activity?.length ? (
                        <ol className="a-timeline">
                            {data.recent_activity.map((a) => (
                                <li key={a.id}>
                                    <span className={`a-timeline-dot kind-${a.action.split('.')[0]}`} aria-hidden="true" />
                                    <div>
                                        <p>{a.description}</p>
                                        <small>
                                            {a.user?.name ?? 'System'} · {timeAgo(a.created_at)}
                                        </small>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    ) : (
                        <p className="a-muted">{data ? 'No activity yet.' : 'Loading…'}</p>
                    )}
                </Card>
            )}
        </>
    );
}
