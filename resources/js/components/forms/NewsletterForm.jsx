import { useId, useState } from 'react';
import Button from '@/components/ui/Button';
import { cx } from '@/lib/utils';
import { Honeypot } from './Field';
import useSubmit, { isEmail } from './useSubmit';

export default function NewsletterForm({ placeholder = 'Your work email', label = 'Work email' }) {
    const id = useId();
    const [email, setEmail] = useState('');
    const [website, setWebsite] = useState('');
    const { submit, sending, sent, message, errors, alert, setErrors } = useSubmit('/api/newsletter');

    const onSubmit = async (e) => {
        e.preventDefault();
        if (!isEmail(email)) return setErrors({ email: 'Please enter a valid email.' });
        if (await submit({ email: email.trim(), website })) setEmail('');
    };

    const error = errors.email || alert;

    return (
        <div className="inline-wrap">
            {sent ? (
                <p className="inline-msg ok" role="status">
                    {message || 'Thanks! You are subscribed.'}
                </p>
            ) : (
                <form className={cx('inline-form', error && 'error')} onSubmit={onSubmit} noValidate>
                    <label className="sr-only" htmlFor={id}>
                        {label}
                    </label>
                    <input
                        id={id}
                        type="email"
                        name="email"
                        autoComplete="email"
                        placeholder={placeholder}
                        maxLength={190}
                        required
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        aria-invalid={error ? true : undefined}
                        aria-describedby={error ? `${id}-msg` : undefined}
                    />
                    <Honeypot value={website} onChange={setWebsite} />
                    <Button type="submit" loading={sending}>
                        Subscribe
                    </Button>
                </form>
            )}
            {!sent && error && (
                <p className="inline-msg bad" id={`${id}-msg`} role="alert">
                    {error}
                </p>
            )}
        </div>
    );
}
