import { Head, useForm, usePage } from "@inertiajs/react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

const targetOptions = [
    {
        value: "all",
        label: "All Active Users",
    },
    {
        value: "role",
        label: "User Role",
    },
    {
        value: "country",
        label: "Country",
    },
    {
        value: "state",
        label: "State",
    },
    {
        value: "client_id",
        label: "Specific Client ID",
    },
];

const roles = [
    {
        value: "artist",
        label: "Artists",
    },
    {
        value: "label",
        label: "Labels",
    },
    {
        value: "admin",
        label: "Admins",
    },
    {
        value: "super_admin",
        label: "Super Admins",
    },
];

export default function Index({
    countries = [],
    states = [],
    recentBroadcasts = [],
}) {
    const page = usePage();

    const flash = page.props?.flash ?? {};

    const { data, setData, post, processing, errors, reset } = useForm({
        title: "",
        message: "",
        severity: "info",
        category: "primary",
        action_url: "",
        target_type: "all",
        target_value: "",
    });

    const changeTarget = (value) => {
        setData((current) => ({
            ...current,
            target_type: value,
            target_value: "",
        }));
    };

    const submit = (event) => {
        event.preventDefault();

        post("/v2/admin/notification-management", {
            preserveScroll: true,

            onSuccess: () => {
                reset("title", "message", "action_url");
            },
        });
    };

    const renderTargetValue = () => {
        if (data.target_type === "all") {
            return null;
        }

        if (data.target_type === "role") {
            return (
                <select
                    value={data.target_value}
                    onChange={(event) =>
                        setData("target_value", event.target.value)
                    }
                    className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                >
                    <option value="">Select role</option>

                    {roles.map((role) => (
                        <option key={role.value} value={role.value}>
                            {role.label}
                        </option>
                    ))}
                </select>
            );
        }

        if (data.target_type === "country") {
            return (
                <select
                    value={data.target_value}
                    onChange={(event) =>
                        setData("target_value", event.target.value)
                    }
                    className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                >
                    <option value="">Select country</option>

                    {countries.map((country) => (
                        <option key={country} value={country}>
                            {country}
                        </option>
                    ))}
                </select>
            );
        }

        if (data.target_type === "state") {
            return (
                <select
                    value={data.target_value}
                    onChange={(event) =>
                        setData("target_value", event.target.value)
                    }
                    className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                >
                    <option value="">Select state</option>

                    {states.map((state) => (
                        <option key={state} value={state}>
                            {state}
                        </option>
                    ))}
                </select>
            );
        }

        return (
            <input
                type="text"
                value={data.target_value}
                onChange={(event) =>
                    setData("target_value", event.target.value)
                }
                placeholder="Example: MTINDLL26080700001"
                className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
            />
        );
    };

    return (
        <PanelLayout
            role="super_admin"
            title="Notification Center"
            subtitle="Send targeted panel notifications"
        >
            <Head title="Notification Center" />

            <div className="space-y-6">
                {flash.success && (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-700">
                        {flash.success}
                    </div>
                )}

                <div className="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                    <form
                        onSubmit={submit}
                        className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div>
                            <div className="text-lg font-bold text-slate-900">
                                Create Notification
                            </div>

                            <div className="mt-1 text-sm text-slate-500">
                                Send a notification to selected Mixx Tune
                                accounts.
                            </div>
                        </div>

                        <div className="mt-6 grid gap-5">
                            <div>
                                <label className="text-sm font-semibold text-slate-700">
                                    Notification Category
                                </label>

                                <div className="mt-2 grid grid-cols-3 gap-2">
                                    {[
                                        {
                                            value: "primary",
                                            label: "Primary",
                                        },
                                        {
                                            value: "announcement",
                                            label: "Announcement",
                                        },
                                        {
                                            value: "promotion",
                                            label: "Promotion",
                                        },
                                    ].map((category) => (
                                        <button
                                            key={category.value}
                                            type="button"
                                            onClick={() =>
                                                setData(
                                                    "category",
                                                    category.value,
                                                )
                                            }
                                            className={[
                                                "rounded-xl border px-3 py-3 text-sm font-semibold transition",
                                                data.category === category.value
                                                    ? "border-violet-600 bg-violet-600 text-white"
                                                    : "border-slate-200 bg-white text-slate-600 hover:border-violet-300 hover:bg-violet-50",
                                            ].join(" ")}
                                        >
                                            {category.label}
                                        </button>
                                    ))}
                                </div>

                                {errors.category && (
                                    <div className="mt-1 text-xs text-red-600">
                                        {errors.category}
                                    </div>
                                )}
                            </div>

                            <div>
                                <label className="text-sm font-semibold text-slate-700">
                                    Notification Title
                                </label>

                                <input
                                    type="text"
                                    value={data.title}
                                    onChange={(event) =>
                                        setData("title", event.target.value)
                                    }
                                    placeholder="Enter notification title"
                                    className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                                />

                                {errors.title && (
                                    <div className="mt-1 text-xs text-red-600">
                                        {errors.title}
                                    </div>
                                )}
                            </div>

                            <div>
                                <label className="text-sm font-semibold text-slate-700">
                                    Message
                                </label>

                                <textarea
                                    rows="6"
                                    value={data.message}
                                    onChange={(event) =>
                                        setData("message", event.target.value)
                                    }
                                    placeholder="Write your message..."
                                    className="mt-2 w-full resize-none rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                                />

                                {errors.message && (
                                    <div className="mt-1 text-xs text-red-600">
                                        {errors.message}
                                    </div>
                                )}
                            </div>

                            <div className="grid gap-5 md:grid-cols-2">
                                <div>
                                    <label className="text-sm font-semibold text-slate-700">
                                        Type
                                    </label>

                                    <select
                                        value={data.severity}
                                        onChange={(event) =>
                                            setData(
                                                "severity",
                                                event.target.value,
                                            )
                                        }
                                        className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                                    >
                                        <option value="info">
                                            Information
                                        </option>

                                        <option value="success">Success</option>

                                        <option value="warning">Warning</option>

                                        <option value="danger">
                                            Important
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label className="text-sm font-semibold text-slate-700">
                                        Target
                                    </label>

                                    <select
                                        value={data.target_type}
                                        onChange={(event) =>
                                            changeTarget(event.target.value)
                                        }
                                        className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                                    >
                                        {targetOptions.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>

                                    {renderTargetValue()}

                                    {errors.target_value && (
                                        <div className="mt-1 text-xs text-red-600">
                                            {errors.target_value}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div>
                                <label className="text-sm font-semibold text-slate-700">
                                    Action URL
                                    <span className="ml-1 font-normal text-slate-400">
                                        Optional
                                    </span>
                                </label>

                                <input
                                    type="text"
                                    value={data.action_url}
                                    onChange={(event) =>
                                        setData(
                                            "action_url",
                                            event.target.value,
                                        )
                                    }
                                    placeholder="/artist/releases"
                                    className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex min-h-12 items-center justify-center rounded-xl bg-violet-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {processing
                                    ? "Sending..."
                                    : "Send Notification"}
                            </button>
                        </div>
                    </form>

                    <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="text-lg font-bold text-slate-900">
                            Targeting
                        </div>

                        <div className="mt-1 text-sm text-slate-500">
                            Available notification audiences
                        </div>

                        <div className="mt-5 space-y-3">
                            {targetOptions.map((option) => (
                                <div
                                    key={option.value}
                                    className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4"
                                >
                                    <div className="text-sm font-semibold text-slate-900">
                                        {option.label}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-100 px-6 py-5">
                        <div className="text-lg font-bold text-slate-900">
                            Recent Broadcasts
                        </div>

                        <div className="mt-1 text-sm text-slate-500">
                            Recently sent manual notifications
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200">
                            <thead className="bg-slate-50">
                                <tr>
                                    {[
                                        "Title",
                                        "Target",
                                        "Recipients",
                                        "Type",
                                        "Sent",
                                    ].map((heading) => (
                                        <th
                                            key={heading}
                                            className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {recentBroadcasts.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="5"
                                            className="px-6 py-10 text-center text-sm text-slate-500"
                                        >
                                            No manual notifications sent yet.
                                        </td>
                                    </tr>
                                )}

                                {recentBroadcasts.map((item) => (
                                    <tr key={item.broadcast_id}>
                                        <td className="px-6 py-4">
                                            <div className="font-semibold text-slate-900">
                                                {item.title}
                                            </div>

                                            <div className="mt-1 max-w-md truncate text-xs text-slate-500">
                                                {item.message}
                                            </div>
                                        </td>

                                        <td className="px-6 py-4 text-sm text-slate-600">
                                            {item.target_type}

                                            {item.target_value
                                                ? `: ${item.target_value}`
                                                : ""}
                                        </td>

                                        <td className="px-6 py-4 text-sm font-semibold text-slate-700">
                                            {item.recipients}
                                        </td>

                                        <td className="px-6 py-4">
                                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold capitalize text-slate-700">
                                                {item.severity}
                                            </span>
                                        </td>

                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-500">
                                            {item.created_at}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </PanelLayout>
    );
}
