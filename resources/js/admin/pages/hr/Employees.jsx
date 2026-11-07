import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import useFetch, { useDebounced } from '../../hooks/useFetch';
import { api } from '../../lib/api';
import { money } from '../../lib/format';
import { Avatar, Badge, Button, Card, EmptyState, ErrorNote, Input, Modal, PageHeader, SearchInput, Select, Table } from '../../ui';
import { useToast } from '../../ui/feedback';

const EMPTY = {
    name: '', email: '', phone: '', department_id: '', job_title_id: '', joined_on: '', basic_salary: '',
    allowances: [], shift_start: '09:00', shift_end: '18:00', bank_name: '', bank_account: '', status: 'active',
};

/** Add or edit an employee. Department and job title come from the lists; allowances are one row each. */
export function EmployeeModal({ open, employee, onClose, onSaved }) {
    const { can } = useAuth();
    const toast = useToast();
    const lists = useFetch(open ? '/hr/lists' : null);
    const [form, setForm] = useState(EMPTY);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const [openedFor, setOpenedFor] = useState(null);
    const key = open ? (employee?.id ?? 'new') : null;
    if (key !== openedFor) {
        setOpenedFor(key);
        setErrors({});
        setForm(
            employee
                ? {
                      ...EMPTY,
                      ...Object.fromEntries(['name', 'email', 'phone', 'joined_on', 'shift_start', 'shift_end', 'bank_name', 'bank_account', 'status'].map((k) => [k, employee[k] ?? ''])),
                      department_id: employee.department_id ? String(employee.department_id) : '',
                      job_title_id: employee.job_title_id ? String(employee.job_title_id) : '',
                      basic_salary: employee.basic_salary ?? '',
                      allowances: (employee.allowances ?? []).map((a) => ({ allowance_type_id: String(a.allowance_type_id), amount: a.amount })),
                  }
                : EMPTY,
        );
    }
    const bind = (k) => ({ value: form[k] ?? '', onChange: (e) => setForm({ ...form, [k]: e.target.value }), error: errors[k] });
    const showPay = can('payroll.view');
    const departments = lists.data?.departments ?? [];
    // A job title belongs to one department, so only that department's titles are offered.
    const titles = departments.find((d) => String(d.id) === String(form.department_id))?.job_titles ?? [];
    const allowanceTypes = (lists.data?.allowance_types ?? []).filter((t) => t.is_active);

    const setAllowance = (index, patch) => setForm((f) => ({ ...f, allowances: f.allowances.map((a, i) => (i === index ? { ...a, ...patch } : a)) }));
    const addAllowance = () => setForm((f) => ({ ...f, allowances: [...f.allowances, { allowance_type_id: '', amount: '' }] }));
    const removeAllowance = (index) => setForm((f) => ({ ...f, allowances: f.allowances.filter((_, i) => i !== index) }));

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setErrors({});
        const body = {
            ...form,
            department_id: form.department_id || null,
            job_title_id: form.job_title_id || null,
            allowances: form.allowances.filter((a) => a.allowance_type_id && a.amount !== '').map((a) => ({ allowance_type_id: Number(a.allowance_type_id), amount: a.amount })),
        };
        if (!showPay) delete body.basic_salary;
        try {
            const res = employee ? await api.put(`/hr/employees/${employee.id}`, body) : await api.post('/hr/employees', body);
            toast(employee ? 'Employee updated.' : `${res.data.name} added as ${res.data.code}.`);
            onSaved(res.data);
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
            title={employee ? `Edit ${employee.name}` : 'Add an employee'}
            description={employee ? employee.code : 'They get an employee code now, and a portal invitation when you send one.'}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="employee-form" loading={busy} icon="check">
                        {employee ? 'Save' : 'Add employee'}
                    </Button>
                </>
            }
        >
            <form id="employee-form" className="a-form" onSubmit={submit} noValidate>
                {errors.form && <p className="a-alert">{errors.form}</p>}
                <div className="a-form-grid">
                    <Input label="Full name" required autoFocus maxLength={120} {...bind('name')} />
                    <Input label="Work email" type="email" required maxLength={190} {...bind('email')} />
                    <Select label="Department" options={[{ value: '', label: 'None yet' }, ...departments.map((d) => ({ value: String(d.id), label: d.name }))]} value={form.department_id} error={errors.department_id} onChange={(e) => setForm({ ...form, department_id: e.target.value, job_title_id: '' })} />
                    <Select label="Job title" options={[{ value: '', label: form.department_id ? 'None yet' : 'Pick a department first' }, ...titles.map((t) => ({ value: String(t.id), label: t.name }))]} value={form.job_title_id} error={errors.job_title_id} disabled={!form.department_id} onChange={(e) => setForm({ ...form, job_title_id: e.target.value })} />
                    <Input label="Phone" maxLength={40} {...bind('phone')} />
                    <Input label="Joined on" type="date" required {...bind('joined_on')} />
                </div>
                <div className="a-form-grid">
                    <Input label="Shift starts" type="time" required {...bind('shift_start')} hint="Late check-ins count after the grace period." />
                    <Input label="Shift ends" type="time" required {...bind('shift_end')} />
                </div>

                {showPay && (
                    <fieldset className="a-fieldset">
                        <legend>Salary (PKR per month)</legend>
                        <Input label="Basic salary" type="number" min="0" step="0.01" inputMode="decimal" required {...bind('basic_salary')} />
                        <div className="a-allowances">
                            <span className="a-small a-muted">Allowances, one line each</span>
                            {form.allowances.map((a, i) => (
                                <div className="a-allowance-row" key={i}>
                                    <Select
                                        aria-label={`Allowance ${i + 1}`}
                                        value={a.allowance_type_id}
                                        onChange={(e) => setAllowance(i, { allowance_type_id: e.target.value })}
                                        options={[{ value: '', label: 'Choose…' }, ...allowanceTypes.map((t) => ({ value: String(t.id), label: t.name }))]}
                                        error={errors[`allowances.${i}.allowance_type_id`]}
                                    />
                                    <Input aria-label={`Amount for allowance ${i + 1}`} type="number" min="0" step="0.01" inputMode="decimal" placeholder="Amount" value={a.amount} onChange={(e) => setAllowance(i, { amount: e.target.value })} error={errors[`allowances.${i}.amount`]} />
                                    <Button variant="ghost" size="sm" icon="trash" aria-label={`Remove allowance ${i + 1}`} onClick={() => removeAllowance(i)} />
                                </div>
                            ))}
                            <Button variant="secondary" size="sm" icon="plus" onClick={addAllowance} disabled={allowanceTypes.length === 0}>
                                Add an allowance
                            </Button>
                            {allowanceTypes.length === 0 && <span className="a-small a-muted">Add allowance types under People → Lists first.</span>}
                        </div>
                        <div className="a-form-grid">
                            <Input label="Bank" maxLength={120} {...bind('bank_name')} />
                            <Input label="Account number" maxLength={60} {...bind('bank_account')} />
                        </div>
                    </fieldset>
                )}
                {employee && <Select label="Status" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })} options={[{ value: 'active', label: 'Active' }, { value: 'left', label: 'Has left (portal access ends)' }]} />}
            </form>
        </Modal>
    );
}

