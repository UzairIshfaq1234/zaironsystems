import pages from '@content/pages.json';
import { serviceDetail } from './services';

/** Same lookup the server does in App\Support\Seo, so client-side navigation keeps meta tags in sync. */
export function metaFor(pathname) {
    const path = pathname.length > 1 ? pathname.replace(/\/+$/, '') : pathname;

    if (pages[path]) return pages[path];

    const match = path.match(/^\/services\/([a-z0-9-]+)$/);
    const detail = match && serviceDetail(match[1]);
    if (detail) return detail.seo;

    return pages['404'];
}
