export default function Pagination({ page, pageCount, onChange, label = 'Pagination' }) {
    if (pageCount <= 1) return null;

    return (
        <nav className="pagination" aria-label={label}>
            {Array.from({ length: pageCount }, (_, i) => i + 1).map((n) => (
                <button
                    key={n}
                    type="button"
                    className="glass"
                    aria-current={n === page ? 'page' : undefined}
                    aria-label={`Page ${n}`}
                    onClick={() => onChange(n)}
                >
                    {n}
                </button>
            ))}
        </nav>
    );
}
