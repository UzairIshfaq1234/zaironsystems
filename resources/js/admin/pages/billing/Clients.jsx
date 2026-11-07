import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import useFetch, { useDebounced } from '../../hooks/useFetch';
import { api } from '../../lib/api';
import { dateOnly, money } from '../../lib/format';
import { Avatar, Button, Card, EmptyState, ErrorNote, Input, Modal, PageHeader, Pagination, SearchInput, Skeleton, Table } from '../../ui';
import { useConfirm, useToast } from '../../ui/feedback';
import { DocStatus } from './Documents';

const EMPTY = { name: '', company: '', email: '', phone: '', address: '', tax_number: '', notes: '' };

/** Create / edit a client. Calls onSaved(client) after a successful save. */
export function ClientModal({ open, client, onClose, onSaved }) {
    const toast = useToast();
    const [form, setForm] = useState(EMPTY);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const [openedFor, setOpenedFor] = useState(null);
    const key = open ? (client?.id ?? 'new') : null;
    if (key !== openedFor) {
        setOpenedFor(key);
        setErrors({});
        setForm(client ? { ...EMPTY, ...Object.fromEntries(Object.keys(EMPTY).map((k) => [k, client[k] ?? ''])) } : EMPTY);
    }

    const bind = (k) => ({ value: form[k], onChange: (e) => setForm({ ...form, [k]: e.target.value }), error: errors[k] });

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setErrors({});
        try {
            const res = client ? await api.put(`/clients/${client.id}`, form) : await api.post('/clients', form);
            toast(client ? 'Client updated.' : 'Client added.');
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
            title={client ? `Edit ${client.company || client.name}` : 'New client'}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="client-form" loading={busy} icon="check">
                        {client ? 'Save' : 'Add client'}
                    </Button>
                </>
            }
        >
            <form id="client-form" className="a-form" onSubmit={submit} noValidate>
                {errors.form && <p className="a-alert">{errors.form}</p>}
                <div className="a-form-grid">
                    <Input label="Contact name" required autoFocus maxLength={120} {...bind('name')} />
                    <Input label="Company" maxLength={160} {...bind('company')} />
                    <Input label="Email" type="email" required maxLength={190} {...bind('email')} />
                    <Input label="Phone" maxLength={40} {...bind('phone')} />
                </div>
                <Input as="textarea" rows={3} label="Billing address" maxLength={400} {...bind('address')} />
                <Input label="Tax / VAT number" maxLength={60} {...bind('tax_number')} />
                <Input as="textarea" rows={2} label="Internal notes" maxLength={2000} {...bind('notes')} />
            </form>
        </Modal>
    );
}

