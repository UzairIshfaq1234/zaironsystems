import engagement from '@content/engagement.json';
import faqs from '@content/faqs.json';
import site from '@content/site.json';
import { StepCard } from '@/components/cards';
import ContactForm from '@/components/forms/ContactForm';
import Faq from '@/components/ui/Faq';
import Icon from '@/components/ui/Icon';
import Reveal from '@/components/ui/Reveal';
import { Chip, Glows, Grad, Section, SectionHead } from '@/components/ui/Section';
import SmartLink from '@/components/ui/SmartLink';

const glows = [
    { top: -200, left: -200, width: 900, height: 700 },
    { top: 300, left: 'calc(100% - 700px)', width: 700, height: 700 },
];

const methods = [{ icon: 'mail', label: 'Email us', value: site.email, to: `mailto:${site.email}` }];

function Method({ icon, label, value, to, delay }) {
    const body = (
        <>
            <span className="tile">
                <Icon name={icon} />
            </span>
            <span>
                <span className="small muted">{label}</span>
                <b>{value}</b>
            </span>
        </>
    );
    return to ? (
        <Reveal as={SmartLink} to={to} className="card method hover" delay={delay}>
            {body}
        </Reveal>
    ) : (
        <Reveal className="card method" delay={delay}>
            {body}
        </Reveal>
    );
}

export default function Contact() {
    return (
        <>
            <Glows items={glows} />

            <section className="hero left">
                <div className="container contact-grid">
                    <div className="stack" style={{ gap: 28 }}>
                        <Reveal>
                            <Chip>Contact</Chip>
                        </Reveal>
                        <Reveal as="h1" delay={0.08}>
                            Let&apos;s build something <Grad>great.</Grad>
                        </Reveal>
                        <Reveal as="p" className="lead" delay={0.16}>
                            Tell us about your idea, product or problem. A senior engineer will reply within one business day with next steps.
                        </Reveal>
                        {methods.map((m, i) => (
                            <Method key={m.label} {...m} delay={i * 0.06} />
                        ))}
                        <Reveal as="p" className="online">
                            {site.responseTime}
                        </Reveal>
                    </div>
                    <Reveal className="form glass-strong" delay={0.12}>
                        <ContactForm />
                    </Reveal>
                </div>
            </section>

            <Section className="tight-top">
                <SectionHead eyebrow="What happens next" title="From message to plan in three steps." />
                <div className="grid grid-3">
                    {engagement.nextSteps.map((step, i) => (
                        <StepCard key={step.title} index={i} {...step} delay={i * 0.08} />
                    ))}
                </div>
            </Section>

            <Section className="flush-top" narrow>
                <SectionHead eyebrow="Before you write" title="Quick answers." />
                <Reveal>
                    <Faq items={faqs.contact} />
                </Reveal>
            </Section>
        </>
    );
}
