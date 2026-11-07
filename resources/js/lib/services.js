import data from '@content/services.json';

export const { items: services, pillars, formOptions, budgets } = data;

const bySlug = Object.fromEntries(services.map((s) => [s.slug, s]));

export const findService = (slug) => bySlug[slug] ?? null;

export const pillarOf = (slug) => pillars.find((p) => p.services.includes(slug)) ?? null;

/** Services with a detail page link to it; the rest pre-select the service on the contact form. */
export const serviceHref = ({ slug }) => (data.details[slug] ? `/services/${slug}` : `/contact?service=${slug}`);

export const serviceDetail = (slug) => (data.details[slug] && bySlug[slug] ? { ...data.details[slug], ...bySlug[slug] } : null);

/** Other services in the same pillar, topped up from the rest so there are always `count`. */
export function relatedServices(slug, count = 3) {
    const sameP = (pillarOf(slug)?.services ?? []).filter((s) => s !== slug).map(findService);
    const others = services.filter((s) => s.slug !== slug && !sameP.includes(s));
    return [...sameP, ...others].slice(0, count);
}

export const footerServices = services.filter((s) => s.footer);
