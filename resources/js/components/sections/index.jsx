import Button from '@/components/ui/Button';
import Icon from '@/components/ui/Icon';
import Reveal from '@/components/ui/Reveal';
import { Chip, Section } from '@/components/ui/Section';
import { cx } from '@/lib/utils';

/** Page intro: chip, h1, lead and actions, each revealed in sequence. */
export function PageHero({ eyebrow, title, lead, actions, display, children }) {
    return (
        <section className="hero">
            <div className="container inner">
                <Reveal>
                    <Chip>{eyebrow}</Chip>
                </Reveal>
                <Reveal as="h1" className={cx(display && 'display')} delay={0.08}>
                    {title}
                </Reveal>
                {lead && (
                    <Reveal as="p" className="lead" delay={0.16}>
                        {lead}
                    </Reveal>
                )}
                {actions && (
                    <Reveal className="actions center" delay={0.24}>
                        {actions}
                    </Reveal>
                )}
                {children}
            </div>
        </section>
    );
}

const START = { label: 'Start a project', to: '/contact' };
const CALL = { label: 'Book a free call', to: '/contact' };

/** Closing call-to-action panel shown at the bottom of most pages. */
export function CtaPanel({ title, lead, primary = START, secondary = CALL }) {
    return (
        <Section className="cta-top">
            <Reveal className="cta-panel glass">
                <span className="glow" aria-hidden="true" />
                <h2>{title}</h2>
                <p className="lead">{lead}</p>
                <div className="actions center">
                    <Button to={primary.to}>{primary.label}</Button>
                    <Button to={secondary.to} variant="secondary">
                        {secondary.label}
                    </Button>
                </div>
            </Reveal>
        </Section>
    );
}

/** Mock computer-vision output: scan lines, labelled detection boxes and a metrics row. */
export function VisionPreview({ boxes, metrics, lines = false, tall = false }) {
    return (
        <>
            <div className={cx('preview', tall && 'tall')} aria-hidden="true">
                {lines &&
                    [160, 220, 280, 160, 220, 280].map((width, i) => (
                        <span key={i} className="ln" style={{ top: 60 + i * 26, width }} />
                    ))}
                {boxes.map((b) => (
                    <span
                        key={b.label}
                        className="box"
                        style={{ left: b.left, top: b.top, width: b.width, height: b.height, transitionDelay: b.delay ? `${b.delay}s` : undefined }}
                    >
                        <span className="tag">{b.label}</span>
                    </span>
                ))}
            </div>
            <div className="metrics">
                {metrics.map((m) => (
                    <div key={m.label} className="metric glass">
                        <span className="small muted">{m.label}</span>
                        <b>{m.value}</b>
                    </div>
                ))}
            </div>
        </>
    );
}

const CODE = [
    ['c eyebrow', 'deploy.ts'],
    ['c', '// ship with confidence'],
    ['k', 'await pipeline.run({'],
    ['', "  build:    'passing',"],
    ['', "  tests:    'all green',"],
    ['', "  security: 'scanned',"],
    ['', "  target:   'production',"],
    ['k', '});'],
    ['', ' '],
    ['ok', '✓ deployed in 2m 41s'],
    ['ok', '✓ 0 vulnerabilities found'],
];

const STATUS = [
    { icon: 'refresh', title: 'Automation', text: '1,284 tasks run today' },
    { icon: 'shield', title: 'Security', text: 'No open threats' },
    { icon: 'cloud', title: 'Infrastructure', text: 'All systems normal' },
    { icon: 'check-circle', title: 'Quality', text: '248 tests passing' },
];

/** Home-page "control center" product mock. Purely decorative. */
export function ProductWindow() {
    return (
        <section className="window-wrap" aria-label="Product preview">
            <div className="container">
                <Reveal variant="window glass-strong tilt" data-tilt="4">
                    <span className="glow" aria-hidden="true" />
                    <div className="titlebar" aria-hidden="true">
                        <span className="dot" />
                        <span className="dot" />
                        <span className="dot" />
                        <span className="url glass">app.zairon.com/control-center</span>
                    </div>
                    <div className="window-body">
                        <div className="code" aria-hidden="true">
                            {CODE.map(([cls, text], i) => (
                                <div key={i} className={cx('line', cls)} style={{ '--d': `${0.3 + i * 0.12}s` }}>
                                    {text}
                                </div>
                            ))}
                        </div>
                        <div className="vision">
                            <div className="vision-head">
                                <span>Vision model · document scan</span>
                                <span className="live">LIVE</span>
                            </div>
                            <VisionPreview
                                lines
                                boxes={[
                                    { label: 'invoice_no · 0.98', left: '6%', top: 48, width: '58%', height: 70 },
                                    { label: 'total · 0.97', left: '56%', top: 150, width: '32%', height: 70, delay: 0.3 },
                                ]}
                                metrics={[
                                    { label: 'Fields extracted', value: '12 / 12' },
                                    { label: 'Confidence', value: '97.6%' },
                                    { label: 'Time', value: '0.4s' },
                                ]}
                            />
                        </div>
                        <div className="status">
                            {STATUS.map((s) => (
                                <div key={s.title} className="status-item glass">
                                    <span className="tile">
                                        <Icon name={s.icon} />
                                    </span>
                                    <span>
                                        <b>{s.title}</b>
                                        <span className="small">{s.text}</span>
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </Reveal>
            </div>
        </section>
    );
}
