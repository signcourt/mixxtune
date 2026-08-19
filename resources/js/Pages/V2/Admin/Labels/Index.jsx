import {
    Head,
    router,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'admin',
    labels = {},
    search = '',
}) {
    return (
        <PanelLayout
            role={role}
            title="Labels"
            subtitle="Assigned label accounts"
        >
            <Head title="Labels" />

            <div className="space-y-5">
                <input
                    type="search"
                    defaultValue={search}
                    placeholder="Search label..."
                    onKeyDown={(event) => {
                        if (
                            event.key === 'Enter'
                        ) {
                            router.get(
                                '/v2/admin/labels',
                                {
                                    search:
                                        event
                                            .currentTarget
                                            .value,
                                }
                            );
                        }
                    }}
                    className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 md:max-w-md"
                />

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                {[
                                    'Label',
                                    'Type',
                                    'Status',
                                    'Artists',
                                    'Releases',
                                    'Assigned Admins',
                                    'Action',
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
                            {(labels.data ?? []).map(
                                (label) => (
                                    <tr key={label.id}>
                                        <Cell>
                                            <div className="font-semibold text-slate-900">
                                                {label.name}
                                            </div>

                                            <div className="text-xs text-slate-500">
                                                {label.email}
                                            </div>
                                        </Cell>

                                        <Cell>
                                            {label.label_type}
                                        </Cell>

                                        <Cell>
                                            {label.status}
                                        </Cell>

                                        <Cell>
                                            <a
                                                href={`/v2/admin/artists?label_id=${label.id}`}
                                                className="font-semibold text-violet-600 hover:text-violet-800 hover:underline"
                                                title={`View artists for ${label.name}`}
                                            >
                                                {
                                                    label.artists_count
                                                }
                                            </a>
                                        </Cell>

                                        <Cell>
                                            <a
                                                href={`/v2/releases?label_id=${label.id}`}
                                                className="font-semibold text-violet-600 hover:text-violet-800 hover:underline"
                                                title={`View releases for ${label.name}`}
                                            >
                                                {
                                                    label.releases_count
                                                }
                                            </a>
                                        </Cell>

                                        <Cell>
                                            {(
                                                label.assigned_admins ??
                                                []
                                            )
                                                .map(
                                                    (
                                                        admin
                                                    ) =>
                                                        admin.name
                                                )
                                                .join(', ') ||
                                                'Unassigned'}
                                        </Cell>
                                        <Cell>
                                            {role === 'super_admin' ? (
                                                <a
                                                    href={`/v2/admin/admins?label_id=${label.id}`}
                                                    className="inline-flex rounded-lg bg-violet-600 px-3 py-2 text-xs font-semibold text-white hover:bg-violet-700"
                                                    title={`Manage admin assignment for ${label.name}`}
                                                >
                                                    Assign Admin
                                                </a>
                                            ) : (
                                                <span className="text-xs text-slate-400">
                                                    —
                                                </span>
                                            )}
                                        </Cell>

                                    </tr>
                                )
                            )}
                        </tbody>
                    </table>
                </section>

                {(labels.links ?? []).length > 3 && (
                    <div className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                        <div className="text-sm text-slate-500">
                            Showing{' '}
                            <span className="font-semibold text-slate-700">
                                {labels.from ?? 0}
                            </span>
                            {' '}to{' '}
                            <span className="font-semibold text-slate-700">
                                {labels.to ?? 0}
                            </span>
                            {' '}of{' '}
                            <span className="font-semibold text-slate-700">
                                {labels.total ?? 0}
                            </span>
                            {' '}labels
                        </div>

                        <Pagination links={labels.links ?? []} />
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

function Pagination({ links = [] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav
            className="flex flex-wrap items-center gap-1"
            aria-label="Labels pagination"
        >
            {links.map((link, index) => {
                const label = String(link.label ?? '')
                    .replace('&laquo;', '«')
                    .replace('&raquo;', '»');

                return link.url ? (
                    <button
                        key={`${label}-${index}`}
                        type="button"
                        onClick={() =>
                            router.visit(link.url, {
                                preserveScroll: true,
                                preserveState: true,
                            })
                        }
                        className={`min-w-10 rounded-lg border px-3 py-2 text-sm font-medium transition ${
                            link.active
                                ? 'border-slate-900 bg-slate-900 text-white'
                                : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                        }`}
                    >
                        {label}
                    </button>
                ) : (
                    <span
                        key={`${label}-${index}`}
                        className="min-w-10 cursor-not-allowed rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-center text-sm text-slate-300"
                    >
                        {label}
                    </span>
                );
            })}
        </nav>
    );
}
