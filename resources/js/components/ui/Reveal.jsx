import { useCallback } from 'react';
import useInView from '@/hooks/useInView';
import { cx } from '@/lib/utils';

/**
 * Animates its element in on first scroll into view by adding `.in`.
 * `variant` is the base animation class (reveal, tilt, steps…); `delay` is in seconds.
 * An outer `ref` is supported and receives the same element.
 */
export default function Reveal({ as: Tag = 'div', variant = 'reveal', delay, className, style, children, ref: outerRef, ...rest }) {
    const [inViewRef, inView] = useInView();

    const ref = useCallback(
        (el) => {
            inViewRef.current = el;
            if (typeof outerRef === 'function') outerRef(el);
            else if (outerRef) outerRef.current = el;
        },
        [inViewRef, outerRef],
    );

    return (
        <Tag
            ref={ref}
            className={cx(variant, inView && 'in', className)}
            style={delay ? { ...style, '--d': `${delay}s` } : style}
            {...rest}
        >
            {children}
        </Tag>
    );
}
