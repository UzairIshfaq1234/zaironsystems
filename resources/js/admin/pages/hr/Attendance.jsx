import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import useFetch from '../../hooks/useFetch';
import { api } from '../../lib/api';
import { dateOnly, isoDate } from '../../lib/format';
import { Badge, Button, Card, ErrorNote, Input, Modal, PageHeader, Skeleton, Table } from '../../ui';
import { useToast } from '../../ui/feedback';
import { Tabs } from '../accounting/shared';

const clock = (iso) => (iso ? new Date(iso).toLocaleTimeString('en', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Karachi' }) : '—');

/** Set or correct one employee's times for a day (for people who forgot to check in, or a missed check-out). */
function RecordModal({ open, row, date, onClose, onSaved }) {
    const toast = useToast();
    const [form, setForm] = useState({ check_in: '', check_out: '', note: '' });
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const [openedFor, setOpenedFor] = useState(null);
    const key = open ? row?.employee_id : null;
    if (key !== openedFor) {
        setOpenedFor(key);
        setErrors({});
        const start = (iso) => (iso ? new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Karachi' }) : '');
        setForm({ check_in: start(row?.record?.check_in_at), check_out: start(row?.record?.check_out_at), note: row?.record?.note ?? '' });
    }

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        try {
            await api.post('/hr/attendance', { employee_id: row.employee_id, work_date: date, ...form });
            toast(`Saved ${row.name}'s times for ${dateOnly(date)}.`);
            onSaved();
        } catch (err) {
            setErrors(Object.keys(err.errors).length ? err.errors : { form: err.message });
        } finally {
            setBusy(false);
        }
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={row ? `${row.name} · ${dateOnly(date)}` : ''}
            description={row ? `Shift ${row.shift}. Times are Pakistan time.` : undefined}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="record-form" loading={busy} icon="check">
                        Save times
                    </Button>
                </>
            }
        >
            <form id="record-form" className="a-form" onSubmit={submit} noValidate>
                {errors.form && <p className="a-alert">{errors.form}</p>}
                <div className="a-form-grid">
                    <Input label="Checked in" type="time" value={form.check_in} onChange={(e) => setForm({ ...form, check_in: e.target.value })} error={errors.check_in} />
                    <Input label="Checked out" type="time" value={form.check_out} onChange={(e) => setForm({ ...form, check_out: e.target.value })} error={errors.check_out} />
                </div>
                <Input label="Note (optional)" maxLength={255} value={form.note} onChange={(e) => setForm({ ...form, note: e.target.value })} placeholder="e.g. Forgot to check in, was on site" />
            </form>
        </Modal>
    );
}

function Board() {
    const [date, setDate] = useState(isoDate());
    const { data, error, loading, reload } = useFetch('/hr/attendance', { date });
    const [editing, setEditing] = useState(null);

    return (
        <>
            <div className="a-toolbar">
                <Input label="Day" type="date" value={date} max={isoDate()} onChange={(e) => setDate(e.target.value)} />
            </div>
            {data && (
                <div className="a-stats">
                    <div className="a-stat">
                        <span className="a-stat-top">Present</span>
                        <strong>{data.summary.present}</strong>
                    </div>
                    <div className={`a-stat${data.summary.late ? ' is-alert' : ''}`}>
                        <span className="a-stat-top">Late</span>
                        <strong>{data.summary.late}</strong>
                    </div>
                    <div className="a-stat">
                        <span className="a-stat-top">Not checked in</span>
                        <strong>{data.summary.absent}</strong>
                    </div>
                </div>
            )}
            <ErrorNote error={error} onRetry={reload} />
            <Card padded={false}>
                {!data ? (
                    <Skeleton height={320} />
                ) : (
                    <Table
                        rows={data.data}
                        loading={loading}
                        onRowClick={(r) => setEditing(r)}
                        columns={[
                            { key: 'name', label: 'Employee', render: (r) => <span className="a-two-line"><b>{r.name}</b><small>{r.code}</small></span> },
                            { key: 'shift', label: 'Shift', className: 'a-hide-sm a-muted a-nowrap', render: (r) => r.shift },
                            { key: 'in', label: 'In', className: 'a-nowrap', render: (r) => clock(r.record?.check_in_at) },
                            { key: 'out', label: 'Out', className: 'a-nowrap', render: (r) => clock(r.record?.check_out_at) },
                            { key: 'status', label: 'Status', render: (r) => (!r.record?.check_in_at ? <Badge tone="neutral" dot>Not in</Badge> : r.record.late_minutes ? <Badge tone="amber" dot>{r.record.late_minutes} min late</Badge> : <Badge tone="green" dot>On time</Badge>) },
                            { key: 'hours', label: 'Hours', className: 'a-num a-hide-sm', render: (r) => (r.record?.hours ?? '—') },
                        ]}
                        empty={<p className="a-muted a-pad">No active employees.</p>}
                    />
                )}
            </Card>
            <RecordModal open={!!editing} row={editing} date={date} onClose={() => setEditing(null)} onSaved={() => { setEditing(null); reload(); }} />
        </>
    );
}

function MonthlyReport() {
    const [month, setMonth] = useState(isoDate().slice(0, 7));
    const { data, error, loading, reload } = useFetch('/hr/attendance/report', { month });

    return (
        <>
            <div className="a-toolbar">
                <Input label="Month" type="month" value={month} onChange={(e) => setMonth(e.target.value)} />
            </div>
            <ErrorNote error={error} onRetry={reload} />
            <Card padded={false}>
                <Table
                    rows={data?.data}
                    loading={loading}
                    columns={[
                        { key: 'name', label: 'Employee', render: (r) => <span className="a-two-line"><b>{r.name}</b><small>{r.code}</small></span> },
                        { key: 'days_present', label: 'Days in', className: 'a-num' },
                        { key: 'late_days', label: 'Late days', className: 'a-num' },
                        { key: 'late_minutes', label: 'Minutes late', className: 'a-num a-hide-sm' },
                        { key: 'hours', label: 'Hours', className: 'a-num', render: (r) => `${r.hours} h` },
                    ]}
                    empty={<p className="a-muted a-pad">No attendance this month.</p>}
                />
            </Card>
        </>
    );
}

export default function Attendance() {
    const [params] = useSearchParams();
    const view = params.get('view') === 'report' ? 'report' : 'board';
    return (
        <>
            <PageHeader title="Attendance" subtitle="Who came in, when, and who was late. Employees check in themselves from the portal." />
            <Tabs
                label="Attendance views"
                items={[
                    { to: '/attendance?view=board', label: 'Today', active: view === 'board' },
                    { to: '/attendance?view=report', label: 'Monthly report', active: view === 'report' },
                ]}
            />
            {view === 'board' ? <Board /> : <MonthlyReport />}
        </>
    );
}
