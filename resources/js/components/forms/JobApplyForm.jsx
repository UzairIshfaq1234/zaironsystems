import { useRef, useState } from 'react';
import site from '@content/site.json';
import Button from '@/components/ui/Button';
import Icon from '@/components/ui/Icon';
import SmartLink from '@/components/ui/SmartLink';
import { cx } from '@/lib/utils';
import Field, { Honeypot } from './Field';
import useSubmit, { isEmail } from './useSubmit';

// Must match App\Http\Requests\StoreJobApplicationRequest.
const MAX_CV_MB = 10;
const CV_TYPES = ['pdf', 'doc', 'docx'];
const empty = { name: '', email: '', phone: '', links: '', message: '', consent: false, website: '' };

function validate(values, file) {
    const errors = {};
    if (!values.name.trim()) errors.name = 'Please enter your name.';
    if (!isEmail(values.email)) errors.email = 'Please enter a valid email.';
    if (!file) errors.cv = 'Please attach your CV.';
    else if (!CV_TYPES.includes(file.name.split('.').pop().toLowerCase())) errors.cv = 'Please attach a PDF, DOC or DOCX file.';
    else if (file.size > MAX_CV_MB * 1024 * 1024) errors.cv = `Please attach a file under ${MAX_CV_MB} MB.`;
    if (!values.consent) errors.consent = 'Please accept the privacy policy.';
    return errors;
}

/** Application form for one open role. Posts to /api/careers/apply, which stores it for the admin portal. */
export default function JobApplyForm({ opening }) {
    const [values, setValues] = useState(empty);
    const [file, setFile] = useState(null);
    const fileInput = useRef(null);
    const { submit, sending, sent, errors, alert, setErrors } = useSubmit('/api/careers/apply');

    const set = (key) => (e) => setValues((v) => ({ ...v, [key]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }));

    const onSubmit = async (e) => {
        e.preventDefault();
        const found = validate(values, file);
        if (Object.keys(found).length) {
            setErrors(found);
            return;
        }

        const body = new FormData();
        body.append('job_opening_id', String(opening.id));
        Object.entries(values).forEach(([k, v]) => body.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : v.trim()));
        body.append('cv', file);
        await submit(body);
    };

    if (sent) {
        return (
            <div className="form-success" role="status">
                <span className="map-pin">
                    <Icon name="check" />
                </span>
                <h3 className="h4">Application received.</h3>
                <p>Thanks for applying for {opening.title}. Our team reviews every application and will be in touch.</p>
            </div>
        );
    }

    const field = (id, props) => <Field id={id} value={values[id]} onChange={set(id)} error={errors[id]} {...props} />;

    return (
        <form onSubmit={onSubmit} noValidate aria-label={`Apply for ${opening.title}`}>
            {alert && (
                <p className="form-alert" role="alert">
                    {alert}
                </p>
            )}
            {field('name', { label: 'Full name', required: true, autoComplete: 'name', maxLength: 120 })}
            {field('email', { label: 'Email', type: 'email', required: true, autoComplete: 'email', maxLength: 190 })}
            {field('phone', { label: 'Phone', type: 'tel', autoComplete: 'tel', placeholder: '+92 ...', maxLength: 40 })}
            {field('links', { label: 'Portfolio, GitHub or LinkedIn', placeholder: 'https://', maxLength: 500 })}
            {field('message', { as: 'textarea', label: 'Why you, in a few lines', maxLength: 5000 })}

            <div className={cx('field', errors.cv && 'error')}>
                <label className="upload">
                    <Icon name="plus" />
                    <span className="file-name">{file ? file.name : `Attach your CV (PDF, DOC or DOCX, max ${MAX_CV_MB} MB)`}</span>
                    <input
                        ref={fileInput}
                        type="file"
                        name="cv"
                        accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                        aria-invalid={errors.cv ? true : undefined}
                    />
                </label>
                {errors.cv && <span className="err">{errors.cv}</span>}
            </div>

            <div className={cx('field', errors.consent && 'error')}>
                <label className="consent">
                    <input type="checkbox" name="consent" checked={values.consent} onChange={set('consent')} aria-invalid={errors.consent ? true : undefined} />
                    <span>
                        I agree to the {site.privacyUrl ? <SmartLink to={site.privacyUrl}>privacy policy</SmartLink> : 'privacy policy'}. We only use your details to
                        review this application.
                    </span>
                </label>
                {errors.consent && <span className="err">{errors.consent}</span>}
            </div>

            <Honeypot value={values.website} onChange={(website) => setValues((v) => ({ ...v, website }))} />

            <Button type="submit" block loading={sending}>
                {sending ? 'Sending…' : 'Submit application'}
            </Button>
        </form>
    );
}
