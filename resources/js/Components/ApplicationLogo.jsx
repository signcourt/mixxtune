export default function ApplicationLogo({
    className = '',
    showText = true,
}) {
    return (
        <div
            className={`flex items-center gap-3 ${className}`}
        >
            <div className="relative flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-600 shadow-lg shadow-blue-600/20">
                <div className="absolute inset-[1px] rounded-[15px] border border-white/20" />

                <span className="relative text-sm font-black tracking-tight text-white">
                    MT
                </span>
            </div>

            {showText && (
                <div className="leading-none">
                    <div className="text-lg font-black tracking-tight text-slate-950">
                        MIXX TUNE
                    </div>

                    <div className="mt-1 text-[9px] font-bold uppercase tracking-[0.32em] text-slate-400">
                        Music Distribution
                    </div>
                </div>
            )}
        </div>
    );
}
