import { useEffect, useRef, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { Avatar, Button, cx } from '../ui';
import Icon from '../ui/Icon';
import GlobalSearch from './GlobalSearch';
import Notifications from './Notifications';
import { visibleNav } from './nav';

const THEME_KEY = 'zairon-admin-theme';
const COLLAPSED_KEY = 'zairon-admin-collapsed';

function useTheme() {
    const [theme, setTheme] = useState(() => document.documentElement.dataset.theme || 'dark');
    useEffect(() => {
        document.documentElement.dataset.theme = theme;
        try {
            localStorage.setItem(THEME_KEY, theme);
        } catch {
            /* storage unavailable: theme just won't persist */
        }
    }, [theme]);
    return [theme, () => setTheme((t) => (t === 'dark' ? 'light' : 'dark'))];
}

/** Remembers whether the sidebar is collapsed (per browser). */
function useCollapsed() {
    const [collapsed, setCollapsed] = useState(() => {
        try {
            return localStorage.getItem(COLLAPSED_KEY) === '1';
        } catch {
            return false;
        }
    });
    const toggle = () =>
        setCollapsed((c) => {
            try {
                localStorage.setItem(COLLAPSED_KEY, c ? '0' : '1');
            } catch {
                /* storage unavailable */
            }
            return !c;
        });
    return [collapsed, toggle];
}

function UserMenu() {
    const { user, logout } = useAuth();
    const navigate = useNavigate();
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

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

    const signOut = async () => {
        await logout();
        navigate('/login', { replace: true });
    };

    return (
        <div className="a-usermenu" ref={ref}>
            <button type="button" className="a-usermenu-trigger" aria-expanded={open} aria-haspopup="menu" aria-label={`Account menu for ${user.name}`} onClick={() => setOpen((o) => !o)}>
                <Avatar name={user.name} size={34} />
                <span className="a-usermenu-name">
                    <b>{user.name}</b>
                    <small>{user.is_super_admin ? 'Super Admin' : user.roles.map((r) => r.name).join(', ') || 'No role'}</small>
                </span>
                <Icon name="chevron" />
            </button>
            {open && (
                <div className="a-menu" role="menu">
                    <div className="a-menu-head">
                        <b>{user.name}</b>
                        <small>{user.email}</small>
                    </div>
                    <Link to="/profile" role="menuitem" onClick={() => setOpen(false)}>
                        <Icon name="user" /> Profile & password
                    </Link>
                    <a href="/" target="_blank" rel="noopener" role="menuitem">
                        <Icon name="external" /> View website
                    </a>
                    <button type="button" role="menuitem" onClick={signOut}>
                        <Icon name="logout" /> Sign out
                    </button>
                </div>
            )}
        </div>
    );
}

export default function AdminLayout() {
    const { can } = useAuth();
    const { pathname } = useLocation();
    const [theme, toggleTheme] = useTheme();
    const [drawer, setDrawer] = useState(false);
    const [collapsed, toggleCollapsed] = useCollapsed();
    const mainRef = useRef(null);

    useEffect(() => {
        setDrawer(false);
        mainRef.current?.focus({ preventScroll: true });
        window.scrollTo({ top: 0 });
    }, [pathname]);

    const items = visibleNav(can);

    return (
        <div className={cx('a-shell', collapsed && 'is-collapsed', drawer && 'drawer-open')}>
            <a className="a-skip" href="#a-main">
                Skip to content
            </a>

            <aside className="a-sidebar" aria-label="Admin navigation">
                <Link to="/" className="a-brand" title="ZAIRON Systems admin">
                    <span className="a-brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 100 100" focusable="false">
                            <polygon points="12,12 88,12 88,30 71.2,44 44.5,44 62,30 12,30" fill="currentColor" />
                            <polygon points="29.5,56 56.8,56 40,70 88,70 88,88 12,88 12,70" fill="currentColor" />
                            <circle cx="50.5" cy="50" r="3.8" fill="currentColor" />
                        </svg>
                    </span>
                    <span className="a-brand-text">
                        ZAIRON Systems <small>Admin</small>
                    </span>
                </Link>

                <nav className="a-nav">
                    {items.map((item) =>
                        item.section ? (
                            <p key={item.section} className="a-nav-section">
                                {item.section}
                            </p>
                        ) : (
                            <NavLink key={item.to} to={item.to} end={item.end} className="a-nav-link" title={item.label} aria-label={item.label}>
                                <Icon name={item.icon} />
                                <span>{item.label}</span>
                            </NavLink>
                        ),
                    )}
                </nav>

                <div className="a-sidebar-foot">
                    <NavLink to="/profile" className="a-nav-link" title="Profile" aria-label="Profile">
                        <Icon name="user" />
                        <span>Profile</span>
                    </NavLink>
                    <button
                        type="button"
                        className="a-nav-link a-collapse"
                        onClick={toggleCollapsed}
                        aria-expanded={!collapsed}
                        aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                        title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                    >
                        <Icon name={collapsed ? 'chevron-right' : 'chevron-left'} />
                        <span>Collapse</span>
                    </button>
                </div>
            </aside>
            <button type="button" className="a-scrim" aria-label="Close menu" tabIndex={-1} onClick={() => setDrawer(false)} />

            <div className="a-main-col">
                <header className="a-topbar">
                    <Button variant="ghost" icon="menu" className="a-menu-toggle" onClick={() => setDrawer(true)} aria-label="Open navigation" />
                    <GlobalSearch />
                    <div className="a-topbar-spacer" />
                    <Notifications />
                    <Button variant="ghost" icon={theme === 'dark' ? 'sun' : 'moon'} onClick={toggleTheme} aria-label={`Switch to ${theme === 'dark' ? 'light' : 'dark'} theme`} />
                    <UserMenu />
                </header>
                <main id="a-main" ref={mainRef} tabIndex={-1} className="a-main">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
