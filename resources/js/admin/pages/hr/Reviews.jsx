import { useState } from 'react';
import useFetch from '../../hooks/useFetch';
import { api } from '../../lib/api';
import { Avatar, Badge, Button, Card, EmptyState, ErrorNote, Input, Modal, PageHeader, Select, Table, Skeleton } from '../../ui';
import { useToast } from '../../ui/feedback';

const SCORES = [1, 2, 3, 4, 5].map((n) => ({ value: String(n), label: `${n} — ${['Needs improvement', 'Below expectations', 'Meets expectations', 'Exceeds expectations', 'Outstanding'][n - 1]}` }));

/** Write a review. Writing for the same period again replaces the earlier version and asks the employee to read it again. */
export function ReviewModal({ open, employee, onClose, onSaved }) {
    const toast = useToast();
    const [form, setForm] = useState({ period: '', score: '3', strengths: '', improvements: '', goals: '' });
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const [openedFor, setOpenedFor] = useState(null);
    const key = open ? employee?.id : null;
    if (key !== openedFor) {
        setOpenedFor(key);
        setErrors({});
        const now = new Date();
        const quarter = Math.floor(now.getMonth() / 3) + 1;
        setForm({ period: `${now.getFullYear()}-Q${quarter}`, score: '3', strengths: '', improvements: '', goals: '' });
    }
    const bind = (k) => ({ value: form[k], onChange: (e) => setForm({ ...form, [k]: e.target.value }), error: errors[k] });

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setErrors({});
        try {
            await api.post('/hr/reviews', { employee_id: employee.id, ...form, score: Number(form.score) });
            toast(`Review saved for ${employee.name}.`);
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
            size="lg"
            title={employee ? `Review for ${employee.name}` : ''}
            description="Shared with the employee in the portal, who can reply."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="review-form" loading={busy} icon="check">
                        Save review
                    </Button>
                </>
            }
        >
            <form id="review-form" className="a-form" onSubmit={submit} noValidate>
                {errors.form && <p className="a-alert">{errors.form}</p>}
                <div className="a-form-grid">
                    <Input label="Period" required {...bind('period')} hint="e.g. 2026-Q4 or 2026-03" />
                    <Select label="Overall score" options={SCORES} {...bind('score')} />
                </div>
                <Input as="textarea" rows={3} label="Strengths" maxLength={3000} {...bind('strengths')} />
                <Input as="textarea" rows={3} label="What to improve" maxLength={3000} {...bind('improvements')} />
                <Input as="textarea" rows={3} label="Goals for next period" maxLength={3000} {...bind('goals')} />
            </form>
        </Modal>
    );
}

/** Reviews written across the team, newest first. */
export default function Reviews() {
    const [employeeId, setEmployeeId] = useState('');
    const { data, error, loading, reload } = useFetch('/hr/reviews', { employee_id: employeeId });
    const people = useFetch('/hr/employees', { status: 'active' });

    return (
        <>
            <PageHeader title="Performance reviews" subtitle="Scores and feedback for each period. Open a person to write a new review." />
            <div className="a-toolbar">
                <Select
                    aria-label="Filter by person"
                    value={employeeId}
                    onChange={(e) => setEmployeeId(e.target.value)}
                    options={[{ value: '', label: 'Everyone' }, ...(people.data?.data ?? []).map((p) => ({ value: String(p.id), label: `${p.name} (${p.code})` }))]}
                />
            </div>
            <ErrorNote error={error} onRetry={reload} />
            <Card padded={false}>
                {!data ? (
                    <Skeleton height={300} />
                ) : (
                    <Table
                        rows={data.data}
                        loading={loading}
                        columns={[
                            { key: 'employee', label: 'Employee', render: (r) => <span className="a-cell-user"><Avatar name={r.employee} size={32} /><span><b>{r.employee}</b><small>{r.employee_code}</small></span></span> },
                            { key: 'period', label: 'Period', className: 'a-nowrap', render: (r) => r.period },
                            { key: 'score', label: 'Score', className: 'a-num', render: (r) => <b>{r.score}/5</b> },
                            { key: 'label', label: 'Rating', className: 'a-hide-sm', render: (r) => r.score_label },
                            { key: 'read', label: 'Employee', render: (r) => (r.acknowledged ? <Badge tone="green" dot>Read</Badge> : <Badge tone="amber" dot>Not read</Badge>) },
                        ]}
                        empty={<EmptyState icon="spark" title="No reviews yet">Open a person in Employees and choose “Write review”.</EmptyState>}
                    />
                )}
            </Card>
        </>
    );
}
