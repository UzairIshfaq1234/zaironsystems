import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import useFetch from '../hooks/useFetch';
import { api } from '../lib/api';
import { Badge, Button, Card, Checkbox, EmptyState, ErrorNote, Input, PageHeader, Skeleton } from '../ui';
import { useConfirm, useToast } from '../ui/feedback';
import Icon from '../ui/Icon';

export function RoleList() {
    const { can } = useAuth();
    const { data, error, reload } = useFetch('/roles');
    const { data: catalog } = useFetch('/roles/permissions');
    const total = catalog?.data.reduce((n, g) => n + g.permissions.length, 0);

    return (
        <>
            <PageHeader
                title="Roles & permissions"
                subtitle="A role is a named set of permissions. Assign roles to users on the Users page."
                actions={
                    can('roles.create') && (
                        <Button to="/roles/new" icon="plus">
                            New role
                        </Button>
                    )
                }
            />
            <ErrorNote error={error} onRetry={reload} />
            {!data ? (
                <div className="a-role-grid">
                    {[1, 2, 3].map((i) => (
                        <Skeleton key={i} height={170} />
                    ))}
                </div>
            ) : (
                <div className="a-role-grid">
                    {data.data.map((role) => (
                        <Link key={role.id} to={`/roles/${role.id}`} className={`a-role-card ${role.is_system ? 'is-system' : ''}`}>
                            <div className="a-role-card-top">
                                <span className="a-role-icon">
                                    <Icon name={role.is_system ? 'key' : 'shield'} />
                                </span>
                                {role.is_system && <Badge tone="accent">System</Badge>}
                            </div>
                            <h2>{role.name}</h2>
                            <p>{role.description || 'No description.'}</p>
                            <div className="a-role-meter" aria-hidden="true">
                                <span style={{ width: `${total ? (role.permissions.length / total) * 100 : 0}%` }} />
                            </div>
                            <div className="a-role-card-foot">
                                <span>
                                    {role.permissions.length}
                                    {total ? ` of ${total}` : ''} permissions
                                </span>
                                <span>
                                    {role.users_count} {role.users_count === 1 ? 'user' : 'users'}
                                </span>
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </>
    );
}

/** Create (/roles/new) or edit (/roles/:id) a role with a grouped permission matrix. */
export function RoleEditor() {
    const { id } = useParams();
    const isNew = id === 'new';
    const navigate = useNavigate();
    const { user: me, can } = useAuth();
    const toast = useToast();
    const confirm = useConfirm();
    const { data: catalog } = useFetch('/roles/permissions');
    const { data: roles, error } = useFetch(isNew ? null : '/roles');
    const role = useMemo(() => roles?.data.find((r) => String(r.id) === id), [roles, id]);

    const [form, setForm] = useState(isNew ? { name: '', description: '', permissions: ['dashboard.view'] } : null);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (role) setForm({ name: role.name, description: role.description ?? '', permissions: role.permissions });
    }, [role]);

    const readOnly = role?.is_system || !(isNew ? can('roles.create') : can('roles.update'));
    // Admins can only grant what they hold themselves (the server enforces this too).
    const grantable = (key) => me.is_super_admin || me.permissions.includes(key);

    const toggle = (key) => setForm((f) => ({ ...f, permissions: f.permissions.includes(key) ? f.permissions.filter((p) => p !== key) : [...f.permissions, key] }));
    const toggleGroup = (group, on) =>
        setForm((f) => {
            const keys = group.permissions.map((p) => p.key).filter(grantable);
            const rest = f.permissions.filter((p) => !keys.includes(p));
            return { ...f, permissions: on ? [...rest, ...keys] : rest };
        });

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setErrors({});
        try {
            const data = isNew ? await api.post('/roles', form) : await api.put(`/roles/${id}`, form);
            toast(isNew ? `Role "${data.data.name}" created.` : 'Role saved.');
            navigate('/roles');
        } catch (err) {
            setErrors(Object.keys(err.errors).length ? err.errors : { form: err.message });
        } finally {
            setBusy(false);
        }
    };

    const remove = async () => {
        const users = role.users_count;
        if (!(await confirm({ title: `Delete "${role.name}"?`, message: users ? `${users} ${users === 1 ? 'user' : 'users'} will lose the permissions this role gives them.` : 'This role is not assigned to anyone.', confirmLabel: 'Delete role', danger: true }))) return;
        try {
            await api.del(`/roles/${id}`);
            toast('Role deleted.');
            navigate('/roles');
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    if (error) return <ErrorNote error={error} />;
    if (!isNew && roles && !role) return <EmptyState icon="shield" title="Role not found" />;
    if (!form || !catalog) return <Skeleton height={480} />;

    return (
        <>
            <PageHeader
                back={{ to: '/roles', label: 'All roles' }}
                title={isNew ? 'New role' : role.name}
                subtitle={role?.is_system ? 'System role: has every permission and cannot be changed.' : 'Choose exactly what people with this role can see and do.'}
                actions={!isNew && !role.is_system && can('roles.delete') && <Button variant="danger" icon="trash" onClick={remove}>Delete</Button>}
            />
            <form className="a-grid-detail reverse" onSubmit={submit} noValidate>
                <div className="a-stack">
                    {catalog.data.map((group) => {
                        const keys = group.permissions.map((p) => p.key);
                        const on = keys.filter((k) => form.permissions.includes(k)).length;
                        return (
                            <Card
                                key={group.group}
                                title={group.group}
                                subtitle={`${on} of ${keys.length} enabled`}
                                actions={
                                    !readOnly && (
                                        <Button variant="ghost" size="sm" onClick={() => toggleGroup(group, on < keys.length)}>
                                            {on < keys.length ? 'Select all' : 'Clear'}
                                        </Button>
                                    )
                                }
                            >
                                <div className="a-perm-list">
                                    {group.permissions.map((p) => (
                                        <Checkbox
                                            key={p.key}
                                            className="a-perm"
                                            checked={form.permissions.includes(p.key)}
                                            disabled={readOnly || !grantable(p.key)}
                                            onChange={() => toggle(p.key)}
                                            label={p.label}
                                            description={p.key}
                                        />
                                    ))}
                                </div>
                            </Card>
                        );
                    })}
                </div>
                <div className="a-stack a-sticky">
                    <Card title="Details">
                        <div className="a-form">
                            {errors.form && <p className="a-alert">{errors.form}</p>}
                            <Input label="Role name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} error={errors.name} maxLength={80} disabled={readOnly} required />
                            <Input as="textarea" rows={3} label="Description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} error={errors.description} maxLength={255} disabled={readOnly} />
                            {errors.permissions && <p className="a-field-error">{errors.permissions}</p>}
                            <p className="a-muted a-small">
                                {form.permissions.length} permission{form.permissions.length === 1 ? '' : 's'} selected
                                {role ? ` · assigned to ${role.users_count} ${role.users_count === 1 ? 'user' : 'users'}` : ''}
                            </p>
                            {!readOnly && (
                                <Button type="submit" loading={busy} icon="check" className="a-btn-block">
                                    {isNew ? 'Create role' : 'Save role'}
                                </Button>
                            )}
                        </div>
                    </Card>
                </div>
            </form>
        </>
    );
}
