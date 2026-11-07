import { useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import site from '@content/site.json';
import Button from '@/components/ui/Button';
import Icon from '@/components/ui/Icon';
import SmartLink from '@/components/ui/SmartLink';
import ToggleGroup from '@/components/ui/ToggleGroup';
import { budgets, findService, formOptions } from '@/lib/services';
import { cx } from '@/lib/utils';
import Field, { Honeypot } from './Field';
import useSubmit, { isEmail } from './useSubmit';

// Must match App\Http\Requests\ContactRequest.
const MAX_FILE_MB = 10;
const FILE_TYPES = ['pdf', 'doc', 'docx'];
const budgetOptions = budgets.map((b) => ({ value: b, label: b }));
const empty = { name: '', email: '', company: '', phone: '', details: '', consent: false, website: '' };

function validate(values, file) {
    const errors = {};
    if (!values.name.trim()) errors.name = 'Please enter your name.';
    if (!isEmail(values.email)) errors.email = 'Please enter a valid email.';
    if (!values.company.trim()) errors.company = 'Please enter your company.';
    if (values.details.trim().length < 10) errors.details = 'Please add a few details (at least 10 characters).';
    if (!values.consent) errors.consent = 'Please accept the privacy policy.';
    if (file) {
        const ext = file.name.split('.').pop().toLowerCase();
        if (!FILE_TYPES.includes(ext)) errors.brief = 'Please attach a PDF, DOC or DOCX file.';
        else if (file.size > MAX_FILE_MB * 1024 * 1024) errors.brief = `Please attach a file under ${MAX_FILE_MB} MB.`;
    }
    return errors;
}

/**
 * Project enquiry form. On a service page pass `service` (+ `plans`, `plan`, `onPlanChange`) and it
 * becomes that service's "start a project" form: the service is fixed and the visitor picks a plan instead.
 */
export default function ContactForm({ service, plans, plan = '', onPlanChange }) {
    const [params] = useSearchParams();
    const preselected = service?.option ?? findService(params.get('service'))?.option;

    const [values, setValues] = useState(empty);
    const [services, setServices] = useState(preselected ? [preselected] : []);
    const [budget, setBudget] = useState('');
    const [file, setFile] = useState(null);
    const fileInput = useRef(null);
    const formRef = useRef(null);
    const successRef = useRef(null);
    const { submit, sending, sent, errors, alert, setErrors } = useSubmit('/api/contact');

    useEffect(() => {
        if (sent) successRef.current?.focus();
    }, [sent]);

    const set = (key) => (e) => setValues((v) => ({ ...v, [key]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }));

    const clearFile = () => {
        setFile(null);
        if (fileInput.current) fileInput.current.value = '';
    };

    const onSubmit = async (e) => {
        e.preventDefault();
        const found = validate(values, file);
        if (Object.keys(found).length) {
            setErrors(found);
            formRef.current?.querySelector(`[name="${Object.keys(found)[0]}"]`)?.focus();
            return;
        }

        const body = new FormData();
        Object.entries(values).forEach(([k, v]) => body.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : v.trim()));
        services.forEach((s) => body.append('services[]', s));
        if (service) body.append('service', service.slug);
        if (plan) body.append('plan', plan);
        if (budget) body.append('budget', budget);
        if (file) body.append('brief', file);
        await submit(body);
    };

    if (sent) {
        return (
            <div className="form-success" role="status" tabIndex={-1} ref={successRef}>
                <span className="map-pin">
                    <Icon name="check" />
                </span>
                <h2 className="h3">Message sent.</h2>
                <p>
                    {service
                        ? `Thanks! A senior ${service.title} engineer will reply within one business day with next steps.`
                        : 'Thanks for reaching out. A senior engineer will reply within one business day.'}
                </p>
            </div>
        );
    }

    const field = (id, props) => <Field id={id} value={values[id]} onChange={set(id)} error={errors[id]} {...props} />;

    return (
        <form ref={formRef} onSubmit={onSubmit} noValidate aria-label={service ? `Start a ${service.title} project` : 'Project enquiry'}>
            <h2 className="h3">{service ? `Start your ${service.title} project` : 'Start a project'}</h2>

            {alert && (
                <p className="form-alert" role="alert">
                    {alert}
                </p>
            )}

            <div className="two">
                {field('name', { label: 'Full name', required: true, autoComplete: 'name', placeholder: 'Your name', maxLength: 120 })}
                {field('email', { label: 'Work email', required: true, type: 'email', autoComplete: 'email', placeholder: 'you@company.com', maxLength: 190 })}
            </div>
            <div className="two">
                {field('company', { label: 'Company', required: true, autoComplete: 'organization', placeholder: 'Company name', maxLength: 160 })}
                {field('phone', { label: 'Phone', type: 'tel', autoComplete: 'tel', placeholder: '+92 ...', maxLength: 40 })}
            </div>

            {plans ? (
                <div className={cx('field', errors.plan && 'error')}>
                    <span className="label" id="plan-label">
                        How would you like to start?
                    </span>
                    <ToggleGroup
                        options={plans.map((p) => ({ value: p.id, label: p.title }))}
                        value={plan}
                        onChange={(v) => onPlanChange?.(plan === v ? '' : v)}
                        labelledBy="plan-label"
                        checkmarks
                    />
                    {errors.plan && <span className="err">{errors.plan}</span>}
                </div>
            ) : (
                <div className={cx('field', errors.services && 'error')}>
                    <span className="label" id="svc-label">
                        What do you need?
                    </span>
                    <ToggleGroup options={formOptions} value={services} onChange={setServices} labelledBy="svc-label" checkmarks />
                    {errors.services && <span className="err">{errors.services}</span>}
                </div>
            )}

            <div className={cx('field', errors.budget && 'error')}>
                <span className="label" id="bud-label">
                    Estimated budget
                </span>
                <ToggleGroup options={budgetOptions} value={budget} onChange={(v) => setBudget((b) => (b === v ? '' : v))} labelledBy="bud-label" checkmarks />
                {errors.budget && <span className="err">{errors.budget}</span>}
            </div>

            {field('details', {
                as: 'textarea',
                label: 'Project details',
                required: true,
                placeholder: 'Tell us about your goals, timeline and anything we should know...',
                maxLength: 5000,
            })}

            <div className={cx('field', errors.brief && 'error')}>
                <label className="upload">
                    <Icon name="plus" />
                    <span className="file-name">{file ? file.name : `Attach a brief or file (PDF, DOC, max ${MAX_FILE_MB} MB)`}</span>
                    <input
                        ref={fileInput}
                        type="file"
                        name="brief"
                        accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                        aria-invalid={errors.brief ? true : undefined}
                    />
                    {file && (
                        <button type="button" className="clear" onClick={(e) => (e.preventDefault(), clearFile())} aria-label="Remove attached file">
                            <Icon name="close" />
                        </button>
                    )}
                </label>
                {errors.brief && <span className="err">{errors.brief}</span>}
            </div>

            <div className={cx('field', errors.consent && 'error')}>
                <label className="consent">
                    <input type="checkbox" name="consent" checked={values.consent} onChange={set('consent')} aria-invalid={errors.consent ? true : undefined} />
                    <span>
                        I agree to the {site.privacyUrl ? <SmartLink to={site.privacyUrl}>privacy policy</SmartLink> : 'privacy policy'}. We never share your
                        details.
                    </span>
                </label>
                {errors.consent && <span className="err">{errors.consent}</span>}
            </div>

            <Honeypot value={values.website} onChange={(website) => setValues((v) => ({ ...v, website }))} />

            <Button type="submit" block loading={sending}>
                {sending ? 'Sending…' : 'Send message'}
            </Button>
        </form>
    );
}
