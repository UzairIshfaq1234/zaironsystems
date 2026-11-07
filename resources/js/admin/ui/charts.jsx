import { useId, useMemo, useRef, useState } from 'react';
import { compact, shortDate } from '../lib/format';

const W = 640;
const H = 220;
const PAD = { top: 16, right: 12, bottom: 28, left: 32 };
const PADG = { top: 12, right: 8, bottom: 26, left: 48 };

/** Round the axis max up to a clean number and return evenly spaced ticks. */
function ticksFor(max) {
    if (max <= 4) return [0, 1, 2, 3, 4];
    const step = Math.pow(10, Math.floor(Math.log10(max))) * ([1, 2, 5].find((m) => (max / (Math.pow(10, Math.floor(Math.log10(max))) * m)) <= 5) ?? 10);
    const top = Math.ceil(max / step) * step;
    return Array.from({ length: top / step + 1 }, (_, i) => i * step);
}

/**
 * Single-series area chart for a daily count (series: [{ date, count }]).
 * One hue, 2px line over a ~10% wash, hairline grid, crosshair + tooltip that snaps to the nearest day.
 * Also keyboard-explorable (arrow keys) and backed by a visually hidden table.
 */
export function AreaChart({ series, label }) {
    const [active, setActive] = useState(null);
    const svgRef = useRef(null);
    const gradId = useId();

    const { points, ticks, x, y } = useMemo(() => {
        const max = Math.max(...series.map((d) => d.count), 0);
        const ticks = ticksFor(max);
        const top = ticks[ticks.length - 1] || 1;
        const innerW = W - PAD.left - PAD.right;
        const innerH = H - PAD.top - PAD.bottom;
        const x = (i) => PAD.left + (series.length > 1 ? (i / (series.length - 1)) * innerW : innerW / 2);
        const y = (v) => PAD.top + innerH - (v / top) * innerH;
        return { points: series.map((d, i) => [x(i), y(d.count)]), ticks, x, y };
    }, [series]);

    if (!series.length) return null;

    const line = points.map(([px, py], i) => `${i ? 'L' : 'M'}${px.toFixed(1)},${py.toFixed(1)}`).join(' ');
    const area = `${line} L${points.at(-1)[0]},${y(0)} L${points[0][0]},${y(0)} Z`;
    const total = series.reduce((s, d) => s + d.count, 0);

    const pick = (clientX) => {
        const rect = svgRef.current.getBoundingClientRect();
        const svgX = ((clientX - rect.left) / rect.width) * W;
        let best = 0;
        points.forEach(([px], i) => {
            if (Math.abs(px - svgX) < Math.abs(points[best][0] - svgX)) best = i;
        });
        setActive(best);
    };

    const onKey = (e) => {
        if (e.key === 'ArrowRight') setActive((a) => Math.min((a ?? -1) + 1, series.length - 1));
        else if (e.key === 'ArrowLeft') setActive((a) => Math.max((a ?? series.length) - 1, 0));
        else return;
        e.preventDefault();
    };

    const activeDay = active != null ? series[active] : null;
    const labelEvery = Math.ceil(series.length / 6);

    return (
        <figure className="a-chart">
            <div className="a-chart-plot">
                <svg
                    ref={svgRef}
                    viewBox={`0 0 ${W} ${H}`}
                    role="img"
                    aria-label={`${label}: ${total} in the last ${series.length} days. Use arrow keys to read daily values.`}
                    tabIndex={0}
                    onPointerMove={(e) => pick(e.clientX)}
                    onPointerLeave={() => setActive(null)}
                    onKeyDown={onKey}
                    onBlur={() => setActive(null)}
                >
                    <defs>
                        <linearGradient id={gradId} x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stopColor="var(--a-series)" stopOpacity="0.22" />
                            <stop offset="100%" stopColor="var(--a-series)" stopOpacity="0.02" />
                        </linearGradient>
                    </defs>
                    {ticks.map((t) => (
                        <g key={t}>
                            <line x1={PAD.left} x2={W - PAD.right} y1={y(t)} y2={y(t)} className="a-grid" />
                            <text x={PAD.left - 8} y={y(t)} className="a-tick" textAnchor="end" dominantBaseline="middle">
                                {t}
                            </text>
                        </g>
                    ))}
                    {series.map((d, i) =>
                        i % labelEvery === 0 || i === series.length - 1 ? (
                            <text key={d.date} x={x(i)} y={H - 8} className="a-tick" textAnchor="middle">
                                {shortDate(d.date)}
                            </text>
                        ) : null,
                    )}
                    <path d={area} fill={`url(#${gradId})`} />
                    <path d={line} fill="none" stroke="var(--a-series)" strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />
                    {activeDay && (
                        <g>
                            <line x1={points[active][0]} x2={points[active][0]} y1={PAD.top} y2={y(0)} className="a-crosshair" />
                            <circle cx={points[active][0]} cy={points[active][1]} r="5" fill="var(--a-series)" stroke="var(--a-surface)" strokeWidth="2" />
                        </g>
                    )}
                </svg>
                {activeDay && (
                    <div className="a-tooltip" style={{ left: `${(points[active][0] / W) * 100}%` }} aria-hidden="true">
                        <strong>{activeDay.count}</strong>
                        <span>
                            {activeDay.count === 1 ? 'enquiry' : 'enquiries'} · {shortDate(activeDay.date)}
                        </span>
                    </div>
                )}
            </div>
            <table className="sr-only">
                <caption>{label}</caption>
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Enquiries</th>
                    </tr>
                </thead>
                <tbody>
                    {series.map((d) => (
                        <tr key={d.date}>
                            <td>{d.date}</td>
                            <td>{d.count}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}

/** Bar with 4px rounded data end (top), square at the baseline. */
function barPath(x, y, w, base) {
    const h = base - y;
    if (h <= 0) return '';
    const r = Math.min(4, h, w / 2);
    return `M${x},${base}V${y + r}Q${x},${y} ${x + r},${y}H${x + w - r}Q${x + w},${y} ${x + w},${y + r}V${base}Z`;
}

/**
 * Paired vertical bars per category, e.g. income vs expenses per month.
 * series: [{ key, label, color }] (validated colours, fixed order); rows: [{ label, title, [key]: number }].
 * Legend always shown; hover/arrow keys show a tooltip for the whole group; hidden table for screen readers.
 */
export function GroupedBars({ rows, series, label, format = String }) {
    const [active, setActive] = useState(null);

    const { ticks, y, groupW, barW } = useMemo(() => {
        const max = Math.max(...rows.flatMap((r) => series.map((s) => r[s.key])), 0);
        const ticks = ticksFor(max);
        const top = ticks[ticks.length - 1] || 1;
        const innerH = H - PADG.top - PADG.bottom;
        const groupW = (W - PADG.left - PADG.right) / Math.max(rows.length, 1);
        const barW = Math.max(Math.min(16, (groupW - 12) / series.length - 2), 3);
        return { ticks, y: (v) => PADG.top + innerH - (Math.max(v, 0) / top) * innerH, groupW, barW };
    }, [rows, series]);

    if (!rows.length) return null;
    const base = y(0);
    const groupX = (i) => PADG.left + i * groupW;
    const clusterW = series.length * barW + (series.length - 1) * 2;
    const current = active != null ? rows[active] : null;

    const onKey = (e) => {
        if (e.key === 'ArrowRight') setActive((a) => Math.min((a ?? -1) + 1, rows.length - 1));
        else if (e.key === 'ArrowLeft') setActive((a) => Math.max((a ?? rows.length) - 1, 0));
        else return;
        e.preventDefault();
    };

    return (
        <figure className="a-chart">
            <ul className="a-legend" aria-hidden="true">
                {series.map((s) => (
                    <li key={s.key}>
                        <span className="a-legend-swatch" style={{ background: s.color }} />
                        {s.label}
                    </li>
                ))}
            </ul>
            <div className="a-chart-scroll">
                <div className="a-chart-plot">
                    <svg
                        viewBox={`0 0 ${W} ${H}`}
                        role="img"
                        aria-label={`${label}. Use arrow keys to read each month.`}
                        tabIndex={0}
                        onKeyDown={onKey}
                        onBlur={() => setActive(null)}
                        onPointerLeave={() => setActive(null)}
                        style={{ cursor: 'default' }}
                    >
                        {ticks.map((t) => (
                            <g key={t}>
                                <line x1={PADG.left} x2={W - PADG.right} y1={y(t)} y2={y(t)} className="a-grid" />
                                <text x={PADG.left - 8} y={y(t)} className="a-tick" textAnchor="end" dominantBaseline="middle">
                                    {compact(t)}
                                </text>
                            </g>
                        ))}
                        {rows.map((r, i) => {
                            const x0 = groupX(i) + (groupW - clusterW) / 2;
                            return (
                                <g key={r.label}>
                                    {active === i && <rect x={groupX(i) + 2} y={PADG.top} width={groupW - 4} height={base - PADG.top} rx="6" className="a-bar-hover" />}
                                    {series.map((s, j) => (
                                        <path key={s.key} d={barPath(x0 + j * (barW + 2), y(r[s.key]), barW, base)} fill={s.color} />
                                    ))}
                                    <text x={groupX(i) + groupW / 2} y={H - 8} className="a-tick" textAnchor="middle">
                                        {r.label}
                                    </text>
                                    {/* Hit target: the whole column, bigger than the bars. */}
                                    <rect x={groupX(i)} y={PADG.top} width={groupW} height={base - PADG.top} fill="transparent" onPointerEnter={() => setActive(i)} />
                                </g>
                            );
                        })}
                        <line x1={PADG.left} x2={W - PADG.right} y1={base} y2={base} className="a-axis" />
                    </svg>
                    {current && (
                        <div className="a-tooltip" style={{ left: `${Math.min(Math.max(((groupX(active) + groupW / 2) / W) * 100, 14), 86)}%` }} aria-hidden="true">
                            <span>{current.title ?? current.label}</span>
                            {series.map((s) => (
                                <span key={s.key} className="a-tooltip-row">
                                    <span className="a-legend-swatch" style={{ background: s.color }} />
                                    {s.label}
                                    <b>{format(current[s.key])}</b>
                                </span>
                            ))}
                        </div>
                    )}
                </div>
            </div>
            <table className="sr-only">
                <caption>{label}</caption>
                <thead>
                    <tr>
                        <th scope="col">Period</th>
                        {series.map((s) => (
                            <th key={s.key} scope="col">
                                {s.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r) => (
                        <tr key={r.label}>
                            <td>{r.title ?? r.label}</td>
                            {series.map((s) => (
                                <td key={s.key}>{format(r[s.key])}</td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}

/**
 * Horizontal bars for a ranked breakdown: label + value as text (never coloured),
 * a thin single-hue bar with a rounded data end. `marker` renders before the label (e.g. a status dot).
 */
export function BarList({ items, marker, empty = 'No data yet.' }) {
    const max = Math.max(...items.map((i) => i.count), 0);
    if (!items.length || max === 0) return <p className="a-muted a-bar-empty">{empty}</p>;

    return (
        <ul className="a-barlist">
            {items.map((item) => (
                <li key={item.label} title={`${item.label}: ${item.count}`}>
                    <div className="a-barlist-row">
                        <span className="a-barlist-label">
                            {marker?.(item)}
                            {item.label}
                        </span>
                        <span className="a-barlist-value">{item.count}</span>
                    </div>
                    <span className="a-barlist-track" aria-hidden="true">
                        <span className="a-barlist-bar" style={{ width: `${Math.max((item.count / max) * 100, 2)}%` }} />
                    </span>
                </li>
            ))}
        </ul>
    );
}
