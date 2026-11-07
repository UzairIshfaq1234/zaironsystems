import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom';
import Notifications from '../admin/layout/Notifications';
import { Avatar, Button } from '../admin/ui';
import Icon from '../admin/ui/Icon';
import { useAuth } from './auth';
import { api } from './lib/api';

const TABS = [
    { to: '/', label: 'Today', icon: 'grid', end: true },
    { to: '/attendance', label: 'Attendance', icon: 'calendar' },
    { to: '/leave', label: 'Leave', icon: 'edit' },
    { to: '/payslips', label: 'Salary slips', icon: 'wallet' },
    { to: '/reviews', label: 'Reviews', icon: 'spark' },
    { to: '/profile', label: 'Profile', icon: 'user' },
];

/** Employee portal chrome: a side menu on desktop, a tab bar on phones. */
export default function Shell() {
    const { employee, logout } = useAuth();
    const navigate = useNavigate();
    const signOut = async () => {
        await logout();
        navigate('/login', { replace: true });
    };

    return (
        <div className="p-shell">
            <a className="a-skip" href="#p-main">
                Skip to content
            </a>
            <aside className="p-side" aria-label="Portal navigation">
                <Link to="/" className="p-brand">
                    <span className="a-brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 100 100" focusable="false">
                            <polygon points="12,12 88,12 88,30 71.2,44 44.5,44 62,30 12,30" fill="currentColor" />
                            <polygon points="29.5,56 56.8,56 40,70 88,70 88,88 12,88 12,70" fill="currentColor" />
                            <circle cx="50.5" cy="50" r="3.8" fill="currentColor" />
                        </svg>
                    </span>
                    <span>
                        ZAIRON Systems <small>Team</small>
                    </span>
                </Link>
                <nav className="p-nav">
                    {TABS.map((t) => (
                        <NavLink key={t.to} to={t.to} end={t.end} className="a-nav-link">
                            <Icon name={t.icon} />
                            <span>{t.label}</span>
                        </NavLink>
                    ))}
                </nav>
                <div className="p-side-foot">
                    <div className="p-me">
                        <Avatar name={employee.name} size={34} />
                        <span>
                            <b>{employee.name}</b>
                            <small>{employee.code}</small>
                        </span>
                    </div>
                    <button type="button" className="a-nav-link" onClick={signOut}>
                        <Icon name="logout" />
                        <span>Sign out</span>
                    </button>
                </div>
            </aside>

            <div className="p-main-col">
                <header className="p-top">
                    <span className="p-top-title">ZAIRON Systems Team</span>
                    <div className="p-top-actions">
                        <Notifications api={api} />
                        <Button variant="ghost" icon="logout" onClick={signOut} aria-label="Sign out" className="p-top-out" />
                    </div>
                </header>
                <main id="p-main" className="p-main" tabIndex={-1}>
                    <Outlet />
                </main>
            </div>

            <nav className="p-tabbar" aria-label="Portal sections">
                {TABS.slice(0, 5).map((t) => (
                    <NavLink key={t.to} to={t.to} end={t.end} className="p-tab">
                        <Icon name={t.icon} />
                        <span>{t.label.split(' ')[0]}</span>
                    </NavLink>
                ))}
            </nav>
        </div>
    );
}
