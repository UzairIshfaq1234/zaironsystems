import { useCallback, useEffect, useRef, useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import Icon from '@/components/ui/Icon';
import { findService, pillars } from '@/lib/services';
import { cx } from '@/lib/utils';

const CLOSE_DELAY = 160;

/** Service link with name + tagline, shared by the desktop mega menu and the mobile accordion. */
export function ServiceLink({ slug, onClick }) {
    const s = findService(slug);
    return (
        <NavLink to={`/services/${slug}`} className="mega-link" onClick={onClick}>
            <span>
                <b>{s.title}</b>
                <small>{s.tagline}</small>
            </span>
        </NavLink>
    );
}

/**
 * "Services" nav item with a mega menu: opens on hover (mouse) or click/Enter (keyboard),
 * closes on Escape, outside click, pointer leave or navigation.
 */
export default function MegaMenu() {
    const { pathname } = useLocation();
    const [open, setOpen] = useState(false);
    const itemRef = useRef(null);
    const triggerRef = useRef(null);
    const timer = useRef(0);
    const canHover = useRef(typeof window !== 'undefined' && window.matchMedia('(hover: hover)').matches);

    const close = useCallback((focusTrigger = false) => {
        clearTimeout(timer.current);
        setOpen(false);
        if (focusTrigger) triggerRef.current?.focus();
    }, []);

    useEffect(() => close(), [pathname, close]);

    useEffect(() => {
        if (!open) return;
        const onKey = (e) => e.key === 'Escape' && close(true);
        const onDown = (e) => !itemRef.current?.contains(e.target) && close();
        document.addEventListener('keydown', onKey);
        document.addEventListener('pointerdown', onDown);
        return () => {
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('pointerdown', onDown);
        };
    }, [open, close]);

    const onEnter = () => {
        if (!canHover.current) return;
        clearTimeout(timer.current);
        setOpen(true);
    };
    const onLeave = () => {
        if (!canHover.current) return;
        timer.current = setTimeout(() => setOpen(false), CLOSE_DELAY);
    };
    const onBlur = (e) => {
        if (!itemRef.current?.contains(e.relatedTarget)) setOpen(false);
    };

    return (
        <li ref={itemRef} onMouseEnter={onEnter} onMouseLeave={onLeave} onBlur={onBlur}>
            <button
                ref={triggerRef}
                type="button"
                className={cx('nav-trigger', pathname.startsWith('/services') && 'current')}
                aria-expanded={open}
                aria-controls="mega-services"
                onClick={() => setOpen((o) => !o)}
            >
                Services
                <Icon name="chevron" />
            </button>

            <div id="mega-services" className={cx('mega', open && 'open')} role="region" aria-label="Services">
                <div className="mega-grid">
                    {pillars.map((pillar, i) => (
                        <div key={pillar.num} className="mega-col" style={{ '--i': i }}>
                            <p className="mega-title">
                                <span className="num grad">{pillar.num}</span>
                                {pillar.title}
                            </p>
                            <ul>
                                {pillar.services.map((slug) => (
                                    <li key={slug}>
                                        <ServiceLink slug={slug} />
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            </div>
        </li>
    );
}
