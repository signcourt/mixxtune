export default function TopListCard({
    title,
    subtitle,
    rows = [],
    valueKey = 'earnings',
    formatter = (value) => value,
}) {
    const maximum = Math.max(
        1,
        ...rows.map((row) =>
            Number(row?.[valueKey] ?? 0)
        )
    );

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <h2 className="text-lg font-bold text-slate-950">
                    {title}
                </h2>

                <p className="mt-1 text-sm text-slate-500">
                    {subtitle}
                </p>
            </div>

            <div className="mt-5 space-y-4">
                {rows.length > 0 ? (
                    rows.map((row, index) => {
                        const value = Number(
                            row?.[valueKey] ?? 0
                        );

                        const width = Math.max(
                            4,
                            Math.round(
                                (value / maximum) * 100
                            )
                        );

                        return (
                            <div key={`${row.name}-${index}`}>
                                <div className="flex items-center justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-slate-800">
                                            {row.name || 'Unknown'}
                                        </p>
                                    </div>

                                    <p className="whitespace-nowrap text-sm font-bold text-slate-950">
                                        {formatter(value)}
                                    </p>
                                </div>

                                <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        className="h-full rounded-full bg-violet-500"
                                        style={{
                                            width: `${width}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        );
                    })
                ) : (
                    <div className="rounded-xl bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                        No analytics data available.
                    </div>
                )}
            </div>
        </section>
    );
}
