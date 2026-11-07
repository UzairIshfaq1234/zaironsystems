import { useState } from 'react';
import useFetch from '../../hooks/useFetch';
import { api } from '../../lib/api';
import { Badge, Button, Card, Input, PageHeader, Select, Skeleton, Switch } from '../../ui';
import { useConfirm, useToast } from '../../ui/feedback';

/** Add a new entry. `children` renders extra fields (e.g. the department picker) above the name. */
function AddForm({ placeholder, onAdd, children, disabled }) {
    const [name, setName] = useState('');
    const [busy, setBusy] = useState(false);
    const toast = useToast();

    const submit = async (e) => {
        e.preventDefault();
        if (!name.trim()) return;
        setBusy(true);
        try {
            await onAdd(name.trim());
            setName('');
        } catch (err) {
            toast(err.errors?.name ?? err.errors?.department_id ?? err.message, 'error');
        } finally {
            setBusy(false);
        }
    };

    return (
        <form className="a-list-add" onSubmit={submit}>
            {children}
            <Input aria-label={placeholder} placeholder={placeholder} value={name} maxLength={120} onChange={(e) => setName(e.target.value)} />
            <Button type="submit" icon="plus" loading={busy} disabled={!name.trim() || disabled}>
                Add
            </Button>
        </form>
    );
}

