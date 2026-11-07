import engagement from '@content/engagement.json';
import { ModelCard, ServiceCard } from '@/components/cards';
import { CtaPanel, PageHero } from '@/components/sections';
import Button from '@/components/ui/Button';
import Reveal from '@/components/ui/Reveal';
import { Glows, Grad, Section, SectionHead } from '@/components/ui/Section';
import TagCloud from '@/components/ui/TagCloud';
import { findService, pillars } from '@/lib/services';

const glows = [
    { top: -300, left: 'calc(50% - 500px)', width: 1000, height: 700 },
    { soft: true, top: 1800, left: -300, width: 700, height: 600 },
    { soft: true, top: 3200, left: 'calc(100% - 500px)', width: 700, height: 600 },
];

export default function Services() {
    return (
        <>
            <Glows items={glows} />

            <PageHero
                eyebrow="Services"
                title={
                    <>
                        Engineering services for <Grad>every stage of your product.</Grad>
                    </>
                }
                lead="Build, add intelligence, automate and run securely. Pick one service or let one team own the whole lifecycle."
                actions={
                    <>
                        <Button to="/contact">Start a project</Button>
                        <Button to="/contact" variant="secondary">
                            Talk to an engineer
                        </Button>
                    </>
                }
            />

            <Section className="tight-top">
                {pillars.map((pillar) => (
                    <div key={pillar.num} className="pillar">
                        <Reveal className="stack">
                            <span className="num grad">{pillar.num}</span>
                            <h2 className="h3">{pillar.title}</h2>
                            <p>{pillar.text}</p>
                        </Reveal>
                        <div className="grid grid-2">
                            {pillar.services.map((slug, i) => (
                                <ServiceCard key={slug} service={findService(slug)} delay={i * 0.06} />
                            ))}
                        </div>
                    </div>
                ))}
            </Section>

            <Section>
                <SectionHead
                    eyebrow="Ways to work together"
                    title="Flexible engagement, clear commitments."
                    lead="Choose the model that fits your stage and budget. Switch any time as you grow."
                />
                <div className="grid grid-3">
                    {engagement.models.map((m, i) => (
                        <ModelCard key={m.title} {...m} delay={i * 0.08} />
                    ))}
                </div>
            </Section>

            <Section className="tight-top">
                <SectionHead eyebrow="Industries" title="Built for the way your industry works." />
                <TagCloud items={engagement.industries} />
            </Section>

            <CtaPanel title="Not sure which service you need?" lead="Describe the problem. We will recommend the right approach in a free 30-minute call." />
        </>
    );
}
