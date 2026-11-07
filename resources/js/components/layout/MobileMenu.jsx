import { useEffect, useRef, useState } from 'react';
import { NavLink } from 'react-router-dom';
import site from '@content/site.json';
import Button from '@/components/ui/Button';
import Icon from '@/components/ui/Icon';
import { services } from '@/lib/services';
import { cx } from '@/lib/utils';
import { ServiceLink } from './MegaMenu';

const links = [{ label: 'Home', to: '/' }, ...site.nav];

export default function MobileMenu({ open, onClose }) {
    const ref = useRef(null);
    const [servicesOpen, setServicesOpen] = useState(false);

    useEffect(() => {
        if (!open) return setServicesOpen(false);
        document.body.classList.add('menu-open');
        ref.current?.querySelector('a')?.focus();
        const onKey = (e) => e.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        return () => {
            document.body.classList.remove('menu-open');
            document.removeEventListener('keydown', onKey);
        };
    }, [open, onClose]);

    return (
        <div ref={ref} id="mobile-menu" className={cx('mobile-menu', open && 'open')} aria-hidden={!open} inert={!open}>
            <div className="m-inner">
                {links.map((item, i) => {
                    const style = { '--d': open ? `${i * 0.05 + 0.05}s` : '0s' };
                    if (item.to !== '/services') {
                        return (
                            <NavLink key={item.to} to={item.to} end={item.to === '/'} className="m-link" style={style} onClick={onClose}>
                                {item.label}
                            </NavLink>
                        );
                    }
                    return (
                        <div key={item.to}>
                            <button
                                type="button"
                                className="m-link m-toggle"
                                style={style}
                                aria-expanded={servicesOpen}
                                aria-controls="m-services"
                                onClick={() => setServicesOpen((o) => !o)}
                            >
                                Services
                                <Icon name="chevron" />
                            </button>
                            <div id="m-services" className={cx('m-sub', servicesOpen && 'open')} inert={!servicesOpen}>
                                <div>
                                    {services.map((s) => (
                                        <ServiceLink key={s.slug} slug={s.slug} onClick={onClose} />
                                    ))}
                                    <NavLink to="/services" end onClick={onClose}>
                                        <span className="icon-tile sm">
                                            <Icon name="arrow" />
                                        </span>
                                        All services
                                    </NavLink>
                                </div>
                            </div>
                        </div>
                    );
                })}
                <Button to="/contact" block onClick={onClose}>
                    Start a project
                </Button>
            </div>
        </div>
    );
}
