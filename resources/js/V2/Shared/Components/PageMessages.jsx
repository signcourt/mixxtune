import { usePage } from '@inertiajs/react';

/* W59: success / error messages from the server, for pages that showed none */
export default function PageMessages({ skip = ['action_otp'] }) {
    const { flash = {}, errors = {} } = usePage().props;
    const list = Object.entries(errors || {}).filter(([k]) => !skip.includes(k) && !k.includes('.'));

    return (
        <>
            {flash.success && (
                <div className="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm font-semibold text-emerald-800">{flash.success}</div>
            )}
            {flash.error && (
                <div className="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>
            )}
            {list.length > 0 && (
                <div className="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3 text-sm text-rose-800">
                    {list.map(([k, v]) => (
                        <div key={k}>{Array.isArray(v) ? v[0] : v}</div>
                    ))}
                </div>
            )}
        </>
    );
}
