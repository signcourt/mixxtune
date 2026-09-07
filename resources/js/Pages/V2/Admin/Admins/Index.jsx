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
            title="Admins"
            subtitle="Manage admin assignments"
        >
            <Head title="Admins" />

            <div className="mb-4 flex justify-end">
                <Link
                    href="/v2/admin/users/create"
                    className="inline-flex items-center rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700"
                >
                    + Create Admin
                </Link>
            </div>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table className="min-w-full">
                    <thead className="bg-slate-50">
                        <tr>
                            {[
                                'Admin',
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
