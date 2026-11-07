import { Fragment, useRef, useState } from 'react';
import { useParams } from 'react-router-dom';
import engagement from '@content/engagement.json';
import faqs from '@content/faqs.json';
import { FeatureCard, PlanCard, ServiceCard, StepCard } from '@/components/cards';
import HoloVisual from '@/components/fx/HoloVisual';
import ContactForm from '@/components/forms/ContactForm';
import { CtaPanel, VisionPreview } from '@/components/sections';
import Breadcrumb from '@/components/ui/Breadcrumb';
import Button from '@/components/ui/Button';
import Faq from '@/components/ui/Faq';
import Icon from '@/components/ui/Icon';
import Reveal from '@/components/ui/Reveal';
import { Chip, Glows, Grad, Section, SectionHead } from '@/components/ui/Section';
import TagCloud from '@/components/ui/TagCloud';
import { pillarOf, relatedServices, serviceDetail } from '@/lib/services';
import { prefersReducedMotion, stagger } from '@/lib/utils';
import NotFound from './NotFound';

const glows = [
    { top: -260, left: 'calc(50% - 100px)', width: 900, height: 700 },
    { soft: true, top: 1600, left: -300, width: 700, height: 600 },
    { soft: true, top: 3400, left: 'calc(100% - 500px)', width: 700, height: 600 },
];

/** One template for every service; content comes from services.json → details[slug]. */
export default function ServiceDetail() {
    const { slug } = useParams();
    const s = serviceDetail(slug);
    const [plan, setPlan] = useState('build');
    const formRef = useRef(null);

    if (!s) return <NotFound />;

    const pillar = pillarOf(slug);
    const choosePlan = (id) => {
        setPlan(id);
        formRef.current?.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
    };

    return (
        <>
            <Glows items={glows} />

            <section className="hero left">
                <div className="container">
                    <Breadcrumb
                        items={[
                            { label: 'Home', to: '/' },
                            { label: 'Services', to: '/services' },
                            { label: s.title },
                        ]}
                    />
                    <div className="split">
                        <div className="stack gap-lg start">
                            <Reveal>
                                <Chip>{`Service · ${pillar?.title ?? 'ZAIRON Systems'}`}</Chip>
                            </Reveal>
                            <Reveal as="h1" delay={0.08}>
                                {s.heading} <Grad>{s.highlight}</Grad>
                            </Reveal>
                            <Reveal as="p" className="lead" delay={0.16}>
                                {s.lead}
                            </Reveal>
                            <Reveal className="actions" delay={0.24}>
                                <Button to="#start">Start a project</Button>
                                <Button to={`/contact?service=${s.slug}`} variant="secondary">
                                    Contact us
                                </Button>
                            </Reveal>
                        </div>
                        {s.visual === 'vision' ? (
                            <Reveal variant="window glass-strong tilt" className="padded" data-tilt="6">
                                <VisionPreview tall boxes={s.preview.boxes} metrics={s.preview.metrics} />
                            </Reveal>
                        ) : (
                            <Reveal variant="tilt" data-tilt="8">
                                <HoloVisual icon={s.icon} chips={s.orbit} />
                            </Reveal>
                        )}
                    </div>
                </div>
            </section>

            <Section>
                <SectionHead eyebrow="What we deliver" title={s.capabilitiesTitle} lead={s.capabilitiesLead} />
                <div className="grid grid-3">
                    {s.capabilities.map((item, i) => (
                        <FeatureCard key={item.title} {...item} small={false} delay={stagger(i)} />
                    ))}
                </div>
            </Section>

            <Section alt inner="split top">
                <Reveal>
                    <SectionHead left reveal={false} eyebrow="What you get" title="Everything you need to launch and grow." />
                    <ul className="checks tight">
                        {s.deliverables.map((d) => (
                            <li key={d}>
                                <span className="check">
                                    <Icon name="check" />
                                </span>
                                {d}
                            </li>
                        ))}
                    </ul>
                </Reveal>
                <Reveal className="card roomy ideal" delay={0.1} data-tilt="5">
                    <Chip>Ideal for</Chip>
                    <ul>
                        {s.idealFor.map((who) => (
                            <li key={who}>
                                <Icon name="arrow" />
                                {who}
                            </li>
                        ))}
                    </ul>
                    <Button to="#start">Start a project</Button>
                </Reveal>
            </Section>

            <Section>
                <SectionHead eyebrow="How it works" title={s.processTitle} />
                <ol className="pipeline">
                    {s.process.map((step, i) => (
                        <Fragment key={step.title}>
                            {i > 0 && (
                                <li className="arrow" aria-hidden="true">
                                    <Icon name="arrow" />
                                </li>
                            )}
                            <StepCard as="li" mono index={i} {...step} delay={i * 0.1} />
                        </Fragment>
                    ))}
                </ol>
            </Section>

            <Section className="tight-top tight-bottom">
                <SectionHead eyebrow="Tools we use" title="Proven tools, chosen for your problem." />
                <TagCloud items={s.tools} narrow />
            </Section>

            <Section id="start">
                <SectionHead
                    eyebrow="Start a project"
                    title={`Start your ${s.title} project.`}
                    lead="Pick how you would like to begin. Every option starts with a free call, and you get a fixed quote before any work starts."
                />
                <div className="grid grid-3">
                    {engagement.plans.map((p, i) => (
                        <PlanCard key={p.id} plan={p} selected={plan === p.id} onSelect={choosePlan} delay={i * 0.08} />
                    ))}
                </div>
                <Reveal ref={formRef} className="form glass-strong service-form">
                    <ContactForm service={s} plans={engagement.plans} plan={plan} onPlanChange={setPlan} />
                </Reveal>
            </Section>

            {faqs[s.slug] && (
                <Section narrow className="tight-top">
                    <SectionHead eyebrow="FAQ" title={`${s.title} questions.`} />
                    <Reveal>
                        <Faq items={faqs[s.slug]} />
                    </Reveal>
                </Section>
            )}

            <Section>
                <SectionHead eyebrow="Related services" title="Often paired with this service." />
                <div className="grid grid-3">
                    {relatedServices(s.slug).map((r, i) => (
                        <ServiceCard key={r.slug} service={r} delay={stagger(i)} />
                    ))}
                </div>
            </Section>

            <CtaPanel {...s.cta} />
        </>
    );
}
