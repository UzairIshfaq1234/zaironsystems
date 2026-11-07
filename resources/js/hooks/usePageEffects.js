import { useEffect, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { metaFor } from '@/lib/seo';
import { prefersReducedMotion } from '@/lib/utils';

/** True once the page has scrolled past `offset` pixels. */
export function useScrolled(offset = 40) {
    const [scrolled, setScrolled] = useState(false);
    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > offset);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, [offset]);
    return scrolled;
}

/** Scroll to the top (or to #hash) and move focus to <main> after each client-side navigation. */
export function useRouteFocus(mainRef) {
    // `key` changes on every navigation, so clicking the same #hash link twice still scrolls.
    const { pathname, hash, key } = useLocation();
    const first = useRef(true);

    useEffect(() => {
        const id = hash ? decodeURIComponent(hash.slice(1)) : null;
        const isFirst = first.current;
        first.current = false;
        let frame = 0;
        let tries = 0;

        // The new page may commit a few frames later (lazy chunks), so keep looking for the #target briefly.
        const settle = () => {
            const target = id && document.getElementById(id);
            if (id && !target && tries++ < 60) {
                frame = requestAnimationFrame(settle);
                return;
            }
            if (target) target.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
            else if (!isFirst) window.scrollTo({ top: 0, behavior: 'instant' });
            if (!isFirst) mainRef.current?.focus({ preventScroll: true });
        };
        frame = requestAnimationFrame(settle);
        return () => cancelAnimationFrame(frame);
    }, [pathname, hash, key, mainRef]);
}

const setMeta = (selector, attr, value) => document.querySelector(selector)?.setAttribute(attr, value);

/** Keep title, description, canonical and social tags in sync during client-side navigation. */
export function useDocumentMeta() {
    const { pathname } = useLocation();

    useEffect(() => applyMeta(pathname, metaFor(pathname)), [pathname]);
}

function applyMeta(pathname, meta) {
    const canonical = document.querySelector('link[rel="canonical"]');
    const url = canonical ? new URL(pathname, canonical.href).href : window.location.href;

    document.title = meta.title;
    setMeta('meta[name="description"]', 'content', meta.description ?? '');
    setMeta('meta[name="robots"]', 'content', meta.noindex ? 'noindex, follow' : 'index, follow');
    canonical?.setAttribute('href', url);
    for (const prefix of ['og', 'twitter']) {
        const key = prefix === 'og' ? 'property' : 'name';
        setMeta(`meta[${key}="${prefix}:title"]`, 'content', meta.title);
        setMeta(`meta[${key}="${prefix}:description"]`, 'content', meta.description ?? '');
    }
    setMeta('meta[property="og:url"]', 'content', url);
}

/**
 * Cursor effects via one delegated listener: spotlight on cards, 3D tilt + holographic sheen on
 * [data-tilt] elements, and a gentle parallax on background glows.
 */
export function usePointerEffects() {
    useEffect(() => {
        if (prefersReducedMotion() || !window.matchMedia('(pointer: fine)').matches) return;

        let frame = 0;
        let tilted = null;
        const resetTilt = () => {
            tilted?.style.setProperty('--rx', '0deg');
            tilted?.style.setProperty('--ry', '0deg');
            tilted = null;
        };

        const onMove = (e) => {
            if (frame) return;
            frame = requestAnimationFrame(() => {
                frame = 0;
                const target = e.target instanceof Element ? e.target : null;
                const card = target?.closest('.card');
                if (card) {
                    const r = card.getBoundingClientRect();
                    card.style.setProperty('--mx', `${e.clientX - r.left}px`);
                    card.style.setProperty('--my', `${e.clientY - r.top}px`);
                }

                const tilt = target?.closest('[data-tilt]');
                if (tilt !== tilted) resetTilt();
                if (tilt) {
                    const r = tilt.getBoundingClientRect();
                    const px = (e.clientX - r.left) / r.width;
                    const py = (e.clientY - r.top) / r.height;
                    const strength = Number(tilt.dataset.tilt) || 8;
                    tilt.style.setProperty('--ry', `${((px - 0.5) * strength).toFixed(2)}deg`);
                    tilt.style.setProperty('--rx', `${((0.5 - py) * strength).toFixed(2)}deg`);
                    tilt.style.setProperty('--sx', `${(px * 100).toFixed(1)}%`);
                    tilt.style.setProperty('--sy', `${(py * 100).toFixed(1)}%`);
                    tilted = tilt;
                }
                const x = (e.clientX / window.innerWidth - 0.5) * 40;
                const y = (e.clientY / window.innerHeight - 0.5) * 40;
                document.querySelectorAll('.glow.bg').forEach((g, i) => {
                    g.style.translate = `${i % 2 ? -x : x}px ${y}px`;
                });
            });
        };

        window.addEventListener('pointermove', onMove, { passive: true });
        document.documentElement.addEventListener('pointerleave', resetTilt);
        return () => {
            window.removeEventListener('pointermove', onMove);
            document.documentElement.removeEventListener('pointerleave', resetTilt);
            cancelAnimationFrame(frame);
        };
    }, []);
}
