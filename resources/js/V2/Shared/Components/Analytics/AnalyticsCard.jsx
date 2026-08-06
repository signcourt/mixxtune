export default function AnalyticsCard({
    label,
    value,
    description,
    icon,
    tone = 'violet',
}) {
    const tones = {
        violet: 'bg-violet-50 text-violet-700 ring-violet-100',
        blue: 'bg-blue-50 text-blue-700 ring-blue-100',
        emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        amber: 'bg-amber-50 text-amber-700 ring-amber-100',
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-medium text-slate-500">
                        {label}
                    </p>

                    <p className="mt-2 text-2xl font-black tracking-tight text-slate-950">
                        {value}
                    </p>

                    {description && (
                        <p className="mt-1 text-xs leading-5 text-slate-400">
                            {description}
                        </p>
                    )}
                </div>

                <div
                    className={[
                        'flex h-11 w-11 items-center justify-center rounded-xl ring-1',
                        tones[tone] ?? tones.violet,
                    ].join(' ')}
                >
                    {icon}
                </div>
            </div>
        </div>
    );
}
