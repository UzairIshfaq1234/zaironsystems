import about from '@content/about.json';
import site from '@content/site.json';
import { FeatureCard } from '@/components/cards';
import { CtaPanel, PageHero } from '@/components/sections';
import Button from '@/components/ui/Button';
import { LogoMark } from '@/components/ui/Logo';
import Reveal from '@/components/ui/Reveal';
import { Chip, Glows, Grad, Section, SectionHead } from '@/components/ui/Section';
import SmartLink from '@/components/ui/SmartLink';

const glows = [
    { top: -300, left: 'calc(50% - 500px)', width: 1000, height: 700 },
    { soft: true, top: 1300, left: -300, width: 700, height: 600 },
];

export default function About() {
    return (
        <>
            <Glows items={glows} />

            <PageHero
                eyebrow="About ZAIRON Systems"
                title={
                    <>
                        Two founders. <Grad>One intelligent core.</Grad>
                    </>
                }
                lead="We are a technology company that turns ideas into working systems, built on craft, trust and partnership."
                actions={
                    <>
                        <Button to="/contact">Work with us</Button>
                        <Button to="/careers" variant="secondary">
                            Join the team
                        </Button>
                    </>
                }
            />

            <Section className="tight-top" inner="split">
                <Reveal className="stack gap-lg">
                    <SectionHead left flush reveal={false} eyebrow="Our story" title="Named for the helper. Built to help." />
                    {about.story.map((p) => (
                        <p key={p} className="lead">
                            {p}
                        </p>
                    ))}
                </Reveal>
                <Reveal variant="mark-panel glass tilt" data-tilt="6">
                    <span className="glow" aria-hidden="true" />
                    <LogoMark className="about-logo" />
                </Reveal>
            </Section>

            <Section alt inner="grid grid-2">
                {[
                    ['Mission', about.mission],
                    ['Vision', about.vision],
                ].map(([label, text], i) => (
                    <Reveal key={label} className="card roomy stack" delay={i * 0.08}>
                        <Chip>{label}</Chip>
                        <h2 className="h3">{text}</h2>
                    </Reveal>
                ))}
            </Section>

            <Section>
                <SectionHead eyebrow="Our values" title="What we stand for." />
                <div className="grid grid-4">
                    {about.values.map((v, i) => (
                        <FeatureCard key={v.title} {...v} delay={i * 0.06} />
                    ))}
                </div>
            </Section>

            <Section className="tight-top" narrow>
                <SectionHead eyebrow="Leadership" title="The founders." />
                <div className="grid grid-2">
                    {site.founders.map((f, i) => (
                        <Reveal as="article" key={f.name} className="card stack" delay={i * 0.08}>
                            <div className="photo">
                                {f.photo ? <img src={f.photo} alt={`Photo of ${f.name}`} loading="lazy" /> : <span aria-hidden="true">[PHOTO]</span>}
                            </div>
                            <h3>{f.name}</h3>
                            <p className="accent">{f.role}</p>
                            <div className="row small">
                                {f.links
                                    .filter((l) => l.url)
                                    .map((l) => (
                                        <SmartLink key={l.label} to={l.url} aria-label={`${f.name} on ${l.label}`}>
                                            {l.label}
                                        </SmartLink>
                                    ))}
                            </div>
                        </Reveal>
                    ))}
                </div>
            </Section>

            <CtaPanel
                title="Let's build what's next, together."
                lead="Whether you are starting fresh or scaling up, we would love to hear what you are working on."
                secondary={{ label: 'See open roles', to: '/careers' }}
            />
        </>
    );
}
