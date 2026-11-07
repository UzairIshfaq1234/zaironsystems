import { useEffect, useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import engagement from '@content/engagement.json';
import services from '@content/services.json';
import { useAuth } from '../auth/AuthContext';
import useFetch, { useDebounced } from '../hooks/useFetch';
import { api, fileUrl } from '../lib/api';
import { dateTime, STATUSES, timeAgo } from '../lib/format';
import { Avatar, Badge, Button, Card, EmptyState, ErrorNote, Input, PageHeader, Pagination, SearchInput, Select, Skeleton, StatusBadge, Table } from '../ui';
import { useConfirm, useToast } from '../ui/feedback';
import Icon from '../ui/Icon';

const serviceTitle = Object.fromEntries(services.items.map((s) => [s.slug, s.title]));
const optionLabel = Object.fromEntries(services.formOptions.map((o) => [o.value, o.label]));
const planTitle = Object.fromEntries(engagement.plans.map((p) => [p.id, p.title]));
const STATUS_OPTIONS = [{ value: '', label: 'All statuses' }, ...STATUSES];
const SERVICE_OPTIONS = [{ value: '', label: 'All services' }, ...services.items.map((s) => ({ value: s.slug, label: s.title }))];

export function EnquiryList() {
    const navigate = useNavigate();
    const [params, setParams] = useSearchParams();
    const [q, setQ] = useState(params.get('q') ?? '');
    const debouncedQ = useDebounced(q);
    const status = params.get('status') ?? '';
    const service = params.get('service') ?? '';
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

    const { data, error, loading, reload } = useFetch('/enquiries', { q: params.get('q') ?? '', status, service, page });
    const filtered = Boolean(params.get('q') || status || service);

    return (
        <>
            <PageHeader title="Enquiries" subtitle="Project requests from the contact form and service pages." />
            <div className="a-toolbar">
                <SearchInput value={q} onChange={setQ} placeholder="Search name, email or company…" />
                <Select aria-label="Filter by status" value={status} onChange={(e) => update({ status: e.target.value })} options={STATUS_OPTIONS} />
                <Select aria-label="Filter by service" value={service} onChange={(e) => update({ service: e.target.value })} options={SERVICE_OPTIONS} />
            </div>
            <ErrorNote error={error} onRetry={reload} />
            <Card padded={false}>
                <Table
                    rows={data?.data}
                    loading={loading}
                    onRowClick={(row) => navigate(`/enquiries/${row.id}`)}
                    columns={[
                        {
                            key: 'name',
                            label: 'Client',
                            render: (r) => (
                                <span className="a-cell-user">
                                    <Avatar name={r.name} size={34} />
                                    <span>
                                        <b>{r.name}</b>
                                        <small>{r.company}</small>
                                    </span>
                                </span>
                            ),
                        },
                        { key: 'email', label: 'Email', className: 'a-hide-sm a-muted' },
                        { key: 'service', label: 'Service', className: 'a-hide-md', render: (r) => (r.service ? serviceTitle[r.service] ?? r.service : <span className="a-muted">Contact page</span>) },
                        { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
                        {
                            key: 'created_at',
                            label: 'Received',
                            className: 'a-nowrap a-muted',
                            render: (r) => (
                                <span title={dateTime(r.created_at)}>
                                    {r.has_brief && <Icon name="file" className="a-inline-icon" />}
                                    {timeAgo(r.created_at)}
                                </span>
                            ),
                        },
                    ]}
                    empty={
                        <EmptyState icon="inbox" title={filtered ? 'No matching enquiries' : 'No enquiries yet'}>
                            {filtered ? 'Try a different search or filter.' : 'New enquiries from the website will appear here.'}
                        </EmptyState>
                    }
                />
            </Card>
            <Pagination meta={data?.meta} onPage={(p) => update({ page: String(p) })} />
        </>
    );
}

export function EnquiryDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const { can } = useAuth();
    const toast = useToast();
    const confirm = useConfirm();
    const { data, error, setData } = useFetch(`/enquiries/${id}`);
    const e = data?.data;
    const [notes, setNotes] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => setNotes(e?.notes ?? ''), [e?.id, e?.notes]);

    const save = async (patch, message) => {
        setSaving(true);
        try {
            setData(await api.patch(`/enquiries/${id}`, patch));
            toast(message);
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            setSaving(false);
        }
    };

    const remove = async () => {
        if (!(await confirm({ title: 'Delete this enquiry?', message: 'The enquiry and its attachment will be permanently deleted.', confirmLabel: 'Delete', danger: true }))) return;
        try {
            await api.del(`/enquiries/${id}`);
            toast('Enquiry deleted.');
            navigate('/enquiries', { replace: true });
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    if (error) return <ErrorNote error={error} />;
    if (!e) return <Skeleton height={400} />;

    return (
        <>
            <PageHeader
                back={{ to: '/enquiries', label: 'All enquiries' }}
                title={e.name}
                subtitle={`${e.company} · received ${dateTime(e.created_at)}`}
                actions={
                    <>
                        <Button variant="secondary" icon="mail" href={`mailto:${e.email}?subject=${encodeURIComponent('Re: your project enquiry — ZAIRON Systems')}`}>
                            Reply
                        </Button>
                        {can('billing.manage') && (
                            <Button
                                icon="file"
                                onClick={async () => {
                                    try {
                                        const res = await api.post(`/enquiries/${id}/client`);
                                        navigate(`/billing/quotes/new?client=${res.data.id}`);
                                    } catch (err) {
                                        toast(err.message, 'error');
                                    }
                                }}
                            >
                                Create client & quote
                            </Button>
                        )}
                        {can('enquiries.delete') && <Button variant="danger" icon="trash" onClick={remove} aria-label="Delete enquiry" />}
                    </>
                }
            />

            <div className="a-grid-detail">
                <div className="a-stack">
                    <Card title="Project details">
                        <p className="a-prewrap">{e.details}</p>
                        {e.services.length > 0 && (
                            <div className="a-chips">
                                {e.services.map((s) => (
                                    <Badge key={s}>{optionLabel[s] ?? s}</Badge>
                                ))}
                            </div>
                        )}
                    </Card>
                    <Card title="Internal notes" subtitle="Only visible to your team.">
                        {can('enquiries.update') ? (
                            <form
                                className="a-form"
                                onSubmit={(ev) => {
                                    ev.preventDefault();
                                    save({ notes }, 'Notes saved.');
                                }}
                            >
                                <Input as="textarea" rows={5} maxLength={5000} value={notes} onChange={(ev) => setNotes(ev.target.value)} placeholder="Call notes, next steps, quote sent…" aria-label="Internal notes" />
                                <div>
                                    <Button type="submit" loading={saving} disabled={notes === (e.notes ?? '')} icon="check">
                                        Save notes
                                    </Button>
                                </div>
                            </form>
                        ) : (
                            <p className="a-prewrap a-muted">{e.notes || 'No notes yet.'}</p>
                        )}
                    </Card>
                </div>

                <div className="a-stack">
                    <Card title="Status">
                        {can('enquiries.update') ? (
                            <Select aria-label="Status" value={e.status} disabled={saving} onChange={(ev) => save({ status: ev.target.value }, 'Status updated.')} options={STATUSES} />
                        ) : (
                            <StatusBadge status={e.status} />
                        )}
                    </Card>
                    <Card title="Contact">
                        <dl className="a-dl">
                            <dt>
                                <Icon name="mail" /> Email
                            </dt>
                            <dd>
                                <a className="a-link" href={`mailto:${e.email}`}>
                                    {e.email}
                                </a>
                            </dd>
                            <dt>
                                <Icon name="phone" /> Phone
                            </dt>
                            <dd>{e.phone ? <a className="a-link" href={`tel:${e.phone.replace(/[^\d+]/g, '')}`}>{e.phone}</a> : '—'}</dd>
                            <dt>
                                <Icon name="building" /> Company
                            </dt>
                            <dd>{e.company}</dd>
                        </dl>
                    </Card>
                    <Card title="Request">
                        <dl className="a-dl">
                            <dt>Service page</dt>
                            <dd>{e.service ? serviceTitle[e.service] ?? e.service : 'Contact page'}</dd>
                            <dt>Plan</dt>
                            <dd>{e.plan ? planTitle[e.plan] ?? e.plan : '—'}</dd>
                            <dt>Budget</dt>
                            <dd>{e.budget ?? '—'}</dd>
                            <dt>Attachment</dt>
                            <dd>
                                {e.has_brief ? (
                                    <a className="a-link a-download" href={fileUrl(`/enquiries/${e.id}/brief`)} download>
                                        <Icon name="download" /> {e.brief_name}
                                    </a>
                                ) : (
                                    '—'
                                )}
                            </dd>
                        </dl>
                    </Card>
                    <Card title="Technical">
                        <dl className="a-dl a-dl-small">
                            <dt>IP address</dt>
                            <dd>{e.ip_address ?? '—'}</dd>
                            <dt>Browser</dt>
                            <dd className="a-break">{e.user_agent ?? '—'}</dd>
                            <dt>Last updated</dt>
                            <dd>{timeAgo(e.updated_at)}</dd>
                        </dl>
                    </Card>
                </div>
            </div>
        </>
    );
}
