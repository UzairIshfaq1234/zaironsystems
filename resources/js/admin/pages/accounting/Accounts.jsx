import { useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import useFetch from '../../hooks/useFetch';
import { api } from '../../lib/api';
import { dateOnly, money } from '../../lib/format';
import { Badge, Button, Card, ErrorNote, Input, Modal, PageHeader, Select, Skeleton, Switch, Table } from '../../ui';
import { useConfirm, useToast } from '../../ui/feedback';
import { PeriodPicker, TYPE_LABELS, defaultPeriod, downloadCsv, useAccounting } from './shared';

const EMPTY = { code: '', name: '', type: 'expense', subtype: '', description: '', is_active: true };
const SUBTYPE = { bank: 'Bank', cash: 'Cash', receivable: 'Receivable', payable: 'Payable', tax: 'Tax', retained: 'Retained', revenue: 'Sales', fx: 'FX' };

function AccountModal({ open, account, types, onClose, onSaved }) {
    const toast = useToast();
    const confirm = useConfirm();
    const [form, setForm] = useState(EMPTY);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const [openedFor, setOpenedFor] = useState(null);
    const key = open ? (account?.id ?? 'new') : null;
    if (key !== openedFor) {
        setOpenedFor(key);
        setErrors({});
        setForm(account ? { ...EMPTY, ...Object.fromEntries(Object.keys(EMPTY).map((k) => [k, account[k] ?? ''])), is_active: account.is_active } : EMPTY);
    }
    const system = account?.is_system;
    const bind = (k) => ({ value: form[k], onChange: (e) => setForm({ ...form, [k]: e.target.value }), error: errors[k] });

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setErrors({});
        try {
            const body = { ...form, subtype: form.type === 'asset' ? form.subtype || null : null };
            const res = account ? await api.put(`/accounting/accounts/${account.id}`, body) : await api.post('/accounting/accounts', body);
            toast(account ? 'Account updated.' : 'Account added.');
            onSaved(res.data);
        } catch (err) {
            setErrors(Object.keys(err.errors).length ? err.errors : { form: err.message });
        } finally {
            setBusy(false);
        }
    };

    const remove = async () => {
        if (!(await confirm({ title: `Delete ${account.name}?`, message: 'Only accounts with no postings can be deleted.', confirmLabel: 'Delete', danger: true }))) return;
        try {
            await api.del(`/accounting/accounts/${account.id}`);
            toast('Account deleted.');
            onSaved(null);
        } catch (err) {
            setErrors({ form: err.message });
        }
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={account ? `Edit ${account.code} · ${account.name}` : 'New account'}
            description={system ? 'A system account: the ledger posts to it automatically, so it can be renamed but not retyped, switched off or deleted.' : undefined}
            footer={
                <>
                    {account && !system && !account.in_use && (
                        <Button variant="danger" icon="trash" onClick={remove} className="a-mr-auto">
                            Delete
                        </Button>
                    )}
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="account-form" loading={busy} icon="check">
                        {account ? 'Save' : 'Add account'}
                    </Button>
                </>
            }
        >
            <form id="account-form" className="a-form" onSubmit={submit} noValidate>
                {errors.form && <p className="a-alert">{errors.form}</p>}
                <div className="a-form-grid">
                    <Input label="Code" required maxLength={20} placeholder="e.g. 1020" {...bind('code')} />
                    <Select label="Type" disabled={system || account?.in_use} options={Object.entries(types).map(([value, label]) => ({ value, label }))} {...bind('type')} />
                </div>
                <Input label="Name" required maxLength={120} placeholder="e.g. Meezan Bank — USD" {...bind('name')} />
                {form.type === 'asset' && (
                    <Select
                        label="Holds money?"
                        disabled={system}
                        options={[
                            { value: '', label: 'No — other asset' },
                            { value: 'bank', label: 'Yes — bank account' },
                            { value: 'cash', label: 'Yes — cash' },
                        ]}
                        {...bind('subtype')}
                    />
                )}
                <Input label="Description" maxLength={255} {...bind('description')} />
                {!system && <Switch label="Active (can be used in new entries)" checked={!!form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} />}
            </form>
        </Modal>
    );
}

