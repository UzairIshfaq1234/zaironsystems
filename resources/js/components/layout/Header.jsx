import { NavLink } from 'react-router-dom';
import site from '@content/site.json';
import Button from '@/components/ui/Button';
import Icon from '@/components/ui/Icon';
import Logo from '@/components/ui/Logo';
import { useScrolled } from '@/hooks/usePageEffects';
import { cx } from '@/lib/utils';
import MegaMenu from './MegaMenu';

export default function Header({ menuOpen, onToggleMenu, menuButtonRef }) {
    const scrolled = useScrolled();

    return (
        <header className={cx('nav-wrap', scrolled && 'scrolled')}>
            <nav className="nav glass" aria-label="Main">
                <Logo />
                <ul className="nav-links">
                    {site.nav.map((item) =>
                        item.to === '/services' ? (
                            <MegaMenu key={item.to} />
                        ) : (
                            <li key={item.to}>
                                <NavLink to={item.to}>{item.label}</NavLink>
                            </li>
                        ),
                    )}
                </ul>
                <Button to="/contact">Start a project</Button>
                <button
                    ref={menuButtonRef}
                    type="button"
                    className="menu-btn"
                    aria-label={menuOpen ? 'Close menu' : 'Open menu'}
                    aria-expanded={menuOpen}
                    aria-controls="mobile-menu"
                    onClick={onToggleMenu}
                >
                    <Icon name={menuOpen ? 'close' : 'menu'} />
                </button>
            </nav>
        </header>
    );
}
