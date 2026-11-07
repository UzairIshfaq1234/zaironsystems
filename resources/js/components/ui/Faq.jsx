import Icon from './Icon';

/** Accessible accordion built on native <details>; the first answer starts open. */
export default function Faq({ items }) {
    return (
        <div className="faq">
            {items.map(({ q, a }, i) => (
                <details key={q} className="glass" open={i === 0}>
                    <summary>
                        {q}
                        <Icon name="plus" />
                    </summary>
                    <p className="answer">{a}</p>
                </details>
            ))}
        </div>
    );
}
