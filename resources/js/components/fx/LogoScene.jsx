import { useEffect, useRef, useState } from 'react';
import {
    ACESFilmicToneMapping,
    AdditiveBlending,
    AmbientLight,
    BufferAttribute,
    BufferGeometry,
    EdgesGeometry,
    ExtrudeGeometry,
    Group,
    LineBasicMaterial,
    LineSegments,
    Mesh,
    MeshBasicMaterial,
    MeshPhysicalMaterial,
    PerspectiveCamera,
    PointLight,
    Points,
    PointsMaterial,
    Scene,
    Shape,
    SphereGeometry,
    TorusGeometry,
    Vector2,
    WebGLRenderer,
} from 'three';
import { cx, prefersReducedMotion } from '@/lib/utils';

// The ZAIRON Systems mark (same polygons as the SVG logo, 0–100 space), centred and scaled to ±1.5 units.
const HALVES = [
    [[12, 12], [88, 12], [88, 30], [71.2, 44], [44.5, 44], [62, 30], [12, 30]],
    [[29.5, 56], [56.8, 56], [40, 70], [88, 70], [88, 88], [12, 88], [12, 70]],
];
const toShape = (points) => new Shape(points.map(([x, y]) => new Vector2((x - 50) / 25, -(y - 50) / 25)));

function buildScene(compact) {
    const scene = new Scene();
    const root = new Group();
    scene.add(root);

    // Extruded metallic "Z" with a holographic edge glow.
    const logoGeometry = new ExtrudeGeometry(HALVES.map(toShape), {
        depth: 0.45,
        bevelEnabled: true,
        bevelThickness: 0.06,
        bevelSize: 0.04,
        bevelSegments: 4,
    });
    logoGeometry.center();
    const logo = new Mesh(
        logoGeometry,
        new MeshPhysicalMaterial({ color: 0x8c0f1f, metalness: 0.65, roughness: 0.22, clearcoat: 1, clearcoatRoughness: 0.15, emissive: 0x2b0008 }),
    );
    const edges = new LineSegments(new EdgesGeometry(logoGeometry, 25), new LineBasicMaterial({ color: 0xf2b8b8, transparent: true, opacity: 0.45 }));
    logo.add(edges);

    // The single node where the two halves meet.
    const node = new Mesh(new SphereGeometry(0.11, 32, 32), new MeshBasicMaterial({ color: 0xffd6dc }));
    node.position.set(0.02, 0, 0.32);
    logo.add(node);
    root.add(logo);

    // Orbiting hologram rings.
    const ringMaterial = new MeshBasicMaterial({ color: 0xf2b8b8, transparent: true, opacity: 0.32 });
    const rings = [2.5, 2.95, 3.4].map((radius, i) => {
        const ring = new Mesh(new TorusGeometry(radius, 0.007, 8, 160), ringMaterial);
        ring.rotation.set(1.2 + i * 0.25, i * 0.5, 0);
        root.add(ring);
        return ring;
    });

    // Particle field.
    const count = compact ? 500 : 1400;
    const positions = new Float32Array(count * 3);
    for (let i = 0; i < count; i++) {
        const r = 3.6 + Math.random() * 4;
        const theta = Math.random() * Math.PI * 2;
        const phi = Math.acos(2 * Math.random() - 1);
        positions.set([r * Math.sin(phi) * Math.cos(theta), r * Math.sin(phi) * Math.sin(theta) * 0.6, r * Math.cos(phi)], i * 3);
    }
    const particlesGeometry = new BufferGeometry();
    particlesGeometry.setAttribute('position', new BufferAttribute(positions, 3));
    const particles = new Points(
        particlesGeometry,
        new PointsMaterial({ color: 0xf2b8b8, size: 0.035, transparent: true, opacity: 0.75, blending: AdditiveBlending, depthWrite: false }),
    );
    scene.add(particles);

    // Lighting: warm key, cool fill, maroon rim.
    scene.add(new AmbientLight(0xffffff, 0.35));
    [
        [0xff5470, 60, [3, 3, 4]],
        [0xffffff, 35, [-4, -2, 5]],
        [0xb3142c, 90, [0, 0.5, -3.5]],
    ].forEach(([color, intensity, [x, y, z]]) => {
        const light = new PointLight(color, intensity);
        light.position.set(x, y, z);
        scene.add(light);
    });

    return { scene, root, logo, rings, particles };
}

