import { useState } from 'react';
import useFetch from '../../hooks/useFetch';
import { api } from '../../lib/api';
import { dateOnly } from '../../lib/format';
import { Badge, Button, Card, EmptyState, ErrorNote, PageHeader, Pagination, Select, Table } from '../../ui';
import { useConfirm, useToast } from '../../ui/feedback';

const FILTERS = [
    { value: 'pending', label: 'Waiting for a decision' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'all', label: 'All' },
];

const TONE = { pending: 'amber', approved: 'green', rejected: 'neutral', cancelled: 'neutral' };

/** Approve or reject leave. Approval checks the employee still has the days. */
export default function Leave() {
    const toast = useToast();
    const confirm = useConfirm();
    const [status, setStatus] = useState('pending');
    const [page, setPage] = useState(1);
    const { data, error, loading, reload } = useFetch('/hr/leave', { status, page });

    const decide = async (leave, decision) => {
        let note = '';
        if (decision === 'rejected') {
            const ok = await confirm({ title: `Reject ${leave.employee}'s leave?`, message: 'They will see it as rejected in the portal.', confirmLabel: 'Reject', danger: true });
            if (!ok) return;
        }
        try {
            await api.post(`/hr/leave/${leave.id}/decide`, { decision, note });
            toast(decision === 'approved' ? 'Leave approved.' : 'Leave rejected.');
            reload();
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    return (
        <>
            <PageHeader title="Leave requests" subtitle="Approve or reject what people ask for. Balances update automatically." />
            <div className="a-toolbar">
                <Select aria-label="Filter leave" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} options={FILTERS} />
            </div>
            <ErrorNote error={error} onRetry={reload} />
            <Card padded={false}>
                <Table
                    rows={data?.data}
                    loading={loading}
                    columns={[
                        { key: 'employee', label: 'Employee', render: (l) => <span className="a-two-line"><b>{l.employee}</b><small>{l.employee_code}</small></span> },
                        { key: 'type', label: 'Leave', render: (l) => <span className="a-two-line"><b>{l.type_label}</b><small>{l.reason || 'No reason given'}</small></span> },
                        { key: 'dates', label: 'Dates', className: 'a-nowrap', render: (l) => `${dateOnly(l.start_date)} – ${dateOnly(l.end_date)}` },
                        { key: 'days', label: 'Days', className: 'a-num', render: (l) => l.days },
                        { key: 'status', label: 'Status', render: (l) => <Badge tone={TONE[l.status]} dot>{l.status}</Badge> },
                        {
                            key: 'actions',
                            label: '',
                            className: 'a-actions',
                            render: (l) =>
                                l.status === 'pending' ? (
                                    <span className="a-row-actions">
                                        <Button size="sm" icon="check" onClick={(e) => { e.stopPropagation(); decide(l, 'approved'); }}>
                                            Approve
                                        </Button>
                                        <Button size="sm" variant="secondary" onClick={(e) => { e.stopPropagation(); decide(l, 'rejected'); }}>
                                            Reject
                                        </Button>
                                    </span>
                                ) : null,
                        },
                    ]}
                    empty={
                        <EmptyState icon="calendar" title={status === 'pending' ? 'Nothing waiting for you' : 'No leave here'}>
                            Requests from the employee portal appear here.
                        </EmptyState>
                    }
                />
            </Card>
            <Pagination meta={data?.meta} onPage={setPage} />
        </>
    );
}
