import work from '@content/work.json';
import { CaseCard } from '@/components/cards';
import { CtaPanel, PageHero } from '@/components/sections';
import Button from '@/components/ui/Button';
import Reveal from '@/components/ui/Reveal';
import { Glows, Grad, Section } from '@/components/ui/Section';
import ToggleGroup from '@/components/ui/ToggleGroup';
import useCollection from '@/hooks/useCollection';

const glows = [
    { top: -300, left: 'calc(50% - 500px)', width: 1000, height: 700 },
    { soft: true, top: 1400, left: 'calc(100% - 500px)', width: 700, height: 600 },
];

export default function Work() {
    const { filter, setFilter, upTo, hasMore, page, setPage } = useCollection(work.cases, work.pageSize);

    return (
        <>
            <Glows items={glows} />

            <PageHero
                eyebrow="Case studies"
                title={
                    <>
                        Real systems. <Grad>Real results.</Grad>
                    </>
                }
                lead="A selection of products, platforms and automations we have designed, built and run for our clients."
            />

            <Section className="flush-top">
                <Reveal>
                    <ToggleGroup className="center" options={work.filters} value={filter} onChange={setFilter} label="Filter case studies" />
                </Reveal>
                <div className="grid grid-2" aria-live="polite">
                    {upTo.map((c, i) => (
                        <CaseCard key={c.title} {...c} delay={(i % 2) * 0.08} />
                    ))}
                </div>
                {upTo.length === 0 && <p className="empty-state">No case studies in this category yet.</p>}
                {hasMore && (
                    <div className="actions center spaced">
                        <Button variant="secondary" onClick={() => setPage(page + 1)}>
                            Load more case studies
                        </Button>
                    </div>
                )}
            </Section>

            <CtaPanel title="Your project could be next." lead="Tell us what you want to build, and we will show you how we would approach it." />
        </>
    );
}
