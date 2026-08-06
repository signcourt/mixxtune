export default function EmptyState({
    title,
    description,
    action = null,
}) {
    return (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
            <h3 className="text-lg font-semibold text-slate-900">
                {title}
            </h3>

            <p className="mx-auto mt-2 max-w-xl text-sm text-slate-500">
                {description}
            </p>

            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
