import { Link } from 'react-router-dom';
import { isExternal } from '@/lib/utils';

/** Router <Link> for internal paths, a plain <a> for mail/phone/external (new tab, no opener). */
export default function SmartLink({ to, children, ref, ...rest }) {
    if (!isExternal(to)) {
        return (
            <Link ref={ref} to={to} {...rest}>
                {children}
            </Link>
        );
    }

    const newTab = /^https?:/i.test(to);
    return (
        <a ref={ref} href={to} {...(newTab && { target: '_blank', rel: 'noopener noreferrer' })} {...rest}>
            {children}
        </a>
    );
}
