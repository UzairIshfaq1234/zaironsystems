import { Link } from 'react-router-dom';
import Reveal from './Reveal';

/** items: [{ label, to }]; the last item is the current page. */
export default function Breadcrumb({ items }) {
    return (
        <Reveal as="nav" className="crumb" aria-label="Breadcrumb">
            <ol>
                {items.map(({ label, to }, i) => (
                    <li key={label}>
                        {i < items.length - 1 ? <Link to={to}>{label}</Link> : <span aria-current="page">{label}</span>}
                    </li>
                ))}
            </ol>
        </Reveal>
    );
}
