import { useState } from 'react';
import { useAuth } from '../../auth/AuthContext';
import useFetch, { useDebounced } from '../../hooks/useFetch';
import { api } from '../../lib/api';
import { Avatar, Button, Card, EmptyState, ErrorNote, Input, Modal, PageHeader, SearchInput, Table } from '../../ui';
import { useConfirm, useToast } from '../../ui/feedback';

const EMPTY = { name: '', email: '', phone: '', tax_number: '', notes: '' };

/** Create / edit a vendor. Calls onSaved(vendor). */
export function VendorModal({ open, vendor, onClose, onSaved }) {
    const toast = useToast();
    const [form, setForm] = useState(EMPTY);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const [openedFor, setOpenedFor] = useState(null);
    const key = open ? (vendor?.id ?? 'new') : null;
    if (key !== openedFor) {
        setOpenedFor(key);
        setErrors({});
        setForm(vendor ? Object.fromEntries(Object.keys(EMPTY).map((k) => [k, vendor[k] ?? ''])) : EMPTY);
    }
    const bind = (k) => ({ value: form[k], onChange: (e) => setForm({ ...form, [k]: e.target.value }), error: errors[k] });

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setErrors({});
        try {
            const res = vendor ? await api.put(`/accounting/vendors/${vendor.id}`, form) : await api.post('/accounting/vendors', form);
            toast(vendor ? 'Vendor updated.' : 'Vendor added.');
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
            title={vendor ? `Edit ${vendor.name}` : 'New vendor'}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="vendor-form" loading={busy} icon="check">
                        {vendor ? 'Save' : 'Add vendor'}
                    </Button>
                </>
            }
        >
            <form id="vendor-form" className="a-form" onSubmit={submit} noValidate>
                {errors.form && <p className="a-alert">{errors.form}</p>}
                <Input label="Name" required autoFocus maxLength={160} placeholder="e.g. AWS, landlord, freelancer" {...bind('name')} />
                <div className="a-form-grid">
                    <Input label="Email" type="email" maxLength={190} {...bind('email')} />
                    <Input label="Phone" maxLength={40} {...bind('phone')} />
                </div>
                <Input label="Tax / NTN number" maxLength={60} {...bind('tax_number')} />
                <Input as="textarea" rows={2} label="Notes" maxLength={2000} {...bind('notes')} />
            </form>
        </Modal>
    );
}

export default function Vendors() {
    const { can } = useAuth();
    const toast = useToast();
    const confirm = useConfirm();
    const [q, setQ] = useState('');
    const search = useDebounced(q);
    const { data, error, loading, reload } = useFetch('/accounting/vendors', { q: search });
    const [modal, setModal] = useState(null); // null | 'new' | vendor
    const manage = can('accounting.manage');

    const remove = async (v) => {
        if (!(await confirm({ title: `Delete ${v.name}?`, message: 'This cannot be undone.', confirmLabel: 'Delete', danger: true }))) return;
        try {
            await api.del(`/accounting/vendors/${v.id}`);
            toast('Vendor deleted.');
            reload();
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    return (
        <>
            <PageHeader
                title="Vendors"
                subtitle="The suppliers you pay: hosting, software, rent, contractors."
                actions={manage && <Button icon="plus" onClick={() => setModal('new')}>New vendor</Button>}
            />
            <div className="a-toolbar">
                <SearchInput value={q} onChange={setQ} placeholder="Search vendors…" />
            </div>
            <ErrorNote error={error} onRetry={reload} />
            <Card padded={false}>
                <Table
                    rows={data?.data}
                    loading={loading}
                    onRowClick={manage ? (v) => setModal(v) : undefined}
                    columns={[
                        {
                            key: 'name',
                            label: 'Vendor',
                            render: (v) => (
                                <span className="a-cell-user">
                                    <Avatar name={v.name} size={34} />
                                    <span>
                                        <b>{v.name}</b>
                                        <small>{v.email || v.phone || '—'}</small>
                                    </span>
                                </span>
                            ),
                        },
                        { key: 'tax_number', label: 'Tax no.', className: 'a-hide-sm a-muted', render: (v) => v.tax_number || '—' },
                        { key: 'expenses_count', label: 'Expenses', className: 'a-num' },
                        { key: 'unpaid_count', label: 'Unpaid', className: 'a-num', render: (v) => (v.unpaid_count ? <b>{v.unpaid_count}</b> : '—') },
                        ...(can('accounting.manage')
                            ? [{ key: 'actions', label: '', className: 'a-actions', render: (v) => <Button variant="ghost" size="sm" icon="trash" aria-label={`Delete ${v.name}`} onClick={(e) => { e.stopPropagation(); remove(v); }} /> }]
                            : []),
                    ]}
                    empty={
                        <EmptyState icon="store" title={search ? 'No matching vendors' : 'No vendors yet'}>
                            Add the companies and people you pay, then link expenses to them.
                        </EmptyState>
                    }
                />
            </Card>
            <VendorModal
                open={!!modal}
                vendor={modal === 'new' ? null : modal}
                onClose={() => setModal(null)}
                onSaved={() => {
                    setModal(null);
                    reload();
                }}
            />
        </>
    );
}
