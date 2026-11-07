import { useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import useFetch from '../../hooks/useFetch';
import { api, fileUrl } from '../../lib/api';
import { money } from '../../lib/format';
import { Badge, Button, Card, ErrorNote, PageHeader, Skeleton, Table } from '../../ui';
import { useConfirm, useToast } from '../../ui/feedback';

/** One month: totals, every slip, and the buttons that generate, post or delete it. */
export default function PayrollRun() {
    const { id } = useParams();
    const navigate = useNavigate();
    const { can } = useAuth();
    const toast = useToast();
    const confirm = useConfirm();
    const { data, error, reload } = useFetch(`/payroll/runs/${id}`);

    if (error) return <ErrorNote error={error} />;
    if (!data) return <Skeleton height={520} />;
    const run = data.data;
    const posted = run.status === 'posted';
    const manage = can('payroll.manage');

    const act = async (path, confirmation, message) => {
        if (confirmation && !(await confirm(confirmation))) return;
        try {
            const res = await api.post(path);
            toast(res.message ?? message);
            reload();
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    const remove = async () => {
        if (!(await confirm({ title: `Delete the ${run.label} draft?`, message: 'Its slips are removed. You can start the month again afterwards.', confirmLabel: 'Delete', danger: true }))) return;
        try {
            await api.del(`/payroll/runs/${id}`);
            toast('Draft deleted.');
            navigate('/payroll', { replace: true });
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    return (
        <>
            <PageHeader
                back={{ to: '/payroll', label: 'Payroll' }}
                title={
                    <span className="a-title-status">
                        {run.label} {posted ? <Badge tone="green" dot>Posted to books</Badge> : <Badge tone="amber" dot>Draft</Badge>}
                    </span>
                }
                subtitle={posted ? `Posted ${new Date(run.posted_at).toLocaleDateString('en', { dateStyle: 'medium' })}. The salary expense is in the journal.` : 'Check the figures, then post the month.'}
                actions={
                    manage &&
                    !posted && (
                        <>
                            <Button variant="secondary" icon="refresh" onClick={() => act(`/payroll/runs/${id}/generate`, null, 'Slips rebuilt.')}>
                                Rebuild slips
                            </Button>
                            <Button variant="secondary" icon="trash" onClick={remove}>
                                Delete draft
                            </Button>
                            <Button icon="check" disabled={run.slips.length === 0} onClick={() => act(`/payroll/runs/${id}/post`, { title: `Post ${run.label} to the books?`, message: 'The salaries and tax are recorded in the books and this month is locked. This cannot be undone.', confirmLabel: 'Post to books' }, 'Posted to the books.')}>
                                Post to books
                            </Button>
                        </>
                    )
                }
            />

            {posted && <p className="a-lock-note">This month is posted. Its figures can no longer change, and the journal holds the salary entries.</p>}

            <div className="a-stats money">
                <div className="a-stat">
                    <span className="a-stat-top">Gross pay</span>
                    <strong>{money(run.gross, 'PKR')}</strong>
                </div>
                <div className="a-stat">
                    <span className="a-stat-top">Tax withheld</span>
                    <strong>{money(run.tax, 'PKR')}</strong>
                </div>
                <div className="a-stat">
                    <span className="a-stat-top">Net pay</span>
                    <strong>{money(run.net, 'PKR')}</strong>
                </div>
            </div>

            <Card padded={false} title="Salary slips" subtitle={`${run.slips.length} people`}>
                <Table
                    rows={run.slips}
                    columns={[
                        { key: 'employee', label: 'Employee', render: (s) => <span className="a-two-line"><b>{s.employee}</b><small>{s.code}</small></span> },
                        { key: 'basic', label: 'Basic', className: 'a-num a-hide-md', render: (s) => money(s.basic, 'PKR') },
                        { key: 'unpaid', label: 'Unpaid leave', className: 'a-num a-hide-sm', render: (s) => (s.unpaid_days ? `${s.unpaid_days} day(s)` : '—') },
                        { key: 'gross', label: 'Gross', className: 'a-num', render: (s) => money(s.gross, 'PKR') },
                        { key: 'tax', label: 'Tax', className: 'a-num a-hide-sm', render: (s) => money(s.tax, 'PKR') },
                        { key: 'net', label: 'Net pay', className: 'a-num', render: (s) => <b>{money(s.net, 'PKR')}</b> },
                        { key: 'pdf', label: '', className: 'a-actions', render: (s) => <Button variant="ghost" size="sm" icon="download" href={fileUrl(`/payroll/slips/${s.id}/pdf`)} download aria-label={`Download ${s.employee}'s slip`} /> },
                    ]}
                    empty={<p className="a-muted a-pad">No slips yet. Use “Rebuild slips” once people are on the payroll.</p>}
                />
            </Card>
        </>
    );
}
