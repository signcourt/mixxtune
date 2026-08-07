const monthLabel = (value) => {
    if (!value) {
        return '—';
    }

    const date = new Date(`${value}-01T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('en-IN', {
        month: 'short',
        year: '2-digit',
    }).format(date);
};

export default function TrendChart({
    rows = [],
    metric = 'earnings',
    title = 'Monthly Trend',
}) {
    const values = rows.map((row) =>
        Number(row?.[metric] ?? 0)
    );

    const maximum = Math.max(1, ...values);

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h2 className="text-lg font-bold text-slate-950">
                        {title}
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        Last {rows.length || 0} reporting months
                    </p>
                </div>

                <span className="rounded-full bg-violet-50 px-3 py-1 text-xs font-bold text-violet-700">
                    {metric === 'earnings'
                        ? 'Revenue'
                        : metric === 'streams'
                          ? 'Streams'
                          : 'Units'}
                </span>
            </div>

            {rows.length > 0 ? (
                <div className="mt-8 flex h-64 items-end gap-3 overflow-x-auto pb-2">
                    {rows.map((row, index) => {
                        const value = Number(
                            row?.[metric] ?? 0
                        );

                        const height = Math.max(
                            8,
                            Math.round(
                                (value / maximum) * 100
                            )
                        );

                        return (
                            <div
                                key={`${row.month}-${index}`}
                                className="flex min-w-[46px] flex-1 flex-col items-center justify-end"
                            >
                                <div
                                    title={String(value)}
                                    className="w-full rounded-t-xl bg-gradient-to-t from-violet-600 to-fuchsia-400 transition hover:opacity-80"
                                    style={{
                                        height: `${height}%`,
                                    }}
                                />

                                <span className="mt-3 text-[10px] font-semibold text-slate-500">
                                    {monthLabel(row.month)}
                                </span>
                            </div>
                        );
                    })}
                </div>
            ) : (
                <div className="mt-6 rounded-xl bg-slate-50 px-4 py-16 text-center text-sm text-slate-500">
                    No monthly trend data available.
                </div>
            )}
        </section>
    );
}
