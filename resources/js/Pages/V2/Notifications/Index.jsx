import {
    Head,
    router,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'artist',
    notifications = {},
    unreadCount = 0,
}) {
    return (
        <PanelLayout
            role={role}
            title="Notifications"
            subtitle={`${unreadCount} unread notifications`}
        >
            <Head title="Notifications" />

            <div className="space-y-4">
                <div className="flex justify-end">
                    <button
                        type="button"
                        onClick={() =>
                            router.post(
                                '/v2/notifications/read-all'
                            )
                        }
                        className="rounded-xl border border-violet-300 bg-white px-4 py-3 text-sm font-semibold text-violet-700"
                    >
                        Mark All Read
                    </button>
                </div>

                {(notifications.data ?? []).map(
                    (item) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() =>
                                router.post(
                                    `/v2/notifications/${item.id}/read`
                                )
                            }
                            className={[
                                'block w-full rounded-2xl border p-5 text-left shadow-sm',
                                item.read_at
                                    ? 'border-slate-200 bg-white'
                                    : 'border-violet-300 bg-violet-50',
                            ].join(' ')}
                        >
                            <div className="font-semibold text-slate-900">
                                {item.title}
                            </div>

                            <div className="mt-2 text-sm text-slate-600">
                                {item.message}
                            </div>

                            <div className="mt-3 text-xs text-slate-400">
                                {item.created_at}
                            </div>
                        </button>
                    )
                )}
            </div>
        </PanelLayout>
    );
}
