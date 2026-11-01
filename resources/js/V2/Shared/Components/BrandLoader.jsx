/*
 * W136 MIXXTUNE_BRAND_LOADER
 * The Mixx Tune sound-mark "Live Audio" motion, used as the loading / processing indicator across
 * the panel: the icon's own bars rise and fall (scaleY) until the work is done. Self-contained
 * (inline SVG + a <style> block of keyframes), theme-aware via the mt-night class on <html>.
 *
 *   <BrandLoader size={96} label="Generating report…" />   // inline, in a card
 *   <BrandLoader overlay label="Refreshing…" />            // full centred overlay
 *
 * Bars measured from the 512x512 icon file: [left, right, top, bottom].
 */
const BARS = [
    [52, 67, 232, 338], [88, 103, 176, 369], [117, 133, 134, 406], [150, 166, 150, 426],
    [184, 199, 82, 452], [218, 233, 55, 454], [249, 265, 55, 456], [282, 297, 54, 455],
    [312, 328, 80, 448], [345, 361, 156, 431], [378, 393, 132, 404], [406, 422, 178, 371],
    [444, 459, 243, 348],
];

export default function BrandLoader({ size = 72, label = '', overlay = false, className = '' }) {
    const isNight =
        typeof document !== 'undefined' &&
        document.documentElement.classList.contains('mt-night');

    /* Mixx Tune bar colour — brand blue in day, the brighter "Midnight" tone in night.
       The background stays fully transparent: only the bars show. */
    const bar = isNight ? '#8584ff' : '#4e4be2';

    const loader = (
        <div
            style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: size >= 56 ? 14 : 8 }}
            className={className}
        >
            <div
                role="status"
                aria-label="Loading"
                style={{
                    width: size,
                    height: size,
                    background: 'transparent',
                    display: 'grid',
                    placeItems: 'center',
                    overflow: 'visible',
                }}
            >
                <svg viewBox="-40 -40 592 592" width="72%" height="72%" style={{ overflow: 'visible' }} aria-hidden="true">
                    {BARS.map(([l, r, t, b], k) => {
                        const w = r - l + 1;
                        return (
                            <rect
                                key={k}
                                x={l}
                                y={t}
                                width={w}
                                height={b - t + 1}
                                rx={w / 2}
                                fill={bar}
                                style={{
                                    transformBox: 'fill-box',
                                    transformOrigin: 'center',
                                    animation: `mtwave 1.5s ease-in-out ${(-k * 0.11).toFixed(2)}s infinite`,
                                }}
                            />
                        );
                    })}
                </svg>
            </div>
            {label ? (
                <div style={{ fontSize: 13, fontWeight: 600, color: isNight ? '#cbd5e1' : '#475569' }}>{label}</div>
            ) : null}
            <style>{`@keyframes mtwave{0%,100%{transform:scaleY(0.38)}50%{transform:scaleY(1)}}`}</style>
        </div>
    );

    if (!overlay) return loader;

    return (
        <div
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 99998,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                background: 'transparent',
                pointerEvents: 'none',
            }}
        >
            {loader}
        </div>
    );
}
