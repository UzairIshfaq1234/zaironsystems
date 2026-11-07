import { useEffect, useRef, useState } from 'react';
import { prefersReducedMotion } from '@/lib/utils';

// One observer shared by every animated element on the page.
const callbacks = new WeakMap();
let observer;

function getObserver() {
    observer ??= new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;
                callbacks.get(entry.target)?.();
                callbacks.delete(entry.target);
                observer.unobserve(entry.target);
            }
        },
        { threshold: 0.18, rootMargin: '0px 0px -40px 0px' },
    );
    return observer;
}

const supported = () => typeof window !== 'undefined' && 'IntersectionObserver' in window && !prefersReducedMotion();

/** Returns [ref, inView]; flips to true once the element scrolls into view, then stops observing. */
export default function useInView() {
    const ref = useRef(null);
    const [inView, setInView] = useState(() => !supported());

    useEffect(() => {
        const el = ref.current;
        if (inView || !el) return;
        const obs = getObserver();
        callbacks.set(el, () => setInView(true));
        obs.observe(el);
        return () => {
            callbacks.delete(el);
            obs.unobserve(el);
        };
    }, [inView]);

    return [ref, inView];
}
