import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import useFetch, { useDebounced } from '../../hooks/useFetch';
import { api } from '../../lib/api';
import { dateOnly, isoDate, money } from '../../lib/format';
import { Badge, Button, Card, EmptyState, ErrorNote, Input, Modal, PageHeader, Pagination, SearchInput, Select, Table, cx } from '../../ui';
import { useConfirm, useToast } from '../../ui/feedback';
import { LockNote, useAccounting } from './shared';

const SOURCE = { invoice: ['Invoice', 'blue'], expense: ['Expense', 'amber'] };

export function EntrySource({ entry }) {
    if (entry.is_manual) return <Badge tone="accent">Manual</Badge>;
    const [label, tone] = SOURCE[entry.source?.kind] ?? ['Automatic', 'neutral'];
    return <Badge tone={tone}>{label}</Badge>;
}

const sourceLink = (s) => (s?.kind === 'invoice' ? `/billing/invoices/${s.id}` : s?.kind === 'expense' ? `/accounting/expenses?open=${s.id}` : null);

const newLine = () => ({ account_id: '', debit: '', credit: '', memo: '' });
const cents = (v) => Math.round((Number(v) || 0) * 100);

/** Create or edit a manual entry; lines must balance before it can be posted. */
function EntryEditor({ open, entry, accounts, base, onClose, onSaved }) {
    const toast = useToast();
    const [form, setForm] = useState(null);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const [openedFor, setOpenedFor] = useState(null);
    const key = open ? (entry?.id ?? 'new') : null;
    if (key !== openedFor) {
        setOpenedFor(key);
        setErrors({});
        setForm(
            entry
                ? { date: entry.date, description: entry.description, lines: entry.lines.map((l) => ({ account_id: l.account_id, debit: l.debit || '', credit: l.credit || '', memo: l.memo ?? '' })) }
                : { date: isoDate(), description: '', lines: [newLine(), newLine()] },
        );
    }
    if (!form) return null;

    const setLine = (i, patch) => setForm((f) => ({ ...f, lines: f.lines.map((l, j) => (j === i ? { ...l, ...patch } : l)) }));
    const debit = form.lines.reduce((s, l) => s + cents(l.debit), 0);
    const credit = form.lines.reduce((s, l) => s + cents(l.credit), 0);
    const balanced = debit === credit && debit > 0;
    const options = [{ value: '', label: 'Choose account…' }, ...accounts.map((a) => ({ value: a.id, label: `${a.code} · ${a.name}` }))];

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setErrors({});
        try {
            const body = { ...form, lines: form.lines.map((l) => ({ ...l, debit: l.debit || null, credit: l.credit || null })) };
            const res = entry ? await api.put(`/accounting/journal/${entry.id}`, body) : await api.post('/accounting/journal', body);
            toast(entry ? 'Entry updated.' : `Entry ${res.data.number} posted.`);
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
            title={entry ? `Edit ${entry.number}` : 'New journal entry'}
            description="For things invoices and expenses don't cover: opening balances, owner's capital, salaries, depreciation, corrections."
            footer={
                <>
                    <span className={cx('a-balance-pill', balanced ? 'ok' : 'bad')} role="status">
                        {balanced ? 'Balanced' : debit + credit === 0 ? 'Enter amounts' : `Difference ${money(Math.abs(debit - credit) / 100, base)}`}
                    </span>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="entry-form" loading={busy} icon="check" disabled={!balanced}>
                        {entry ? 'Save entry' : 'Post entry'}
                    </Button>
                </>
            }
        >
            <form id="entry-form" className="a-form" onSubmit={submit} noValidate>
                {errors.form && <p className="a-alert">{errors.form}</p>}
                {errors.lines && <p className="a-alert">{errors.lines}</p>}
                <div className="a-form-grid">
                    <Input label="Date" type="date" required value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} error={errors.date} />
                    <Input label="Description" required maxLength={250} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} error={errors.description} placeholder="e.g. Opening balances" />
                </div>
                <div className="a-jlines">
                    <div className="a-jline a-line-head" aria-hidden="true">
                        <span>Account</span>
                        <span>Debit</span>
                        <span>Credit</span>
                        <span>Memo</span>
                        <span />
                    </div>
                    {form.lines.map((l, i) => (
                        <div className="a-jline" key={i}>
                            <Select aria-label={`Line ${i + 1} account`} value={l.account_id} onChange={(e) => setLine(i, { account_id: e.target.value })} options={options} error={errors[`lines.${i}.account_id`]} />
                            <Input aria-label={`Line ${i + 1} debit`} type="number" min="0" step="0.01" inputMode="decimal" placeholder="Debit" value={l.debit} onChange={(e) => setLine(i, { debit: e.target.value, credit: e.target.value ? '' : l.credit })} error={errors[`lines.${i}.debit`]} />
                            <Input aria-label={`Line ${i + 1} credit`} type="number" min="0" step="0.01" inputMode="decimal" placeholder="Credit" value={l.credit} onChange={(e) => setLine(i, { credit: e.target.value, debit: e.target.value ? '' : l.debit })} error={errors[`lines.${i}.credit`]} />
                            <Input aria-label={`Line ${i + 1} memo`} maxLength={250} placeholder="Memo" value={l.memo} onChange={(e) => setLine(i, { memo: e.target.value })} />
                            <Button variant="ghost" size="sm" icon="trash" aria-label={`Remove line ${i + 1}`} disabled={form.lines.length <= 2} onClick={() => setForm((f) => ({ ...f, lines: f.lines.filter((_, j) => j !== i) }))} />
                        </div>
                    ))}
                    <div className="a-jline a-jtotal">
                        <Button variant="ghost" size="sm" icon="plus" onClick={() => setForm((f) => ({ ...f, lines: [...f.lines, newLine()] }))} disabled={form.lines.length >= 50}>
                            Add line
                        </Button>
                        <span className="a-num">{money(debit / 100, base)}</span>
                        <span className="a-num">{money(credit / 100, base)}</span>
                        <span />
                        <span />
                    </div>
                </div>
            </form>
        </Modal>
    );
}

