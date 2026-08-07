export default function DashboardPanel({
    title,
    subtitle = null,
    action = null,
    children,
    className = '',
}) {
    return (
        <section
            className={`overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm ${className}`}
        >
            {(title || subtitle || action) && (
                <div className="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        {title && (
                            <h3 className="text-lg font-black text-slate-950">
                                {title}
                            </h3>
                        )}

                        {subtitle && (
                            <p className="mt-1 text-sm text-slate-500">
                                {subtitle}
                            </p>
                        )}
                    </div>

                    {action}
                </div>
            )}

            {children}
        </section>
    );
}
