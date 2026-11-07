import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import useFetch, { useDebounced } from '../../hooks/useFetch';
import { api, fileUrl } from '../../lib/api';
import { addDays, dateOnly, dateTime, isoDate, money, timeAgo } from '../../lib/format';
import { Badge, Button, Card, EmptyState, ErrorNote, Input, Modal, PageHeader, Pagination, SearchInput, Select, Skeleton, Table } from '../../ui';
import { useConfirm, useToast } from '../../ui/feedback';
import Icon from '../../ui/Icon';
import { ClientModal } from './Clients';

/* ---------- Shared config ---------- */

const KINDS = {
    quote: {
        label: 'Quote',
        plural: 'Quotes',
        path: '/billing/quotes',
        api: '/quotes',
        dateField: 'valid_until',
        dateLabel: 'Valid until',
        subtitle: 'Proposals your clients can accept online.',
        statuses: ['draft', 'sent', 'accepted', 'declined', 'expired', 'invoiced'],
    },
    invoice: {
        label: 'Invoice',
        plural: 'Invoices',
        path: '/billing/invoices',
        api: '/invoices',
        dateField: 'due_date',
        dateLabel: 'Due date',
        subtitle: 'Bills you send to clients, and the payments against them.',
        statuses: ['draft', 'sent', 'partial', 'overdue', 'paid', 'void'],
    },
};

const STATUS = {
    draft: ['Draft', 'neutral'],
    sent: ['Sent', 'blue'],
    accepted: ['Accepted', 'green'],
    declined: ['Declined', 'accent'],
    expired: ['Expired', 'amber'],
    invoiced: ['Invoiced', 'green'],
    partial: ['Partly paid', 'amber'],
    overdue: ['Overdue', 'accent'],
    paid: ['Paid', 'green'],
    void: ['Void', 'neutral'],
};

export function DocStatus({ status }) {
    const [label, tone] = STATUS[status] ?? [status, 'neutral'];
    return (
        <Badge tone={tone} dot>
            {label}
        </Badge>
    );
}

/** Mirrors App\Support\Billing::totals (integer cents) so the editor shows exactly what will be saved. */
function totals(items, discount, taxRate) {
    const cents = (v) => Math.round((Number(v) || 0) * 100);
    const lines = items.map((i) => Math.round((Number(i.quantity) || 0) * cents(i.unit_price)));
    const subtotal = lines.reduce((a, b) => a + b, 0);
    const disc = Math.min(Math.max(cents(discount), 0), subtotal);
    const tax = Math.round(((subtotal - disc) * (Number(taxRate) || 0)) / 100);
    return { lines: lines.map((c) => c / 100), subtotal: subtotal / 100, discount: disc / 100, tax: tax / 100, total: (subtotal - disc + tax) / 100 };
}

/* ---------- List ---------- */

