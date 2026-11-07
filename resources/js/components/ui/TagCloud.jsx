import { cx } from '@/lib/utils';
import Reveal from './Reveal';

export default function TagCloud({ items, narrow }) {
    return (
        <Reveal as="ul" className={cx('stack-chips', narrow && 'narrow')}>
            {items.map((item) => (
                <li key={item} className="glass">
                    {item}
                </li>
            ))}
        </Reveal>
    );
}
