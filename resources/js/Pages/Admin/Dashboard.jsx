import AdminLayout from '@/Layouts/AdminLayout';
import { Head } from '@inertiajs/react';

const stats = [
    {
        label: 'Total Artists',
        value: '0',
        change: '+0 this month',
        icon: '◉',
    },
    {
        label: 'Total Releases',
        value: '0',
        change: '0 pending review',
        icon: '♫',
    },
    {
        label: 'Wallet Balance',
        value: '₹0',
        change: 'Available balance',
        icon: '₹',
    },
    {
        label: 'Pending Withdrawals',
        value: '0',
        change: '₹0 requested',
        icon: '↗',
    },
];

export default function AdminDashboard() {
    return (
        <AdminLayout title="Dashboard">
            <Head title="Admin Dashboard" />

            <section className="mb-8 overflow-hidden rounded-3xl bg-[#0d1526] px-7 py-8 text-white shadow-xl lg:px-10">
                <div className="flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
                    <div>
                        <p className="mb-2 text-sm font-medium text-slate-400">
                            ADMINISTRATION OVERVIEW
                        </p>

                        <h2 className="text-3xl font-bold lg:text-4xl">
                            Welcome back, Admin
                        </h2>

                        <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                            Review artists, approve releases, manage royalties and monitor
                            platform activity from one place.
                        </p>
                    </div>

                    <button
                        type="button"
                        className="w-fit rounded-xl bg-white px-6 py-3 text-sm font-semibold text-[#0d1526] transition hover:bg-slate-100"
                    >
                        + Create Release
                    </button>
                </div>
            </section>

            <section className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                {stats.map((stat) => (
                    <article
                        key={stat.label}
                        className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <div className="flex items-start justify-between">
                            <div>
                                <p className="text-sm font-medium text-slate-500">
                                    {stat.label}
                                </p>

                                <p className="mt-3 text-3xl font-bold text-slate-900">
                                    {stat.value}
                                </p>
                            </div>

                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-lg font-bold text-[#0d1526]">
                                {stat.icon}
                            </div>
                        </div>

                        <p className="mt-5 text-xs font-medium text-slate-400">
                            {stat.change}
                        </p>
                    </article>
                ))}
            </section>

            <section className="mt-6 grid gap-6 xl:grid-cols-[1.5fr_1fr]">
                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-lg font-bold text-slate-900">
                                Recent Releases
                            </h3>
                            <p className="mt-1 text-sm text-slate-500">
                                Latest submissions from artists
                            </p>
                        </div>

                        <button
                            type="button"
                            className="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600"
                        >
                            View all
                        </button>
                    </div>

                    <div className="mt-8 flex min-h-64 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 text-center">
                        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-2xl shadow-sm">
                            ♫
                        </div>

                        <h4 className="mt-4 font-semibold text-slate-800">
                            No releases submitted
                        </h4>

                        <p className="mt-1 text-sm text-slate-500">
                            New release submissions will appear here.
                        </p>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 className="text-lg font-bold text-slate-900">
                        Quick Actions
                    </h3>

                    <p className="mt-1 text-sm text-slate-500">
                        Common administration tasks
                    </p>

                    <div className="mt-6 space-y-3">
                        {[
                            ['Add New Artist', '◉'],
                            ['Review Releases', '♫'],
                            ['Upload Royalty Report', '▥'],
                            ['Process Withdrawal', '↗'],
                            ['Open Support Tickets', '?'],
                        ].map(([label, icon]) => (
                            <button
                                key={label}
                                type="button"
                                className="flex w-full items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-left text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                            >
                                <span className="flex items-center">
                                    <span className="mr-3 flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100">
                                        {icon}
                                    </span>
                                    {label}
                                </span>

                                <span className="text-slate-400">›</span>
                            </button>
                        ))}
                    </div>
                </div>
            </section>

            <section className="mt-6 grid gap-6 lg:grid-cols-3">
                {[
                    ['Pending KYC', '0', 'Artist profiles awaiting verification'],
                    ['Open Tickets', '0', 'Support requests requiring attention'],
                    ['Monthly Revenue', '₹0', 'Revenue recorded this month'],
                ].map(([title, value, description]) => (
                    <article
                        key={title}
                        className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <p className="text-sm font-medium text-slate-500">{title}</p>
                        <p className="mt-3 text-2xl font-bold text-slate-900">{value}</p>
                        <p className="mt-2 text-xs text-slate-400">{description}</p>
                    </article>
                ))}
            </section>
        </AdminLayout>
    );
}
