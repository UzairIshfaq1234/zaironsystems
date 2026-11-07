import { useEffect, useState } from 'react';
import useInView from '@/hooks/useInView';
import { prefersReducedMotion } from '@/lib/utils';

const NOISE = '#%&*+=<>/';

/**
 * Anagram decode: when scrolled into view, the text shuffles its own letters, then locks them in
 * left to right. Screen readers always get the real text.
 */
export default function Scramble({ text, duration = 1100 }) {
    const [ref, inView] = useInView();
    const [shown, setShown] = useState(text);

    useEffect(() => {
        if (!inView || prefersReducedMotion()) return setShown(text);

        const pool = [...text.replace(/\s/g, ''), ...NOISE];
        const pick = () => pool[(Math.random() * pool.length) | 0];
        const start = performance.now();
        let frame;
        let last = 0;

        const tick = (now) => {
            const p = Math.min((now - start) / duration, 1);
            if (now - last > 40 || p === 1) {
                last = now;
                const locked = Math.floor(p * text.length);
                setShown([...text].map((c, i) => (i < locked || c === ' ' ? c : pick())).join(''));
            }
            if (p < 1) frame = requestAnimationFrame(tick);
        };
        frame = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(frame);
    }, [inView, text, duration]);

    return (
        <span ref={ref} className="scramble">
            <span className="sr-only">{text}</span>
            <span aria-hidden="true">{shown}</span>
        </span>
    );
}
