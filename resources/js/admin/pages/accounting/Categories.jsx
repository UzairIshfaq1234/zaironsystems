import { useState } from 'react';
import useFetch from '../../hooks/useFetch';
import { api } from '../../lib/api';
import { Badge, Button, Card, EmptyState, ErrorNote, Input, PageHeader, Skeleton, Switch } from '../../ui';
import { useToast } from '../../ui/feedback';

/** Expense categories: what a bill is for (Rent, Software, Travel…). Add as many as you need. */
export default function Categories() {
    const { data, error, reload } = useFetch('/accounting/categories');
    const toast = useToast();
    const [name, setName] = useState('');
    const [busy, setBusy] = useState(false);
    const [editing, setEditing] = useState({});

    const add = async (e) => {
        e.preventDefault();
        if (!name.trim()) return;
        setBusy(true);
        try {
            await api.post('/accounting/categories', { name: name.trim() });
            setName('');
            toast('Category added. You can pick it when recording an expense.');
            reload();
        } catch (err) {
            toast(err.errors?.name ?? err.message, 'error');
        } finally {
            setBusy(false);
        }
    };

    const update = async (item, patch) => {
        try {
            await api.put(`/accounting/categories/${item.id}`, { name: patch.name ?? item.name, is_active: patch.is_active ?? item.is_active });
            setEditing((s) => ({ ...s, [item.id]: undefined }));
            reload();
        } catch (err) {
            toast(err.errors?.name ?? err.message, 'error');
        }
    };

    return (
        <>
            <PageHeader title="Expense categories" subtitle="What your bills are for. Each one has its own line in the reports." />
            <ErrorNote error={error} onRetry={reload} />
            {!data ? (
                <Skeleton height={320} />
            ) : (
                <Card>
                    <form className="a-list-add" onSubmit={add}>
                        <Input aria-label="New category name" placeholder="e.g. Office supplies" value={name} maxLength={120} onChange={(e) => setName(e.target.value)} />
                        <Button type="submit" icon="plus" loading={busy} disabled={!name.trim()}>
                            Add category
                        </Button>
                    </form>
                    {data.data.length === 0 ? (
                        <EmptyState icon="layers" title="No categories yet">Add the first one above.</EmptyState>
                    ) : (
                        <ul className="a-list-items">
                            {data.data.map((item) => {
                                const draft = editing[item.id];
                                return (
                                    <li key={item.id} className={!item.is_active ? 'is-off' : undefined}>
                                        {draft !== undefined ? (
                                            <form
                                                className="a-list-edit"
                                                onSubmit={(e) => {
                                                    e.preventDefault();
                                                    update(item, { name: draft });
                                                }}
                                            >
                                                <Input aria-label="Name" value={draft} maxLength={120} autoFocus onChange={(e) => setEditing((s) => ({ ...s, [item.id]: e.target.value }))} />
                                                <Button type="submit" size="sm" icon="check">
                                                    Save
                                                </Button>
                                                <Button variant="ghost" size="sm" onClick={() => setEditing((s) => ({ ...s, [item.id]: undefined }))}>
                                                    Cancel
                                                </Button>
                                            </form>
                                        ) : (
                                            <>
                                                <span className="a-list-name">
                                                    <span className="a-code">{item.code}</span>
                                                    <b>{item.name}</b>
                                                    {item.in_use && <Badge tone="blue">In use</Badge>}
                                                    {!item.is_active && <Badge>Off</Badge>}
                                                </span>
                                                <span className="a-row-actions">
                                                    <Switch label="" aria-label={`${item.name} active`} checked={item.is_active} onChange={(e) => update(item, { is_active: e.target.checked })} />
                                                    <Button variant="ghost" size="sm" icon="edit" aria-label={`Rename ${item.name}`} onClick={() => setEditing((s) => ({ ...s, [item.id]: item.name }))} />
                                                </span>
                                            </>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                    <p className="a-muted a-small a-gap-top">Switch a category off to hide it from new expenses. Past expenses keep it.</p>
                </Card>
            )}
        </>
    );
}
