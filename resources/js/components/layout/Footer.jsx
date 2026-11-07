import site from '@content/site.json';
import NewsletterForm from '@/components/forms/NewsletterForm';
import Logo from '@/components/ui/Logo';
import SmartLink from '@/components/ui/SmartLink';
import { footerServices, serviceHref } from '@/lib/services';

// Links with no destination yet (e.g. Privacy Policy) are hidden until a URL is set in site.json.
const columns = [
    { title: 'Services', links: footerServices.map((s) => ({ label: s.footer, to: serviceHref(s) })) },
    ...site.footer,
    {
        title: 'Contact',
        links: [{ label: site.email, to: `mailto:${site.email}` }],
    },
];

const social = site.social.filter((s) => s.url);

export default function Footer() {
    return (
        <footer className="footer">
            <div className="container">
                <div className="footer-top">
                    <div className="footer-brand">
                        <Logo />
                        <p className="small">{site.summary}</p>
                        <NewsletterForm />
                    </div>
                    <div className="footer-cols">
                        {columns.map((col) => (
                            <div key={col.title}>
                                <h3>{col.title}</h3>
                                <ul>
                                    {col.links
                                        .filter((l) => l.to !== '')
                                        .map((l) => (
                                            <li key={l.label}>{l.to ? <SmartLink to={l.to}>{l.label}</SmartLink> : l.label}</li>
                                        ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                </div>
                <div className="footer-bottom">
                    <span>
                        © {new Date().getFullYear()} {site.name}. All rights reserved.
                    </span>
                    {social.length > 0 && (
                        <nav aria-label="Social">
                            <ul>
                                {social.map((s) => (
                                    <li key={s.label}>
                                        <SmartLink to={s.url}>{s.label}</SmartLink>
                                    </li>
                                ))}
                            </ul>
                        </nav>
                    )}
                </div>
            </div>
        </footer>
    );
}
