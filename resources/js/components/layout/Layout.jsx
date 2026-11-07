import { Suspense, useCallback, useEffect, useRef, useState } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import { useDocumentMeta, usePointerEffects, useRouteFocus } from '@/hooks/usePageEffects';
import Footer from './Footer';
import Header from './Header';
import MobileMenu from './MobileMenu';

export default function Layout() {
    const { pathname } = useLocation();
    const mainRef = useRef(null);
    const menuButtonRef = useRef(null);
    const [menuOpen, setMenuOpen] = useState(false);

    const closeMenu = useCallback(() => {
        setMenuOpen(false);
        menuButtonRef.current?.focus();
    }, []);

    useEffect(() => setMenuOpen(false), [pathname]);

    useEffect(() => {
        const desktopQuery = window.matchMedia('(min-width: 1024px)');
        const onChange = (e) => e.matches && setMenuOpen(false);
        desktopQuery.addEventListener('change', onChange);
        return () => desktopQuery.removeEventListener('change', onChange);
    }, []);

    useRouteFocus(mainRef);
    useDocumentMeta();
    usePointerEffects();

    return (
        <>
            <a className="skip-link" href="#main">
                Skip to content
            </a>
            <div className="scroll-progress" aria-hidden="true" />
            <Header menuOpen={menuOpen} onToggleMenu={() => (menuOpen ? closeMenu() : setMenuOpen(true))} menuButtonRef={menuButtonRef} />
            <MobileMenu open={menuOpen} onClose={closeMenu} />
            <div className="page" inert={menuOpen}>
                <main id="main" ref={mainRef} tabIndex={-1}>
                    <Suspense fallback={null}>
                        <div key={pathname} className="route">
                            <Outlet />
                        </div>
                    </Suspense>
                </main>
                <Footer />
            </div>
        </>
    );
}
