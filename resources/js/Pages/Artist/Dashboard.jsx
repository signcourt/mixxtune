import ArtistLayout from '@/Layouts/ArtistLayout';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { auth } = usePage().props;
    const user = auth?.user;

    const stats = [
        {
            title: 'Total Releases',
            value: '0',
            note: 'No release submitted',
            icon: '♫',
        },
        {
            title: 'Live Releases',
            value: '0',
            note: 'Available on stores',
            icon: '◉',
        },
        {
            title: 'Wallet Balance',
            value: '₹0.00',
            note: 'Available balance',
            icon: '₹',
        },
        {
            title: 'Pending Withdrawals',
            value: '0',
            note: 'No pending request',
            icon: '↗',
        },
    ];

    return (
        <ArtistLayout
            title="Dashboard"
            subtitle={`Welcome back, ${user?.name || 'Artist'}`}
        >
            <Head title="Artist Dashboard" />

            <section className="overflow-hidden rounded-3xl bg-[#0d1526] px-7 py-8 text-white shadow-xl lg:px-10">
                <div className="flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
                    <div>
                        <p className="text-sm font-medium text-slate-400">
                            ARTIST OVERVIEW
                        </p>

                        <h2 className="mt-2 text-3xl font-bold lg:text-4xl">
                            Hello, {user?.name || 'Artist'}
                        </h2>

                        <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                            Upload releases, manage your catalogue and view
                            monthly royalty reports from one place.
                        </p>
                    </div>

                    <Link
                        href="/releases/create"
                        className="w-fit rounded-xl bg-white px-6 py-3 text-sm font-semibold text-[#0d1526]"
                    >
                        + Create Release
                    </Link>
                </div>
            </section>

            <section className="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                {stats.map((stat) => (
                    <article
                        key={stat.title}
                        className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div className="flex items-start justify-between">
                            <div>
                                <p className="text-sm font-medium text-slate-500">
                                    {stat.title}
                                </p>

                                <p className="mt-3 text-3xl font-bold text-slate-900">
                                    {stat.value}
                                </p>

                                <p className="mt-3 text-xs text-slate-400">
                                    {stat.note}
                                </p>
                            </div>

                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-lg font-bold text-[#0d1526]">
                                {stat.icon}
                            </div>
                        </div>
                    </article>
                ))}
            </section>

            <section className="mt-6 grid gap-6 xl:grid-cols-[1.6fr_1fr]">
                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <h3 className="text-lg font-bold text-slate-900">
                                Recent Releases
                            </h3>

                            <p className="mt-1 text-sm text-slate-500">
                                Your latest music submissions.
                            </p>
                        </div>

                        <Link
                            href="/releases"
                            className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700"
                        >
                            View all
                        </Link>
                    </div>

                    <div className="mt-6 flex min-h-64 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-2xl shadow-sm">
                            ♫
                        </div>

                        <h4 className="mt-4 font-semibold text-slate-900">
                            No releases yet
                        </h4>

                        <p className="mt-2 text-sm text-slate-500">
                            Submit your first release to start distribution.
                        </p>

                        <Link
                            href="/releases/create"
                            className="mt-5 rounded-xl bg-[#0d1526] px-5 py-3 text-sm font-semibold text-white"
                        >
                            Create First Release
                        </Link>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 className="text-lg font-bold text-slate-900">
                        Quick Actions
                    </h3>

                    <p className="mt-1 text-sm text-slate-500">
                        Common artist tasks.
                    </p>

                    <div className="mt-6 space-y-3">
                        {[
                            ['Create New Release', '/releases/create'],
                            ['View Catalogue', '/catalogue'],
                            ['Monthly Royalties', '/royalties'],
                            ['Request Withdrawal', '/withdrawals'],
                            ['Profile & KYC', '/profile'],
                        ].map(([label, href]) => (
                            <Link
                                key={label}
                                href={href}
                                className="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                <span>{label}</span>
                                <span className="text-slate-400">›</span>
                            </Link>
                        ))}
                    </div>
                </div>
            </section>

            <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h3 className="text-lg font-bold text-slate-900">
                            Monthly Royalty Reports
                        </h3>

                        <p className="mt-1 text-sm text-slate-500">
                            Reports will appear here according to their report
                            month, even if uploaded later.
                        </p>
                    </div>

                    <Link
                        href="/royalties"
                        className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700"
                    >
                        View Reports
                    </Link>
                </div>

                <div className="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <p className="font-semibold text-slate-800">
                        No monthly royalty report available
                    </p>

                    <p className="mt-2 text-sm text-slate-500">
                        When the admin uploads and publishes a monthly report,
                        its data will be shown under that report month.
                    </p>
                </div>
            </section>
        </ArtistLayout>
    );
}
