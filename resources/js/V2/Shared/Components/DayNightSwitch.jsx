import { useEffect, useState } from "react";

/*
 * W90 MIXXTUNE_DAY_NIGHT
 * Sun / moon switch in the top bar of every panel (artist, label, manager,
 * super admin). Night turns the white middle part dark. The choice is kept
 * in this browser (localStorage) and set before the page paints
 * (resources/views/app.blade.php), so there is no white flash.
 */
const KEY = "mt-theme";

const readNight = () => {
    try {
        return window.localStorage.getItem(KEY) === "night";
    } catch {
        return document.documentElement.classList.contains("mt-night");
    }
};

export default function DayNightSwitch() {
    const [night, setNight] = useState(() => (typeof window === "undefined" ? false : readNight()));

    useEffect(() => {
        document.documentElement.classList.toggle("mt-night", night);
        try {
            window.localStorage.setItem(KEY, night ? "night" : "day");
        } catch {
            /* private window: works for this page only */
        }
    }, [night]);

    return (
        <button
            type="button"
            role="switch"
            aria-checked={night}
            aria-label={night ? "Night mode on - switch to day" : "Day mode on - switch to night"}
            title={night ? "Night" : "Day"}
            onClick={() => setNight((n) => !n)}
            className={`relative flex h-11 w-[84px] shrink-0 items-center justify-between rounded-full border px-2.5 shadow-sm transition-colors ${
                night ? "border-slate-600 bg-slate-800" : "border-slate-200 bg-amber-50"
            }`}
        >
            {/* sun */}
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className={`relative z-10 h-5 w-5 transition-colors ${night ? "text-slate-500" : "text-amber-500"}`} aria-hidden="true">
                <circle cx="12" cy="12" r="4" />
                <path strokeLinecap="round" d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
            </svg>
            {/* moon */}
            <svg viewBox="0 0 24 24" fill="currentColor" className={`relative z-10 h-5 w-5 transition-colors ${night ? "text-indigo-200" : "text-slate-400"}`} aria-hidden="true">
                <path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z" />
            </svg>
            {/* knob */}
            <span
                className={`absolute top-1 h-[34px] w-[34px] rounded-full shadow transition-all duration-300 ${
                    night ? "left-[45px] bg-slate-950" : "left-1 bg-white"
                }`}
            />
        </button>
    );
}
