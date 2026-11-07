import { Link } from 'react-router-dom';
import Button from '@/components/ui/Button';
import Icon from '@/components/ui/Icon';
import Reveal from '@/components/ui/Reveal';
import SmartLink from '@/components/ui/SmartLink';
import useCountUp from '@/hooks/useCountUp';
import useInView from '@/hooks/useInView';
import { serviceHref } from '@/lib/services';
import { cx } from '@/lib/utils';

/** Linked service tile used on Home, Services and related-service rows. */
export function ServiceCard({ service, delay }) {
    return (
        <Reveal as={Link} to={serviceHref(service)} className="card svc" delay={delay} data-tilt="8">
            <span className="icon-tile">
                <Icon name={service.icon} />
            </span>
            <h3 className="h4">{service.title}</h3>
            <p>{service.text}</p>
            <span className="more">
                Explore service
                <Icon name="arrow" />
            </span>
        </Reveal>
    );
}

/** Icon + title + text. Used for values, perks and capabilities. */
export function FeatureCard({ icon, title, text, delay, small = true }) {
    return (
        <Reveal className="card stack gap-sm" delay={delay} data-tilt="10">
            <span className="icon-tile">
                <Icon name={icon} />
            </span>
            <h3 className="h4">{title}</h3>
            <p className={cx(small && 'small')}>{text}</p>
        </Reveal>
    );
}

/** Numbered process step. `mono` renders the small accent label used in pipelines. */
export function StepCard({ index, title, text, delay, as = 'div', mono }) {
    const num = String(index + 1).padStart(2, '0');
    return (
        <Reveal as={as} className="card step" delay={delay}>
            {mono ? <span className="mono small accent">{num}</span> : <span className="num grad">{num}</span>}
            <h3 className="h4">{title}</h3>
            <p className="small">{text}</p>
        </Reveal>
    );
}

/** Big number with label. Animates when `value` is a number; shows the [XX] placeholder until it is. */
export function StatCard({ value, suffix, label, delay }) {
    const [ref, inView] = useInView();
    const count = useCountUp(value, inView);
    return (
        <Reveal className="card stat" delay={delay} data-tilt="12">
            <span ref={ref} className="value grad">
                {typeof value === 'number' ? count : '[XX]'}
                {suffix}
            </span>
            <span>{label}</span>
        </Reveal>
    );
}

export function QuoteCard({ quote, name, role, initials, delay }) {
    return (
        <Reveal as="figure" className="card quote" delay={delay}>
            <span className="mark grad" aria-hidden="true">
                “
            </span>
            <blockquote>{quote}</blockquote>
            <figcaption className="person">
                <span className="avatar" aria-hidden="true">
                    {initials}
                </span>
                <span>
                    <b>{name}</b>
                    <span className="small muted">{role}</span>
                </span>
            </figcaption>
        </Reveal>
    );
}

export function CaseCard({ client, title, outcome, tags, color, url, delay }) {
    return (
        <Reveal as="article" className="card case hover" delay={delay} data-tilt="5">
            <div className="case-visual" style={{ '--c': color }} aria-hidden="true">
                <div className="mock glass-strong">
                    <span style={{ width: '100%' }} />
                    <span style={{ width: '84%' }} />
                    <span style={{ width: '68%' }} />
                    <span style={{ width: '52%' }} />
                </div>
            </div>
            <div className="case-body">
                <div className="tags">
                    {tags.map((tag) => (
                        <span key={tag} className="pill glass tag-pill">
                            {tag}
                        </span>
                    ))}
                </div>
                <h3>{url ? <SmartLink to={url}>{`${client} · ${title}`}</SmartLink> : `${client} · ${title}`}</h3>
                <p>{outcome}</p>
                {url && (
                    <Button to={url} variant="ghost">
                        Read case study
                    </Button>
                )}
            </div>
        </Reveal>
    );
}

/** Selectable "how to start" plan on service pages. The toggle button stretches over the whole card. */
export function PlanCard({ plan, selected, onSelect, delay }) {
    return (
        <Reveal as="article" className={cx('card plan', selected && 'selected')} delay={delay} data-tilt="6">
            {plan.featured && <span className="beam-border" aria-hidden="true" />}
            {plan.badge && <span className="badge">{plan.badge}</span>}
            <span className="duration">{plan.duration}</span>
            <h3>{plan.title}</h3>
            <p>{plan.text}</p>
            <ul>
                {plan.points.map((p) => (
                    <li key={p}>
                        <Icon name="check" />
                        {p}
                    </li>
                ))}
            </ul>
            <button type="button" className="pick" aria-pressed={selected} onClick={() => onSelect(plan.id)}>
                {selected ? 'Selected' : 'Choose this plan'}
                <span className="sr-only"> – {plan.title}</span>
                <Icon name={selected ? 'check' : 'arrow'} />
            </button>
        </Reveal>
    );
}

/** Blog post card; `featured` renders the large two-column version. */
export function PostCard({ post, featured, delay }) {
    const Title = featured ? 'h2' : 'h3';
    return (
        <Reveal as="article" className={cx('card hover', featured ? 'featured-post' : 'post')} delay={delay} data-tilt="4">
            <div className="cover" style={{ '--c': post.color }}>
                <span className="glass-strong">{featured ? `Featured · ${post.category}` : post.category}</span>
            </div>
            <div className="post-body">
                <p className="small muted">
                    {post.date} · {post.readTime}
                </p>
                <Title className={cx(!featured && 'h4')}>
                    {post.url ? <SmartLink to={post.url}>{post.title}</SmartLink> : post.title}
                </Title>
                {post.excerpt && <p>{post.excerpt}</p>}
                {post.url && (
                    <Button to={post.url} variant="ghost">
                        {featured ? 'Read article' : 'Read more'}
                    </Button>
                )}
            </div>
        </Reveal>
    );
}

export function RoleCard({ title, team, type, location, applyTo }) {
    const subject = encodeURIComponent(`Application: ${title}`);
    return (
        <Reveal as="article" className="card role">
            <div>
                <h3 className="h4">{title}</h3>
                <div className="meta">
                    {[team, type, location].map((m) => (
                        <span key={m} className="glass">
                            {m}
                        </span>
                    ))}
                </div>
            </div>
            <Button to={`mailto:${applyTo}?subject=${subject}`} variant="ghost" aria-label={`Apply for ${title}`}>
                Apply now
            </Button>
        </Reveal>
    );
}

export function ModelCard({ title, text, points, featured, badge, delay }) {
    return (
        <Reveal className={cx('card model', featured && 'featured')} delay={delay} data-tilt="6">
            {featured && <span className="beam-border" aria-hidden="true" />}
            {badge && <span className="badge">{badge}</span>}
            <h3>{title}</h3>
            <p>{text}</p>
            <ul>
                {points.map((p) => (
                    <li key={p}>
                        <Icon name="check" />
                        {p}
                    </li>
                ))}
            </ul>
            <Button to="/contact" variant={featured ? 'primary' : 'secondary'}>
                Get a proposal
            </Button>
        </Reveal>
    );
}
