import {
    Head,
    router,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'admin',
    statements = {},
    invoiceStatementIds = [],
}) {
    const generated = new Set(
        invoiceStatementIds.map(Number)
    );

    const generate = (id) => {
        router.post(
            `/v2/admin/invoices/statements/${id}/generate`,
            {},
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="Generate Invoices"
            subtitle="Create invoices from royalty statements"
        >
            <Head title="Generate Invoices" />

            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table className="min-w-full">
                    <thead className="bg-slate-50">
                        <tr>
                            {[
                                'Statement Month',
                                'Artist',
                                'Net Payable',
                                'Status',
                                'Invoice',
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
                        {(statements.data ?? []).map(
                            (item) => (
                                <tr key={item.id}>
                                    <Cell>
                                        {item.statement_month}
                                    </Cell>

                                    <Cell>
                                        {item.artist_id ??
                                            item.label_id ??
                                            '—'}
                                    </Cell>

                                    <Cell>
                                        {item.currency}{' '}
                                        {item.net_payable}
                                    </Cell>

                                    <Cell>
                                        {item.status}
                                    </Cell>

                                    <Cell>
                                        {generated.has(
                                            Number(item.id)
                                        ) ? (
                                            <span className="font-semibold text-emerald-600">
                                                Generated
                                            </span>
                                        ) : (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    generate(
                                                        item.id
                                                    )
                                                }
                                                className="rounded-lg bg-violet-600 px-4 py-2 text-xs font-semibold text-white"
                                            >
                                                Generate
                                            </button>
                                        )}
                                    </Cell>
                                </tr>
                            )
                        )}
                    </tbody>
                </table>
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
