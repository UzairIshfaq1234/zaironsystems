import faqs from '@content/faqs.json';
import home from '@content/home.json';
import site from '@content/site.json';
import work from '@content/work.json';
import { CaseCard, ServiceCard, StatCard, StepCard } from '@/components/cards';
import { CtaPanel, PageHero, ProductWindow } from '@/components/sections';
import Button from '@/components/ui/Button';
import Faq from '@/components/ui/Faq';
import Icon from '@/components/ui/Icon';
import Reveal from '@/components/ui/Reveal';
import { Glows, Grad, Section, SectionHead } from '@/components/ui/Section';
import TagCloud from '@/components/ui/TagCloud';
import { services } from '@/lib/services';
import { stagger } from '@/lib/utils';

const glows = [
    { top: -300, left: 'calc(50% - 550px)', width: 1100, height: 760 },
    { soft: true, top: 1500, left: -360, width: 760, height: 620 },
    { soft: true, top: 2600, left: 'calc(100% - 500px)', width: 700, height: 600 },
    { soft: true, top: 5200, left: -200, width: 700, height: 600 },
];

export default function Home() {
    return (
        <>
            <Glows items={glows} />

            <PageHero
                display
                eyebrow="AI · Automation · Software Engineering"
                title={
                    <>
                        Ideas, engineered
                        <br />
                        into <Grad>reality.</Grad>
                    </>
                }
                lead="ZAIRON Systems designs, builds, automates and secures software for ambitious businesses, from AI and computer vision to web, mobile, data and cloud."
                actions={
                    <>
                        <Button to="/contact">Start a project</Button>
                        <Button to="/services" variant="secondary">
                            Explore services
                        </Button>
                    </>
                }
            >
                <Reveal className="trust" delay={0.32}>
                    <p className="small muted">Trusted by teams building what&apos;s next</p>
                    <ul className="logo-row">
                        {site.clients.map((client) => (
                            <li key={client.name} className="logo-slot glass">
                                <img src={client.logo} alt={`${client.name} logo`} width="64" height="64" decoding="async" />
                            </li>
                        ))}
                    </ul>
                </Reveal>
            </PageHero>

            <ProductWindow />

            <Section id="services">
                <SectionHead
                    eyebrow="What we do"
                    title="One partner for everything your product needs."
                    lead="From the first line of code to secure, automated operations at scale, our engineers cover the full lifecycle."
                />
                <div className="grid grid-3">
                    {services.map((s, i) => (
                        <ServiceCard key={s.slug} service={s} delay={stagger(i)} />
                    ))}
                    <Reveal className="card card-maroon svc" delay={0.12}>
                        <h3>Not sure where to start?</h3>
                        <p>Tell us the problem. We will recommend the right mix of services in a free 30-minute call.</p>
                        <Button to="/contact" variant="secondary">
                            Book a free call
                        </Button>
                    </Reveal>
                </div>
            </Section>

            <Section id="process">
                <SectionHead
                    eyebrow="How we work"
                    title="From idea to impact in five clear steps."
                    lead="A transparent process with a working demo every sprint, so you always see progress."
                />
                <Reveal variant="steps">
                    <div className="track" aria-hidden="true">
                        <span />
                    </div>
                    <ol className="grid grid-5">
                        {home.process.map((step, i) => (
                            <StepCard key={step.title} as="li" index={i} {...step} delay={i * 0.08} />
                        ))}
                    </ol>
                </Reveal>
            </Section>

            <Section alt inner="split">
                <Reveal>
                    <SectionHead
                        left
                        reveal={false}
                        eyebrow="Why ZAIRON Systems"
                        title="Engineering you can trust, delivered with clarity."
                        lead="Senior engineers, honest timelines and security from day one. We treat your product like our own."
                    />
                    <ul className="checks">
                        {home.why.map((item) => (
                            <li key={item}>
                                <span className="check">
                                    <Icon name="check" />
                                </span>
                                {item}
                            </li>
                        ))}
                    </ul>
                </Reveal>
                <div className="grid grid-2 gap-sm">
                    {home.stats.map((stat, i) => (
                        <StatCard key={stat.label} {...stat} delay={i * 0.06} />
                    ))}
                </div>
            </Section>

            <Section>
                <Reveal className="head-row">
                    <SectionHead left flush reveal={false} eyebrow="Selected work" title="Built for real operations, not demos." />
                    <Button to="/work" variant="secondary">
                        View all work
                    </Button>
                </Reveal>
                <div className="grid grid-2">
                    {work.cases.slice(0, 2).map((c, i) => (
                        <CaseCard key={c.title} {...c} delay={i * 0.08} />
                    ))}
                </div>
            </Section>

            <Section>
                <SectionHead
                    eyebrow="Technology"
                    title="Modern stacks, chosen for your problem."
                    lead="We pick proven tools for each job, never a one-size-fits-all template."
                />
                <TagCloud items={home.tech} />
            </Section>

            <Section id="faq" inner="split top">
                <Reveal>
                    <SectionHead
                        left
                        reveal={false}
                        eyebrow="FAQ"
                        title="Questions, answered."
                        lead="Can't find what you are looking for? Talk to us directly."
                    />
                    <Button to="/contact" variant="secondary">
                        Ask us anything
                    </Button>
                </Reveal>
                <Reveal delay={0.1}>
                    <Faq items={faqs.home} />
                </Reveal>
            </Section>

            <CtaPanel
                title="Have an idea? Let's make it real."
                lead="Tell us what you are building. You will hear back from a senior engineer within one business day."
            />
        </>
    );
}
