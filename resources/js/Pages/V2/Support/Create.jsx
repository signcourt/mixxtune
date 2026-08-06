import {
    Head,
    useForm,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Create({
    role = 'artist',
}) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        subject: '',
        category: 'general',
        priority: 'normal',
        message: '',
        attachments: [],
    });

    const submit = (event) => {
        event.preventDefault();

        post('/v2/support', {
            forceFormData: true,
        });
    };

    return (
        <PanelLayout
            role={role}
            title="New Support Ticket"
            subtitle="Describe the issue clearly"
        >
            <Head title="New Support Ticket" />

            <form
                onSubmit={submit}
                className="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            >
                <div className="grid gap-5 md:grid-cols-2">
                    <label className="md:col-span-2">
                        <span className="text-sm font-semibold text-slate-700">
                            Subject
                        </span>

                        <input
                            value={data.subject}
                            onChange={(event) =>
                                setData(
                                    'subject',
                                    event.target.value
                                )
                            }
                            className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"
                        />

                        {errors.subject && (
                            <div className="mt-1 text-sm text-red-600">
                                {errors.subject}
                            </div>
                        )}
                    </label>

                    <label>
                        <span className="text-sm font-semibold text-slate-700">
                            Category
                        </span>

                        <select
                            value={data.category}
                            onChange={(event) =>
                                setData(
                                    'category',
                                    event.target.value
                                )
                            }
                            className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"
                        >
                            {[
                                'release',
                                'report',
                                'royalty',
                                'wallet',
                                'withdrawal',
                                'kyc',
                                'technical',
                                'copyright',
                                'general',
                            ].map((item) => (
                                <option
                                    key={item}
                                    value={item}
                                >
                                    {item}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label>
                        <span className="text-sm font-semibold text-slate-700">
                            Priority
                        </span>

                        <select
                            value={data.priority}
                            onChange={(event) =>
                                setData(
                                    'priority',
                                    event.target.value
                                )
                            }
                            className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"
                        >
                            <option value="low">
                                Low
                            </option>

                            <option value="normal">
                                Normal
                            </option>

                            <option value="high">
                                High
                            </option>

                            <option value="urgent">
                                Urgent
                            </option>
                        </select>
                    </label>

                    <label className="md:col-span-2">
                        <span className="text-sm font-semibold text-slate-700">
                            Message
                        </span>

                        <textarea
                            value={data.message}
                            onChange={(event) =>
                                setData(
                                    'message',
                                    event.target.value
                                )
                            }
                            className="mt-2 min-h-48 w-full rounded-xl border border-slate-300 p-4"
                        />

                        {errors.message && (
                            <div className="mt-1 text-sm text-red-600">
                                {errors.message}
                            </div>
                        )}
                    </label>

                    <label className="md:col-span-2">
                        <span className="text-sm font-semibold text-slate-700">
                            Attachments
                        </span>

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
                            className="mt-2 block w-full rounded-xl border border-slate-300 p-3"
                        />
                    </label>
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="mt-6 rounded-xl bg-violet-600 px-6 py-3 text-sm font-semibold text-white disabled:opacity-50"
                >
                    {processing
                        ? 'Creating...'
                        : 'Create Ticket'}
                </button>
            </form>
        </PanelLayout>
    );
}
