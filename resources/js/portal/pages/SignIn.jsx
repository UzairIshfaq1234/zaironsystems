import { useState } from 'react';
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../auth';
import { Button, Input, cx } from '../../admin/ui';
import Icon from '../../admin/ui/Icon';

/** Shared frame for the portal's signed-out pages. */
function Frame({ title, children, subtitle }) {
    return (
        <div className="p-auth">
            <div className="p-auth-card">
                <div className="p-auth-brand">
                    <span className="a-brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 100 100" focusable="false">
                            <polygon points="12,12 88,12 88,30 71.2,44 44.5,44 62,30 12,30" fill="currentColor" />
                            <polygon points="29.5,56 56.8,56 40,70 88,70 88,88 12,88 12,70" fill="currentColor" />
                            <circle cx="50.5" cy="50" r="3.8" fill="currentColor" />
                        </svg>
                    </span>
                    <span>ZAIRON Systems Team</span>
                </div>
                <h1>{title}</h1>
                {subtitle && <p className="a-muted">{subtitle}</p>}
                {children}
            </div>
        </div>
    );
}

export function Login() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const [form, setForm] = useState({ email: '', password: '', remember: false });
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setErrors({});
        try {
            await login(form);
            navigate(location.state?.from ?? '/', { replace: true });
        } catch (err) {
            setErrors(Object.keys(err.errors).length ? err.errors : { form: err.message });
        } finally {
            setBusy(false);
        }
    };

    return (
        <Frame title="Welcome back" subtitle="Sign in to see your shift, leave, salary slips and reviews.">
            <form className="a-form" onSubmit={submit} noValidate>
                {errors.form && <p className="a-alert">{errors.form}</p>}
                <Input label="Work email" type="email" autoComplete="username" required autoFocus value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} error={errors.email} />
                <Input label="Password" type="password" autoComplete="current-password" required value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} error={errors.password} />
                <label className="a-check">
                    <input type="checkbox" checked={form.remember} onChange={(e) => setForm({ ...form, remember: e.target.checked })} />
                    <span className="a-check-box" aria-hidden="true">
                        <Icon name="check" />
                    </span>
                    <span className="a-check-text">Keep me signed in on this device</span>
                </label>
                <Button type="submit" loading={busy} className="a-btn-block">
                    Sign in
                </Button>
            </form>
            <Link to="/forgot-password" className="a-link p-auth-link">
                Forgot your password?
            </Link>
            <p className="a-muted a-small">No password yet? Ask HR to send your portal invitation.</p>
        </Frame>
    );
}

export function ForgotPassword() {
    const [email, setEmail] = useState('');
    const [sent, setSent] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setError(null);
        try {
            await api.post('/forgot-password', { email });
            setSent(true);
        } catch (err) {
            setError(err.errors?.email ?? err.message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Frame title="Reset your password" subtitle="We'll email you a link to choose a new one.">
            {sent ? (
                <p className="a-success-note">If that email has portal access, a link is on its way. Check your inbox and spam folder.</p>
            ) : (
                <form className="a-form" onSubmit={submit} noValidate>
                    {error && <p className="a-alert">{error}</p>}
                    <Input label="Work email" type="email" required autoFocus value={email} onChange={(e) => setEmail(e.target.value)} />
                    <Button type="submit" loading={busy} className="a-btn-block">
                        Email me a link
                    </Button>
                </form>
            )}
            <Link to="/login" className="a-link p-auth-link">
                Back to sign in
            </Link>
        </Frame>
    );
}

/** Opened from the email link: sets the first password (invitation) or a new one (reset). */
export function ResetPassword() {
    const [params] = useSearchParams();
    const navigate = useNavigate();
    const [form, setForm] = useState({ password: '', password_confirmation: '' });
    const [errors, setErrors] = useState({});
    const [done, setDone] = useState(false);
    const [busy, setBusy] = useState(false);
    const token = params.get('token') ?? '';
    const email = params.get('email') ?? '';

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setErrors({});
        try {
            await api.post('/reset-password', { ...form, token, email });
            setDone(true);
        } catch (err) {
            setErrors(Object.keys(err.errors).length ? err.errors : { form: err.message });
        } finally {
            setBusy(false);
        }
    };

    if (done) {
        return (
            <Frame title="Your password is set" subtitle="You can sign in now.">
                <Button className="a-btn-block" onClick={() => navigate('/login', { replace: true })}>
                    Go to sign in
                </Button>
            </Frame>
        );
    }

    return (
        <Frame title="Choose a password" subtitle="At least 10 characters, with upper and lower case letters and numbers.">
            {!token || !email ? (
                <p className="a-alert">This link is incomplete. Use the most recent email, or ask for a new link.</p>
            ) : (
                <form className="a-form" onSubmit={submit} noValidate>
                    {errors.form && <p className="a-alert">{errors.form}</p>}
                    {errors.email && <p className="a-alert">{errors.email}</p>}
                    <Input label="New password" type="password" autoComplete="new-password" required autoFocus value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} error={errors.password} />
                    <Input label="Repeat it" type="password" autoComplete="new-password" required value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} />
                    <Button type="submit" loading={busy} className={cx('a-btn-block')}>
                        Save password
                    </Button>
                </form>
            )}
        </Frame>
    );
}
