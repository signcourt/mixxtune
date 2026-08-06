import {
    Head,
    useForm,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Show({
    role = 'artist',
    ticket,
}) {
    const {
        data,
        setData,
        post,
        processing,
        reset,
    } = useForm({
        message: '',
        attachments: [],
    });

    const submit = (event) => {
        event.preventDefault();

        post(
            `/v2/support/${ticket.id}/reply`,
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () =>
                    reset(),
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title={ticket.ticket_number}
            subtitle={ticket.subject}
        >
            <Head title={ticket.subject} />

            <div className="mx-auto max-w-5xl space-y-5">
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap justify-between gap-3">
                        <div>
                            <div className="text-sm text-slate-500">
                                {ticket.category} •{' '}
                                {ticket.priority}
                            </div>
                        </div>

                        <span className="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold capitalize text-violet-700">
                            {ticket.status.replaceAll(
                                '_',
                                ' '
                            )}
                        </span>
                    </div>
                </div>

                <div className="space-y-3">
                    {(ticket.messages ?? [])
                        .filter(
                            (message) =>
                                !message.is_internal
                        )
                        .map((message) => (
                            <div
                                key={message.id}
                                className={[
                                    'rounded-2xl border p-5 shadow-sm',
                                    message.user_id ===
                                    ticket.user_id
                                        ? 'border-violet-200 bg-violet-50'
                                        : 'border-slate-200 bg-white',
                                ].join(' ')}
                            >
                                <div className="text-sm font-semibold text-slate-900">
                                    {message.user?.name}
                                </div>

                                <div className="mt-3 whitespace-pre-wrap text-sm text-slate-700">
                                    {message.message}
                                </div>
                            </div>
                        ))}
                </div>

                {ticket.status !== 'closed' && (
                    <form
                        onSubmit={submit}
                        className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <textarea
                            value={data.message}
                            onChange={(event) =>
                                setData(
                                    'message',
                                    event.target.value
                                )
                            }
                            placeholder="Write your reply..."
                            className="min-h-32 w-full rounded-xl border border-slate-300 p-4"
                        />

                        <input
                            type="file"
                            multiple
                            onChange={(event) =>
                                setData(
                                    'attachments',
                                    Array.from(
                                        event.target.files ??
                                            []
                                    )
                                )
                            }
                            className="mt-3 block w-full rounded-xl border border-slate-300 p-3"
                        />

                        <button
                            type="submit"
                            disabled={processing}
                            className="mt-4 rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            Send Reply
                        </button>
                    </form>
                )}
            </div>
        </PanelLayout>
    );
}