export function DocumentList({ kind }) {
    const cfg = KINDS[kind];
    const { can } = useAuth();
    const navigate = useNavigate();
    const [params, setParams] = useSearchParams();
    const [q, setQ] = useState('');
    const search = useDebounced(q);
    const status = params.get('status') ?? '';
    const [page, setPage] = useState(1);
    const { data, error, loading, reload } = useFetch(cfg.api, { q: search, status, page });

    return (
        <>
            <PageHeader
                title={cfg.plural}
                subtitle={cfg.subtitle}
                actions={
                    can('billing.manage') && (
                        <Button to={`${cfg.path}/new`} icon="plus">
                            New {cfg.label.toLowerCase()}
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
                    placeholder="Search number or client…"
                />
                <Select
                    aria-label="Filter by status"
                    value={status}
                    onChange={(e) => {
                        setParams(e.target.value ? { status: e.target.value } : {}, { replace: true });
                        setPage(1);
                    }}
                    options={[{ value: '', label: 'All statuses' }, ...cfg.statuses.map((s) => ({ value: s, label: STATUS[s][0] }))]}
                />
            </div>
            <ErrorNote error={error} onRetry={reload} />
            <Card padded={false}>
                <Table
                    rows={data?.data}
                    loading={loading}
                    onRowClick={(d) => navigate(`${cfg.path}/${d.id}`)}
                    columns={[
                        {
                            key: 'number',
                            label: cfg.label,
                            render: (d) => (
                                <span className="a-cell-user">
                                    <span>
                                        <b>{d.number}</b>
                                        <small>{d.client?.company || d.client?.name}</small>
                                    </span>
                                </span>
                            ),
                        },
                        { key: 'status', label: 'Status', render: (d) => <DocStatus status={d.status} /> },
                        { key: 'total', label: 'Total', className: 'a-num', render: (d) => <b>{money(d.total, d.currency)}</b> },
                        ...(kind === 'invoice' ? [{ key: 'balance', label: 'Balance', className: 'a-num a-hide-sm', render: (d) => money(d.balance, d.currency) }] : []),
                        { key: cfg.dateField, label: cfg.dateLabel, className: 'a-muted a-nowrap a-hide-sm', render: (d) => dateOnly(d[cfg.dateField]) },
                        {
                            key: 'pdf',
                            label: '',
                            className: 'a-actions',
                            render: (d) => (
                                <Button variant="ghost" size="sm" icon="download" href={pdfUrl(cfg, d)} download aria-label={`Download ${d.number} as PDF`} title="Download PDF" onClick={(e) => e.stopPropagation()} />
                            ),
                        },
                    ]}
                    empty={
                        <EmptyState icon="file" title={search || status ? `No matching ${cfg.plural.toLowerCase()}` : `No ${cfg.plural.toLowerCase()} yet`}>
                            {can('billing.manage') && !search && !status && (
                                <Button to={`${cfg.path}/new`} icon="plus" variant="secondary">
                                    Create your first {cfg.label.toLowerCase()}
                                </Button>
                            )}
                        </EmptyState>
                    }
                />
            </Card>
            <Pagination meta={data?.meta} onPage={setPage} />
        </>
    );
}

/* ---------- Editor (new + edit) ---------- */

const blankItem = () => ({ key: crypto.randomUUID(), description: '', quantity: 1, unit_price: '' });

export function DocumentEditor({ kind }) {
    const cfg = KINDS[kind];
    const { id } = useParams();
    const isNew = !id;
    const [params] = useSearchParams();
    const navigate = useNavigate();
    const toast = useToast();
    const { data: settingsData } = useFetch('/settings/billing');
    const { data: clientsData, reload: reloadClients } = useFetch('/clients', { all: 1 });
    const { data: existing, error } = useFetch(isNew ? null : `${cfg.api}/${id}`);
    const [form, setForm] = useState(null);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const [clientModal, setClientModal] = useState(false);

    useEffect(() => {
        if (form) return;
        if (!isNew && existing) {
            const d = existing.data;
            setForm({
                client_id: String(d.client_id),
                issue_date: d.issue_date,
                [cfg.dateField]: d[cfg.dateField] ?? '',
                currency: d.currency,
                ...(kind === 'invoice' && { exchange_rate: d.exchange_rate ?? '' }),
                tax_rate: d.tax_rate,
                discount: d.discount || '',
                notes: d.notes ?? '',
                items: d.items.map((i) => ({ key: crypto.randomUUID(), description: i.description, quantity: i.quantity, unit_price: i.unit_price })),
            });
        } else if (isNew && settingsData) {
            const s = settingsData.data;
            setForm({
                client_id: params.get('client') ?? '',
                issue_date: isoDate(),
                [cfg.dateField]: addDays(kind === 'invoice' ? s.due_days : s.quote_valid_days),
                currency: s.currency,
                ...(kind === 'invoice' && { exchange_rate: '' }),
                tax_rate: s.tax_rate,
                discount: '',
                notes: '',
                items: [blankItem()],
            });
        }
    }, [form, isNew, existing, settingsData, params, cfg.dateField, kind]);

    const t = useMemo(() => (form ? totals(form.items, form.discount, form.tax_rate) : null), [form]);

    if (error) return <ErrorNote error={error} />;
    if (!form || !t) return <Skeleton height={520} />;
    if (!isNew && existing && !existing.data.editable) {
        return (
            <EmptyState icon="lock" title={`This ${cfg.label.toLowerCase()} can no longer be edited`}>
                <Button to={`${cfg.path}/${id}`} variant="secondary" icon="arrow-left">
                    Back
                </Button>
            </EmptyState>
        );
    }

    const set = (patch) => setForm((f) => ({ ...f, ...patch }));
    const setItem = (key, patch) => set({ items: form.items.map((i) => (i.key === key ? { ...i, ...patch } : i)) });
    const move = (index, dir) => {
        const items = [...form.items];
        [items[index], items[index + dir]] = [items[index + dir], items[index]];
        set({ items });
    };
    const err = (k) => errors[k];
    const currencies = Object.entries(settingsData?.currencies ?? { [form.currency]: form.currency }).map(([value, label]) => ({ value, label: `${value} — ${label}` }));
    // Foreign-currency invoices need a rate into the base currency before they can be sent (posted to the books).
    const base = settingsData?.base_currency;
    const needsRate = kind === 'invoice' && base && form.currency !== base;

    const save = async (e) => {
        e.preventDefault();
        setBusy(true);
        setErrors({});
        try {
            const body = { ...form, client_id: Number(form.client_id) || null, discount: form.discount || 0, ...(kind === 'invoice' && { exchange_rate: needsRate ? form.exchange_rate || null : null }), items: form.items.map(({ description, quantity, unit_price }) => ({ description, quantity, unit_price: unit_price === '' ? 0 : unit_price })) };
            const res = isNew ? await api.post(cfg.api, body) : await api.put(`${cfg.api}/${id}`, body);
            toast(isNew ? `${cfg.label} ${res.data.number} created as a draft.` : 'Changes saved.');
            navigate(`${cfg.path}/${res.data.id}`, { replace: true });
        } catch (er) {
            const fieldErrors = Object.keys(er.errors ?? {}).length > 0;
            setErrors(fieldErrors ? er.errors : { form: er.message });
            toast(fieldErrors ? 'Please check the highlighted fields.' : er.message, 'error');
        } finally {
            setBusy(false);
        }
    };

    return (
        <form onSubmit={save} noValidate>
            <PageHeader
                back={{ to: isNew ? cfg.path : `${cfg.path}/${id}`, label: isNew ? cfg.plural : existing?.data.number }}
                title={isNew ? `New ${cfg.label.toLowerCase()}` : `Edit ${existing.data.number}`}
                subtitle="Saved as a draft. You can review it before sending."
                actions={
                    <Button type="submit" loading={busy} icon="check">
                        {isNew ? 'Create draft' : 'Save changes'}
                    </Button>
                }
            />
            {errors.form && <p className="a-alert">{errors.form}</p>}
            <div className="a-grid-detail">
                <div className="a-stack">
                    <Card title="Client & dates">
                        <div className="a-form">
                            <div className="a-client-pick">
                                <Select
                                    label="Client"
                                    value={form.client_id}
                                    onChange={(e) => set({ client_id: e.target.value })}
                                    error={err('client_id')}
                                    options={[{ value: '', label: clientsData ? 'Choose a client…' : 'Loading…' }, ...(clientsData?.data ?? []).map((c) => ({ value: String(c.id), label: c.company ? `${c.company} (${c.name})` : c.name }))]}
                                />
                                <Button variant="secondary" icon="plus" onClick={() => setClientModal(true)}>
                                    New client
                                </Button>
                            </div>
                            <div className="a-form-grid three">
                                <Input label="Issue date" type="date" value={form.issue_date} onChange={(e) => set({ issue_date: e.target.value })} error={err('issue_date')} />
                                <Input label={cfg.dateLabel} type="date" value={form[cfg.dateField]} onChange={(e) => set({ [cfg.dateField]: e.target.value })} error={err(cfg.dateField)} />
                                <Select label="Currency" value={form.currency} onChange={(e) => set({ currency: e.target.value })} options={currencies} error={err('currency')} />
                            </div>
                            {needsRate && (
                                <Input
                                    label={`Exchange rate: 1 ${form.currency} = ? ${base}`}
                                    type="number"
                                    min="0"
                                    step="any"
                                    inputMode="decimal"
                                    value={form.exchange_rate}
                                    onChange={(e) => set({ exchange_rate: e.target.value })}
                                    error={err('exchange_rate')}
                                    hint={Number(form.exchange_rate) > 0 ? `Books this invoice at ${money(t.total * Number(form.exchange_rate), base)}. Needed before sending.` : `Your books are in ${base}. Use the rate on the issue date; needed before sending.`}
                                />
                            )}
                        </div>
                    </Card>

                    <Card title="Line items" subtitle="What you're charging for.">
                        <div className="a-lines" role="table" aria-label="Line items">
                            <div className="a-line a-line-head" role="row">
                                <span role="columnheader">Description</span>
                                <span role="columnheader">Qty</span>
                                <span role="columnheader">Unit price</span>
                                <span role="columnheader" className="a-num">
                                    Amount
                                </span>
                                <span />
                            </div>
                            {form.items.map((item, i) => (
                                <div key={item.key} className="a-line" role="row">
                                    <Input as="textarea" rows={1} aria-label={`Line ${i + 1} description`} value={item.description} onChange={(e) => setItem(item.key, { description: e.target.value })} error={err(`items.${i}.description`)} maxLength={500} placeholder="e.g. Website design & development" />
                                    <Input type="number" min="0" step="0.01" aria-label={`Line ${i + 1} quantity`} value={item.quantity} onChange={(e) => setItem(item.key, { quantity: e.target.value })} error={err(`items.${i}.quantity`)} />
                                    <Input type="number" min="0" step="0.01" aria-label={`Line ${i + 1} unit price`} value={item.unit_price} onChange={(e) => setItem(item.key, { unit_price: e.target.value })} error={err(`items.${i}.unit_price`)} placeholder="0.00" />
                                    <span className="a-num a-line-amount">{money(t.lines[i], form.currency)}</span>
                                    <span className="a-line-tools">
                                        <Button variant="ghost" size="sm" icon="chevron" className="a-up" disabled={i === 0} onClick={() => move(i, -1)} aria-label="Move up" />
                                        <Button variant="ghost" size="sm" icon="chevron" disabled={i === form.items.length - 1} onClick={() => move(i, 1)} aria-label="Move down" />
                                        <Button variant="ghost" size="sm" icon="trash" disabled={form.items.length === 1} onClick={() => set({ items: form.items.filter((x) => x.key !== item.key) })} aria-label="Remove line" />
                                    </span>
                                </div>
                            ))}
                        </div>
                        {err('items') && <p className="a-field-error">{err('items')}</p>}
                        <Button variant="secondary" size="sm" icon="plus" onClick={() => set({ items: [...form.items, blankItem()] })} disabled={form.items.length >= 100}>
                            Add line
                        </Button>
                    </Card>

                    <Card title="Notes" subtitle="Shown on this document. The standard terms come from Billing settings.">
                        <div className="a-form">
                            <Input as="textarea" rows={3} label="Notes" value={form.notes} onChange={(e) => set({ notes: e.target.value })} maxLength={2000} placeholder="e.g. Thanks for choosing ZAIRON Systems!" />
                        </div>
                    </Card>
                </div>

                <div className="a-stack a-sticky">
                    <Card title="Totals">
                        <div className="a-form">
                            <div className="a-form-grid">
                                <Input label="Discount" type="number" min="0" step="0.01" value={form.discount} onChange={(e) => set({ discount: e.target.value })} error={err('discount')} placeholder="0.00" />
                                <Input label="Tax %" type="number" min="0" max="100" step="0.01" value={form.tax_rate} onChange={(e) => set({ tax_rate: e.target.value })} error={err('tax_rate')} />
                            </div>
                            <dl className="a-totals">
                                <dt>Subtotal</dt>
                                <dd>{money(t.subtotal, form.currency)}</dd>
                                {t.discount > 0 && (
                                    <>
                                        <dt>Discount</dt>
                                        <dd>−{money(t.discount, form.currency)}</dd>
                                    </>
                                )}
                                {Number(form.tax_rate) > 0 && (
                                    <>
                                        <dt>Tax ({form.tax_rate}%)</dt>
                                        <dd>{money(t.tax, form.currency)}</dd>
                                    </>
                                )}
                                <dt className="grand">Total</dt>
                                <dd className="grand">{money(t.total, form.currency)}</dd>
                            </dl>
                            <Button type="submit" loading={busy} icon="check" className="a-btn-block">
                                {isNew ? 'Create draft' : 'Save changes'}
                            </Button>
                        </div>
                    </Card>
                </div>
            </div>
            <ClientModal
                open={clientModal}
                onClose={() => setClientModal(false)}
                onSaved={(c) => {
                    setClientModal(false);
                    reloadClients();
                    set({ client_id: String(c.id) });
                }}
            />
        </form>
    );
}

/* ---------- Detail view + actions ---------- */

/** `copy: 'sent'` = the PDF saved when it was last emailed; `inline` opens it in the browser. */
const pdfUrl = (cfg, doc, { copy, inline } = {}) => {
    const query = new URLSearchParams({ ...(copy && { copy }), ...(inline && { inline: 1 }) }).toString();
    return fileUrl(`${cfg.api}/${doc.id}/pdf${query ? `?${query}` : ''}`);
};

/** The document as a PDF: download or preview the current version, and the exact copy last emailed. */
function PdfCard({ doc, cfg }) {
    return (
        <Card title="PDF" subtitle="Branded A4, attached automatically when you send.">
            <div className="a-pdf">
                <a className="a-pdf-file" href={pdfUrl(cfg, doc, { inline: true })} target="_blank" rel="noopener">
                    <span className="a-pdf-icon" aria-hidden="true">
                        <Icon name="file" />
                        <b>PDF</b>
                    </span>
                    <span className="a-pdf-name">
                        <b>
                            {cfg.label} {doc.number}.pdf
                        </b>
                        <small>Current version · open preview</small>
                    </span>
                    <Icon name="external" className="a-pdf-go" />
                </a>
                <Button variant="secondary" icon="download" href={pdfUrl(cfg, doc)} download className="a-btn-block">
                    Download PDF
                </Button>
                {doc.has_sent_pdf && (
                    <a className="a-pdf-sent" href={pdfUrl(cfg, doc, { copy: 'sent' })} download>
                        <Icon name="mail" />
                        <span>
                            <b>Copy emailed to the client</b>
                            <small>Saved {doc.sent_at ? dateTime(doc.sent_at) : ''}</small>
                        </span>
                        <Icon name="download" />
                    </a>
                )}
            </div>
        </Card>
    );
}

function SendModal({ open, doc, cfg, onClose, onSent }) {
    const toast = useToast();
    const [email, setEmail] = useState('');
    const [message, setMessage] = useState('');
    const [busy, setBusy] = useState(false);
    const [err, setErr] = useState({});
    useEffect(() => {
        if (open) {
            setEmail(doc.client?.email ?? '');
            setMessage('');
            setErr({});
        }
    }, [open, doc]);

    const send = async (e) => {
        e.preventDefault();
        setBusy(true);
        try {
            const res = await api.post(`${cfg.api}/${doc.id}/send`, { email, message: message || null });
            toast(`${cfg.label} sent to ${email}.`);
            onSent(res.data);
        } catch (er) {
            setErr(Object.keys(er.errors).length ? er.errors : { form: er.message });
        } finally {
            setBusy(false);
        }
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={`Send ${doc.number}`}
            description="The client gets an email with the PDF attached and a secure link to view it online. A copy of the PDF is saved here."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="send-form" loading={busy} icon="send">
                        Send {cfg.label.toLowerCase()}
                    </Button>
                </>
            }
        >
            <form id="send-form" className="a-form" onSubmit={send} noValidate>
                {err.form && <p className="a-alert">{err.form}</p>}
                <Input label="To" type="email" value={email} onChange={(e) => setEmail(e.target.value)} error={err.email} />
                <Input as="textarea" rows={5} label="Personal message (optional)" value={message} onChange={(e) => setMessage(e.target.value)} maxLength={3000} hint="Leave empty to use the standard wording." />
            </form>
        </Modal>
    );
}

function PaymentModal({ open, invoice, methods, base, accounts, onClose, onSaved }) {
    const toast = useToast();
    const [form, setForm] = useState(null);
    const [err, setErr] = useState({});
    const [busy, setBusy] = useState(false);
    const foreign = base && invoice.currency !== base;
    useEffect(() => {
        if (open) {
            setForm({ amount: invoice.balance, paid_on: isoDate(), method: methods[0], reference: '', account_id: '', exchange_rate: invoice.exchange_rate ?? '' });
            setErr({});
        }
    }, [open, invoice, methods]);

    const save = async (e) => {
        e.preventDefault();
        setBusy(true);
        try {
            const res = await api.post(`/invoices/${invoice.id}/payments`, { ...form, account_id: form.account_id || null, exchange_rate: foreign ? form.exchange_rate || null : null });
            toast('Payment recorded.');
            onSaved(res.data);
        } catch (er) {
            setErr(Object.keys(er.errors).length ? er.errors : { form: er.message });
        } finally {
            setBusy(false);
        }
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Record a payment"
            description={`Balance due: ${money(invoice.balance, invoice.currency)}`}
            size="sm"
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="pay-form" loading={busy} icon="check">
                        Record payment
                    </Button>
                </>
            }
        >
            {form && (
                <form id="pay-form" className="a-form" onSubmit={save} noValidate>
                    {err.form && <p className="a-alert">{err.form}</p>}
                    <div className="a-form-grid">
                        <Input label={`Amount (${invoice.currency})`} type="number" min="0" step="0.01" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} error={err.amount} />
                        <Input label="Date" type="date" max={isoDate()} value={form.paid_on} onChange={(e) => setForm({ ...form, paid_on: e.target.value })} error={err.paid_on} />
                    </div>
                    {foreign && (
                        <Input
                            label={`Rate received: 1 ${invoice.currency} = ? ${base}`}
                            type="number"
                            min="0"
                            step="any"
                            inputMode="decimal"
                            value={form.exchange_rate}
                            onChange={(e) => setForm({ ...form, exchange_rate: e.target.value })}
                            error={err.exchange_rate}
                            hint={Number(form.exchange_rate) && Number(form.amount) ? `${money(Number(form.amount) * Number(form.exchange_rate), base)} into the bank. Any difference from the invoice rate is booked as exchange gain/loss.` : 'The rate your bank actually converted at.'}
                        />
                    )}
                    <div className="a-form-grid">
                        <Select label="Method" value={form.method} onChange={(e) => setForm({ ...form, method: e.target.value })} options={methods.map((m) => ({ value: m, label: m }))} />
                        <Select label="Deposited to" value={form.account_id} onChange={(e) => setForm({ ...form, account_id: e.target.value })} error={err.account_id} options={[{ value: '', label: 'Default bank account' }, ...accounts.map((a) => ({ value: a.id, label: a.name }))]} />
                    </div>
                    <Input label="Reference (optional)" value={form.reference} onChange={(e) => setForm({ ...form, reference: e.target.value })} maxLength={120} placeholder="Transaction ID, cheque no.…" />
                </form>
            )}
        </Modal>
    );
}

