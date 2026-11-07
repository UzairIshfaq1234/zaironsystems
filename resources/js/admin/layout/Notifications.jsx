import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api as adminApi } from '../lib/api';
import { timeAgo } from '../lib/format';
import { Button, cx } from '../ui';
import Icon from '../ui/Icon';

const KIND_ICON = { enquiry: 'inbox', quote: 'edit', payment: 'wallet', overdue: 'alert', viewed: 'eye', leave: 'calendar', review: 'spark', payslip: 'wallet' };
const POLL_MS = 60000;

/**
 * The bell: your own notifications (new enquiries, quotes answered, payments, leave, reviews…).
 * The admin uses the default client; the employee portal passes its own so it reads the employee's alerts.
 */
export default function Notifications({ api = adminApi }) {
    const navigate = useNavigate();
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState([]);
    const [unread, setUnread] = useState(0);
    const [loaded, setLoaded] = useState(false);
    const ref = useRef(null);

    const load = () =>
        api
            .get('/notifications')
            .then((res) => {
                setItems(res.data);
                setUnread(res.unread);
            })
            .catch(() => {})
            .finally(() => setLoaded(true));

    // Refresh on sign-in, every minute, and whenever the panel opens.
    useEffect(() => {
        load();
        const timer = setInterval(load, POLL_MS);
        return () => clearInterval(timer);
    }, []);
    useEffect(() => {
        if (open) load();
    }, [open]);

    useEffect(() => {
        if (!open) return;
        const onDown = (e) => !ref.current?.contains(e.target) && setOpen(false);
        const onKey = (e) => e.key === 'Escape' && setOpen(false);
        document.addEventListener('pointerdown', onDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('pointerdown', onDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    const openItem = async (item) => {
        setOpen(false);
        if (!item.read) {
            setItems((list) => list.map((n) => (n.id === item.id ? { ...n, read: true } : n)));
            setUnread((count) => Math.max(count - 1, 0));
            api.post(`/notifications/${item.id}/read`).catch(() => {});
        }
        navigate(item.url);
    };

    const readAll = async () => {
        setItems((list) => list.map((n) => ({ ...n, read: true })));
        setUnread(0);
        await api.post('/notifications/read-all').catch(() => {});
    };

    return (
        <div className="a-bell-wrap" ref={ref}>
            <button type="button" className="a-bell" aria-haspopup="dialog" aria-expanded={open} aria-label={unread ? `Notifications, ${unread} unread` : 'Notifications'} onClick={() => setOpen((o) => !o)}>
                <Icon name="bell" />
                {unread > 0 && <span className="a-bell-count">{unread > 99 ? '99+' : unread}</span>}
            </button>

            {open && (
                <div className="a-notif" role="dialog" aria-label="Notifications">
                    <header className="a-notif-head">
                        <div>
                            <b>Notifications</b>
                            <small>{unread ? `${unread} unread` : 'All caught up'}</small>
                        </div>
                        {unread > 0 && (
                            <Button variant="ghost" size="sm" icon="check" onClick={readAll}>
                                Mark all read
                            </Button>
                        )}
                    </header>
                    <ul className="a-notif-list">
                        {items.map((item) => (
                            <li key={item.id}>
                                <button type="button" className={cx('a-notif-item', !item.read && 'is-unread')} onClick={() => openItem(item)}>
                                    <span className={cx('a-notif-icon', `kind-${item.kind}`)}>
                                        <Icon name={KIND_ICON[item.kind] ?? 'spark'} />
                                    </span>
                                    <span className="a-notif-text">
                                        <b>{item.title}</b>
                                        <small>{item.body}</small>
                                        <time dateTime={item.created_at}>{timeAgo(item.created_at)}</time>
                                    </span>
                                    {!item.read && <span className="a-notif-dot" aria-label="Unread" />}
                                </button>
                            </li>
                        ))}
                    </ul>
                    {loaded && items.length === 0 && (
                        <div className="a-notif-empty">
                            <Icon name="bell" />
                            <p>Nothing yet. New enquiries, answered quotes and payments show up here.</p>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
