import {
    Head,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'artist',
    invoices = {},
}) {
    return (
        <PanelLayout
            role={role}
            title="Invoices"
            subtitle="Generated royalty invoices"
        >
            <Head title="Invoices" />

            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table className="min-w-full">
                    <thead className="bg-slate-50">
                        <tr>
                            {[
                                'Invoice',
                                'Date',
                                'Month',
                                'Amount',
                                'Status',
                                'Download',
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
                        {(invoices.data ?? []).map(
                            (item) => (
                                <tr key={item.id}>
                                    <Cell>
                                        {item.invoice_number}
                                    </Cell>

                                    <Cell>
                                        {item.invoice_date}
                                    </Cell>

                                    <Cell>
                                        {item.statement
                                            ?.statement_month ??
                                            '—'}
                                    </Cell>

                                    <Cell>
                                        {item.currency}{' '}
                                        {item.total_amount}
                                    </Cell>

                                    <Cell>
                                        {item.status}
                                    </Cell>

                                    <Cell>
                                        <a
                                            href={`/v2/invoices/${item.id}/download`}
                                            className="font-semibold text-violet-600"
                                        >
                                            PDF
                                        </a>
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
