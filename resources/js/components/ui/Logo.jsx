import { Link } from 'react-router-dom';

/** The ZAIRON Systems "Z" mark: two halves meeting at a single node. */
export function LogoMark({ className }) {
    return (
        <svg className={className} viewBox="0 0 100 100" aria-hidden="true" focusable="false">
            <polygon points="12,12 88,12 88,30 71.2,44 44.5,44 62,30 12,30" fill="#fff" />
            <polygon points="29.5,56 56.8,56 40,70 88,70 88,88 12,88 12,70" fill="#fff" />
            <circle cx="50.5" cy="50" r="3.8" fill="#fff" />
        </svg>
    );
}

export default function Logo() {
    return (
        <Link className="logo" to="/" aria-label="ZAIRON Systems home">
            <LogoMark />
            <span>ZAIRON Systems</span>
        </Link>
    );
}
