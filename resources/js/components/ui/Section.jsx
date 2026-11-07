import Scramble from '@/components/fx/Scramble';
import { cx } from '@/lib/utils';
import Reveal from './Reveal';

/** Page section with the standard container. `inner` adds classes to the container (e.g. "split"). */
export function Section({ id, alt, className, inner, narrow, label, children }) {
    return (
        <section id={id} className={cx('section', alt && 'alt', className)} aria-label={label}>
            <div className={cx('container', narrow && 'narrow', inner)}>{children}</div>
        </section>
    );
}

const scrambled = (children, duration) => (typeof children === 'string' ? <Scramble text={children} duration={duration} /> : children);

export function Chip({ children, className }) {
    return <span className={cx('chip glass eyebrow', className)}>{scrambled(children, 700)}</span>;
}

/** Gradient-highlighted words inside a heading; plain strings get the anagram decode effect. */
export function Grad({ children }) {
    return <span className="grad shimmer">{scrambled(children)}</span>;
}

/** Eyebrow chip + heading + optional lead. Centered by default; `left` aligns it to the start. */
export function SectionHead({ eyebrow, title, lead, left, flush, as: Heading = 'h2', reveal = true, className }) {
    const Wrapper = reveal ? Reveal : 'div';
    return (
        <Wrapper className={cx('section-head', left && 'left', flush && 'flush', className)}>
            {eyebrow && <Chip>{eyebrow}</Chip>}
            <Heading>{title}</Heading>
            {lead && <p className="lead">{lead}</p>}
        </Wrapper>
    );
}

/** Decorative blurred background glows, positioned relative to the page. */
export function Glows({ items }) {
    return items.map(({ soft, top, left, width, height }, i) => (
        <span key={i} className={cx('glow bg', soft && 'soft')} style={{ top, left, width, height }} aria-hidden="true" />
    ));
}
