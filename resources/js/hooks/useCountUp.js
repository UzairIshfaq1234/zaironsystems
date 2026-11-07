import { useEffect, useState } from 'react';
import { prefersReducedMotion } from '@/lib/utils';

/** Animates from 0 to `target` (ease-out) once `start` is true. */
export default function useCountUp(target, start, duration = 1200) {
    const [value, setValue] = useState(0);

    useEffect(() => {
        if (!start || typeof target !== 'number') return;
        if (prefersReducedMotion()) return setValue(target);

        let frame;
        let t0;
        const tick = (t) => {
            t0 ??= t;
            const p = Math.min((t - t0) / duration, 1);
            setValue(Math.round(target * (1 - Math.pow(1 - p, 4))));
            if (p < 1) frame = requestAnimationFrame(tick);
        };
        frame = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(frame);
    }, [target, start, duration]);

    return value;
}
