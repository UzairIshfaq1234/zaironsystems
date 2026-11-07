import { useState } from 'react';
import { useAuth } from '../auth/AuthContext';
import useFetch, { useDebounced } from '../hooks/useFetch';
import { api } from '../lib/api';
import { dateTime, timeAgo } from '../lib/format';
import { Avatar, Badge, Button, Card, EmptyState, ErrorNote, Input, PageHeader, Pagination, SearchInput, Skeleton } from '../ui';
import { useToast } from '../ui/feedback';

export function Activity() {
    const [q, setQ] = useState('');
    const [page, setPage] = useState(1);
    const search = useDebounced(q);
    const { data, error, loading, reload } = useFetch('/activity', { q: search, page });

    return (
        <>
            <PageHeader title="Activity log" subtitle="Sign-ins and every change made in the portal, newest first." />
            <div className="a-toolbar">
                <SearchInput
                    value={q}
                    onChange={(v) => {
                        setQ(v);
                        setPage(1);
                    }}
                    placeholder="Search activity…"
                />
            </div>
            <ErrorNote error={error} onRetry={reload} />
            <Card>
                {!data ? (
                    <Skeleton height={300} />
                ) : data.data.length === 0 ? (
                    <EmptyState icon="activity" title="Nothing here yet" />
                ) : (
                    <ol className={`a-timeline full ${loading ? 'is-loading' : ''}`}>
                        {data.data.map((a) => (
                            <li key={a.id}>
                                <span className={`a-timeline-dot kind-${a.action.split('.')[0]}`} aria-hidden="true" />
                                <div>
                                    <p>{a.description}</p>
                                    <small>
                                        {a.user?.name ?? 'Visitor / system'} · <span title={dateTime(a.created_at)}>{timeAgo(a.created_at)}</span>
                                        {a.ip_address && ` · ${a.ip_address}`}
                                    </small>
                                </div>
                                <Badge>{a.action}</Badge>
                            </li>
                        ))}
                    </ol>
                )}
            </Card>
            <Pagination meta={data?.meta} onPage={setPage} />
        </>
    );
}

export function Profile() {
    const { user, setUser } = useAuth();
    const toast = useToast();
    const [info, setInfo] = useState({ name: user.name, email: user.email });
    const [pw, setPw] = useState({ current_password: '', password: '', password_confirmation: '' });
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState('');

    const run = async (which, fn) => {
        setBusy(which);
        setErrors({});
        try {
            await fn();
        } catch (err) {
            setErrors(Object.keys(err.errors).length ? err.errors : { [which]: err.message });
        } finally {
            setBusy('');
        }
    };

    return (
        <>
            <PageHeader title="Profile" subtitle="Your account details and password." />
            <div className="a-profile-head">
                <Avatar name={user.name} size={64} />
                <div>
                    <h2>{user.name}</h2>
                    <div className="a-chips">
                        {user.is_super_admin ? <Badge tone="accent">Super Admin</Badge> : user.roles.map((r) => <Badge key={r.id}>{r.name}</Badge>)}
                    </div>
                </div>
            </div>
            <div className="a-grid-split">
                <Card title="Account details">
                    <form
                        className="a-form"
                        onSubmit={(e) => {
                            e.preventDefault();
                            run('info', async () => {
                                setUser((await api.put('/profile', info)).user);
                                toast('Profile updated.');
                            });
                        }}
                        noValidate
                    >
                        {errors.info && <p className="a-alert">{errors.info}</p>}
                        <Input label="Full name" value={info.name} onChange={(e) => setInfo({ ...info, name: e.target.value })} error={errors.name} maxLength={120} />
                        <Input label="Email" type="email" value={info.email} onChange={(e) => setInfo({ ...info, email: e.target.value })} error={errors.email} maxLength={190} />
                        <div>
                            <Button type="submit" loading={busy === 'info'} icon="check">
                                Save details
                            </Button>
                        </div>
                    </form>
                </Card>
                <Card title="Change password" subtitle="You'll stay signed in here; other devices are signed out.">
                    <form
                        className="a-form"
                        onSubmit={(e) => {
                            e.preventDefault();
                            run('pw', async () => {
                                toast((await api.put('/profile/password', pw)).message);
                                setPw({ current_password: '', password: '', password_confirmation: '' });
                            });
                        }}
                        noValidate
                    >
                        {errors.pw && <p className="a-alert">{errors.pw}</p>}
                        <Input label="Current password" type="password" autoComplete="current-password" value={pw.current_password} onChange={(e) => setPw({ ...pw, current_password: e.target.value })} error={errors.current_password} />
                        <Input label="New password" type="password" autoComplete="new-password" hint="At least 10 characters, mixed case and a number." value={pw.password} onChange={(e) => setPw({ ...pw, password: e.target.value })} error={errors.password} />
                        <Input label="Confirm new password" type="password" autoComplete="new-password" value={pw.password_confirmation} onChange={(e) => setPw({ ...pw, password_confirmation: e.target.value })} />
                        <div>
                            <Button type="submit" loading={busy === 'pw'} icon="lock">
                                Update password
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </>
    );
}

export function Forbidden() {
    return (
        <EmptyState icon="lock" title="You don't have access to this page">
            Ask a super admin to give your role the permission you need.
        </EmptyState>
    );
}

export function NotFound() {
    return (
        <EmptyState icon="search" title="Page not found">
            <Button to="/" variant="secondary" icon="arrow-left">
                Back to dashboard
            </Button>
        </EmptyState>
    );
}

/** Users without dashboard.view land on the first section they can use. */
export function Home({ children }) {
    const { can } = useAuth();
    if (can('dashboard.view')) return children;
    return (
        <EmptyState icon="grid" title="Welcome to ZAIRON Systems Admin">
            Use the menu to open the sections your role gives you access to.
        </EmptyState>
    );
}
