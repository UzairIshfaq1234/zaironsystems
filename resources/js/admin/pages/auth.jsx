import { useState } from 'react';
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import AuthLayout from '../layout/AuthLayout';
import { api } from '../lib/api';
import { Button, Checkbox, Input } from '../ui';
import Icon from '../ui/Icon';

/** Shared submit state for the auth forms. */
function useForm(initial) {
    const [values, setValues] = useState(initial);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const bind = (key) => ({
        name: key,
        value: values[key],
        error: errors[key],
        onChange: (e) => setValues((v) => ({ ...v, [key]: e.target.type === 'checkbox' ? e.target.checked : e.target.value })),
    });
    const run = async (fn) => {
        setBusy(true);
        setErrors({});
        try {
            await fn(values);
        } catch (err) {
            setErrors(Object.keys(err.errors ?? {}).length ? err.errors : { form: err.message || 'Something went wrong. Please try again.' });
        } finally {
            setBusy(false);
        }
    };
    return { values, errors, busy, bind, run };
}

function PasswordInput(props) {
    const [show, setShow] = useState(false);
    return (
        <div className="a-password">
            <Input type={show ? 'text' : 'password'} {...props} />
            <button type="button" className="a-password-toggle" onClick={() => setShow((s) => !s)} aria-label={show ? 'Hide password' : 'Show password'}>
                <Icon name={show ? 'eye-off' : 'eye'} />
            </button>
        </div>
    );
}

export function Login() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const { errors, busy, bind, run } = useForm({ email: '', password: '', remember: true });

    const submit = (e) => {
        e.preventDefault();
        run(async (v) => {
            await login(v);
            navigate(location.state?.from ?? '/', { replace: true });
        });
    };

    return (
        <AuthLayout title="Welcome back" subtitle="Sign in to manage ZAIRON Systems.">
            <form className="a-form" onSubmit={submit} noValidate>
                {errors.form && <p className="a-alert">{errors.form}</p>}
                <Input label="Email" type="email" autoComplete="username" autoFocus required {...bind('email')} />
                <PasswordInput label="Password" autoComplete="current-password" required {...bind('password')} />
                <div className="a-form-row">
                    <Checkbox label="Keep me signed in" checked={bind('remember').value} onChange={bind('remember').onChange} />
                    <Link to="/forgot-password" className="a-link">
                        Forgot password?
                    </Link>
                </div>
                <Button type="submit" loading={busy} className="a-btn-block" iconRight="arrow">
                    Sign in
                </Button>
            </form>
        </AuthLayout>
    );
}

export function ForgotPassword() {
    const [sent, setSent] = useState('');
    const { errors, busy, bind, run } = useForm({ email: '' });

    const submit = (e) => {
        e.preventDefault();
        run(async (v) => setSent((await api.post('/forgot-password', v)).message));
    };

    return (
        <AuthLayout title="Reset your password" subtitle="Enter your account email and we'll send you a reset link.">
            {sent ? (
                <div className="a-success-note" role="status">
                    <Icon name="mail" />
                    <p>{sent}</p>
                </div>
            ) : (
                <form className="a-form" onSubmit={submit} noValidate>
                    {errors.form && <p className="a-alert">{errors.form}</p>}
                    <Input label="Email" type="email" autoComplete="email" autoFocus required {...bind('email')} />
                    <Button type="submit" loading={busy} className="a-btn-block" icon="send">
                        Send reset link
                    </Button>
                </form>
            )}
            <Link to="/login" className="a-link a-auth-back">
                <Icon name="arrow-left" /> Back to sign in
            </Link>
        </AuthLayout>
    );
}

/** Used for both password resets and invitations (?invite=1). */
export function ResetPassword() {
    const [params] = useSearchParams();
    const navigate = useNavigate();
    const invite = params.get('invite') === '1';
    const [done, setDone] = useState('');
    const { errors, busy, bind, run } = useForm({ password: '', password_confirmation: '' });

    const submit = (e) => {
        e.preventDefault();
        run(async (v) => {
            const data = await api.post('/reset-password', { ...v, token: params.get('token') ?? '', email: params.get('email') ?? '', invite });
            setDone(data.message);
        });
    };

    return (
        <AuthLayout
            title={invite ? 'Set up your account' : 'Choose a new password'}
            subtitle={invite ? `Welcome! Choose a password for ${params.get('email') ?? 'your account'}.` : 'Use at least 10 characters with upper and lower case letters and a number.'}
        >
            {done ? (
                <>
                    <div className="a-success-note" role="status">
                        <Icon name="check" />
                        <p>{done}</p>
                    </div>
                    <Button className="a-btn-block" iconRight="arrow" onClick={() => navigate('/login', { replace: true })}>
                        Go to sign in
                    </Button>
                </>
            ) : (
                <form className="a-form" onSubmit={submit} noValidate>
                    {(errors.form || errors.email || errors.token) && <p className="a-alert">{errors.form || errors.email || errors.token}</p>}
                    <PasswordInput label="New password" autoComplete="new-password" autoFocus required hint="At least 10 characters, mixed case and a number." {...bind('password')} />
                    <PasswordInput label="Confirm password" autoComplete="new-password" required {...bind('password_confirmation')} />
                    <Button type="submit" loading={busy} className="a-btn-block" icon="lock">
                        {invite ? 'Activate account' : 'Save new password'}
                    </Button>
                </form>
            )}
        </AuthLayout>
    );
}
