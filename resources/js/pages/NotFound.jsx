import Button from '@/components/ui/Button';
import Reveal from '@/components/ui/Reveal';
import { Glows } from '@/components/ui/Section';

export default function NotFound() {
    return (
        <>
            <Glows items={[{ top: 0, left: 'calc(50% - 450px)', width: 900, height: 700 }]} />
            <section className="hero not-found">
                <div className="container inner">
                    <Reveal as="p" className="big-404 grad shimmer" aria-hidden="true">
                        404
                    </Reveal>
                    <Reveal as="h1" className="h2" delay={0.08}>
                        This idea hasn&apos;t become reality yet.
                    </Reveal>
                    <Reveal as="p" className="lead" delay={0.16}>
                        The page you are looking for moved or never existed. Let&apos;s get you back on track.
                    </Reveal>
                    <Reveal className="actions center" delay={0.24}>
                        <Button to="/">Back to home</Button>
                        <Button to="/contact" variant="secondary">
                            Contact us
                        </Button>
                    </Reveal>
                </div>
            </section>
        </>
    );
}
