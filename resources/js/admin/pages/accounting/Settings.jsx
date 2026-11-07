import { useEffect, useState } from 'react';
import { useAuth } from '../../auth/AuthContext';
import useFetch from '../../hooks/useFetch';
import { api } from '../../lib/api';
import { dateOnly, isoDate } from '../../lib/format';
import { Button, Card, ErrorNote, Input, PageHeader, Select, Skeleton } from '../../ui';
import { useConfirm, useToast } from '../../ui/feedback';
import { currencyOptions, fiscalYear } from './shared';

export default function AccountingSettings() {
    const { can } = useAuth();
    const toast = useToast();
    const confirm = useConfirm();
    const { data, error, reload } = useFetch('/accounting/settings');
    const [form, setForm] = useState(null);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const editable = can('accounting.close');

    useEffect(() => {
        if (data) setForm({ ...data.data, lock_date: data.data.lock_date ?? '' });
    }, [data]);

    if (error) return <ErrorNote error={error} onRetry={reload} />;
    if (!form) return <Skeleton height={420} />;

    const save = async (patch = {}) => {
        const body = { ...form, ...patch, fiscal_year_start: Number(form.fiscal_year_start), lock_date: (patch.lock_date ?? form.lock_date) || null };
        setBusy(true);
        setErrors({});
        try {
            await api.put('/accounting/settings', body);
            toast('Accounting settings saved.');
            reload();
        } catch (er) {
            setErrors(er.errors ?? {});
            toast(er.message, 'error');
        } finally {
            setBusy(false);
        }
    };

    const fy = fiscalYear(Number(form.fiscal_year_start));
    const lastFyEnd = isoDate(new Date(new Date(`${fy.start}T00:00:00`).getTime() - 86400000));

    const closeYear = async () => {
        if (!(await confirm({ title: `Close the books up to ${dateOnly(lastFyEnd)}?`, message: 'Nothing dated on or before then can be added, edited or deleted until you reopen it. Do this once the year is reviewed and filed.', confirmLabel: 'Close period' }))) return;
        setForm({ ...form, lock_date: lastFyEnd });
        save({ lock_date: lastFyEnd });
    };

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                save();
            }}
            noValidate
        >
            <PageHeader
                title="Accounting settings"
                subtitle="How the books are kept."
                actions={
                    editable && (
                        <Button type="submit" loading={busy} icon="check">
                            Save settings
                        </Button>
                    )
                }
            />
            <div className="a-grid-split">
                <Card title="Books" subtitle="The currency and year every report uses.">
                    <div className="a-form">
                        <Select
                            label="Base currency"
                            options={currencyOptions(data.currencies)}
                            value={form.base_currency}
                            onChange={(e) => setForm({ ...form, base_currency: e.target.value })}
                            disabled={!editable || data.base_locked}
                            error={errors.base_currency}
                        />
                        <p className="a-field-hint a-tight">
                            {data.base_locked
                                ? 'Fixed: the books already have entries. Invoices and expenses can still be in any currency; they are converted at their exchange rate.'
                                : 'Choose carefully: it is fixed once the first entry is posted. Other currencies are converted at the rate you enter.'}
                        </p>
                        <Select
                            label="Financial year starts in"
                            options={Object.entries(data.months).map(([value, label]) => ({ value, label }))}
                            value={String(form.fiscal_year_start)}
                            onChange={(e) => setForm({ ...form, fiscal_year_start: e.target.value })}
                            disabled={!editable}
                            error={errors.fiscal_year_start}
                        />
                        <p className="a-field-hint a-tight">
                            Current year: <b>{fy.label}</b> ({dateOnly(fy.start)} – {dateOnly(fy.end)}). Pakistan&apos;s tax year runs July–June.
                        </p>
                    </div>
                </Card>
                <Card title="Close a period" subtitle="Lock finished periods so filed numbers can't change by accident.">
                    <div className="a-form">
                        <Input
                            label="Books closed up to (and including)"
                            type="date"
                            max={isoDate()}
                            value={form.lock_date}
                            onChange={(e) => setForm({ ...form, lock_date: e.target.value })}
                            disabled={!editable}
                            error={errors.lock_date}
                            hint={form.lock_date ? `Nothing dated on or before ${dateOnly(form.lock_date)} can change.` : 'All periods are open.'}
                        />
                        {editable && (
                            <div className="a-form-row">
                                <Button variant="secondary" icon="lock" onClick={closeYear} disabled={busy || form.lock_date === lastFyEnd}>
                                    Close up to end of last year
                                </Button>
                                {form.lock_date && (
                                    <Button variant="ghost" icon="refresh" onClick={() => { setForm({ ...form, lock_date: '' }); save({ lock_date: '' }); }} disabled={busy}>
                                        Reopen all periods
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>
                </Card>
            </div>
        </form>
    );
}
