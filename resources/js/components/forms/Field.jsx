import { cx } from '@/lib/utils';

/**
 * Labelled input/textarea with error wiring (aria-invalid + aria-describedby).
 * Pass `as="textarea"` for multi-line input; everything else goes to the control.
 */
export default function Field({ id, label, required, error, as: Control = 'input', className, ...rest }) {
    const errorId = `${id}-error`;
    return (
        <div className={cx('field', error && 'error', className)}>
            <label htmlFor={id}>
                {label} {required && <span className="req" aria-hidden="true">*</span>}
            </label>
            <Control
                id={id}
                name={id}
                required={required}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? errorId : undefined}
                {...rest}
            />
            {error && (
                <span className="err" id={errorId}>
                    {error}
                </span>
            )}
        </div>
    );
}

/** Hidden field real people never see or fill; bots usually do. The server silently drops those. */
export function Honeypot({ value, onChange }) {
    return (
        <div className="hp" aria-hidden="true">
            <label>
                Leave this field empty
                <input type="text" name="website" tabIndex={-1} autoComplete="off" value={value} onChange={(e) => onChange(e.target.value)} />
            </label>
        </div>
    );
}