function EntryModal({ entry, base, onClose, onEdit, onDeleted }) {
    const { can } = useAuth();
    const toast = useToast();
    const confirm = useConfirm();
    const link = entry && sourceLink(entry.source);

    const remove = async () => {
        if (!(await confirm({ title: `Delete ${entry.number}?`, message: 'The entry is removed from the books. This cannot be undone.', confirmLabel: 'Delete', danger: true }))) return;
        try {
            await api.del(`/accounting/journal/${entry.id}`);
            toast('Entry deleted.');
            onDeleted();
        } catch (err) {
            toast(err.errors?.date ?? err.message, 'error');
        }
    };

    return (
        <Modal
            open={!!entry}
            onClose={onClose}
            size="lg"
            title={entry ? `${entry.number} · ${dateOnly(entry.date)}` : ''}
            description={entry?.description}
            footer={
                entry && (
                    <>
                        {entry.is_manual && can('accounting.manage') && (
                            <>
                                <Button variant="danger" icon="trash" onClick={remove} className="a-mr-auto">
                                    Delete
                                </Button>
                                <Button variant="secondary" icon="edit" onClick={onEdit}>
                                    Edit
                                </Button>
                            </>
                        )}
                        {link && (
                            <Button variant="secondary" icon="external" to={link}>
                                Open {entry.source.kind}
                            </Button>
                        )}
                        <Button onClick={onClose}>Close</Button>
                    </>
                )
            }
        >
            {entry && (
                <>
                    <p className="a-entry-meta">
                        <EntrySource entry={entry} />
                        {entry.created_by && <span className="a-muted a-small">by {entry.created_by}</span>}
                    </p>
                    <div className="a-table-wrap">
                        <table className="a-table a-report">
                            <thead>
                                <tr>
                                    <th scope="col">Account</th>
                                    <th scope="col" className="a-num">Debit</th>
                                    <th scope="col" className="a-num">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                {entry.lines.map((l) => (
                                    <tr key={l.id}>
                                        <td>
                                            <Link className="a-link" to={`/accounting/accounts/${l.account_id}`}>
                                                {l.account}
                                            </Link>
                                            {l.memo && <small className="a-block a-muted">{l.memo}</small>}
                                        </td>
                                        <td className="a-num">{l.debit ? money(l.debit, base) : ''}</td>
                                        <td className="a-num">{l.credit ? money(l.credit, base) : ''}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th scope="row">Total</th>
                                    <td className="a-num">{money(entry.total, base)}</td>
                                    <td className="a-num">{money(entry.total, base)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    {!entry.is_manual && <p className="a-muted a-small a-gap-top">Automatic entries follow their document. To change this one, edit the {entry.source?.kind ?? 'document'}.</p>}
                </>
            )}
        </Modal>
    );
}

const KINDS = [
    { value: '', label: 'All entries' },
    { value: 'auto', label: 'Automatic' },
    { value: 'manual', label: 'Manual' },
];

export default function Journal() {
    const { can } = useAuth();
    const { base, lockDate } = useAccounting();
    const [q, setQ] = useState('');
    const [kind, setKind] = useState('');
    const [page, setPage] = useState(1);
    const search = useDebounced(q);
    const { data, error, loading, reload } = useFetch('/accounting/journal', { q: search, kind, page });
    const { data: accounts } = useFetch(can('accounting.manage') ? '/accounting/accounts' : null);
    const [viewing, setViewing] = useState(null);
    const [editing, setEditing] = useState(null); // null | 'new' | entry

    const activeAccounts = (accounts?.data ?? []).filter((a) => a.is_active);
    const after = () => {
        setEditing(null);
        setViewing(null);
        reload();
    };

    return (
        <>
            <PageHeader
                title="Journal"
                subtitle="Every posting in the books. Invoices, payments and expenses post here automatically."
                actions={
                    can('accounting.manage') && (
                        <Button icon="plus" onClick={() => setEditing('new')} disabled={!accounts}>
                            New entry
                        </Button>
                    )
                }
            />
            <LockNote lockDate={lockDate} />
            <div className="a-toolbar">
                <SearchInput value={q} onChange={(v) => { setQ(v); setPage(1); }} placeholder="Search number or description…" />
                <Select aria-label="Filter by kind" value={kind} onChange={(e) => { setKind(e.target.value); setPage(1); }} options={KINDS} />
            </div>
            <ErrorNote error={error} onRetry={reload} />
            <Card padded={false}>
                <Table
                    rows={data?.data}
                    loading={loading}
                    onRowClick={setViewing}
                    columns={[
                        { key: 'date', label: 'Date', className: 'a-nowrap a-muted', render: (e) => dateOnly(e.date) },
                        {
                            key: 'description',
                            label: 'Entry',
                            render: (e) => (
                                <span className="a-two-line">
                                    <b>{e.description}</b>
                                    <small>{e.number} · {e.lines.map((l) => l.account?.split(' · ')[1]).filter(Boolean).slice(0, 3).join(', ')}</small>
                                </span>
                            ),
                        },
                        { key: 'kind', label: 'Source', className: 'a-hide-sm', render: (e) => <EntrySource entry={e} /> },
                        { key: 'total', label: 'Amount', className: 'a-num', render: (e) => <b>{money(e.total, base)}</b> },
                    ]}
                    empty={
                        <EmptyState icon="book" title={search || kind ? 'No matching entries' : 'The journal is empty'}>
                            Send an invoice or record an expense and its entry appears here. Use “New entry” for opening balances.
                        </EmptyState>
                    }
                />
            </Card>
            <Pagination meta={data?.meta} onPage={setPage} />
            <EntryModal entry={editing ? null : viewing} base={base} onClose={() => setViewing(null)} onEdit={() => setEditing(viewing)} onDeleted={after} />
            {accounts && <EntryEditor open={!!editing} entry={editing === 'new' ? null : editing} accounts={activeAccounts} base={base} onClose={() => setEditing(null)} onSaved={after} />}
        </>
    );
}
