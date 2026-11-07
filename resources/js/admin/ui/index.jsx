import { useEffect, useId, useRef } from 'react';
import { Link } from 'react-router-dom';
import { initials, statusLabel } from '../lib/format';
import Icon from './Icon';

export const cx = (...c) => c.filter(Boolean).join(' ');

/* ---------- Buttons ---------- */

/** variant: primary | secondary | ghost | danger. Pass `to` for a router link, `href` for a plain link. */
export function Button({ variant = 'primary', size, icon, iconRight, loading, to, href, className, children, ...rest }) {
    const classes = cx('a-btn', `a-btn-${variant}`, size === 'sm' && 'a-btn-sm', !children && 'a-btn-icon', className);
    const content = (
        <>
            {loading ? <span className="a-spinner" aria-hidden="true" /> : icon && <Icon name={icon} />}
            {children && <span>{children}</span>}
            {iconRight && <Icon name={iconRight} />}
        </>
    );
    if (to) return <Link to={to} className={classes} {...rest}>{content}</Link>;
    if (href) return <a href={href} className={classes} {...rest}>{content}</a>;
    return (
        <button type="button" className={classes} disabled={loading || rest.disabled} aria-busy={loading || undefined} {...rest}>
            {content}
        </button>
    );
}

/* ---------- Form fields ---------- */

export function Field({ label, error, hint, children, id, className }) {
    return (
        <div className={cx('a-field', error && 'has-error', className)}>
            {label && <label htmlFor={id}>{label}</label>}
            {children}
            {error ? (
                <span className="a-field-error" id={`${id}-error`} role="alert">
                    {error}
                </span>
            ) : (
                hint && <span className="a-field-hint">{hint}</span>
            )}
        </div>
    );
}

/** Text input wired to a Field. Extra props go to the <input>. */
export function Input({ label, error, hint, className, as: Control = 'input', ...rest }) {
    const autoId = useId();
    const id = rest.id ?? autoId;
    return (
        <Field label={label} error={error} hint={hint} id={id} className={className}>
            <Control id={id} className="a-input" aria-invalid={error ? true : undefined} aria-describedby={error ? `${id}-error` : undefined} {...rest} />
        </Field>
    );
}

export function Select({ label, error, options, className, ...rest }) {
    const autoId = useId();
    const id = rest.id ?? autoId;
    return (
        <Field label={label} error={error} id={id} className={className}>
            <span className="a-select">
                <select id={id} className="a-input" {...rest}>
                    {options.map((o) => (
                        <option key={o.value} value={o.value}>
                            {o.label}
                        </option>
                    ))}
                </select>
                <Icon name="chevron" />
            </span>
        </Field>
    );
}

export function Checkbox({ label, description, className, ...rest }) {
    return (
        <label className={cx('a-check', className)}>
            <input type="checkbox" {...rest} />
            <span className="a-check-box" aria-hidden="true">
                <Icon name="check" />
            </span>
            <span className="a-check-text">
                <span>{label}</span>
                {description && <small>{description}</small>}
            </span>
        </label>
    );
}

export function Switch({ label, ...rest }) {
    return (
        <label className="a-switch">
            <input type="checkbox" role="switch" {...rest} />
            <span className="a-switch-track" aria-hidden="true" />
            <span>{label}</span>
        </label>
    );
}

export function SearchInput({ value, onChange, placeholder = 'Search…', label = 'Search' }) {
    return (
        <label className="a-search">
            <Icon name="search" />
            <span className="sr-only">{label}</span>
            <input type="search" value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} maxLength={100} />
        </label>
    );
}

/* ---------- Display ---------- */

export function Badge({ tone = 'neutral', dot, children }) {
    return (
        <span className={cx('a-badge', `tone-${tone}`)}>
            {dot && <span className="a-badge-dot" aria-hidden="true" />}
            {children}
        </span>
    );
}

const STATUS_TONE = { new: 'accent', contacted: 'amber', in_progress: 'blue', won: 'green', lost: 'neutral', reviewing: 'blue', shortlisted: 'amber', hired: 'green', rejected: 'neutral', open: 'green', closed: 'neutral' };
export const StatusBadge = ({ status }) => (
    <Badge tone={STATUS_TONE[status] ?? 'neutral'} dot>
        {statusLabel(status)}
    </Badge>
);

export function Avatar({ name, size = 36 }) {
    return (
        <span className="a-avatar" style={{ width: size, height: size, fontSize: size * 0.38 }} aria-hidden="true">
            {initials(name)}
        </span>
    );
}

export function Card({ title, subtitle, actions, className, children, padded = true }) {
    return (
        <section className={cx('a-card', padded && 'padded', className)}>
            {(title || actions) && (
                <header className="a-card-head">
                    <div>
                        {title && <h2>{title}</h2>}
                        {subtitle && <p>{subtitle}</p>}
                    </div>
                    {actions && <div className="a-card-actions">{actions}</div>}
                </header>
            )}
            {children}
        </section>
    );
}

