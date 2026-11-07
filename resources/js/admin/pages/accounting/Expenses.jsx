import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import useFetch, { useDebounced } from '../../hooks/useFetch';
import { api, fileUrl } from '../../lib/api';
import { dateOnly, isoDate, money } from '../../lib/format';
import { Button, Card, Checkbox, EmptyState, ErrorNote, Input, Modal, PageHeader, Pagination, SearchInput, Select, Switch, Table } from '../../ui';
import { useConfirm, useToast } from '../../ui/feedback';
import Icon from '../../ui/Icon';
import { ExpenseStatus, LockNote, currencyOptions, useAccounting } from './shared';
import { VendorModal } from './Vendors';

const blank = (base) => ({
    vendor_id: '',
    account_id: '',
    date: isoDate(),
    due_date: '',
    description: '',
    reference: '',
    currency: base,
    exchange_rate: '',
    amount: '',
    paid: true,
    paid_on: isoDate(),
    paid_from_account_id: '',
});

function ExpenseModal({ open, expense, options, onClose, onSaved, onDeleted }) {
    const { can } = useAuth();
    const toast = useToast();
    const confirm = useConfirm();
    const [form, setForm] = useState(() => blank(options.base_currency));
    const [vendors, setVendors] = useState(options.vendors);
    const [receipt, setReceipt] = useState(null);
    const [removeReceipt, setRemoveReceipt] = useState(false);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const [vendorModal, setVendorModal] = useState(false);
    const [openedFor, setOpenedFor] = useState(null);
    const manage = can('accounting.manage');

    const key = open ? (expense?.id ?? 'new') : null;
    if (key !== openedFor) {
        setOpenedFor(key);
        setErrors({});
        setReceipt(null);
        setRemoveReceipt(false);
        setForm(
            expense
                ? {
                      ...blank(options.base_currency),
                      ...Object.fromEntries(['vendor_id', 'account_id', 'date', 'due_date', 'description', 'reference', 'currency', 'exchange_rate', 'amount', 'paid_on', 'paid_from_account_id'].map((k) => [k, expense[k] ?? ''])),
                      paid: !!expense.paid_on,
                  }
                : { ...blank(options.base_currency), account_id: options.categories.find((c) => c.type === 'expense')?.id ?? '' },
        );
    }
    useEffect(() => setVendors(options.vendors), [options.vendors]);

    const set = (patch) => setForm((f) => ({ ...f, ...patch }));
    const bind = (k) => ({ value: form[k], onChange: (e) => set({ [k]: e.target.value }), error: errors[k], disabled: !manage });
    const foreign = form.currency !== options.base_currency;
    const baseAmount = foreign && Number(form.amount) && Number(form.exchange_rate) ? Number(form.amount) * Number(form.exchange_rate) : null;

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setErrors({});
        const { paid, ...fields } = form;
        try {
            const res = await api.upload(expense ? `/accounting/expenses/${expense.id}` : '/accounting/expenses', {
                ...fields,
                exchange_rate: foreign ? fields.exchange_rate : null,
                paid_on: paid ? fields.paid_on : null,
                paid_from_account_id: paid ? fields.paid_from_account_id : null,
                receipt: receipt ?? undefined,
                remove_receipt: removeReceipt,
            });
            toast(expense ? 'Expense updated.' : 'Expense recorded.');
            onSaved(res.data);
        } catch (err) {
            setErrors(Object.keys(err.errors).length ? err.errors : { form: err.message });
        } finally {
            setBusy(false);
        }
    };

    const remove = async () => {
        if (!(await confirm({ title: `Delete ${expense.number}?`, message: 'Its journal entries are removed too. This cannot be undone.', confirmLabel: 'Delete', danger: true }))) return;
        try {
            await api.del(`/accounting/expenses/${expense.id}`);
            toast('Expense deleted.');
            onDeleted();
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    return (
        <>
            <Modal
                open={open}
                onClose={onClose}
                size="lg"
                title={expense ? `${expense.number}` : 'Record an expense'}
                description={expense ? expense.description : 'A bill or purchase. It posts to the ledger automatically.'}
                footer={
                    <>
                        {expense && manage && (
                            <Button variant="danger" icon="trash" onClick={remove} className="a-mr-auto">
                                Delete
                            </Button>
                        )}
                        <Button variant="secondary" onClick={onClose}>
                            {manage ? 'Cancel' : 'Close'}
                        </Button>
                        {manage && (
                            <Button type="submit" form="expense-form" loading={busy} icon="check">
                                {expense ? 'Save' : 'Record expense'}
                            </Button>
                        )}
                    </>
                }
            >
                <form id="expense-form" className="a-form" onSubmit={submit} noValidate>
                    {errors.form && <p className="a-alert">{errors.form}</p>}
                    <Input label="What was it for?" required autoFocus maxLength={255} placeholder="e.g. AWS hosting, October" {...bind('description')} />
                    <div className="a-form-grid">
                        <div className="a-client-pick">
                            <Select label="Vendor" options={[{ value: '', label: '— None —' }, ...vendors.map((v) => ({ value: v.id, label: v.name }))]} {...bind('vendor_id')} />
                            {manage && (
                                <Button variant="secondary" icon="plus" onClick={() => setVendorModal(true)}>
                                    New vendor
                                </Button>
                            )}
                        </div>
                        <Select label="Category" options={[{ value: '', label: 'Choose…' }, ...options.categories.map((c) => ({ value: c.id, label: c.name }))]} {...bind('account_id')} />
                    </div>
                    <div className="a-form-grid three">
                        <Select label="Currency" options={currencyOptions(options.currencies)} {...bind('currency')} />
                        <Input label={`Amount${form.currency ? ` (${form.currency})` : ''}`} type="number" min="0" step="0.01" inputMode="decimal" required {...bind('amount')} />
                        {foreign ? (
                            <Input
                                label={`Rate: 1 ${form.currency} =`}
                                type="number"
                                min="0"
                                step="any"
                                inputMode="decimal"
                                hint={baseAmount ? `≈ ${money(baseAmount, options.base_currency)}` : `${options.base_currency}`}
                                {...bind('exchange_rate')}
                            />
                        ) : (
                            <Input label="Reference" maxLength={120} placeholder="Bill / receipt no." {...bind('reference')} />
                        )}
                    </div>
                    {foreign && <Input label="Reference" maxLength={120} placeholder="Bill / receipt no." {...bind('reference')} />}
                    <div className="a-form-grid">
                        <Input label="Bill date" type="date" required {...bind('date')} />
                        <Input label="Due date" type="date" hint="Leave empty if due on receipt." min={form.date} {...bind('due_date')} />
                    </div>
                    <div className="a-subpanel">
                        <Switch label="Already paid" checked={form.paid} disabled={!manage} onChange={(e) => set({ paid: e.target.checked })} />
                        {form.paid && (
                            <div className="a-form-grid">
                                <Input label="Paid on" type="date" max={isoDate()} {...bind('paid_on')} />
                                <Select label="Paid from" options={[{ value: '', label: 'Default bank account' }, ...options.money_accounts.map((a) => ({ value: a.id, label: a.name }))]} {...bind('paid_from_account_id')} />
                            </div>
                        )}
                    </div>
                    <div className="a-field">
                        <label htmlFor="expense-receipt">Receipt</label>
                        {expense?.has_receipt && !removeReceipt && !receipt && (
                            <div className="a-file-row">
                                <a className="a-link a-download" href={fileUrl(`/accounting/expenses/${expense.id}/receipt`)}>
                                    <Icon name="download" />
                                    {expense.receipt_name || 'Download receipt'}
                                </a>
                                {manage && <Checkbox label="Remove" checked={removeReceipt} onChange={(e) => setRemoveReceipt(e.target.checked)} />}
                            </div>
                        )}
                        {manage && (
                            <input id="expense-receipt" type="file" className="a-input a-file" accept=".pdf,.jpg,.jpeg,.png,.webp" onChange={(e) => setReceipt(e.target.files?.[0] ?? null)} />
                        )}
                        {errors.receipt ? <span className="a-field-error">{errors.receipt}</span> : <span className="a-field-hint">PDF or image, up to 5 MB. Stored privately.</span>}
                    </div>
                </form>
            </Modal>
            <VendorModal
                open={vendorModal}
                onClose={() => setVendorModal(false)}
                onSaved={(v) => {
                    setVendorModal(false);
                    setVendors((list) => [...list, { id: v.id, name: v.name }].sort((a, b) => a.name.localeCompare(b.name)));
                    set({ vendor_id: v.id });
                }}
            />
        </>
    );
}

const STATUS_FILTERS = [
    { value: '', label: 'All expenses' },
    { value: 'unpaid', label: 'Unpaid' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'paid', label: 'Paid' },
];

export default function Expenses() {
    const { can } = useAuth();
    const { lockDate } = useAccounting();
    const [params, setParams] = useSearchParams();
    const [q, setQ] = useState('');
    const [status, setStatus] = useState('');
    const [page, setPage] = useState(1);
    const search = useDebounced(q);
    const { data, error, loading, reload } = useFetch('/accounting/expenses', { q: search, status, page });
    const { data: options, reload: reloadOptions } = useFetch('/accounting/expenses/options');
    const [modal, setModal] = useState(null); // null | 'new' | expense

    // ?new=1 opens the form; ?open=ID opens an expense (links from the journal and overview).
    useEffect(() => {
        const open = params.get('open');
        if (params.get('new') && can('accounting.manage')) setModal('new');
        if (open) api.get(`/accounting/expenses/${open}`).then((r) => setModal(r.data), () => {});
        if (params.size) setParams({}, { replace: true });
    }, [params, setParams, can]);

    const done = () => {
        setModal(null);
        reload();
        reloadOptions();
    };

    return (
        <>
            <PageHeader
                title="Expenses"
                subtitle="Bills and purchases. Each one posts to the ledger; unpaid ones show as payable."
                actions={
                    can('accounting.manage') && (
                        <Button icon="plus" onClick={() => setModal('new')} disabled={!options}>
                            Record expense
                        </Button>
                    )
                }
            />
            <LockNote lockDate={lockDate} />
            <div className="a-toolbar">
                <SearchInput value={q} onChange={(v) => { setQ(v); setPage(1); }} placeholder="Search number, description, vendor…" />
                <Select aria-label="Filter by status" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} options={STATUS_FILTERS} />
            </div>
            <ErrorNote error={error} onRetry={reload} />
            <Card padded={false}>
                <Table
                    rows={data?.data}
                    loading={loading}
                    onRowClick={options ? (x) => setModal(x) : undefined}
                    columns={[
                        {
                            key: 'description',
                            label: 'Expense',
                            render: (x) => (
                                <span className="a-two-line">
                                    <b>{x.description}</b>
                                    <small>
                                        {x.number}
                                        {x.has_receipt && <Icon name="receipt" className="a-inline-icon a-after" />}
                                    </small>
                                </span>
                            ),
                        },
                        { key: 'vendor', label: 'Vendor', className: 'a-hide-sm', render: (x) => x.vendor || '—' },
                        { key: 'account', label: 'Category', className: 'a-hide-md a-muted' },
                        { key: 'date', label: 'Date', className: 'a-nowrap a-muted', render: (x) => dateOnly(x.date) },
                        { key: 'status', label: 'Status', render: (x) => <ExpenseStatus status={x.status} /> },
                        { key: 'amount', label: 'Amount', className: 'a-num', render: (x) => <b>{money(x.amount, x.currency)}</b> },
                    ]}
                    empty={
                        <EmptyState icon="receipt" title={search || status ? 'No matching expenses' : 'No expenses yet'}>
                            Record bills and purchases: rent, salaries, hosting, software, contractors.
                        </EmptyState>
                    }
                />
            </Card>
            <Pagination meta={data?.meta} onPage={setPage} />
            {options && (
                <ExpenseModal
                    open={!!modal}
                    expense={modal === 'new' ? null : modal}
                    options={options}
                    onClose={() => setModal(null)}
                    onSaved={done}
                    onDeleted={done}
                />
            )}
        </>
    );
}