export default function Employees() {
    const { can } = useAuth();
    const navigate = useNavigate();
    const [q, setQ] = useState('');
    const search = useDebounced(q);
    const [status, setStatus] = useState('');
    const { data, error, loading, reload } = useFetch('/hr/employees', { q: search, status });
    const [modal, setModal] = useState(null); // null | 'new' | employee
    const manage = can('hr.manage');

    return (
        <>
            <PageHeader
                title="Employees"
                subtitle="Everyone on the team: their details, shift, leave and pay."
                actions={
                    <>

                        {manage && <Button icon="plus" onClick={() => setModal('new')}>Add employee</Button>}
                    </>
                }
            />
            <div className="a-toolbar">
                <SearchInput value={q} onChange={setQ} placeholder="Search name, code, email, role or team…" />
                <Select aria-label="Filter by status" value={status} onChange={(e) => setStatus(e.target.value)} options={[{ value: '', label: 'Everyone' }, { value: 'active', label: 'Active' }, { value: 'left', label: 'Has left' }]} />
            </div>
            <ErrorNote error={error} onRetry={reload} />
            <Card padded={false}>
                <Table
                    rows={data?.data}
                    loading={loading}
                    onRowClick={(e) => navigate(`/people/${e.id}`)}
                    columns={[
                        {
                            key: 'name',
                            label: 'Employee',
                            render: (e) => (
                                <span className="a-cell-user">
                                    <Avatar name={e.name} size={34} />
                                    <span>
                                        <b>{e.name}</b>
                                        <small>
                                            {e.code} · {e.job_title || 'No job title'}
                                        </small>
                                    </span>
                                </span>
                            ),
                        },
                        { key: 'department', label: 'Department', className: 'a-hide-sm a-muted', render: (e) => e.department || '—' },
                        { key: 'shift', label: 'Shift', className: 'a-hide-md a-muted a-nowrap', render: (e) => `${e.shift_start}–${e.shift_end}` },
                        { key: 'status', label: 'Status', render: (e) => (e.status === 'active' ? <Badge tone="green" dot>Active</Badge> : <Badge dot>Has left</Badge>) },
                        { key: 'portal', label: 'Portal', className: 'a-hide-sm', render: (e) => (e.portal_active ? <Badge tone="blue">Signed up</Badge> : e.portal_invited ? <Badge tone="amber">Invited</Badge> : <span className="a-muted">Not invited</span>) },
                        ...(can('payroll.view') ? [{ key: 'basic_salary', label: 'Salary', className: 'a-num a-hide-md', render: (e) => money(e.basic_salary, 'PKR') }] : []),
                    ]}
                    empty={
                        <EmptyState icon="users" title={q ? 'No matching people' : 'No employees yet'}>
                            {manage && !q && (
                                <Button variant="secondary" icon="plus" onClick={() => setModal('new')}>
                                    Add the first employee
                                </Button>
                            )}
                        </EmptyState>
                    }
                />
            </Card>
            <EmployeeModal
                open={!!modal}
                employee={modal === 'new' || !modal ? null : modal}
                onClose={() => setModal(null)}
                onSaved={(saved) => {
                    setModal(null);
                    reload();
                    if (!modal || modal === 'new') navigate(`/people/${saved.id}`);
                }}
            />
        </>
    );
}
