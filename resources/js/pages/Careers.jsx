import { Fragment, useEffect, useState } from 'react';
import careers from '@content/careers.json';
import site from '@content/site.json';
import { FeatureCard } from '@/components/cards';
import JobApplyForm from '@/components/forms/JobApplyForm';
import { CtaPanel, PageHero } from '@/components/sections';
import Button from '@/components/ui/Button';
import { Glows, Grad, Section, SectionHead } from '@/components/ui/Section';
import { stagger } from '@/lib/utils';

const glows = [
    { top: -300, left: 'calc(50% - 500px)', width: 1000, height: 700 },
    { soft: true, top: 1400, left: -300, width: 700, height: 600 },
];

/** Open roles from the admin portal. `undefined` while loading, `[]` when there are none or the request fails. */
function useOpenRoles() {
    const [roles, setRoles] = useState(undefined);

    useEffect(() => {
        let active = true;
        fetch('/api/careers/openings', { headers: { Accept: 'application/json' } })
            .then((res) => (res.ok ? res.json() : Promise.reject(new Error(res.statusText))))
            .then((json) => active && setRoles(json.data))
            .catch(() => active && setRoles([]));
        return () => {
            active = false;
        };
    }, []);

    return roles;
}

export default function Careers() {
    const roles = useOpenRoles();
    const [applyingTo, setApplyingTo] = useState(null);

    return (
        <>
            <Glows items={glows} />

            <PageHero
                eyebrow="Careers"
                title={
                    <>
                        Build what&apos;s <Grad>next with us.</Grad>
                    </>
                }
                lead="Join a small, senior team shipping AI, automation and software that real businesses run on."
                actions={
                    <>
                        <Button to="#roles">See open roles</Button>
                        <Button to="/about" variant="secondary">
                            Life at ZAIRON Systems
                        </Button>
                    </>
                }
            />

            <Section className="tight-top">
                <SectionHead eyebrow="Why join" title="Work that matters, people who care." />
                <div className="grid grid-3 gap-md">
                    {careers.perks.map((perk, i) => (
                        <FeatureCard key={perk.title} {...perk} delay={stagger(i)} />
                    ))}
                </div>
            </Section>

            <Section alt id="roles">
                {roles?.length > 0 && (
                    <div className="stack gap-sm">
                        {roles.map((role) => {
                            const open = applyingTo === role.id;
                            return (
                                <Fragment key={role.id}>
                                    <article className="card role">
                                        <div>
                                            <h3 className="h4">{role.title}</h3>
                                            <div className="meta">
                                                {[role.team, role.type, role.location].map((m) => (
                                                    <span key={m} className="glass">
                                                        {m}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                        <Button variant="ghost" aria-expanded={open} onClick={() => setApplyingTo(open ? null : role.id)}>
                                            {open ? 'Close application' : 'Apply now'}
                                        </Button>
                                    </article>
                                    {open && (
                                        <div className="card" style={{ padding: 28 }}>
                                            <JobApplyForm opening={role} />
                                        </div>
                                    )}
                                </Fragment>
                            );
                        })}
                    </div>
                )}
                {roles?.length === 0 && <SectionHead eyebrow="Open roles" title="We'll update soon." />}
            </Section>

            <CtaPanel
                title="Don't see your role?"
                lead="Send us your CV and a note about what you want to build. We read every application."
                primary={{ label: 'Send open application', to: `mailto:${site.email}?subject=${encodeURIComponent('Open application')}` }}
                secondary={{ label: 'About ZAIRON Systems', to: '/about' }}
            />
        </>
    );
}
