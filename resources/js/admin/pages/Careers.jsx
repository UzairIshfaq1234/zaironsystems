import { useEffect, useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import useFetch, { useDebounced } from '../hooks/useFetch';
import { api, fileUrl } from '../lib/api';
import { APPLICATION_STATUSES, dateTime, OPENING_STATUSES, timeAgo } from '../lib/format';
import { Avatar, Button, Card, EmptyState, ErrorNote, Input, Modal, PageHeader, Pagination, SearchInput, Select, Skeleton, StatusBadge, Table } from '../ui';
import { useConfirm, useToast } from '../ui/feedback';
import Icon from '../ui/Icon';

const TYPE_OPTIONS = ['Full-time', 'Part-time', 'Contract', 'Internship'].map((t) => ({ value: t, label: t }));
const EMPTY_OPENING = { title: '', team: '', type: 'Full-time', location: '', description: '', status: 'open', sort_order: 0 };

export function JobOpeningList() {
    const { can } = useAuth();
    const toast = useToast();
    const confirm = useConfirm();
    const { data, error, loading, reload } = useFetch('/careers/openings');
    const [editing, setEditing] = useState(null); // null = closed, {} = new, opening = edit
    const [form, setForm] = useState(EMPTY_OPENING);
    const [saving, setSaving] = useState(false);

    const openForm = (opening) => {
        setForm(opening ? { ...EMPTY_OPENING, ...opening, description: opening.description ?? '' } : EMPTY_OPENING);
        setEditing(opening ?? {});
    };
    const set = (key) => (e) => setForm((f) => ({ ...f, [key]: e.target.value }));

    const save = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            if (editing.id) await api.put(`/careers/openings/${editing.id}`, form);
            else await api.post('/careers/openings', form);
            toast(editing.id ? 'Opening updated.' : 'Opening added to the careers page.');
            setEditing(null);
            reload();
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            setSaving(false);
        }
    };

    const remove = async (opening) => {
        if (!(await confirm({ title: `Delete “${opening.title}”?`, message: 'Applications already received stay in the list, without a role.', confirmLabel: 'Delete', danger: true }))) return;
        try {
            await api.del(`/careers/openings/${opening.id}`);
            toast('Opening deleted.');
            reload();
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    return (
        <>
            <PageHeader
                title="Job openings"
                subtitle="Roles shown on the careers page. Only open roles accept applications."
                actions={can('careers.manage') && <Button icon="plus" onClick={() => openForm(null)}>New opening</Button>}
            />
            <ErrorNote error={error} onRetry={reload} />
            <Card padded={false}>
                <Table
                    rows={data?.data}
                    loading={loading}
                    columns={[
                        {
                            key: 'title',
                            label: 'Role',
                            render: (r) => (
                                <span>
                                    <b>{r.title}</b>
                                    <small className="a-block a-muted">
                                        {r.team} · {r.type} · {r.location}
                                    </small>
                                </span>
                            ),
                        },
                        { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
                        { key: 'applications_count', label: 'Applications', className: 'a-hide-sm', render: (r) => r.applications_count },
                        {
                            key: 'actions',
                            label: '',
                            className: 'a-nowrap',
                            render: (r) => (
                                <span className="a-row-actions">
                                    {can('careers.manage') && <Button size="sm" variant="secondary" icon="edit" onClick={() => openForm(r)} aria-label={`Edit ${r.title}`} />}
                                    {can('careers.delete') && <Button size="sm" variant="danger" icon="trash" onClick={() => remove(r)} aria-label={`Delete ${r.title}`} />}
                                </span>
                            ),
                        },
                    ]}
                    empty={<EmptyState icon="spark" title="No openings yet">Add a role and it appears on the careers page. Until then, the page says “We'll update soon.”</EmptyState>}
                />
            </Card>

            <Modal
                open={editing !== null}
                onClose={() => setEditing(null)}
                title={editing?.id ? 'Edit opening' : 'New opening'}
                description="Shown on the careers page while its status is Open."
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setEditing(null)}>
                            Cancel
                        </Button>
                        <Button type="submit" form="opening-form" loading={saving} icon="check">
                            Save
                        </Button>
                    </>
                }
            >
                <form id="opening-form" className="a-form" onSubmit={save}>
                    <Input label="Job title" required maxLength={160} value={form.title} onChange={set('title')} />
                    <div className="a-form-row">
                        <Input label="Team" required maxLength={80} value={form.team} onChange={set('team')} />
                        <Select label="Type" options={TYPE_OPTIONS} value={form.type} onChange={set('type')} />
                    </div>
                    <div className="a-form-row">
                        <Input label="Location" required maxLength={120} value={form.location} onChange={set('location')} />
                        <Select label="Status" options={OPENING_STATUSES} value={form.status} onChange={set('status')} />
                    </div>
                    <Input label="Description" as="textarea" rows={5} maxLength={5000} value={form.description} onChange={set('description')} hint="Optional. Shown under the role title." />
                    <Input label="Display order" type="number" min={0} max={999} value={form.sort_order} onChange={set('sort_order')} hint="Lower numbers appear first." />
                </form>
            </Modal>
        </>
    );
}

export function JobApplicationList() {
    const navigate = useNavigate();
    const [params, setParams] = useSearchParams();
    const [q, setQ] = useState(params.get('q') ?? '');
    const debouncedQ = useDebounced(q);
    const status = params.get('status') ?? '';
    const opening = params.get('opening') ?? '';
    const page = Number(params.get('page') ?? 1);

    const update = (patch) => {
        const next = new URLSearchParams(params);
        Object.entries(patch).forEach(([k, v]) => (v ? next.set(k, v) : next.delete(k)));
        if (!('page' in patch)) next.delete('page');
        setParams(next, { replace: true });
    };

    useEffect(() => {
        if (debouncedQ !== (params.get('q') ?? '')) update({ q: debouncedQ });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debouncedQ]);

    const { data, error, loading, reload } = useFetch('/careers/applications', { q: params.get('q') ?? '', status, opening, page });
    const { data: openings } = useFetch('/careers/openings');
    const filtered = Boolean(params.get('q') || status || opening);
    const roleOptions = [{ value: '', label: 'All roles' }, ...(openings?.data ?? []).map((o) => ({ value: String(o.id), label: o.title }))];
    const statusOptions = [{ value: '', label: 'All statuses' }, ...APPLICATION_STATUSES];

    return (
        <>
            <PageHeader title="Applications" subtitle="Applications received from the careers page, with CVs." />
            <div className="a-toolbar">
                <SearchInput value={q} onChange={setQ} placeholder="Search name or email…" />
                <Select aria-label="Filter by status" value={status} onChange={(e) => update({ status: e.target.value })} options={statusOptions} />
                <Select aria-label="Filter by role" value={opening} onChange={(e) => update({ opening: e.target.value })} options={roleOptions} />
            </div>
            <ErrorNote error={error} onRetry={reload} />
            <Card padded={false}>
                <Table
                    rows={data?.data}
                    loading={loading}
                    onRowClick={(row) => navigate(`/careers/applications/${row.id}`)}
                    columns={[
                        {
                            key: 'name',
                            label: 'Applicant',
                            render: (r) => (
                                <span className="a-cell-user">
                                    <Avatar name={r.name} size={34} />
                                    <span>
                                        <b>{r.name}</b>
                                        <small>{r.email}</small>
                                    </span>
                                </span>
                            ),
                        },
                        { key: 'role', label: 'Role', className: 'a-hide-md', render: (r) => r.role ?? <span className="a-muted">Open application</span> },
                        { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
                        {
                            key: 'created_at',
                            label: 'Received',
                            className: 'a-nowrap a-muted',
                            render: (r) => (
                                <span title={dateTime(r.created_at)}>
                                    {r.has_cv && <Icon name="file" className="a-inline-icon" />}
                                    {timeAgo(r.created_at)}
                                </span>
                            ),
                        },
                    ]}
                    empty={
                        <EmptyState icon="users" title={filtered ? 'No matching applications' : 'No applications yet'}>
                            {filtered ? 'Try a different search or filter.' : 'Applications from the careers page will appear here.'}
                        </EmptyState>
                    }
                />
            </Card>
            <Pagination meta={data?.meta} onPage={(p) => update({ page: String(p) })} />
        </>
    );
}

export function JobApplicationDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const { can } = useAuth();
    const toast = useToast();
    const confirm = useConfirm();
    const { data, error, setData } = useFetch(`/careers/applications/${id}`);
    const a = data?.data;
    const [notes, setNotes] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => setNotes(a?.notes ?? ''), [a?.id, a?.notes]);

    const save = async (patch, message) => {
        setSaving(true);
        try {
            setData(await api.patch(`/careers/applications/${id}`, patch));
            toast(message);
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            setSaving(false);
        }
    };

    const remove = async () => {
        if (!(await confirm({ title: 'Delete this application?', message: 'The application and its CV will be permanently deleted.', confirmLabel: 'Delete', danger: true }))) return;
        try {
            await api.del(`/careers/applications/${id}`);
            toast('Application deleted.');
            navigate('/careers/applications', { replace: true });
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    if (error) return <ErrorNote error={error} />;
    if (!a) return <Skeleton height={400} />;

    return (
        <>
            <PageHeader
                back={{ to: '/careers/applications', label: 'All applications' }}
                title={a.name}
                subtitle={`${a.role ?? 'Open application'} · received ${dateTime(a.created_at)}`}
                actions={
                    <>
                        <Button variant="secondary" icon="mail" href={`mailto:${a.email}?subject=${encodeURIComponent(`Re: your application${a.role ? ` for ${a.role}` : ''}`)}`}>
                            Reply
                        </Button>
                        {can('careers.delete') && <Button variant="danger" icon="trash" onClick={remove} aria-label="Delete application" />}
                    </>
                }
            />

            <div className="a-grid-detail">
                <div className="a-stack">
                    <Card title="Message">
                        <p className="a-prewrap">{a.message || 'No message was included.'}</p>
                    </Card>
                    <Card title="Internal notes" subtitle="Only visible to your team.">
                        {can('careers.manage') ? (
                            <form
                                className="a-form"
                                onSubmit={(ev) => {
                                    ev.preventDefault();
                                    save({ notes }, 'Notes saved.');
                                }}
                            >
                                <Input as="textarea" rows={5} maxLength={5000} value={notes} onChange={(ev) => setNotes(ev.target.value)} placeholder="Interview notes, next steps…" aria-label="Internal notes" />
                                <div>
                                    <Button type="submit" loading={saving} disabled={notes === (a.notes ?? '')} icon="check">
                                        Save notes
                                    </Button>
                                </div>
                            </form>
                        ) : (
                            <p className="a-prewrap a-muted">{a.notes || 'No notes yet.'}</p>
                        )}
                    </Card>
                </div>

                <div className="a-stack">
                    <Card title="Status">
                        {can('careers.manage') ? (
                            <Select aria-label="Status" value={a.status} disabled={saving} onChange={(ev) => save({ status: ev.target.value }, 'Status updated.')} options={APPLICATION_STATUSES} />
                        ) : (
                            <StatusBadge status={a.status} />
                        )}
                    </Card>
                    <Card title="Contact">
                        <dl className="a-dl">
                            <dt>
                                <Icon name="mail" /> Email
                            </dt>
                            <dd>
                                <a className="a-link" href={`mailto:${a.email}`}>
                                    {a.email}
                                </a>
                            </dd>
                            <dt>
                                <Icon name="phone" /> Phone
                            </dt>
                            <dd>{a.phone ? <a className="a-link" href={`tel:${a.phone.replace(/[^\d+]/g, '')}`}>{a.phone}</a> : '—'}</dd>
                            <dt>
                                <Icon name="globe" /> Links
                            </dt>
                            <dd className="a-break">{a.links ? <a className="a-link" href={a.links} target="_blank" rel="noopener noreferrer">{a.links}</a> : '—'}</dd>
                        </dl>
                    </Card>
                    <Card title="CV">
                        {a.has_cv ? (
                            <a className="a-link a-download" href={fileUrl(`/careers/applications/${a.id}/cv`)} download>
                                <Icon name="download" /> {a.cv_name}
                            </a>
                        ) : (
                            <p className="a-muted">No CV attached.</p>
                        )}
                    </Card>
                </div>
            </div>
        </>
    );
}
