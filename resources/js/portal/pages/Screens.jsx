import { useState } from 'react';
import useFetch from '../hooks/useFetch';
import { api, fileUrl } from '../lib/api';
import { useAuth } from '../auth';
import { dateOnly, greeting, isoDate, money } from '../../admin/lib/format';
import { Badge, Button, Card, EmptyState, ErrorNote, Input, Modal, PageHeader, Select, Skeleton, Table, cx } from '../../admin/ui';
import { useConfirm, useToast } from '../../admin/ui/feedback';

const clock = (iso) => (iso ? new Date(iso).toLocaleTimeString('en', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Karachi' }) : '—');

/** Today: check in and out, the shift, leave left, and anything waiting for them. */
export function Home() {
    const { employee } = useAuth();
    const toast = useToast();
    const { data, error, reload } = useFetch('/home');
    const [busy, setBusy] = useState(false);

    const punch = async (action) => {
        setBusy(true);
        try {
            const res = await api.post(`/attendance/${action}`);
            toast(res.message);
            reload();
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            setBusy(false);
        }
    };

    if (error) return <ErrorNote error={error} onRetry={reload} />;
    if (!data) return <Skeleton height={420} />;
    const checkedOut = data.record?.check_out_at;

    return (
        <>
            <PageHeader title={`${greeting()}, ${employee.name.split(' ')[0]}`} subtitle={`${dateOnly(data.date)} · your shift is ${data.shift.start}–${data.shift.end}`} />

            <div className="p-hero">
                <div>
                    <span className="p-hero-label">Today</span>
                    {!data.record ? (
                        <strong>Not checked in yet</strong>
                    ) : checkedOut ? (
                        <strong>Done for today</strong>
                    ) : (
                        <strong>On shift since {clock(data.record.check_in_at)}</strong>
                    )}
                    <small>
                        {data.record?.late_minutes ? `${data.record.late_minutes} minutes late today.` : data.record ? 'On time.' : 'Check in when you start your shift.'}
                    </small>
                </div>
                {data.can_check_in && (
                    <Button size="lg" loading={busy} onClick={() => punch('check-in')} className="p-punch">
                        Check in
                    </Button>
                )}
                {data.can_check_out && (
                    <Button size="lg" variant="secondary" loading={busy} onClick={() => punch('check-out')} className="p-punch">
                        Check out
                    </Button>
                )}
            </div>

            <div className="a-grid-split">
                <Card title="Leave left this year">
                    <ul className="p-balances">
                        {employee.leave_balances.map((b) => (
                            <li key={b.type}>
                                <span>{b.label}</span>
                                <b>{b.remaining === null ? 'As needed' : `${b.remaining} day(s)`}</b>
                            </li>
                        ))}
                    </ul>
                </Card>
                <Card title="Coming up">
                    {data.upcoming_leave.length === 0 && <p className="a-muted">No leave planned. Ask in the Leave tab.</p>}
                    <ul className="p-list">
                        {data.upcoming_leave.map((l) => (
                            <li key={l.id}>
                                <span>
                                    <b>{l.type_label}</b>
                                    <small>
                                        {dateOnly(l.start_date)} – {dateOnly(l.end_date)}
                                    </small>
                                </span>
                                <Badge tone={l.status === 'approved' ? 'green' : 'amber'} dot>
                                    {l.status}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                    {data.unread_reviews > 0 && (
                        <p className="p-note">
                            You have {data.unread_reviews} new review{data.unread_reviews > 1 ? 's' : ''} to read in <b>Reviews</b>.
                        </p>
                    )}
                </Card>
            </div>
        </>
    );
}

/** Attendance for one month: days in, late days, hours, and each day's times. */
export function Attendance() {
    const [month, setMonth] = useState(isoDate().slice(0, 7));
    const { data, error, loading, reload } = useFetch('/attendance', { month });

    return (
        <>
            <PageHeader title="My attendance" subtitle="Your check-ins and check-outs. Late means after the grace period on your shift." />
            <div className="a-toolbar">
                <Input label="Month" type="month" value={month} onChange={(e) => setMonth(e.target.value)} />
            </div>
            <ErrorNote error={error} onRetry={reload} />
            {data && (
                <div className="a-stats">
                    <div className="a-stat">
                        <span className="a-stat-top">Days in</span>
                        <strong>{data.summary.days_present}</strong>
                    </div>
                    <div className={cx('a-stat', data.summary.late_days > 0 && 'is-alert')}>
                        <span className="a-stat-top">Late days</span>
                        <strong>{data.summary.late_days}</strong>
                    </div>
                    <div className="a-stat">
                        <span className="a-stat-top">Hours</span>
                        <strong>{data.summary.hours}</strong>
                    </div>
                </div>
            )}
            <Card padded={false}>
                <Table
                    rows={data?.data}
                    loading={loading}
                    columns={[
                        { key: 'work_date', label: 'Date', className: 'a-nowrap', render: (r) => dateOnly(r.work_date) },
                        { key: 'in', label: 'In', render: (r) => clock(r.check_in_at) },
                        { key: 'out', label: 'Out', render: (r) => clock(r.check_out_at) },
                        { key: 'hours', label: 'Hours', className: 'a-num', render: (r) => (r.hours ?? '—') },
                        { key: 'late', label: 'Late', className: 'a-num', render: (r) => (r.late_minutes ? <Badge tone="amber">{r.late_minutes} min</Badge> : '—') },
                    ]}
                    empty={<EmptyState icon="calendar" title="Nothing this month">Check in from the Today tab on your shift.</EmptyState>}
                />
            </Card>
        </>
    );
}

const STATUS_TONE = { pending: 'amber', approved: 'green', rejected: 'neutral', cancelled: 'neutral' };

/** Balances, leave requests and the form to ask for more. */
export function Leave() {
    const { refresh } = useAuth();
    const toast = useToast();
    const confirm = useConfirm();
    const { data, error, reload } = useFetch('/leave');
    const [asking, setAsking] = useState(false);
    const [form, setForm] = useState({ type: 'annual', start_date: '', end_date: '', reason: '' });
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setErrors({});
        try {
            const res = await api.post('/leave', { ...form, end_date: form.end_date || form.start_date });
            toast(res.message);
            setAsking(false);
            setForm({ type: 'annual', start_date: '', end_date: '', reason: '' });
            reload();
            refresh();
        } catch (err) {
            setErrors(Object.keys(err.errors).length ? err.errors : { form: err.message });
        } finally {
            setBusy(false);
        }
    };

    const cancel = async (leave) => {
        if (!(await confirm({ title: 'Cancel this request?', message: `${leave.type_label}, ${dateOnly(leave.start_date)}.`, confirmLabel: 'Cancel request', danger: true }))) return;
        try {
            await api.post(`/leave/${leave.id}/cancel`);
            toast('Request cancelled.');
            reload();
            refresh();
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    if (error) return <ErrorNote error={error} onRetry={reload} />;
    if (!data) return <Skeleton height={420} />;

    return (
        <>
            <PageHeader title="Leave" subtitle="See what you have left and ask for time off. Your manager approves it here." actions={<Button icon="plus" onClick={() => setAsking(true)}>Request leave</Button>} />
            <div className="p-balance-grid">
                {data.balances.map((b) => (
                    <div key={b.type} className="p-balance">
                        <span>{b.label}</span>
                        <strong>{b.remaining === null ? '∞' : b.remaining}</strong>
                        <small>{b.remaining === null ? 'Needs approval' : `of ${b.allowance} days left`}</small>
                    </div>
                ))}
            </div>
            <Card padded={false} title="My requests">
                <Table
                    rows={data.data}
                    columns={[
                        { key: 'type', label: 'Leave', render: (l) => <span className="a-two-line"><b>{l.type_label}</b><small>{l.reason || '—'}</small></span> },
                        { key: 'dates', label: 'Dates', className: 'a-nowrap', render: (l) => `${dateOnly(l.start_date)} – ${dateOnly(l.end_date)}` },
                        { key: 'days', label: 'Days', className: 'a-num', render: (l) => l.days },
                        { key: 'status', label: 'Status', render: (l) => <Badge tone={STATUS_TONE[l.status]} dot>{l.status}</Badge> },
                        { key: 'cancel', label: '', className: 'a-actions', render: (l) => (l.status === 'pending' ? <Button variant="ghost" size="sm" onClick={() => cancel(l)}>Cancel</Button> : null) },
                    ]}
                    empty={<EmptyState icon="calendar" title="No requests yet">Use “Request leave” when you need time off.</EmptyState>}
                />
            </Card>

            <Modal
                open={asking}
                onClose={() => setAsking(false)}
                title="Request leave"
                description="Weekends and public holidays are counted in the days, for now."
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setAsking(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" form="leave-form" loading={busy} icon="send">
                            Send request
                        </Button>
                    </>
                }
            >
                <form id="leave-form" className="a-form" onSubmit={submit} noValidate>
                    {errors.form && <p className="a-alert">{errors.form}</p>}
                    <Select label="Type" value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} options={data.types} error={errors.type} />
                    <div className="a-form-grid">
                        <Input label="From" type="date" required min={isoDate()} value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} error={errors.start_date} />
                        <Input label="To" type="date" min={form.start_date || isoDate()} value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} error={errors.end_date} />
                    </div>
                    <Input as="textarea" rows={3} label="Reason (optional)" maxLength={1000} value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} />
                </form>
            </Modal>
        </>
    );
}

/** Posted salary slips, downloadable as PDF. */
export function Payslips() {
    const { data, error, reload } = useFetch('/slips');

    if (error) return <ErrorNote error={error} onRetry={reload} />;
    if (!data) return <Skeleton height={300} />;

    return (
        <>
            <PageHeader title="Salary slips" subtitle="Each month once payroll is posted. Download any slip as a PDF." />
            {data.data.length === 0 ? (
                <EmptyState icon="wallet" title="No slips yet">Your first slip appears after payroll is posted for your month.</EmptyState>
            ) : (
                <div className="p-slips">
                    {data.data.map((s) => (
                        <article key={s.id} className="p-slip">
                            <span className="p-slip-period">{s.period}</span>
                            <strong>{money(s.net, 'PKR')}</strong>
                            <small>
                                Gross {money(s.gross, 'PKR')} · Tax {money(s.tax, 'PKR')}
                                {s.unpaid_days > 0 ? ` · ${s.unpaid_days} unpaid day(s)` : ''}
                            </small>
                            <Button variant="secondary" icon="download" href={fileUrl(`/slips/${s.id}/pdf`)} download className="a-btn-block">
                                Download PDF
                            </Button>
                        </article>
                    ))}
                </div>
            )}
        </>
    );
}

/** Reviews from the manager. The employee can add a reply, which also marks the review as read. */
export function Reviews() {
    const { data, error, reload } = useFetch('/reviews');

    if (error) return <ErrorNote error={error} onRetry={reload} />;
    if (!data) return <Skeleton height={300} />;

    return (
        <>
            <PageHeader title="Performance reviews" subtitle="Your manager's feedback for each period. Read it, then reply if you want to." />
            {data.data.length === 0 && <EmptyState icon="spark" title="No reviews yet">Reviews from your manager appear here.</EmptyState>}
            {data.data.map((r) => (
                <ReviewCard key={r.id} review={r} onSaved={reload} />
            ))}
        </>
    );
}

function ReviewCard({ review, onSaved }) {
    const toast = useToast();
    const [reply, setReply] = useState(review.employee_comment ?? '');
    const [busy, setBusy] = useState(false);

    const save = async (e) => {
        e.preventDefault();
        setBusy(true);
        try {
            await api.post(`/reviews/${review.id}/reply`, { comment: reply });
            toast('Thanks. Your reply is saved.');
            onSaved();
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            setBusy(false);
        }
    };

    return (
        <Card title={`${review.period} · ${review.score}/5`} subtitle={`${review.score_label}${review.reviewer ? ` · from ${review.reviewer}` : ''}`} className="p-review">
            {review.strengths && (
                <section>
                    <h3>What's going well</h3>
                    <p className="a-prewrap">{review.strengths}</p>
                </section>
            )}
            {review.improvements && (
                <section>
                    <h3>To work on</h3>
                    <p className="a-prewrap">{review.improvements}</p>
                </section>
            )}
            {review.goals && (
                <section>
                    <h3>Goals</h3>
                    <p className="a-prewrap">{review.goals}</p>
                </section>
            )}
            <form className="a-form" onSubmit={save}>
                <Input as="textarea" rows={2} label="Your reply" maxLength={2000} value={reply} onChange={(e) => setReply(e.target.value)} placeholder="Anything you want your manager to know?" />
                <div className="a-form-row">
                    <span className="a-small a-muted">{review.acknowledged ? `Read on ${dateOnly(review.acknowledged_at)}` : 'Not read yet. Saving marks it as read.'}</span>
                    <Button type="submit" size="sm" loading={busy} icon="check">
                        Save reply
                    </Button>
                </div>
            </form>
        </Card>
    );
}

/** Your details and password. Salary and bank details are kept with HR. */
export function Profile() {
    const { employee } = useAuth();
    const toast = useToast();
    const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' });
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setErrors({});
        try {
            await api.put('/password', form);
            toast('Password changed.');
            setForm({ current_password: '', password: '', password_confirmation: '' });
        } catch (err) {
            setErrors(Object.keys(err.errors).length ? err.errors : { form: err.message });
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <PageHeader title="My profile" />
            <div className="a-grid-split">
                <Card title="Your details">
                    <dl className="a-dl a-dl-small">
                        <dt>Name</dt>
                        <dd>{employee.name}</dd>
                        <dt>Employee code</dt>
                        <dd>{employee.code}</dd>
                        <dt>Email</dt>
                        <dd>{employee.email}</dd>
                        <dt>Job</dt>
                        <dd>{employee.job_title || '—'}</dd>
                        <dt>Team</dt>
                        <dd>{employee.department || '—'}</dd>
                        <dt>Shift</dt>
                        <dd>{employee.shift}</dd>
                    </dl>
                    <p className="a-muted a-small a-gap-top">To change these details, ask HR.</p>
                </Card>
                <Card title="Change password">
                    <form className="a-form" onSubmit={submit} noValidate>
                        {errors.form && <p className="a-alert">{errors.form}</p>}
                        <Input label="Current password" type="password" autoComplete="current-password" required value={form.current_password} onChange={(e) => setForm({ ...form, current_password: e.target.value })} error={errors.current_password} />
                        <Input label="New password" type="password" autoComplete="new-password" required value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} error={errors.password} hint="At least 10 characters, with upper and lower case letters and numbers." />
                        <Input label="Repeat new password" type="password" autoComplete="new-password" required value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} />
                        <Button type="submit" loading={busy} icon="check">
                            Change password
                        </Button>
                    </form>
                </Card>
            </div>
        </>
    );
}

