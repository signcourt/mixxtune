export default function PerformanceList({
    rows = [],
    title = 'Performance',
    emptyText = 'No analytics available.',
    labelKey = 'name',
    valueKey = 'streams',
    secondaryKey = 'earnings',
    formatValue = (value) =>
        Number(value || 0).toLocaleString(),
    formatSecondary = (value) =>
        Number(value || 0).toLocaleString(),
}) {
    const max = Math.max(
        1,
        ...rows.map((row) =>
            Number(row?.[valueKey] || 0)
        )
    );

    return (
        <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 className="text-lg font-black text-slate-950">
                {title}
            </h3>

            {rows.length > 0 ? (
                <div className="mt-5 space-y-5">
                    {rows.map((row, index) => {
                        const value = Number(
                            row?.[valueKey] || 0
                        );

                        const width = Math.max(
                            4,
                            Math.round(
                                (value / max) * 100
                            )
                        );

                        return (
                            <div
                                key={`${row?.[labelKey] ?? index}-${index}`}
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <span className="truncate font-bold text-slate-700">
                                        {row?.[labelKey] ||
                                            'Unknown'}
                                    </span>

                                    <span className="text-sm font-black text-slate-950">
                                        {formatValue(value)}
                                    </span>
                                </div>

                                <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        className="h-full rounded-full bg-violet-600"
                                        style={{
                                            width: `${width}%`,
                                        }}
                                    />
                                </div>

                                {secondaryKey && (
                                    <div className="mt-1 text-right text-xs font-semibold text-emerald-600">
                                        {formatSecondary(
                                            row?.[
                                                secondaryKey
                                            ] || 0
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            ) : (
                <div className="py-10 text-center text-sm text-slate-500">
                    {emptyText}
                </div>
            )}
        </section>
    );
}
