import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { useDebounced } from '../hooks/useFetch';
import { api } from '../lib/api';
import { cx } from '../ui';
import Icon from '../ui/Icon';
import { visibleNav } from './nav';

const KIND_TONE = { new: 'accent', won: 'green', paid: 'green', overdue: 'accent', partial: 'amber', sent: 'blue', draft: 'neutral', void: 'neutral' };
const GROUP_ICON = { enquiries: 'inbox', invoices: 'file', quotes: 'edit', clients: 'building', expenses: 'receipt', vendors: 'store', accounts: 'layers', users: 'users' };

/**
 * Search across the portal. Results come from the server, already limited to what the signed-in
 * user may open. Empty query: a "Go to" list of the pages they can reach. Ctrl/⌘K opens it anywhere.
 */
export default function GlobalSearch() {
    const { can } = useAuth();
    const navigate = useNavigate();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [groups, setGroups] = useState([]);
    const [loading, setLoading] = useState(false);
    const [active, setActive] = useState(0);
    const term = useDebounced(query.trim(), 220);
    const requestId = useRef(0);
    const inputRef = useRef(null);
    const listRef = useRef(null);

    const goTo = useMemo(
        () => visibleNav(can).filter((item) => !item.section).map((item) => ({ id: item.to, title: item.label, subtitle: item.to === '/' ? 'Dashboard' : 'Go to page', url: item.to, icon: item.icon })),
        [can],
    );

    // Results, flattened for keyboard navigation (the order they appear on screen).
    const items = useMemo(() => {
        if (term.length < 2) return goTo.map((item) => ({ ...item, group: 'Go to' }));
        return groups.flatMap((group) => group.items.map((item) => ({ ...item, id: `${group.key}:${item.url}`, group: group.label, icon: GROUP_ICON[group.key] })));
    }, [term, goTo, groups]);

    useEffect(() => {
        const onKey = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setOpen((o) => !o);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    useEffect(() => {
        if (open) requestAnimationFrame(() => inputRef.current?.focus());
        else {
            setQuery('');
            setGroups([]);
            setActive(0);
        }
    }, [open]);

    useEffect(() => {
        if (!open || term.length < 2) {
            setGroups([]);
            setLoading(false);
            return;
        }
        const id = ++requestId.current;
        setLoading(true);
        api.get('/search', { q: term })
            .then((res) => id === requestId.current && setGroups(res.groups))
            .catch(() => id === requestId.current && setGroups([]))
            .finally(() => id === requestId.current && setLoading(false));
    }, [term, open]);

    useEffect(() => setActive(0), [term]);

    useEffect(() => {
        listRef.current?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' });
    }, [active]);

    const go = (item) => {
        setOpen(false);
        navigate(item.url);
    };

    const onKey = (e) => {
        if (e.key === 'Escape') setOpen(false);
        else if (e.key === 'ArrowDown') setActive((i) => Math.min(i + 1, items.length - 1));
        else if (e.key === 'ArrowUp') setActive((i) => Math.max(i - 1, 0));
        else if (e.key === 'Enter' && items[active]) go(items[active]);
        else return;
        e.preventDefault();
    };

    // Items grouped for display; `index` is the item's place in `items`.
    const sections = useMemo(() => {
        const out = [];
        items.forEach((item, index) => {
            const last = out[out.length - 1];
            if (last && last.label === item.group) last.entries.push({ item, index });
            else out.push({ label: item.group, entries: [{ item, index }] });
        });
        return out;
    }, [items]);

    return (
        <>
            <button type="button" className="a-search-trigger" onClick={() => setOpen(true)} aria-label="Search (Ctrl K)">
                <Icon name="search" />
                <span>Search invoices, clients, enquiries…</span>
                <kbd aria-hidden="true">Ctrl K</kbd>
            </button>

            {open && (
                <div className="a-palette-layer" role="presentation">
                    <button type="button" className="a-palette-scrim" aria-label="Close search" tabIndex={-1} onClick={() => setOpen(false)} />
                    <div className="a-palette" role="dialog" aria-modal="true" aria-label="Search">
                        <div className="a-palette-input">
                            <Icon name="search" />
                            <input
                                ref={inputRef}
                                type="search"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                onKeyDown={onKey}
                                placeholder="Search enquiries, invoices, quotes, clients, expenses, team…"
                                aria-label="Search"
                                aria-controls="a-palette-results"
                                aria-activedescendant={items[active] ? `a-palette-${active}` : undefined}
                                role="combobox"
                                aria-expanded="true"
                                autoComplete="off"
                                maxLength={100}
                            />
                            {loading && <span className="a-spinner" aria-label="Searching" />}
                            <kbd>Esc</kbd>
                        </div>

                        <div id="a-palette-results" role="listbox" ref={listRef} className="a-palette-results">
                            {term.length >= 2 && !loading && items.length === 0 && (
                                <p className="a-palette-empty">
                                    Nothing matches “{term}”. Try a number (INV, Q-ZAIRON), an email or a company name.
                                </p>
                            )}
                            <div className="a-palette-grid">
                                {sections.map((section) => (
                                    <section key={section.label} className="a-palette-group" aria-label={section.label}>
                                        <h3>{section.label}</h3>
                                        {section.entries.map(({ item, index }) => (
                                            <button
                                                key={item.id ?? item.url}
                                                id={`a-palette-${index}`}
                                                type="button"
                                                role="option"
                                                aria-selected={index === active}
                                                className={cx('a-palette-item', index === active && 'is-active')}
                                                onMouseEnter={() => setActive(index)}
                                                onClick={() => go(item)}
                                            >
                                                {item.icon && <Icon name={item.icon} />}
                                                <span className="a-palette-text">
                                                    <b>{item.title}</b>
                                                    {item.subtitle && <small>{item.subtitle}</small>}
                                                </span>
                                                {item.badge && <span className={cx('a-palette-badge', `tone-${KIND_TONE[item.badge] ?? 'neutral'}`)}>{item.badge.replace('_', ' ')}</span>}
                                            </button>
                                        ))}
                                    </section>
                                ))}
                            </div>
                        </div>

                        <footer className="a-palette-foot">
                            <span>
                                <kbd>↑</kbd>
                                <kbd>↓</kbd> to move
                            </span>
                            <span>
                                <kbd>Enter</kbd> to open
                            </span>
                            <span>Results are limited to what you can access</span>
                        </footer>
                    </div>
                </div>
            )}
        </>
    );
}
