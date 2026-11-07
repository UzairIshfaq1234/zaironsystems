import { useRef } from 'react';
import insights from '@content/insights.json';
import { PostCard } from '@/components/cards';
import NewsletterForm from '@/components/forms/NewsletterForm';
import { PageHero } from '@/components/sections';
import Pagination from '@/components/ui/Pagination';
import Reveal from '@/components/ui/Reveal';
import { Glows, Grad, Section } from '@/components/ui/Section';
import ToggleGroup from '@/components/ui/ToggleGroup';
import useCollection from '@/hooks/useCollection';
import { stagger } from '@/lib/utils';

const glows = [
    { top: -300, left: 'calc(50% - 500px)', width: 1000, height: 700 },
    { soft: true, top: 1500, left: 'calc(100% - 500px)', width: 700, height: 600 },
];

export default function Insights() {
    const listRef = useRef(null);
    const { filter, setFilter, visible, page, setPage, pageCount } = useCollection(insights.posts, insights.pageSize);

    const goTo = (n) => {
        setPage(n);
        listRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    return (
        <>
            <Glows items={glows} />

            <PageHero
                eyebrow="Insights"
                title={
                    <>
                        Ideas on building <Grad>better software.</Grad>
                    </>
                }
                lead="Practical guides on AI, automation, web, mobile, cloud and security from the engineers who build them."
            />

            <Section className="flush-top">
                <Reveal>
                    <ToggleGroup className="center" options={insights.filters} value={filter} onChange={setFilter} label="Filter articles" />
                </Reveal>
                {filter === 'all' && page === 1 && insights.featured && <PostCard post={insights.featured} featured />}
                <div ref={listRef} className="grid grid-3" aria-live="polite">
                    {visible.map((post, i) => (
                        <PostCard key={post.title} post={post} delay={stagger(i)} />
                    ))}
                </div>
                {visible.length === 0 && <p className="empty-state">No articles in this category yet.</p>}
                <Pagination page={page} pageCount={pageCount} onChange={goTo} label="Article pages" />
            </Section>

            <Section className="tight-top">
                <Reveal className="newsletter glass">
                    <span className="glow" aria-hidden="true" />
                    <div className="stack">
                        <h2 className="h3">Get one useful engineering read a month.</h2>
                        <p>No spam. Unsubscribe any time.</p>
                    </div>
                    <NewsletterForm placeholder="you@company.com" label="Email" />
                </Reveal>
            </Section>
        </>
    );
}