export function AccountList() {
    const { can } = useAuth();
    const navigate = useNavigate();
    const { base } = useAccounting();
    const { data, error, reload } = useFetch('/accounting/accounts');
    const [modal, setModal] = useState(null);
    const manage = can('accounting.manage');

    if (error) return <ErrorNote error={error} onRetry={reload} />;

    return (
        <>
            <PageHeader
                title="Chart of accounts"
                subtitle="Every account in the books with its balance today. Click one to see its ledger."
                actions={manage && <Button icon="plus" onClick={() => setModal('new')}>New account</Button>}
            />
            {!data ? (
                <Skeleton height={480} />
            ) : (
                <div className="a-stack">
                    {Object.entries(data.types).map(([type, label]) => {
                        const rows = data.data.filter((a) => a.type === type);
                        return (
                            <Card key={type} title={label} padded={false} actions={<span className="a-num a-muted">{money(rows.reduce((s, a) => s + a.balance, 0), base)}</span>}>
                                <Table
                                    rows={rows}
                                    onRowClick={(a) => navigate(`/accounting/accounts/${a.id}`)}
                                    columns={[
                                        { key: 'code', label: 'Code', className: 'a-nowrap a-muted a-code' },
                                        {
                                            key: 'name',
                                            label: 'Account',
                                            render: (a) => (
                                                <span className="a-inline-badges">
                                                    <b>{a.name}</b>
                                                    {a.subtype && <Badge tone={a.subtype === 'bank' || a.subtype === 'cash' ? 'green' : 'neutral'}>{SUBTYPE[a.subtype]}</Badge>}
                                                    {!a.is_active && <Badge>Inactive</Badge>}
                                                </span>
                                            ),
                                        },
                                        { key: 'balance', label: 'Balance', className: 'a-num', render: (a) => (a.balance ? money(a.balance, base) : <span className="a-muted">—</span>) },
                                        ...(manage
                                            ? [{ key: 'edit', label: '', className: 'a-actions', render: (a) => <Button variant="ghost" size="sm" icon="edit" aria-label={`Edit ${a.name}`} onClick={(e) => { e.stopPropagation(); setModal(a); }} /> }]
                                            : []),
                                    ]}
                                    empty={<p className="a-muted a-pad">No {label.toLowerCase()} accounts.</p>}
                                />
                            </Card>
                        );
                    })}
                </div>
            )}
            {data && (
                <AccountModal
                    open={!!modal}
                    account={modal === 'new' ? null : modal}
                    types={data.types}
                    onClose={() => setModal(null)}
                    onSaved={() => {
                        setModal(null);
                        reload();
                    }}
                />
            )}
        </>
    );
}

export function AccountLedger() {
    const { id } = useParams();
    const [params] = useSearchParams();
    const { ready, fyStart, base } = useAccounting();
    // Reports link here with ?from=&to= so the ledger opens on the same period.
    const [period, setPeriod] = useState(() => (params.get('from') && params.get('to') ? { preset: 'custom', from: params.get('from'), to: params.get('to') } : null));
    const current = period ?? (ready ? defaultPeriod(fyStart) : null);
    const { data, error, loading, reload } = useFetch(current ? `/accounting/accounts/${id}/ledger` : null, current ? { from: current.from, to: current.to } : undefined);

    if (error) return <ErrorNote error={error} onRetry={reload} />;
    const gl = data?.data;
    const m = (v) => money(v, base);

    const exportCsv = () =>
        downloadCsv(`ledger-${gl.account.code}-${current.from}-to-${current.to}.csv`, [
            ['Date', 'Entry', 'Description', 'Debit', 'Credit', 'Balance'],
            ['', '', 'Opening balance', '', '', gl.opening],
            ...gl.lines.map((l) => [l.date, l.number, l.description, l.debit || '', l.credit || '', l.balance]),
            ['', '', 'Closing balance', gl.totals.debit, gl.totals.credit, gl.closing],
        ]);

    return (
        <>
            <PageHeader
                back={{ to: '/accounting/accounts', label: 'Chart of accounts' }}
                title={gl ? `${gl.account.code} · ${gl.account.name}` : 'Ledger'}
                subtitle={gl ? `${TYPE_LABELS[gl.account.type]} account · general ledger in ${base}` : undefined}
                actions={
                    gl && (
                        <>
                            <Button variant="secondary" icon="printer" onClick={() => window.print()}>
                                Print
                            </Button>
                            <Button variant="secondary" icon="download" onClick={exportCsv}>
                                CSV
                            </Button>
                        </>
                    )
                }
            />
            {current && (
                <div className="a-toolbar a-report-filters">
                    <PeriodPicker startMonth={fyStart} value={current} onChange={setPeriod} />
                </div>
            )}
            {!gl ? (
                <Skeleton height={400} />
            ) : (
                <>
                    <div className="a-stats three">
                        <div className="a-stat">
                            <span className="a-stat-top">Opening, {dateOnly(gl.from)}</span>
                            <strong>{m(gl.opening)}</strong>
                        </div>
                        <div className="a-stat">
                            <span className="a-stat-top">Movement</span>
                            <strong>{m(gl.closing - gl.opening)}</strong>
                            <span className="a-stat-note">{m(gl.totals.debit)} debit · {m(gl.totals.credit)} credit</span>
                        </div>
                        <div className="a-stat">
                            <span className="a-stat-top">Closing, {dateOnly(gl.to)}</span>
                            <strong>{m(gl.closing)}</strong>
                        </div>
                    </div>
                    <Card padded={false} className={loading ? 'is-loading' : undefined}>
                        <Table
                            rows={gl.lines}
                            columns={[
                                { key: 'date', label: 'Date', className: 'a-nowrap a-muted', render: (l) => dateOnly(l.date) },
                                { key: 'description', label: 'Description', render: (l) => <span className="a-two-line"><b>{l.description}</b><small>{l.number}</small></span> },
                                { key: 'debit', label: 'Debit', className: 'a-num', render: (l) => (l.debit ? m(l.debit) : '') },
                                { key: 'credit', label: 'Credit', className: 'a-num', render: (l) => (l.credit ? m(l.credit) : '') },
                                { key: 'balance', label: 'Balance', className: 'a-num', render: (l) => <b>{m(l.balance)}</b> },
                            ]}
                            empty={<p className="a-muted a-pad">No postings to this account in this period.</p>}
                        />
                    </Card>
                </>
            )}
        </>
    );
}
