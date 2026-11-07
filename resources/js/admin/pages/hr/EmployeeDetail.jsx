import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import useFetch from '../../hooks/useFetch';
import { api, fileUrl } from '../../lib/api';
import { dateOnly, dateTime, money } from '../../lib/format';
import { Badge, Button, Card, ErrorNote, PageHeader, Skeleton, Table } from '../../ui';
import { useConfirm, useToast } from '../../ui/feedback';
import { EmployeeModal } from './Employees';
import { ReviewModal } from './Reviews';

const hours = (r) => (r.hours == null ? '—' : `${r.hours} h`);

export default function EmployeeDetail() {
    const { id } = useParams();
    const { can } = useAuth();
    const toast = useToast();
    const confirm = useConfirm();
    const { data, error, reload } = useFetch(`/hr/employees/${id}`);
    const [editing, setEditing] = useState(false);
    const [reviewing, setReviewing] = useState(false);

    if (error) return <ErrorNote error={error} />;
    if (!data) return <Skeleton height={520} />;
    const e = data.data;

    const invite = async () => {
        if (!(await confirm({ title: `Invite ${e.name}?`, message: `They get an email to ${e.email} to set a password for the employee portal.`, confirmLabel: 'Send invitation' }))) return;
        try {
            const res = await api.post(`/hr/employees/${e.id}/invite`);
            toast(res.message);
            reload();
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    return (
        <>
            <PageHeader
                back={{ to: '/people', label: 'Employees' }}
                title={
                    <span className="a-title-status">
                        {e.name} {e.status === 'active' ? <Badge tone="green" dot>Active</Badge> : <Badge dot>Has left</Badge>}
                    </span>
                }
                subtitle={`${e.code} · ${e.job_title || 'No title'}${e.department ? ` · ${e.department}` : ''}`}
                actions={
                    <>
                        {can('hr.manage') && e.status === 'active' && (
                            <Button variant="secondary" icon="send" onClick={invite}>
                                {e.portal_invited ? 'Resend portal invite' : 'Invite to portal'}
                            </Button>
                        )}
                        {can('hr.manage') && (
                            <Button variant="secondary" icon="edit" onClick={() => setEditing(true)}>
                                Edit
                            </Button>
                        )}
                        {can('hr.manage') && (
                            <Button icon="edit" onClick={() => setReviewing(true)}>
                                Write review
                            </Button>
                        )}
                    </>
                }
            />

            <div className="a-grid-detail">
                <div className="a-stack">
                    <Card title="Attendance" subtitle="Last 31 records">
                        <Table
                            rows={e.attendance}
                            columns={[
                                { key: 'work_date', label: 'Date', className: 'a-nowrap', render: (r) => dateOnly(r.work_date) },
                                { key: 'in', label: 'In', render: (r) => (r.check_in_at ? dateTime(r.check_in_at).split(', ').pop() : '—') },
                                { key: 'out', label: 'Out', className: 'a-hide-sm', render: (r) => (r.check_out_at ? dateTime(r.check_out_at).split(', ').pop() : '—') },
                                { key: 'hours', label: 'Hours', className: 'a-num', render: hours },
                                { key: 'late', label: 'Late', className: 'a-num', render: (r) => (r.late_minutes ? <Badge tone="amber">{r.late_minutes} min</Badge> : '—') },
                            ]}
                            empty={<p className="a-muted a-pad">No attendance recorded yet.</p>}
                        />
                    </Card>

                    <Card title="Leave" padded={false}>
                        <Table
                            rows={e.leave}
                            columns={[
                                { key: 'type', label: 'Type', render: (l) => l.type_label },
                                { key: 'dates', label: 'Dates', className: 'a-nowrap', render: (l) => `${dateOnly(l.start_date)} – ${dateOnly(l.end_date)}` },
                                { key: 'days', label: 'Days', className: 'a-num', render: (l) => l.days },
                                { key: 'status', label: 'Status', render: (l) => <Badge tone={l.status === 'approved' ? 'green' : l.status === 'pending' ? 'amber' : 'neutral'} dot>{l.status}</Badge> },
                            ]}
                            empty={<p className="a-muted a-pad">No leave requests.</p>}
                        />
                    </Card>

                    <Card title="Performance reviews">
                        {e.reviews.length === 0 && <p className="a-muted">No reviews yet.</p>}
                        <ul className="a-timeline full">
                            {e.reviews.map((r) => (
                                <li key={r.id}>
                                    <span className="a-timeline-dot" />
                                    <div>
                                        <p>
                                            {r.period} · {r.score}/5 · {r.score_label}
                                        </p>
                                        {r.strengths && <small className="a-prewrap">Strengths: {r.strengths}</small>}
                                        {r.improvements && <small className="a-prewrap">To improve: {r.improvements}</small>}
                                        {r.employee_comment && <small className="a-prewrap">{e.name.split(' ')[0]} said: “{r.employee_comment}”</small>}
                                        <small className="a-muted">{r.acknowledged ? 'Read by the employee' : 'Not read yet'}</small>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>

                <div className="a-stack">
                    <Card title="Profile">
                        <dl className="a-dl a-dl-small">
                            <dt>Email</dt>
                            <dd>
                                <a className="a-link" href={`mailto:${e.email}`}>
                                    {e.email}
                                </a>
                            </dd>
                            <dt>Phone</dt>
                            <dd>{e.phone || '—'}</dd>
                            <dt>Department</dt>
                            <dd>{e.department || '—'}</dd>
                            <dt>Job title</dt>
                            <dd>{e.job_title || '—'}</dd>
                            <dt>Joined</dt>
                            <dd>{dateOnly(e.joined_on)}</dd>
                            <dt>Shift</dt>
                            <dd>
                                {e.shift_start}–{e.shift_end}
                            </dd>
                            <dt>Portal</dt>
                            <dd>{e.portal_active ? 'Signed up' : e.portal_invited ? 'Invited, not yet signed up' : 'No access yet'}</dd>
                            {e.basic_salary !== undefined && (
                                <>
                                    <dt>Basic salary</dt>
                                    <dd>{money(e.basic_salary, 'PKR')}</dd>
                                    <dt>Allowances</dt>
                                    <dd>
                                        {e.allowances.length === 0 && '—'}
                                        {e.allowances.map((a) => (
                                            <span key={a.allowance_type_id} className="a-block">
                                                {a.name}: {money(a.amount, 'PKR')}
                                            </span>
                                        ))}
                                    </dd>
                                    <dt>Bank</dt>
                                    <dd>{e.bank_name ? `${e.bank_name} · ${e.bank_account || ''}` : '—'}</dd>
                                </>
                            )}
                        </dl>
                    </Card>

                    <Card title="Leave left this year">
                        <ul className="a-balances-list">
                            {e.leave_balances.map((b) => (
                                <li key={b.type}>
                                    <span>{b.label}</span>
                                    <b>{b.remaining === null ? 'As needed' : `${b.remaining} day(s)`}</b>
                                </li>
                            ))}
                        </ul>
                    </Card>

                    {can('payroll.view') && (
                        <Card title="Salary slips" padded={false}>
                            <Table
                                rows={e.slips}
                                columns={[
                                    { key: 'period', label: 'Month', render: (s) => s.period },
                                    { key: 'net', label: 'Net pay', className: 'a-num', render: (s) => money(s.net, 'PKR') },
                                    { key: 'pdf', label: '', className: 'a-actions', render: (s) => <Button variant="ghost" size="sm" icon="download" href={fileUrl(`/payroll/slips/${s.id}/pdf`)} download aria-label={`Download slip ${s.period}`} /> },
                                ]}
                                empty={<p className="a-muted a-pad">No salary slips yet.</p>}
                            />
                        </Card>
                    )}
                </div>
            </div>

            <EmployeeModal open={editing} employee={e} onClose={() => setEditing(false)} onSaved={() => { setEditing(false); reload(); }} />
            <ReviewModal open={reviewing} employee={e} onClose={() => setReviewing(false)} onSaved={() => { setReviewing(false); reload(); }} />
        </>
    );
}
