/** Split-screen frame for sign-in, forgot and reset password. */
export default function AuthLayout({ title, subtitle, children }) {
    return (
        <div className="a-auth">
            <aside className="a-auth-brand" aria-hidden="true">
                <div className="a-auth-brand-inner">
                    <svg viewBox="0 0 100 100">
                        <polygon points="12,12 88,12 88,30 71.2,44 44.5,44 62,30 12,30" fill="#fff" />
                        <polygon points="29.5,56 56.8,56 40,70 88,70 88,88 12,88 12,70" fill="#fff" />
                        <circle cx="50.5" cy="50" r="3.8" fill="#fff" />
                    </svg>
                    <p className="a-auth-word">ZAIRON Systems</p>
                    <h2>Ideas, To Reality.</h2>
                    <p>Manage enquiries, your audience and your team from one secure place.</p>
                </div>
                <span className="a-auth-orb one" />
                <span className="a-auth-orb two" />
            </aside>
            <main className="a-auth-panel">
                <div className="a-auth-card">
                    <p className="a-auth-kicker">ZAIRON Systems Admin</p>
                    <h1>{title}</h1>
                    {subtitle && <p className="a-muted">{subtitle}</p>}
                    {children}
                </div>
            </main>
        </div>
    );
}
