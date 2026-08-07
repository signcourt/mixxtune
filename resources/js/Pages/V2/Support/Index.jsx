import {
    Head,
    Link,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'artist',
    tickets = {},
}) {
    return (
        <PanelLayout
            role={role}
            title="Support Tickets"
            subtitle="Get help with your account and releases"
        >
            <Head title="Support Tickets" />

            <div className="space-y-5">
                <div className="flex justify-end">
                    <Link
                        href="/v2/support/create"
                        className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white"
                    >
                        + New Ticket
                    </Link>
                </div>

                <div className="space-y-3">
                    {(tickets.data ?? []).map(
                        (ticket) => (
                            <Link
                                key={ticket.id}
                                href={`/v2/support/${ticket.id}`}
                                className="block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-violet-300"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div className="text-xs font-semibold text-violet-600">
                                            {ticket.ticket_number}
                                        </div>

                                        <h2 className="mt-1 font-semibold text-slate-900">
                                            {ticket.subject}
                                        </h2>

                                        <div className="mt-2 text-sm text-slate-500">
                                            {ticket.category} •{' '}
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
