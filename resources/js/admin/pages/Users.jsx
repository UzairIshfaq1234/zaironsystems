import { useState } from 'react';
import { useAuth } from '../auth/AuthContext';
import useFetch, { useDebounced } from '../hooks/useFetch';
import { api } from '../lib/api';
import { dateTime, timeAgo } from '../lib/format';
import { Avatar, Badge, Button, Card, Checkbox, EmptyState, ErrorNote, Input, Modal, PageHeader, Pagination, SearchInput, Switch, Table } from '../ui';
import { useConfirm, useToast } from '../ui/feedback';

function UserStatus({ user }) {
    if (!user.is_active) return <Badge tone="neutral" dot>Disabled</Badge>;
    if (user.pending_invite) return <Badge tone="amber" dot>Invited</Badge>;
    return <Badge tone="green" dot>Active</Badge>;
}

/** Invite (no `user`) or edit a user. Roles the current admin may not assign are shown disabled. */
function UserModal({ open, user, roles, onClose, onSaved }) {
    const { user: me } = useAuth();
    const toast = useToast();
    const editing = !!user;
    const isSelf = editing && user.id === me.id;
    const [form, setForm] = useState(null);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);

    // Reset the form each time the modal opens.
    const [openedFor, setOpenedFor] = useState(null);
    const key = open ? (user?.id ?? 'new') : null;
    if (key !== openedFor) {
        setOpenedFor(key);
        setErrors({});
        setForm(open ? { name: user?.name ?? '', email: user?.email ?? '', is_active: user?.is_active ?? true, roles: user?.roles.map((r) => r.id) ?? [] } : null);
    }

    const canAssign = (role) => me.is_super_admin || (role.slug !== 'super-admin' && role.permissions.every((p) => me.permissions.includes(p)));
    const toggleRole = (id) => setForm((f) => ({ ...f, roles: f.roles.includes(id) ? f.roles.filter((r) => r !== id) : [...f.roles, id] }));

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setErrors({});
        try {
            const body = isSelf ? { name: form.name, email: form.email } : form;
            const data = editing ? await api.patch(`/users/${user.id}`, body) : await api.post('/users', body);
            toast(editing ? 'User updated.' : `Invitation sent to ${data.data.email}.`);
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
            title={editing ? `Edit ${user.name}` : 'Invite a user'}
            description={editing ? undefined : "They'll get an email with a secure link to set their password."}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="user-form" loading={busy} icon={editing ? 'check' : 'send'}>
                        {editing ? 'Save changes' : 'Send invitation'}
                    </Button>
                </>
            }
        >
            {form && (
                <form id="user-form" className="a-form" onSubmit={submit} noValidate>
                    {errors.form && <p className="a-alert">{errors.form}</p>}
                    <div className="a-form-grid">
                        <Input label="Full name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} error={errors.name} maxLength={120} required autoFocus />
                        <Input label="Email" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} error={errors.email} maxLength={190} required />
                    </div>
                    <fieldset className="a-fieldset" disabled={isSelf}>
                        <legend>Roles</legend>
                        {isSelf && <p className="a-field-hint">You can't change your own roles. Ask another administrator.</p>}
                        <div className="a-role-picks">
                            {roles.map((role) => (
                                <Checkbox
                                    key={role.id}
                                    className="a-role-pick"
                                    checked={form.roles.includes(role.id)}
                                    onChange={() => toggleRole(role.id)}
                                    disabled={!canAssign(role)}
                                    label={role.name}
                                    description={role.description || `${role.permissions.length} permissions`}
                                />
                            ))}
                        </div>
                        {errors.roles && <p className="a-field-error">{errors.roles}</p>}
                    </fieldset>
                    {editing && !isSelf && (
                        <Switch label="Account active (can sign in)" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} />
                    )}
                    {errors.is_active && <p className="a-field-error">{errors.is_active}</p>}
                </form>
            )}
        </Modal>
    );
}

