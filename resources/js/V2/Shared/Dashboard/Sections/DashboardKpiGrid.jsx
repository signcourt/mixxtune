function KpiCard({
    title,
    value,
    note,
    icon: Icon,
    tone = 'violet',
}) {
    const tones = {
        violet: {
            icon: 'bg-violet-100 text-violet-700',
            glow: 'from-violet-500/10',
        },
        blue: {
            icon: 'bg-blue-100 text-blue-700',
            glow: 'from-blue-500/10',
        },
        emerald: {
            icon: 'bg-emerald-100 text-emerald-700',
            glow: 'from-emerald-500/10',
        },
        amber: {
            icon: 'bg-amber-100 text-amber-700',
            glow: 'from-amber-500/10',
        },
        rose: {
            icon: 'bg-rose-100 text-rose-700',
            glow: 'from-rose-500/10',
        },
    };

    const style =
        tones[tone] ??
        tones.violet;

    return (
        <article className="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div
                className={`absolute inset-x-0 top-0 h-24 bg-gradient-to-b ${style.glow} to-transparent`}
            />

            <div className="relative flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-semibold text-slate-500">
                        {title}
                    </p>

                    <p className="mt-3 text-3xl font-black tracking-tight text-slate-950">
                        {value}
                    </p>

                    {note && (
                        <p className="mt-3 text-xs leading-5 text-slate-400">
                            {note}
                        </p>
                    )}
                </div>

                {Icon && (
                    <div
                        className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl ${style.icon}`}
                    >
                        <Icon
                            size={22}
                            strokeWidth={2.2}
                        />
                    </div>
                )}
            </div>
        </article>
    );
}

export default function DashboardKpiGrid({
    cards = [],
}) {
    return (
        <section className="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            {cards.map((card) => (
                <KpiCard
                    key={card.title}
                    {...card}
                />
            ))}
        </section>
    );
}