/** A list of names with rename and remove. `toggle` adds an on/off switch (allowances). */
function NameList({ items, kind, itemNoun, toggle, onChanged }) {
    const toast = useToast();
    const confirm = useConfirm();
    const [editing, setEditing] = useState({});
    const base = `/hr/lists/${kind}`;

    const rename = async (item, name) => {
        try {
            await api.put(`${base}/${item.id}`, { name, ...(item.department_id && { department_id: item.department_id }), ...(toggle && { is_active: item.is_active }) });
            setEditing((s) => ({ ...s, [item.id]: undefined }));
            onChanged();
        } catch (err) {
            toast(err.errors?.name ?? err.message, 'error');
        }
    };

    const switchOn = async (item, is_active) => {
        try {
            await api.put(`${base}/${item.id}`, { name: item.name, is_active });
            onChanged();
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    const remove = async (item) => {
        if (!(await confirm({ title: `Remove “${item.name}”?`, message: 'It disappears from the choices. This cannot be undone.', confirmLabel: 'Remove', danger: true }))) return;
        try {
            await api.del(`${base}/${item.id}`);
            toast('Removed.');
            onChanged();
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    if (items.length === 0) return <p className="a-muted">No {itemNoun}s yet. Add the first one above.</p>;

    return (
        <ul className="a-list-items">
            {items.map((item) => {
                const draft = editing[item.id];
                return (
                    <li key={item.id} className={toggle && !item.is_active ? 'is-off' : undefined}>
                        {draft !== undefined ? (
                            <form
                                className="a-list-edit"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    rename(item, draft);
                                }}
                            >
                                <Input aria-label="Name" value={draft} maxLength={120} autoFocus onChange={(e) => setEditing((s) => ({ ...s, [item.id]: e.target.value }))} />
                                <Button type="submit" size="sm" icon="check">
                                    Save
                                </Button>
                                <Button variant="ghost" size="sm" onClick={() => setEditing((s) => ({ ...s, [item.id]: undefined }))}>
                                    Cancel
                                </Button>
                            </form>
                        ) : (
                            <>
                                <span className="a-list-name">
                                    <b>{item.name}</b>
                                    {item.in_use && <Badge tone="blue">In use</Badge>}
                                    {toggle && !item.is_active && <Badge>Off</Badge>}
                                </span>
                                <span className="a-row-actions">
                                    {toggle && <Switch label="" aria-label={`${item.name} active`} checked={item.is_active} onChange={(e) => switchOn(item, e.target.checked)} />}
                                    <Button variant="ghost" size="sm" icon="edit" aria-label={`Rename ${item.name}`} onClick={() => setEditing((s) => ({ ...s, [item.id]: item.name }))} />
                                    <Button variant="ghost" size="sm" icon="trash" aria-label={`Remove ${item.name}`} disabled={item.in_use} title={item.in_use ? 'In use. Rename it instead, or switch it off.' : 'Remove'} onClick={() => remove(item)} />
                                </span>
                            </>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

/** People → Departments. */
export function DepartmentsPage() {
    const { data, reload } = useFetch('/hr/lists');
    const toast = useToast();

    return (
        <>
            <PageHeader title="Departments" subtitle="Where people work, e.g. Design or Engineering. Each person belongs to one department." />
            {!data ? (
                <Skeleton height={300} />
            ) : (
                <Card>
                    <AddForm
                        placeholder="Add a department, e.g. Design"
                        onAdd={async (name) => {
                            await api.post('/hr/lists/departments', { name });
                            toast('Department added.');
                            reload();
                        }}
                    />
                    <NameList items={data.departments} kind="departments" itemNoun="department" onChanged={reload} />
                </Card>
            )}
        </>
    );
}

/** People → Job titles. Each title belongs to a department, so you pick one when you add it. */
export function JobTitlesPage() {
    const { data, reload } = useFetch('/hr/lists');
    const toast = useToast();
    const [department, setDepartment] = useState('');
    const departments = data?.departments ?? [];
    const groups = departments.filter((d) => !department || String(d.id) === department);

    return (
        <>
            <PageHeader title="Job titles" subtitle="Roles people hold. Each job title sits under one department." />
            {!data ? (
                <Skeleton height={300} />
            ) : departments.length === 0 ? (
                <Card>
                    <p className="a-muted">Add a department first, then its job titles.</p>
                </Card>
            ) : (
                <>
                    <Card title="Add a job title">
                        <AddForm
                            placeholder="e.g. Product Designer"
                            disabled={!department}
                            onAdd={async (name) => {
                                await api.post('/hr/lists/job-titles', { name, department_id: Number(department) });
                                toast('Job title added.');
                                reload();
                            }}
                        >
                            <Select aria-label="Department" value={department} onChange={(e) => setDepartment(e.target.value)} options={[{ value: '', label: 'Choose a department…' }, ...departments.map((d) => ({ value: String(d.id), label: d.name }))]} />
                        </AddForm>
                    </Card>
                    <div className="a-stack a-mt">
                        <div className="a-toolbar">
                            <Select aria-label="Show department" value={department} onChange={(e) => setDepartment(e.target.value)} options={[{ value: '', label: 'All departments' }, ...departments.map((d) => ({ value: String(d.id), label: d.name }))]} />
                        </div>
                        {groups.map((d) => (
                            <Card key={d.id} title={d.name} subtitle={`${d.job_titles.length} job title(s)`}>
                                <NameList items={d.job_titles} kind="job-titles" itemNoun="job title" onChanged={reload} />
                            </Card>
                        ))}
                    </div>
                </>
            )}
        </>
    );
}

/** People → Allowances: generic types, each given to a person with their own amount. */
export function AllowancesPage() {
    const { data, reload } = useFetch('/hr/lists');
    const toast = useToast();

    return (
        <>
            <PageHeader title="Allowances" subtitle="Generic allowance types, e.g. House rent or Fuel. You give each one to a person with their own amount." />
            {!data ? (
                <Skeleton height={300} />
            ) : (
                <Card>
                    <AddForm
                        placeholder="Add an allowance, e.g. Mobile"
                        onAdd={async (name) => {
                            await api.post('/hr/lists/allowance-types', { name });
                            toast('Allowance added.');
                            reload();
                        }}
                    />
                    <NameList items={data.allowance_types} kind="allowance-types" itemNoun="allowance" toggle onChanged={reload} />
                </Card>
            )}
        </>
    );
}
