import { Head, router } from '@inertiajs/react';
import {
    BadgeIndianRupee,
    Download,
    Landmark,
    Percent,
    TrendingUp,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const money = (value) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

const number = (value) =>
    Number(value ?? 0).toLocaleString('en-IN');

const rate = (value) =>
    `${Number(value ?? 0).toLocaleString('en-IN', {
        maximumFractionDigits: 2,
    })}%`;

export default function ProfitIndex({
    summary = {},
    breakdown = [],
    filters = {},
    months = [],
    platforms = [],
    owners = [],
}) {
    const [form, setForm] = useState({
        month: filters.month || '',
        platform: filters.platform || '',
        owner_type: filters.owner_type || '',
        owner_id: filters.owner_id || '',
    });

    const visibleOwners = owners.filter(
        (owner) =>
            !form.owner_type ||
            owner.type === form.owner_type
    );

    const applyFilters = (event) => {
        event.preventDefault();

        router.get(
            '/v2/admin/profit',
            form,
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const clearFilters = () => {
        const empty = {
            month: '',
            platform: '',
            owner_type: '',
            owner_id: '',
        };

        setForm(empty);

        router.get(
            '/v2/admin/profit',
            empty,
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const exportUrl = () => {
        const params = new URLSearchParams();

        Object.entries(form).forEach(
            ([key, value]) => {
                if (value) {
                    params.set(key, value);
                }
            }
        );

        const query = params.toString();

        return query
            ? `/v2/admin/profit/export?${query}`
            : '/v2/admin/profit/export';
    };

    const cards = [
        {
            title: 'Collected Revenue',
            value: money(
                summary.collected_revenue
            ),
            note: `${number(
                summary.rows
            )} report rows`,
            icon: BadgeIndianRupee,
        },
        {
            title: 'User Earning',
            value: money(
                summary.user_earning
            ),
            note: 'Label + Artist entitlement',
            icon: Users,
        },
        {
            title: 'Super Admin Profit',
            value: money(
                summary.super_admin_profit
            ),
            note: 'Retained business revenue',
            icon: Landmark,
        },
        {
            title: 'Negative Adjustments',
            value: money(
                summary.negative_adjustments
            ),
            note: 'Charged 100% to account',
            icon: TrendingUp,
        },
    ];

    return (
        <PanelLayout
            role="super_admin"
            title="Business Profit"
            subtitle="Revenue retained after Label and Artist earnings"
        >
            <Head title="Super Admin Profit" />

            <div className="space-y-6">
                <section className="rounded-3xl border border-slate-800 bg-slate-950/70 p-6 shadow-xl">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div className="text-xs font-bold uppercase tracking-[0.24em] text-emerald-400">
                                Super Admin Finance
                            </div>

                            <h1 className="mt-2 text-2xl font-bold text-white sm:text-3xl">
                                Business Profit
                            </h1>

                            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-400">
                                Track collected revenue,
                                Label/Artist earnings and
                                the revenue retained by
                                Mixx Tune.
                            </p>
                        </div>

                        <a
                            href={exportUrl()}
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400"
                        >
                            <Download size={17} />
                            Export CSV
                        </a>
                    </div>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map((card) => {
                        const Icon = card.icon;

                        return (
                            <div
                                key={card.title}
                                className="rounded-2xl border border-slate-800 bg-slate-950/70 p-5"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                            {card.title}
                                        </p>

                                        <p className="mt-3 text-2xl font-bold text-white">
                                            {card.value}
                                        </p>

                                        <p className="mt-2 text-xs text-slate-500">
                                            {card.note}
                                        </p>
                                    </div>

                                    <div className="rounded-xl bg-slate-900 p-3 text-emerald-400">
                                        <Icon size={20} />
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </section>

                <section className="rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
                    <div className="mb-5">
                        <h2 className="text-lg font-bold text-white">
                            Filters
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Filter profit by reporting
                            month, DSP, account type and
                            individual account.
                        </p>
                    </div>

                    <form
                        onSubmit={applyFilters}
                        className="grid gap-4 lg:grid-cols-5"
                    >
                        <select
                            value={form.month}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    month:
                                        event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white"
                        >
                            <option value="">
                                All Months
                            </option>

                            {months.map((month) => (
                                <option
                                    key={month}
                                    value={month}
                                >
                                    {month}
                                </option>
                            ))}
                        </select>

                        <select
                            value={form.platform}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    platform:
                                        event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white"
                        >
                            <option value="">
                                All Platforms
                            </option>

                            {platforms.map(
                                (platform) => (
                                    <option
                                        key={platform}
                                        value={platform}
                                    >
                                        {platform}
                                    </option>
                                )
                            )}
                        </select>

                        <select
                            value={form.owner_type}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    owner_type:
                                        event.target.value,
                                    owner_id: '',
                                })
                            }
                            className="rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white"
                        >
                            <option value="">
                                Labels + Artists
                            </option>
                            <option value="label">
                                Labels Only
                            </option>
                            <option value="artist">
                                Artists Only
                            </option>
                        </select>

                        <select
                            value={form.owner_id}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    owner_id:
                                        event.target.value,
                                })
                            }
                            disabled={
                                !form.owner_type
                            }
                            className="rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="">
                                {form.owner_type
                                    ? 'All Accounts'
                                    : 'Select Account Type'}
                            </option>

                            {visibleOwners.map(
                                (owner) => (
                                    <option
                                        key={`${owner.type}-${owner.id}`}
                                        value={owner.id}
                                    >
                                        {owner.name}
                                    </option>
                                )
                            )}
                        </select>

                        <div className="flex gap-2">
                            <button
                                type="submit"
                                className="flex-1 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-950"
                            >
                                Apply
                            </button>

                            <button
                                type="button"
                                onClick={clearFilters}
                                className="rounded-xl border border-slate-700 px-4 py-3 text-sm font-semibold text-slate-300"
                            >
                                Clear
                            </button>
                        </div>
                    </form>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/70">
                    <div className="border-b border-slate-800 p-5">
                        <h2 className="text-lg font-bold text-white">
                            Label & Artist Profit Breakdown
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Negative report entries use an
                            effective 100% user rate and
                            generate no Super Admin profit.
                        </p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-900/80 text-left text-xs uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-5 py-4">
                                        Account
                                    </th>
                                    <th className="px-5 py-4">
                                        Type
                                    </th>
                                    <th className="px-5 py-4 text-right">
                                        Assigned Rate
                                    </th>
                                    <th className="px-5 py-4 text-right">
                                        Collected Revenue
                                    </th>
                                    <th className="px-5 py-4 text-right">
                                        User Earning
                                    </th>
                                    <th className="px-5 py-4 text-right">
                                        Profit
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-800">
                                {breakdown.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-5 py-12 text-center text-slate-500"
                                        >
                                            No mapped revenue
                                            found for these
                                            filters.
                                        </td>
                                    </tr>
                                )}

                                {breakdown.map(
                                    (item) => (
                                        <tr
                                            key={`${item.type}-${item.id}`}
                                            className="text-slate-300"
                                        >
                                            <td className="px-5 py-4 font-semibold text-white">
                                                {item.name}
                                            </td>

                                            <td className="px-5 py-4 capitalize">
                                                {item.type}
                                            </td>

                                            <td className="px-5 py-4 text-right">
                                                {item.rate_is_mixed ? (
                                                    <span className="inline-flex rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-300">
                                                        Mixed
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1">
                                                        <Percent
                                                            size={
                                                                13
                                                            }
                                                        />
                                                        {rate(
                                                            item.assigned_rate
                                                        )}
                                                    </span>
                                                )}
                                            </td>

                                            <td className="px-5 py-4 text-right">
                                                {money(
                                                    item.collected_revenue
                                                )}
                                            </td>

                                            <td className="px-5 py-4 text-right">
                                                {money(
                                                    item.user_earning
                                                )}
                                            </td>

                                            <td className="px-5 py-4 text-right font-bold text-emerald-400">
                                                {money(
                                                    item.super_admin_profit
                                                )}
                                            </td>
                                        </tr>
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </PanelLayout>
    );
}
