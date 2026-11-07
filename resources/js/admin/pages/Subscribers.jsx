import { useState } from 'react';
import { useAuth } from '../auth/AuthContext';
import useFetch, { useDebounced } from '../hooks/useFetch';
import { api, fileUrl } from '../lib/api';
import { dateTime, timeAgo } from '../lib/format';
import { Button, Card, EmptyState, ErrorNote, PageHeader, Pagination, SearchInput, Table } from '../ui';
import { useConfirm, useToast } from '../ui/feedback';

export default function Subscribers() {
    const { can } = useAuth();
    const toast = useToast();
    const confirm = useConfirm();
    const [q, setQ] = useState('');
    const [page, setPage] = useState(1);
    const search = useDebounced(q);
    const { data, error, loading, reload } = useFetch('/subscribers', { q: search, page });

    const remove = async (s) => {
        if (!(await confirm({ title: 'Remove subscriber?', message: `${s.email} will no longer receive the newsletter.`, confirmLabel: 'Remove', danger: true }))) return;
        try {
            await api.del(`/subscribers/${s.id}`);
            toast('Subscriber removed.');
            reload();
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    return (
        <>
            <PageHeader
                title="Subscribers"
                subtitle="People who signed up for the newsletter."
                actions={
                    can('subscribers.export') && (
                        <Button variant="secondary" icon="download" href={fileUrl('/subscribers/export')} download>
                            Export CSV
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
                    placeholder="Search email…"
                />
            </div>
            <ErrorNote error={error} onRetry={reload} />
            <Card padded={false}>
                <Table
                    rows={data?.data}
                    loading={loading}
                    columns={[
                        { key: 'email', label: 'Email', render: (s) => <b>{s.email}</b> },
                        { key: 'created_at', label: 'Subscribed', className: 'a-muted a-nowrap', render: (s) => <span title={dateTime(s.created_at)}>{timeAgo(s.created_at)}</span> },
                        ...(can('subscribers.delete')
                            ? [{ key: 'actions', label: '', className: 'a-actions', render: (s) => <Button variant="ghost" size="sm" icon="trash" onClick={() => remove(s)} aria-label={`Remove ${s.email}`} /> }]
                            : []),
                    ]}
                    empty={
                        <EmptyState icon="mail" title={search ? 'No matching subscribers' : 'No subscribers yet'}>
                            {search ? 'Try a different search.' : 'Newsletter sign-ups from the website will appear here.'}
                        </EmptyState>
                    }
                />
            </Card>
            <Pagination meta={data?.meta} onPage={setPage} />
        </>
    );
}