export default function Users() {
    const { user: me, can } = useAuth();
    const toast = useToast();
    const confirm = useConfirm();
    const [q, setQ] = useState('');
    const [page, setPage] = useState(1);
    const search = useDebounced(q);
    const { data, error, loading, reload } = useFetch('/users', { q: search, page });
    const { data: rolesData } = useFetch(can('roles.view') || can('users.create') || can('users.update') ? '/roles' : null);
    const [modal, setModal] = useState(null); // null | 'new' | user

    const manageable = (u) => me.is_super_admin || !u.is_super_admin;

    const remove = async (u) => {
        if (!(await confirm({ title: `Delete ${u.name}?`, message: 'They will lose access immediately. This cannot be undone.', confirmLabel: 'Delete user', danger: true }))) return;
        try {
            await api.del(`/users/${u.id}`);
            toast('User deleted.');
            reload();
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    const resend = async (u) => {
        try {
            toast((await api.post(`/users/${u.id}/resend-invite`)).message);
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    return (
        <>
            <PageHeader
                title="Users"
                subtitle="Everyone who can sign in to this portal, and what they can do."
                actions={
                    can('users.create') && (
                        <Button icon="plus" onClick={() => setModal('new')} disabled={!rolesData}>
                            Invite user
                        </Button>
                    )
                }
            />
            <div className="a-toolbar">
                <SearchInput
                    value={q}
                    onChange={(v) => {
                        setQ(v);
                        setPage(1);
                    }}
                    placeholder="Search name or email…"
                />
            </div>
            <ErrorNote error={error} onRetry={reload} />
            <Card padded={false}>
                <Table
                    rows={data?.data}
                    loading={loading}
                    columns={[
                        {
                            key: 'name',
                            label: 'User',
                            render: (u) => (
                                <span className="a-cell-user">
                                    <Avatar name={u.name} size={36} />
                                    <span>
                                        <b>
                                            {u.name} {u.id === me.id && <span className="a-you">You</span>}
                                        </b>
                                        <small>{u.email}</small>
                                    </span>
                                </span>
                            ),
                        },
                        {
                            key: 'roles',
                            label: 'Roles',
                            render: (u) => (
                                <span className="a-chips">
                                    {u.roles.length ? u.roles.map((r) => <Badge key={r.id} tone={r.slug === 'super-admin' ? 'accent' : 'neutral'}>{r.name}</Badge>) : <span className="a-muted">No role</span>}
                                </span>
                            ),
                        },
                        { key: 'status', label: 'Status', render: (u) => <UserStatus user={u} /> },
                        { key: 'last_login_at', label: 'Last sign-in', className: 'a-hide-sm a-muted a-nowrap', render: (u) => <span title={dateTime(u.last_login_at)}>{u.last_login_at ? timeAgo(u.last_login_at) : 'Never'}</span> },
                        {
                            key: 'actions',
                            label: '',
                            className: 'a-actions',
                            render: (u) =>
                                manageable(u) && (
                                    <span className="a-row-actions">
                                        {u.pending_invite && can('users.create') && <Button variant="ghost" size="sm" icon="send" onClick={() => resend(u)} aria-label={`Resend invitation to ${u.name}`} title="Resend invitation" />}
                                        {can('users.update') && <Button variant="ghost" size="sm" icon="edit" onClick={() => setModal(u)} aria-label={`Edit ${u.name}`} disabled={!rolesData} />}
                                        {can('users.delete') && u.id !== me.id && <Button variant="ghost" size="sm" icon="trash" onClick={() => remove(u)} aria-label={`Delete ${u.name}`} />}
                                    </span>
                                ),
                        },
                    ]}
                    empty={<EmptyState icon="users" title="No users found">Try a different search.</EmptyState>}
                />
            </Card>
            <Pagination meta={data?.meta} onPage={setPage} />

            <UserModal
                open={!!modal}
                user={modal === 'new' ? null : modal}
                roles={rolesData?.data ?? []}
                onClose={() => setModal(null)}
                onSaved={() => {
                    setModal(null);
                    reload();
                }}
            />
        </>
    );
}
