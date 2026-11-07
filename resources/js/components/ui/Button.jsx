import { cx } from '@/lib/utils';
import Icon from './Icon';
import SmartLink from './SmartLink';

/**
 * One button for every call to action. Pass `to` for a link, omit it for a <button>.
 * variant: primary | secondary | ghost
 */
export default function Button({ to, variant = 'primary', block, arrow = true, loading = false, className, children, ...rest }) {
    const classes = cx('btn', `btn-${variant}`, block && 'btn-block', className);
    const content = (
        <>
            {children}
            {loading ? <span className="spinner" aria-hidden="true" /> : arrow && <Icon name="arrow" />}
        </>
    );

    if (to) {
        return (
            <SmartLink to={to} className={classes} {...rest}>
                {content}
            </SmartLink>
        );
    }

    return (
        <button type="button" className={classes} aria-busy={loading || undefined} disabled={loading || rest.disabled} {...rest}>
            {content}
        </button>
    );
}
