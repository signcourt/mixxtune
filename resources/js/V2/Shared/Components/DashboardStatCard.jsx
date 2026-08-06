export default function DashboardStatCard({
    label,
    value,
    helper = null,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-sm font-medium text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-3xl font-bold text-slate-900">
                {value}
            </div>

            {helper && (
                <div className="mt-2 text-xs text-slate-400">
                    {helper}
                </div>
            )}
        </div>
    );
}