export function DocumentView({ kind }) {
    const cfg = KINDS[kind];
    const { id } = useParams();
    const { can } = useAuth();
    const navigate = useNavigate();
    const toast = useToast();
    const confirm = useConfirm();
    const { data, error, setData } = useFetch(`${cfg.api}/${id}`);
    const [modal, setModal] = useState(null);
    const doc = data?.data;

    if (error) return <ErrorNote error={error} />;
    if (!doc) return <Skeleton height={520} />;

    const update = (d) => setData((prev) => ({ ...prev, data: d }));
    const act = async (fn, message) => {
        try {
            const res = await fn();
            if (res?.data) update(res.data);
            toast(res?.message ?? message);
            return res;
        } catch (er) {
            toast(er.message, 'error');
        }
    };
    const manage = can('billing.manage');
    const isInvoice = kind === 'invoice';
    const answered = ['accepted', 'declined', 'invoiced'].includes(doc.status);

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(doc.public_url);
            toast('Client link copied.');
        } catch {
            toast('Could not copy. Use "Client view" instead.', 'error');
        }
    };

    /** Quote → invoice in one click: items, tax, discount and totals are copied, not retyped. */
    const convertQuote = async () => {
        try {
            const res = await api.post(`/quotes/${doc.id}/convert`);
            toast(`Invoice ${res.data.number} created from this quote.`);
            navigate(`/billing/invoices/${res.data.id}`);
        } catch (er) {
            toast(er.message, 'error');
        }
    };
    const timeline = [
        ['Created', doc.created_at],
        ['Sent', doc.sent_at],
        ['Viewed by client', doc.viewed_at],
        ...(isInvoice ? [['Paid in full', doc.paid_at]] : [[doc.status === 'declined' ? 'Declined' : 'Accepted', doc.responded_at]]),
    ];

    return (
        <>
            <PageHeader
                back={{ to: cfg.path, label: cfg.plural }}
                title={
                    <span className="a-title-status">
                        {doc.number} <DocStatus status={doc.status} />
                    </span>
                }
                subtitle={`${doc.client?.company || doc.client?.name} · ${money(doc.total, doc.currency)}`}
                actions={
                    <>
                        {!isInvoice && manage && doc.status === 'accepted' && (
                            <Button icon="arrow" onClick={convertQuote}>
                                Create invoice
                            </Button>
                        )}
                        {!isInvoice && doc.invoice_id && (
                            <Button variant="secondary" icon="arrow" to={`/billing/invoices/`}>
                                View invoice
                            </Button>
                        )}
                        <Button variant="secondary" icon="download" href={pdfUrl(cfg, doc)} download>
                            PDF
                        </Button>
                        {doc.status !== 'draft' && (
                            <>
                                <Button variant="ghost" icon="external" href={doc.public_url} target="_blank" rel="noopener">
                                    Client view
                                </Button>
                                <Button variant="ghost" icon="send" onClick={copyLink}>
                                    Copy link
                                </Button>
                            </>
                        )}
                        {manage && doc.editable && (
                            <Button variant="secondary" icon="edit" to={`${cfg.path}/${doc.id}/edit`}>
                                Edit
                            </Button>
                        )}
                        {manage && doc.editable && (
                            <Button icon="send" onClick={() => setModal('send')}>
                                {doc.sent_at ? 'Resend' : 'Send'}
                            </Button>
                        )}
                    </>
                }
            />

            <div className="a-grid-detail">
                <div className="a-stack">
                    {!isInvoice && manage && ['sent', 'expired', 'accepted'].includes(doc.status) && (
                        <div className="a-callout">
                            <Icon name="spark" />
                            <p>{doc.status === 'accepted' ? 'Accepted! Turn it into an invoice in one click.' : 'Waiting for the client. Record their answer if they replied by email.'}</p>
                            <div className="a-callout-actions">
                                {doc.status !== 'accepted' && (
                                    <>
                                        <Button variant="secondary" size="sm" onClick={() => act(() => api.post(`/quotes/${doc.id}/respond`, { status: 'declined' }), 'Marked as declined.')}>
                                            Declined
                                        </Button>
                                        <Button variant="secondary" size="sm" onClick={() => act(() => api.post(`/quotes/${doc.id}/respond`, { status: 'accepted' }), 'Marked as accepted.')}>
                                            Accepted
                                        </Button>
                                    </>
                                )}
                                <Button size="sm" icon="arrow" onClick={convertQuote}>
                                    Convert to invoice
                                </Button>
                            </div>
                        </div>
                    )}
                    {isInvoice && doc.status === 'overdue' && manage && (
                        <div className="a-callout danger">
                            <Icon name="activity" />
                            <p>
                                Overdue since {dateOnly(doc.due_date)}.{doc.last_reminder_at ? ` Last reminder ${timeAgo(doc.last_reminder_at)}.` : ''}
                            </p>
                            <div className="a-callout-actions">
                                <Button size="sm" icon="send" onClick={() => act(() => api.post(`/invoices/${doc.id}/remind`))}>
                                    Send reminder
                                </Button>
                            </div>
                        </div>
                    )}

                    <Card padded={false}>
                        <div className="a-doc">
                            <div className="a-doc-head">
                                <div>
                                    <p className="a-doc-kind">{cfg.label}</p>
                                    <h2>{doc.number}</h2>
                                </div>
                                <dl>
                                    <dt>Issued</dt>
                                    <dd>{dateOnly(doc.issue_date)}</dd>
                                    <dt>{cfg.dateLabel}</dt>
                                    <dd>{dateOnly(doc[cfg.dateField])}</dd>
                                    {isInvoice && data.base_currency && doc.currency !== data.base_currency && (
                                        <>
                                            <dt>Booked at</dt>
                                            <dd>{doc.exchange_rate ? `1 ${doc.currency} = ${doc.exchange_rate} ${data.base_currency}` : <span className="a-negative">No rate yet</span>}</dd>
                                        </>
                                    )}
                                </dl>
                            </div>
                            <div className="a-doc-client">
                                <span>{isInvoice ? 'Bill to' : 'Prepared for'}</span>
                                <Link to={`/billing/clients/${doc.client_id}`} className="a-link">
                                    {doc.client?.company || doc.client?.name}
                                </Link>
                                <small>{doc.client?.email}</small>
                            </div>
                            <table className="a-doc-items">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th className="a-num">Qty</th>
                                        <th className="a-num">Price</th>
                                        <th className="a-num">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {doc.items.map((i) => (
                                        <tr key={i.id}>
                                            <td className="a-prewrap">{i.description}</td>
                                            <td className="a-num">{i.quantity}</td>
                                            <td className="a-num">{money(i.unit_price, doc.currency)}</td>
                                            <td className="a-num">{money(i.amount, doc.currency)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <dl className="a-totals a-doc-totals">
                                <dt>Subtotal</dt>
                                <dd>{money(doc.subtotal, doc.currency)}</dd>
                                {doc.discount > 0 && (
                                    <>
                                        <dt>Discount</dt>
                                        <dd>−{money(doc.discount, doc.currency)}</dd>
                                    </>
                                )}
                                {doc.tax_rate > 0 && (
                                    <>
                                        <dt>Tax ({doc.tax_rate}%)</dt>
                                        <dd>{money(doc.tax, doc.currency)}</dd>
                                    </>
                                )}
                                <dt className="grand">Total</dt>
                                <dd className="grand">{money(doc.total, doc.currency)}</dd>
                                {isInvoice && doc.paid > 0 && (
                                    <>
                                        <dt>Paid</dt>
                                        <dd>−{money(doc.paid, doc.currency)}</dd>
                                        <dt className="grand">Balance</dt>
                                        <dd className="grand">{money(doc.balance, doc.currency)}</dd>
                                    </>
                                )}
                            </dl>
                            {doc.notes && (
                                <div className="a-doc-notes">
                                    <p className="a-prewrap">{doc.notes}</p>
                                    <p className="a-small a-muted">The standard terms and conditions are printed on the PDF from Billing settings.</p>
                                </div>
                            )}
                        </div>
                    </Card>
                </div>

                <div className="a-stack">
                    {isInvoice && (
                        <Card
                            title="Payments"
                            subtitle={`${money(doc.paid, doc.currency)} of ${money(doc.total, doc.currency)}`}
                            actions={
                                manage &&
                                ['sent', 'partial', 'overdue'].includes(doc.status) && (
                                    <Button size="sm" icon="plus" onClick={() => setModal('pay')}>
                                        Record
                                    </Button>
                                )
                            }
                        >
                            <div className="a-progress" aria-hidden="true">
                                <span style={{ width: `${doc.total ? Math.min((doc.paid / doc.total) * 100, 100) : 0}%` }} />
                            </div>
                            {doc.payments.length ? (
                                <ul className="a-payments">
                                    {doc.payments.map((p) => (
                                        <li key={p.id}>
                                            <span>
                                                <b>{money(p.amount, doc.currency)}</b>
                                                <small>
                                                    {dateOnly(p.paid_on)} · {p.method}
                                                    {p.reference ? ` · ${p.reference}` : ''}
                                                </small>
                                            </span>
                                            {manage && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    icon="trash"
                                                    aria-label="Remove payment"
                                                    onClick={async () => {
                                                        if (await confirm({ title: 'Remove this payment?', message: 'The invoice balance will be recalculated.', confirmLabel: 'Remove', danger: true })) {
                                                            act(() => api.del(`/invoices/${doc.id}/payments/${p.id}`), 'Payment removed.');
                                                        }
                                                    }}
                                                />
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="a-muted a-small">{doc.status === 'draft' ? 'Send the invoice before recording payments.' : 'No payments yet.'}</p>
                            )}
                        </Card>
                    )}
                    <PdfCard doc={doc} cfg={cfg} />
                    <Card title="Timeline">
                        <ol className="a-steps">
                            {timeline.map(([label, at]) => (
                                <li key={label} className={at ? 'done' : ''}>
                                    <span className="a-step-dot" aria-hidden="true">
                                        {at && <Icon name="check" />}
                                    </span>
                                    <span>
                                        <b>{label}</b>
                                        <small>{at ? timeAgo(at) : 'Not yet'}</small>
                                    </span>
                                </li>
                            ))}
                        </ol>
                        {!isInvoice && doc.invoice_id && (
                            <Button variant="secondary" size="sm" icon="arrow" to={`/billing/invoices/${doc.invoice_id}`}>
                                View invoice
                            </Button>
                        )}
                        {isInvoice && doc.quote_id && (
                            <Button variant="ghost" size="sm" icon="arrow-left" to={`/billing/quotes/${doc.quote_id}`}>
                                From quote {doc.quote_number}
                            </Button>
                        )}
                    </Card>
                    {can('billing.delete') && (doc.status === 'draft' || (isInvoice && ['sent', 'overdue'].includes(doc.status) && doc.paid === 0)) && (
                        <Card title="Danger zone">
                            {doc.status === 'draft' ? (
                                <Button
                                    variant="danger"
                                    icon="trash"
                                    onClick={async () => {
                                        if (await confirm({ title: `Delete ${doc.number}?`, message: 'Drafts are removed permanently.', confirmLabel: 'Delete', danger: true })) {
                                            await act(() => api.del(`${cfg.api}/${doc.id}`), 'Deleted.');
                                            navigate(cfg.path, { replace: true });
                                        }
                                    }}
                                >
                                    Delete draft
                                </Button>
                            ) : (
                                <Button
                                    variant="danger"
                                    icon="close"
                                    onClick={async () => {
                                        if (await confirm({ title: `Void ${doc.number}?`, message: 'The invoice stays on record but is cancelled. This cannot be undone.', confirmLabel: 'Void invoice', danger: true })) {
                                            act(() => api.post(`/invoices/${doc.id}/void`), 'Invoice voided.');
                                        }
                                    }}
                                >
                                    Void invoice
                                </Button>
                            )}
                        </Card>
                    )}
                    {!isInvoice && answered && <p className="a-muted a-small">This quote has been answered and can no longer be edited.</p>}
                </div>
            </div>

            <SendModal
                open={modal === 'send'}
                doc={doc}
                cfg={cfg}
                onClose={() => setModal(null)}
                onSent={(d) => {
                    setModal(null);
                    update(d);
                }}
            />
            {isInvoice && (
                <PaymentModal
                    open={modal === 'pay'}
                    invoice={doc}
                    methods={data.methods ?? ['Bank transfer']}
                    base={data.base_currency}
                    accounts={data.deposit_accounts ?? []}
                    onClose={() => setModal(null)}
                    onSaved={(d) => {
                        setModal(null);
                        update(d);
                    }}
                />
            )}
        </>
    );
}
