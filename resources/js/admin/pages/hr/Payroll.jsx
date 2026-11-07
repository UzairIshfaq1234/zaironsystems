import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import useFetch from '../../hooks/useFetch';
import { api } from '../../lib/api';
import { isoDate, money } from '../../lib/format';
import { Badge, Button, Card, EmptyState, ErrorNote, Input, Modal, PageHeader, Skeleton, Table } from '../../ui';
import { useToast } from '../../ui/feedback';

/** Start payroll for a month. It builds the slips straight away from current salaries and approved leave. */
function NewRunModal({ open, onClose, onCreated }) {
    const toast = useToast();
    const [period, setPeriod] = useState(isoDate().slice(0, 7));
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(false);

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setError(null);
        try {
            const res = await api.post('/payroll/runs', { period });
            toast(`Payroll for ${res.data.label} is ready to check.`);
            onCreated(res.data);
        } catch (err) {
            setError(err.errors?.period ?? err.message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="sm"
            title="Start payroll"
            description="Pick the month. You can check every slip before posting it to the books."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="run-form" loading={busy} icon="check">
                        Start
                    </Button>
                </>
            }
        >
            <form id="run-form" className="a-form" onSubmit={submit} noValidate>
                {error && <p className="a-alert">{error}</p>}
                <Input label="Month" type="month" required value={period} onChange={(e) => setPeriod(e.target.value)} />
            </form>
        </Modal>
    );
}

export default function Payroll() {
    const { can } = useAuth();
    const navigate = useNavigate();
    const { data, error, loading, reload } = useFetch('/payroll/runs');
    const [creating, setCreating] = useState(false);

    return (
        <>
            <PageHeader
                title="Payroll"
                subtitle="Each month: check the slips, then post it to the books. Posted months are locked."
                actions={can('payroll.manage') && <Button icon="plus" onClick={() => setCreating(true)}>Start a month</Button>}
            />
            <ErrorNote error={error} onRetry={reload} />
            <Card padded={false}>
                {!data ? (
                    <Skeleton height={300} />
                ) : (
                    <Table
                        rows={data.data}
                        loading={loading}
                        onRowClick={(r) => navigate(`/payroll/${r.id}`)}
                        columns={[
                            { key: 'label', label: 'Month', render: (r) => <b>{r.label}</b> },
                            { key: 'status', label: 'Status', render: (r) => (r.status === 'posted' ? <Badge tone="green" dot>Posted to books</Badge> : <Badge tone="amber" dot>Draft</Badge>) },
                            { key: 'slips_count', label: 'People', className: 'a-num a-hide-sm' },
                            { key: 'gross', label: 'Gross', className: 'a-num a-hide-sm', render: (r) => money(r.gross, 'PKR') },
                            { key: 'net', label: 'Net pay', className: 'a-num', render: (r) => money(r.net, 'PKR') },
                        ]}
                        empty={
                            <EmptyState icon="wallet" title="No payroll yet">
                                {can('payroll.manage') && (
                                    <Button variant="secondary" icon="plus" onClick={() => setCreating(true)}>
                                        Start this month
                                    </Button>
                                )}
                            </EmptyState>
                        }
                    />
                )}
            </Card>
            <NewRunModal open={creating} onClose={() => setCreating(false)} onCreated={(run) => navigate(`/payroll/${run.id}`)} />
        </>
    );
}