export function PageHeader({ title, subtitle, back, actions }) {
    return (
        <header className="a-page-head">
            <div>
                {back && (
                    <Link to={back.to} className="a-back">
                        <Icon name="arrow-left" />
                        {back.label}
                    </Link>
                )}
                <h1>{title}</h1>
                {subtitle && <p>{subtitle}</p>}
            </div>
            {actions && <div className="a-page-actions">{actions}</div>}
        </header>
    );
}

export function EmptyState({ icon = 'inbox', title, children }) {
    return (
        <div className="a-empty">
            <span className="a-empty-icon">
                <Icon name={icon} />
            </span>
            <h3>{title}</h3>
            {children && <p>{children}</p>}
        </div>
    );
}

export function Skeleton({ height = 16, width = '100%', className }) {
    return <span className={cx('a-skeleton', className)} style={{ height, width }} aria-hidden="true" />;
}

export function ErrorNote({ error, onRetry }) {
    if (!error) return null;
    return (
        <div className="a-alert" role="alert">
            <Icon name="activity" />
            <span>{error.status === 403 ? "You don't have permission to see this." : error.message || 'Something went wrong.'}</span>
            {onRetry && error.status !== 403 && (
                <Button variant="ghost" size="sm" icon="refresh" onClick={onRetry}>
                    Retry
                </Button>
            )}
        </div>
    );
}

/* ---------- Table ---------- */

/**
 * columns: [{ key, label, render(row), className }]. Rows are clickable when `onRowClick` is set
 * (and keyboard-reachable). Keeps old rows dimmed while `loading`.
 */
export function Table({ columns, rows, loading, onRowClick, empty, rowKey = 'id' }) {
    if (!rows && loading) {
        return (
            <div className="a-table-skeleton">
                {Array.from({ length: 6 }, (_, i) => (
                    <Skeleton key={i} height={44} />
                ))}
            </div>
        );
    }
    if (rows && rows.length === 0) return empty;

    return (
        <div className={cx('a-table-wrap', loading && 'is-loading')}>
            <table className="a-table">
                <thead>
                    <tr>
                        {columns.map((c) => (
                            <th key={c.key} className={c.className} scope="col">
                                {c.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows?.map((row) => (
                        <tr
                            key={row[rowKey]}
                            className={onRowClick ? 'clickable' : undefined}
                            onClick={onRowClick ? () => onRowClick(row) : undefined}
                            onKeyDown={onRowClick ? (e) => e.key === 'Enter' && onRowClick(row) : undefined}
                            tabIndex={onRowClick ? 0 : undefined}
                        >
                            {columns.map((c) => (
                                <td key={c.key} className={c.className} data-label={c.label}>
                                    {c.render ? c.render(row) : row[c.key]}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export function Pagination({ meta, onPage }) {
    if (!meta || meta.last_page <= 1) return meta ? <p className="a-count">{meta.total} total</p> : null;
    const { current_page: page, last_page: last, total } = meta;
    return (
        <nav className="a-pagination" aria-label="Pagination">
            <p className="a-count">{total} total</p>
            <div>
                <Button variant="secondary" size="sm" icon="chevron-left" disabled={page <= 1} onClick={() => onPage(page - 1)} aria-label="Previous page" />
                <span>
                    Page {page} of {last}
                </span>
                <Button variant="secondary" size="sm" icon="chevron-right" disabled={page >= last} onClick={() => onPage(page + 1)} aria-label="Next page" />
            </div>
        </nav>
    );
}

/* ---------- Modal (native <dialog>: focus trap, Escape and top layer for free) ---------- */

export function Modal({ open, onClose, title, description, children, footer, size }) {
    const ref = useRef(null);
    const titleId = useId();

    useEffect(() => {
        const dialog = ref.current;
        if (!dialog) return;
        if (open && !dialog.open) dialog.showModal();
        if (!open && dialog.open) dialog.close();
    }, [open]);

    return (
        <dialog
            ref={ref}
            className={cx('a-modal', size && `a-modal-${size}`)}
            onClose={onClose}
            onCancel={(e) => {
                e.preventDefault();
                onClose();
            }}
            onClick={(e) => e.target === ref.current && onClose()}
            aria-labelledby={titleId}
        >
            {open && (
                <div className="a-modal-body">
                    <header>
                        <div>
                            <h2 id={titleId}>{title}</h2>
                            {description && <p>{description}</p>}
                        </div>
                        <Button variant="ghost" icon="close" onClick={onClose} aria-label="Close" />
                    </header>
                    <div className="a-modal-content">{children}</div>
                    {footer && <footer>{footer}</footer>}
                </div>
            )}
        </dialog>
    );
}
