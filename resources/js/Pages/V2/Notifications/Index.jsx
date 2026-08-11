import { Head, router } from "@inertiajs/react";
import { Megaphone, Star, Tag, Trash2, UserRound } from "lucide-react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

const categories = [
    {
        key: "primary",
        label: "Primary",
        icon: UserRound,
    },
    {
        key: "announcement",
        label: "Announcement",
        icon: Megaphone,
    },
    {
        key: "promotion",
        label: "Promotion",
        icon: Tag,
    },
];

const formatDate = (value) => {
    if (!value) {
        return "";
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    const today = new Date();

    const sameDay =
        date.getFullYear() === today.getFullYear() &&
        date.getMonth() === today.getMonth() &&
        date.getDate() === today.getDate();

    if (sameDay) {
        return new Intl.DateTimeFormat("en-IN", {
            hour: "2-digit",
            minute: "2-digit",
            hour12: true,
        }).format(date);
    }

    return new Intl.DateTimeFormat("en-IN", {
        day: "2-digit",
        month: "short",
    }).format(date);
};

export default function Index({
    role = "artist",
    notifications = {},
    unreadCount = 0,
    categoryCounts = {},
    activeCategory = "primary",
}) {
    const items = notifications.data ?? [];

    const changeCategory = (category) => {
        router.get(
            "/v2/notifications",
            {
                category,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const openNotification = (item) => {
        router.patch(
            `/v2/notifications/${item.id}/read`,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const toggleStar = (event, item) => {
        event.stopPropagation();

        router.patch(
            `/v2/notifications/${item.id}/star`,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const removeNotification = (event, item) => {
        event.stopPropagation();

        router.delete(`/v2/notifications/${item.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <PanelLayout
            role={role}
            title="Notifications"
            subtitle={`${unreadCount} unread notifications`}
        >
            <Head title="Notifications" />

            <div className="w-full">
                <div className="overflow-x-auto border-b border-slate-200 bg-white">
                    <div className="flex min-w-[580px]">
                        {categories.map((category) => {
                            const Icon = category.icon;

                            const active = activeCategory === category.key;

                            return (
                                <button
                                    key={category.key}
                                    type="button"
                                    onClick={() => changeCategory(category.key)}
                                    className={[
                                        "relative flex min-w-[190px] flex-1 items-center gap-3 px-6 py-4 text-left text-sm font-semibold transition",
                                        active
                                            ? "text-violet-700"
                                            : "text-slate-500 hover:bg-slate-50 hover:text-slate-800",
                                    ].join(" ")}
                                >
                                    <Icon
                                        size={18}
                                        strokeWidth={active ? 2.2 : 1.8}
                                    />

                                    <span>{category.label}</span>

                                    {(categoryCounts[category.key] ?? 0) >
                                        0 && (
                                        <span className="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">
                                            {categoryCounts[category.key] ?? 0}
                                        </span>
                                    )}

                                    {active && (
                                        <span className="absolute inset-x-0 bottom-0 h-0.5 bg-violet-600" />
                                    )}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div className="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-2.5 sm:px-5">
                    <div className="text-xs font-medium text-slate-500">
                        {items.length} notifications
                    </div>

                    {unreadCount > 0 && (
                        <button
                            type="button"
                            onClick={() =>
                                router.patch(
                                    "/v2/notifications/read-all",
                                    {},
                                    {
                                        preserveScroll: true,
                                    },
                                )
                            }
                            className="rounded-lg px-3 py-1.5 text-xs font-semibold text-violet-700 transition hover:bg-violet-50"
                        >
                            Mark all read
                        </button>
                    )}
                </div>

                {items.length === 0 ? (
                    <div className="border-b border-slate-200 bg-white px-5 py-14 text-center">
                        <div className="text-sm font-semibold text-slate-700">
                            No notifications here
                        </div>

                        <div className="mt-1 text-xs text-slate-400">
                            New notifications will appear in this section.
                        </div>
                    </div>
                ) : (
                    <div className="border-b border-slate-200 bg-white">
                        {items.map((item, index) => {
                            const unread = !item.read_at;

                            const starred = Boolean(item.starred_at);

                            return (
                                <div
                                    key={item.id}
                                    role="button"
                                    tabIndex={0}
                                    onClick={() => openNotification(item)}
                                    onKeyDown={(event) => {
                                        if (
                                            event.key === "Enter" ||
                                            event.key === " "
                                        ) {
                                            openNotification(item);
                                        }
                                    }}
                                    className={[
                                        "group grid min-h-[52px] cursor-pointer grid-cols-[30px_30px_minmax(150px,220px)_1fr_auto_38px] items-center gap-2 border-t border-slate-100 px-3 transition first:border-t-0 sm:px-4",
                                        unread
                                            ? "bg-violet-50/40 hover:bg-violet-50/70"
                                            : "bg-white hover:bg-slate-50",
                                    ].join(" ")}
                                >
                                    <button
                                        type="button"
                                        title={
                                            starred
                                                ? "Remove favorite"
                                                : "Add to favorite"
                                        }
                                        onClick={(event) =>
                                            toggleStar(event, item)
                                        }
                                        className={[
                                            "flex h-8 w-8 items-center justify-center rounded-full transition",
                                            starred
                                                ? "text-amber-400"
                                                : "text-slate-300 hover:text-amber-400",
                                        ].join(" ")}
                                    >
                                        <Star
                                            size={18}
                                            fill={
                                                starred
                                                    ? "currentColor"
                                                    : "none"
                                            }
                                        />
                                    </button>

                                    <span
                                        className={[
                                            "mx-auto h-2 w-2 rounded-full",
                                            unread
                                                ? "bg-violet-600"
                                                : "bg-transparent",
                                        ].join(" ")}
                                    />

                                    <div
                                        className={[
                                            "truncate text-[13px]",
                                            unread
                                                ? "font-semibold text-slate-900"
                                                : "font-medium text-slate-700",
                                        ].join(" ")}
                                    >
                                        {item.title}
                                    </div>

                                    <div className="min-w-0 truncate text-[13px] text-slate-500">
                                        {item.message}
                                    </div>

                                    <div
                                        className={[
                                            "whitespace-nowrap text-[11px]",
                                            unread
                                                ? "font-semibold text-slate-700"
                                                : "font-medium text-slate-400",
                                        ].join(" ")}
                                    >
                                        {formatDate(item.created_at)}
                                    </div>

                                    <button
                                        type="button"
                                        title="Delete notification"
                                        onClick={(event) =>
                                            removeNotification(event, item)
                                        }
                                        className="flex h-8 w-8 items-center justify-center rounded-full text-slate-400 opacity-0 transition hover:bg-red-50 hover:text-red-600 group-hover:opacity-100"
                                    >
                                        <Trash2 size={16} />
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </PanelLayout>
    );
}
