import { cx } from '@/lib/utils';
import Icon from './Icon';

/**
 * Row of pill toggle buttons. Single-select (`value` is a string) for filters and budgets,
 * multi-select (`value` is an array) for services.
 */
export default function ToggleGroup({ options, value, onChange, label, labelledBy, checkmarks = false, className }) {
    const multiple = Array.isArray(value);
    const isOn = (v) => (multiple ? value.includes(v) : value === v);

    const toggle = (v) => {
        if (!multiple) return onChange(v);
        onChange(isOn(v) ? value.filter((x) => x !== v) : [...value, v]);
    };

    return (
        <div className={cx('options', className)} role="group" aria-label={label} aria-labelledby={labelledBy}>
            {options.map((opt) => (
                <button
                    key={opt.value}
                    type="button"
                    className="pill glass"
                    aria-pressed={isOn(opt.value)}
                    onClick={() => toggle(opt.value)}
                >
                    {checkmarks && <Icon name="check" />}
                    {opt.label}
                </button>
            ))}
        </div>
    );
}
