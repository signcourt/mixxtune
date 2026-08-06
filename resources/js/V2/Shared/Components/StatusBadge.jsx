const statusClasses = {
    draft: 'bg-slate-100 text-slate-700',
    submitted: 'bg-blue-100 text-blue-700',
    approved: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-red-100 text-red-700',
    processing: 'bg-amber-100 text-amber-700',
    delivered: 'bg-violet-100 text-violet-700',
    live: 'bg-green-100 text-green-700',
    failed: 'bg-rose-100 text-rose-700',
};

export default function StatusBadge({
    status = 'draft',
}) {
    const normalized = String(status).toLowerCase();

    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize ${
                statusClasses[normalized] ??
                'bg-slate-100 text-slate-700'
            }`}
        >
            {normalized}
        </span>
    );
}