export function ClientList() {
    const { can } = useAuth();
    const navigate = useNavigate();
    const [q, setQ] = useState('');
    const [page, setPage] = useState(1);
    const search = useDebounced(q);
    const { data, error, loading, reload } = useFetch('/clients', { q: search, page });
    const [modal, setModal] = useState(false);

    return (
        <>
            <PageHeader
                title="Clients"
                subtitle="The companies you send quotes and invoices to."
                actions={
                    can('billing.manage') && (
                        <Button icon="plus" onClick={() => setModal(true)}>
                            New client
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
                    placeholder="Search name, company or email…"
                />
            </div>
            <ErrorNote error={error} onRetry={reload} />
            <Card padded={false}>
                <Table
                    rows={data?.data}
                    loading={loading}
                    onRowClick={(c) => navigate(`/billing/clients/${c.id}`)}
                    columns={[
                        {
                            key: 'name',
                            label: 'Client',
                            render: (c) => (
                                <span className="a-cell-user">
                                    <Avatar name={c.company || c.name} size={34} />
                                    <span>
                                        <b>{c.company || c.name}</b>
                                        <small>{c.company ? c.name : c.email}</small>
                                    </span>
                                </span>
                            ),
                        },
                        { key: 'email', label: 'Email', className: 'a-hide-sm a-muted' },
                        { key: 'quotes_count', label: 'Quotes', className: 'a-hide-sm' },
                        { key: 'invoices_count', label: 'Invoices', className: 'a-hide-sm' },
                    ]}
                    empty={
                        <EmptyState icon="building" title={search ? 'No matching clients' : 'No clients yet'}>
                            Add one here, or use “Create client & quote” on a won enquiry.
                        </EmptyState>
                    }
                />
            </Card>
            <Pagination meta={data?.meta} onPage={setPage} />
            <ClientModal
                open={modal}
                onClose={() => setModal(false)}
                onSaved={(c) => {
                    setModal(false);
                    navigate(`/billing/clients/${c.id}`);
                }}
            />
        </>
    );
}

export function ClientDetail() {
    const { id } = useParams();
    const { can } = useAuth();
    const navigate = useNavigate();
    const toast = useToast();
    const confirm = useConfirm();
    const { data, error, reload } = useFetch(`/clients/${id}`);
    const [editing, setEditing] = useState(false);

    if (error) return <ErrorNote error={error} />;
    if (!data) return <Skeleton height={400} />;
    const c = data.data;

    // What's owed, invoiced and collected, per currency (a client may be billed in several currencies).
    const sumBy = (rows, field, statuses) => {
        const totals = {};
        rows.filter((r) => !statuses || statuses.includes(r.status)).forEach((r) => {
            totals[r.currency] = (totals[r.currency] ?? 0) + Number(r[field] ?? 0);
        });
        return Object.entries(totals);
    };
    const owed = sumBy(data.invoices, 'balance', ['sent', 'partial', 'overdue']);
    const owedCount = data.invoices.filter((i) => ['sent', 'partial', 'overdue'].includes(i.status)).length;
    const invoiced = sumBy(data.invoices, 'total', ['sent', 'partial', 'paid', 'overdue']);
    const collected = sumBy(data.invoices, 'paid');

    const remove = async () => {
        if (!(await confirm({ title: 'Delete this client?', message: 'This cannot be undone.', confirmLabel: 'Delete', danger: true }))) return;
        try {
            await api.del(`/clients/${id}`);
            toast('Client deleted.');
            navigate('/billing/clients', { replace: true });
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    const docTable = (rows, kind) => (
        <Table
            rows={rows}
            onRowClick={(d) => navigate(`/billing/${kind}s/${d.id}`)}
            columns={[
                { key: 'number', label: 'Number', render: (d) => <b>{d.number}</b> },
                { key: 'status', label: 'Status', render: (d) => <DocStatus status={d.status} /> },
                { key: 'total', label: 'Total', className: 'a-num', render: (d) => money(d.total, d.currency) },
                { key: 'issue_date', label: 'Issued', className: 'a-muted a-nowrap a-hide-sm', render: (d) => dateOnly(d.issue_date) },
            ]}
            empty={<p className="a-muted a-pad">No {kind}s yet.</p>}
        />
    );

    return (
        <>
            <PageHeader
                back={{ to: '/billing/clients', label: 'All clients' }}
                title={c.company || c.name}
                subtitle={c.company ? `${c.name} · ${c.email}` : c.email}
                actions={
                    <>
                        {can('billing.manage') && (
                            <>
                                <Button variant="secondary" icon="edit" onClick={() => setEditing(true)}>
                                    Edit
                                </Button>
                                <Button variant="secondary" icon="plus" to={`/billing/quotes/new?client=${c.id}`}>
                                    Quote
                                </Button>
                                <Button icon="plus" to={`/billing/invoices/new?client=${c.id}`}>
                                    Invoice
                                </Button>
                            </>
                        )}
                        {can('billing.delete') && <Button variant="danger" icon="trash" onClick={remove} aria-label="Delete client" />}
                    </>
                }
            />
            <div className="a-stats money">
                <div className={`a-stat${owed.length ? ' is-alert' : ''}`}>
                    <span className="a-stat-top">Still to collect</span>
                    {owed.length ? owed.map(([cur, amt]) => <strong key={cur}>{money(amt, cur)}</strong>) : <strong>Nothing</strong>}
                    <span className="a-stat-note">{owedCount ? `${owedCount} unpaid invoice(s)` : 'All invoices are paid'}</span>
                </div>
                <div className="a-stat">
                    <span className="a-stat-top">Invoiced</span>
                    {invoiced.length ? invoiced.map(([cur, amt]) => <strong key={cur}>{money(amt, cur)}</strong>) : <strong>—</strong>}
                    <span className="a-stat-note">Sent invoices, before payments</span>
                </div>
                <div className="a-stat">
                    <span className="a-stat-top">Collected</span>
                    {collected.length ? collected.map(([cur, amt]) => <strong key={cur}>{money(amt, cur)}</strong>) : <strong>—</strong>}
                    <span className="a-stat-note">Payments received</span>
                </div>
            </div>
            <div className="a-grid-detail">
                <div className="a-stack">
                    <Card title="Invoices" padded={false}>
                        {docTable(data.invoices, 'invoice')}
                    </Card>
                    <Card title="Quotes" padded={false}>
                        {docTable(data.quotes, 'quote')}
                    </Card>
                </div>
                <Card title="Details">
                    <dl className="a-dl">
                        <dt>Contact</dt>
                        <dd>{c.name}</dd>
                        <dt>Email</dt>
                        <dd>
                            <a className="a-link" href={`mailto:${c.email}`}>
                                {c.email}
                            </a>
                        </dd>
                        <dt>Phone</dt>
                        <dd>{c.phone || '—'}</dd>
                        <dt>Address</dt>
                        <dd className="a-prewrap">{c.address || '—'}</dd>
                        <dt>Tax no.</dt>
                        <dd>{c.tax_number || '—'}</dd>
                        <dt>Notes</dt>
                        <dd className="a-prewrap">{c.notes || '—'}</dd>
                        {c.enquiry_id && (
                            <>
                                <dt>Source</dt>
                                <dd>
                                    <Link className="a-link" to={`/enquiries/${c.enquiry_id}`}>
                                        Enquiry #{c.enquiry_id}
                                    </Link>
                                </dd>
                            </>
                        )}
                    </dl>
                </Card>
            </div>
            <ClientModal
                open={editing}
                client={c}
                onClose={() => setEditing(false)}
                onSaved={() => {
                    setEditing(false);
                    reload();
                }}
            />
        </>
    );
}
