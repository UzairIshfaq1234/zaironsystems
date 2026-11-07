import { useEffect, useState } from 'react';
import { useAuth } from '../../auth/AuthContext';
import useFetch from '../../hooks/useFetch';
import { api } from '../../lib/api';
import { Button, Card, ErrorNote, Input, PageHeader, Select, Skeleton, Switch } from '../../ui';
import { useToast } from '../../ui/feedback';

export default function BillingSettings() {
    const { can } = useAuth();
    const toast = useToast();
    const { data, error } = useFetch('/settings/billing');
    const [form, setForm] = useState(null);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const editable = can('settings.manage');

    useEffect(() => {
        if (data) setForm(data.data);
    }, [data]);

    if (error) return <ErrorNote error={error} />;
    if (!form) return <Skeleton height={480} />;

    const bind = (k) => ({ value: form[k] ?? '', onChange: (e) => setForm({ ...form, [k]: e.target.value }), error: errors[k], disabled: !editable });

    const save = async (e) => {
        e.preventDefault();
        setBusy(true);
        setErrors({});
        try {
            const res = await api.put('/settings/billing', { ...form, tax_rate: Number(form.tax_rate) || 0, due_days: Number(form.due_days), quote_valid_days: Number(form.quote_valid_days) });
            setForm(res.data);
            toast('Billing settings saved.');
        } catch (er) {
            setErrors(er.errors ?? {});
            toast(er.message, 'error');
        } finally {
            setBusy(false);
        }
    };

    return (
        <form onSubmit={save} noValidate>
            <PageHeader
                title="Billing settings"
                subtitle="Your company details and the defaults for new quotes and invoices."
                actions={
                    editable && (
                        <Button type="submit" loading={busy} icon="check">
                            Save settings
                        </Button>
                    )
                }
            />
            <div className="a-grid-split">
                <Card title="Your company" subtitle="Printed at the top of every quote and invoice.">
                    <div className="a-form">
                        <Input label="Company name" maxLength={120} {...bind('company_name')} />
                        <div className="a-form-grid">
                            <Input label="Billing email" type="email" maxLength={190} hint="Clients' replies go here." {...bind('company_email')} />
                            <Input label="Phone" maxLength={40} {...bind('company_phone')} />
                        </div>
                        <Input as="textarea" rows={3} label="Address" maxLength={400} {...bind('company_address')} />
                        <Input label="Tax / NTN number" maxLength={60} {...bind('tax_number')} />
                    </div>
                </Card>
                <Card title="Defaults">
                    <div className="a-form">
                        <div className="a-form-grid">
                            <Select label="Currency" options={Object.entries(data.currencies).map(([v, l]) => ({ value: v, label: `${v} — ${l}` }))} {...bind('currency')} />
                            <Input label="Tax rate %" type="number" min="0" max="100" step="0.01" {...bind('tax_rate')} />
                            <Input label="Invoice due after (days)" type="number" min="0" max="365" {...bind('due_days')} />
                            <Input label="Quotes valid for (days)" type="number" min="1" max="365" {...bind('quote_valid_days')} />
                        </div>
                        <Input as="textarea" rows={4} label="Payment instructions" maxLength={2000} hint="Bank details or how to pay. Shown on invoices and in invoice emails." placeholder={'Bank: …\nAccount title: …\nIBAN: …'} {...bind('payment_instructions')} />
                        <Input as="textarea" rows={6} label="Quotation terms" maxLength={4000} hint="Printed on every quotation, one page. Number each line; they are the same for all quotes." {...bind('quote_terms')} />
                        <Input as="textarea" rows={2} label="Quotation note" maxLength={2000} hint="Used on quotes that have no note of their own." {...bind('quote_notes')} />
                        <Input as="textarea" rows={3} label="Invoice terms" maxLength={4000} hint="Printed on every invoice." {...bind('default_terms')} />
                        <Switch label="Email automatic reminders for overdue invoices (weekly)" checked={!!form.reminders} disabled={!editable} onChange={(e) => setForm({ ...form, reminders: e.target.checked })} />
                    </div>
                </Card>
            </div>
        </form>
    );
}
