import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'admin',
    tickets = {},
    counts = {},
    filters = {},
}) {
    return (
        <PanelLayout
            role={role}
            title="Support Management"
            subtitle="Manage artist and label tickets"
        >
            <Head title="Support Management" />

            <div className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-5">
                    {[
                        ['open', 'Open'],
                        [
                            'customer_reply',
                            'Customer Reply',
                        ],
                        [
                            'admin_reply',
                            'Manager Reply',
                        ],
                        ['waiting', 'Waiting'],
                        ['closed', 'Closed'],
                    ].map(([status, label]) => (
                        <button
                            key={status}
                            type="button"
                            onClick={() =>
                                router.get(
                                    '/v2/admin/support',
                                    { status },
                                    {
                                        preserveState:
                                            true,
                                    }
                                )
                            }
                            className={[
                                'rounded-2xl border bg-white p-5 text-left shadow-sm',
                                filters.status === status
                                    ? 'border-violet-500 ring-2 ring-violet-100'
                                    : 'border-slate-200',
                            ].join(' ')}
                        >
                            <div className="text-sm text-slate-500">
                                {label}
                            </div>

                            <div className="mt-2 text-3xl font-bold text-slate-900">
                                {counts[status] ?? 0}
                            </div>
                        </button>
                    ))}
                </div>

                <div className="space-y-3">
                    {(tickets.data ?? []).map(
                        (ticket) => (
                            <Link
                                key={ticket.id}
                                href={`/v2/admin/support/${ticket.id}`}
                                className="block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-violet-300"
                            >
                                <div className="flex flex-wrap justify-between gap-3">
                                    <div>
                                        <div className="text-xs font-semibold text-violet-600">
                                            {ticket.ticket_number}
                                        </div>

                                        <h2 className="mt-1 font-semibold text-slate-900">
                                            {ticket.subject}
                                        </h2>

                                        <div className="mt-2 text-sm text-slate-500">
                                            {ticket.user?.name} •{' '}
                                            {ticket.priority} •{' '}
                                            {ticket.messages_count}{' '}
                                            messages
                                        </div>
                                    </div>

                                    <span className="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold capitalize text-violet-700">
                                        {ticket.status.replaceAll(
                                            '_',
                                            ' '
                                        )}
                                    </span>
                                </div>
                            </Link>
                        )
                    )}
                </div>
            </div>
        </PanelLayout>
    );
}
