/** Join class names, skipping falsy values. */
export const cx = (...classes) => classes.filter(Boolean).join(' ');

/** Reveal delay for item `i` in a grid with `cols` columns, so each row animates left to right. */
export const stagger = (i, cols = 3, step = 0.06) => (i % cols) * step;

export const prefersReducedMotion = () =>
    typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** True for links that leave the SPA (other sites, mail, phone). */
export const isExternal = (to) => /^(https?:|mailto:|tel:)/i.test(to);

export const telHref = (phone) => `tel:${phone.replace(/[^\d+]/g, '')}`;
