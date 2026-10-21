import {
    Head,
    Link,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'super_admin',
    admins = {},
}) {
    return (
        <PanelLayout
            role={role}
            title="Managers"
            subtitle="Manage manager assignments"
        >
            <Head title="Managers" />

            <div className="mb-4 flex justify-end">
                <Link
                    href="/v2/admin/users/create?type=manager"
                    className="inline-flex items-center rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700"
                >
                    + Create Manager
                </Link>
            </div>

            <section className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table className="min-w-full">
                    <thead className="bg-slate-50">
                        <tr>
                            {[
                                'Manager',
                                'Role',
                                'Status',
                                'Artists',
                                'Labels',
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
                        {(admins.data ?? []).map(
                            (admin) => (
                                <tr key={admin.id}>
                                    <Cell>
                                        <div className="font-semibold text-slate-900">
                                            {admin.name}
                                        </div>

                                        <div className="text-xs text-slate-500">
                                            {admin.email}
                                        </div>
                                    </Cell>

                                    <Cell>
                                        {admin.role}
                                    </Cell>

                                    <Cell>
                                        {admin.account_status}
                                    </Cell>

                                    <Cell>
                                        {
                                            admin.assigned_artists_count
                                        }
                                    </Cell>

                                    <Cell>
                                        {
                                            admin.assigned_labels_count
                                        }
                                    </Cell>

                                    <Cell>
                                        <Link
                                            href={`/v2/admin/admins/${admin.id}`}
                                            className="rounded-lg bg-violet-600 px-4 py-2 text-xs font-semibold text-white"
                                        >
                                            Manage
                                        </Link>
                                    </Cell>
                                </tr>
                            )
                        )}
                    </tbody>
                </table>
                {/* W59: page links (managers after the first 25 were not reachable) */}
                {(admins.links || []).length > 3 && (
                    <div className="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-4">
                        {admins.links.map((link, i) => (
                            <Link key={`${link.label}-${i}`} href={link.url || '#'} preserveScroll className={`rounded-lg border px-3 py-1.5 text-xs font-semibold ${link.active ? 'border-violet-600 bg-violet-600 text-white' : 'border-slate-300 bg-white text-slate-600'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                        ))}
                    </div>
                )}
            </section>
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
