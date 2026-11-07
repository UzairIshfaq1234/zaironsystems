import Icon from '@/components/ui/Icon';

// Where the four orbiting chips float around the projector.
const SPOTS = [
    { left: '8%', top: '15%' },
    { right: '8%', top: '24%' },
    { left: '6%', top: '63%' },
    { right: '6%', top: '70%' },
];

/**
 * CSS-3D "hologram projector": the service icon floats above a light beam inside gyroscope rings,
 * with tech chips orbiting around it. Pure CSS animation, no WebGL needed. Decorative.
 */
export default function HoloVisual({ icon, chips = [] }) {
    return (
        <div className="holo" aria-hidden="true">
            <div className="holo-stage">
                <span className="holo-base" />
                <span className="holo-beam" />
                <span className="holo-ring r1" />
                <span className="holo-ring r2" />
                <span className="holo-ring r3" />
                <span className="holo-gyro" />
                <span className="holo-core">
                    <Icon name={icon} />
                </span>
                {chips.slice(0, 4).map((chip, i) => (
                    <span key={chip} className="holo-chip glass" style={{ ...SPOTS[i], '--i': i }}>
                        {chip}
                    </span>
                ))}
            </div>
            <span className="holo-scan" />
        </div>
    );
}