/**
 * Real-time WebGL ZAIRON Systems mark. Decorative: hidden from assistive tech, pauses off-screen and in
 * background tabs, renders a single still frame for reduced-motion users, and renders nothing
 * if WebGL is unavailable.
 */
export default function LogoScene({ className, compact = false, distance = 9, fallback = null }) {
    const mount = useRef(null);
    const [ready, setReady] = useState(false);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        const el = mount.current;
        let renderer;
        try {
            renderer = new WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
        } catch {
            setFailed(true);
            return undefined;
        }
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.toneMapping = ACESFilmicToneMapping;
        el.appendChild(renderer.domElement);

        const { scene, root, logo, rings, particles } = buildScene(compact);
        const camera = new PerspectiveCamera(35, 1, 0.1, 100);
        camera.position.z = distance;

        const resize = () => {
            const { width, height } = el.getBoundingClientRect();
            if (!width || !height) return;
            renderer.setSize(width, height, false);
            camera.aspect = width / height;
            camera.updateProjectionMatrix();
        };
        const resizer = new ResizeObserver(resize);
        resizer.observe(el);
        resize();

        const pointer = { x: 0, y: 0, tx: 0, ty: 0 };
        const onPointer = (e) => {
            pointer.tx = e.clientX / window.innerWidth - 0.5;
            pointer.ty = e.clientY / window.innerHeight - 0.5;
        };

        const render = (t = 0) => {
            const s = t / 1000;
            pointer.x += (pointer.tx - pointer.x) * 0.05;
            pointer.y += (pointer.ty - pointer.y) * 0.05;
            root.rotation.y = Math.sin(s * 0.35) * 0.45 + pointer.x * 0.6;
            root.rotation.x = pointer.y * 0.35;
            logo.position.y = Math.sin(s * 1.1) * 0.08;
            rings.forEach((ring, i) => {
                ring.rotation.z = s * (0.15 + i * 0.07) * (i % 2 ? -1 : 1);
            });
            particles.rotation.y = s * 0.03;
            renderer.render(scene, camera);
        };

        let frame = 0;
        let visible = true;
        const loop = (t) => {
            render(t);
            frame = requestAnimationFrame(loop);
        };
        const start = () => {
            if (!frame && visible && !document.hidden) frame = requestAnimationFrame(loop);
        };
        const stop = () => {
            cancelAnimationFrame(frame);
            frame = 0;
        };

        const still = prefersReducedMotion();
        render(0);
        setReady(true);

        const observer = new IntersectionObserver(([entry]) => {
            visible = entry.isIntersecting;
            visible ? start() : stop();
        });
        const onVisibility = () => (document.hidden ? stop() : start());

        if (!still) {
            observer.observe(el);
            window.addEventListener('pointermove', onPointer, { passive: true });
            document.addEventListener('visibilitychange', onVisibility);
            start();
        }

        return () => {
            stop();
            observer.disconnect();
            resizer.disconnect();
            window.removeEventListener('pointermove', onPointer);
            document.removeEventListener('visibilitychange', onVisibility);
            scene.traverse((obj) => {
                obj.geometry?.dispose();
                obj.material?.dispose();
            });
            renderer.dispose();
            renderer.forceContextLoss();
            renderer.domElement.remove();
        };
    }, [compact, distance]);

    if (failed) return fallback;
    return <div ref={mount} className={cx('scene3d', ready && 'ready', className)} aria-hidden="true" />;
}
