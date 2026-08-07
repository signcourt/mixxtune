import { Head, Link, router } from '@inertiajs/react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'admin',
    logs = {},
    modules = [],
    filters = {},
}) {
    const update = (changes) => {
        router.get(
            '/v2/admin/audit-logs',
            {
                ...filters,
                ...changes,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="Audit Logs"
            subtitle="System changes and user activity"
        >
            <Head title="Audit Logs" />

            <div className="space-y-5">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="grid gap-3 md:grid-cols-[1fr_220px_auto]">
                        <input
                            type="search"
                            defaultValue={filters.search ?? ''}
                            placeholder="Search action, description or IP..."
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    update({
                                        search:
                                            event.currentTarget.value,
                                    });
                                }
                            }}
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />

                        <select
                            value={filters.module ?? ''}
                            onChange={(event) =>
                                update({
                                    module: event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">All Modules</option>

                            {modules.map((module) => (
                                <option
                                    key={module}
                                    value={module}
                                >
                                    {module}
                                </option>
                            ))}
                        </select>

                        <button
                            type="button"
                            onClick={() =>
                                router.get(
                                    '/v2/admin/audit-logs'
                                )
                            }
                            className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700"
                        >
                            Clear
                        </button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {[
                                        'Date',
                                        'User',
                                        'Module',
                                        'Action',
                                        'Description',
                                        'IP',
                                    ].map((heading) => (
                                        <th
                                            key={heading}
                                            className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {(logs.data ?? []).map((log) => (
                                    <tr key={log.id}>
                                        <Cell>
                                            {log.created_at}
                                        </Cell>

                                        <Cell>
                                            <div className="font-semibold text-slate-900">
                                                {log.user?.name ??
                                                    'System'}
                                            </div>

                                            <div className="text-xs text-slate-500">
                                                {log.user?.email ?? ''}
                                            </div>
                                        </Cell>

                                        <Cell>
                                            {log.module ?? '—'}
                                        </Cell>

                                        <Cell>{log.action}</Cell>

                                        <Cell>
                                            {log.description ?? '—'}
                                        </Cell>

                                        <Cell>
                                            {log.ip_address ?? '—'}
                                        </Cell>
                                    </tr>
                                ))}

                                {(logs.data ?? []).length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-5 py-16 text-center text-sm text-slate-500"
                                        >
                                            अभी कोई audit log नहीं है।
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                {logs.links && (
                    <div className="flex flex-wrap justify-center gap-2">
                        {logs.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={[
                                    'rounded-lg border px-3 py-2 text-sm',
                                    link.active
                                        ? 'border-violet-600 bg-violet-600 text-white'
                                        : 'border-slate-300 bg-white text-slate-700',
                                    !link.url
                                        ? 'pointer-events-none opacity-40'
                                        : '',
                                ].join(' ')}
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </PanelLayout>
    );
}

function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
