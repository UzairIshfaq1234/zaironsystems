import legal from '@content/legal.json';
import site from '@content/site.json';
import Breadcrumb from '@/components/ui/Breadcrumb';
import Reveal from '@/components/ui/Reveal';
import { Chip, Glows } from '@/components/ui/Section';
import SmartLink from '@/components/ui/SmartLink';

/** Privacy Policy / Terms of Service, rendered from resources/content/legal.json. */
export default function Legal({ doc }) {
    const page = legal[doc];

    return (
        <>
            <Glows items={[{ top: -300, left: 'calc(50% - 500px)', width: 1000, height: 700 }]} />
            <section className="hero left">
                <div className="container narrow stack gap-lg start">
                    <Breadcrumb items={[{ label: 'Home', to: '/' }, { label: page.title }]} />
                    <Reveal>
                        <Chip>{page.eyebrow}</Chip>
                    </Reveal>
                    <Reveal as="h1" delay={0.08}>
                        {page.title}
                    </Reveal>
                    <Reveal as="p" className="small muted" delay={0.12}>
                        Last updated {page.updated}
                    </Reveal>
                    <Reveal as="p" className="lead" delay={0.16}>
                        {page.intro}
                    </Reveal>
                </div>
            </section>
            <section className="section flush-top">
                <div className="container narrow">
                    <Reveal as="article" className="card roomy legal">
                        {page.sections.map((section) => (
                            <section key={section.heading}>
                                <h2>{section.heading}</h2>
                                {section.body.map((p) => (
                                    <p key={p}>{p}</p>
                                ))}
                            </section>
                        ))}
                        <section>
                            <h2>Contact</h2>
                            <p>
                                {site.name} · <SmartLink to={`mailto:${site.email}`}>{site.email}</SmartLink>
                            </p>
                        </section>
                    </Reveal>
                </div>
            </section>
        </>
    );
}
