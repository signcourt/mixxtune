import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function DeliveryStatus({
    releases,
    filters = {},
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters = (nextStatus = filters.status ?? '') => {
        router.get(
            '/delivery-status',
            {
                search: search || undefined,
                status: nextStatus || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const submitSearch = (event) => {
        event.preventDefault();
        applyFilters();
    };

    return (
        <AdminLayout title="Delivery Status">
            <Head title="Delivery Status" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">
                        DSP Delivery Status
                    </h1>

                    <p className="mt-1 text-sm text-slate-500">
                        Track approved releases across all digital stores.
                    </p>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-col gap-4">
                        <div className="flex flex-wrap gap-2">
                            {[
                                ['', 'All'],
                                ['pending', 'Pending'],
                                ['processing', 'Processing'],
                                ['delivered', 'Delivered'],
                                ['live', 'Live'],
                                ['failed', 'Failed'],
                                ['takedown', 'Takedown'],
                            ].map(([value, label]) => (
                                <button
                                    key={label}
                                    type="button"
                                    onClick={() =>
                                        applyFilters(value)
                                    }
                                    className={`rounded-xl px-4 py-2 text-sm font-semibold ${
                                        filters.status === value
                                            ? 'bg-slate-900 text-white'
                                            : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                                    }`}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>

                        <form
                            onSubmit={submitSearch}
                            className="flex flex-col gap-3 sm:flex-row"
                        >
                            <input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search title, artist, catalogue or UPC..."
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            />

                            <button
                                type="submit"
                                className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white"
                            >
                                Search
                            </button>
                        </form>
                    </div>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {(releases?.data ?? []).length === 0 ? (
                        <div className="px-6 py-16 text-center">
                            <div className="text-4xl">♫</div>

                            <h3 className="mt-4 text-lg font-semibold text-slate-900">
                                No approved releases found
                            </h3>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {releases.data.map((release) => (
                                <div
                                    key={release.id}
                                    className="grid gap-4 px-6 py-5 lg:grid-cols-[minmax(220px,1.5fr)_minmax(180px,1fr)_150px_140px_auto] lg:items-center"
                                >
                                    <div>
                                        <div className="font-semibold text-slate-900">
                                            {release.title}
                                        </div>

                                        <div className="mt-1 text-sm text-slate-500">
                                            {release.primary_artist_name}
                                        </div>
                                    </div>


                                    <div className="text-sm text-slate-600">
                                        {release.store_deliveries_count} DSPs
                                    </div>

                                    <div>
                                        <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                            Approved
                                        </span>
                                    </div>

                                    <div className="lg:text-right">
                                        <Link
                                            href={`/delivery-status/${release.id}`}
                                            className="inline-flex rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
                                        >
                                            Manage Delivery
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    <Pagination links={releases?.links ?? []} />
                </div>
            </div>
        </AdminLayout>
    );
}

function Pagination({ links = [] }) {
    if (!Array.isArray(links) || links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap justify-center gap-2 border-t border-slate-200 px-5 py-5">
            {links.map((link, index) => (
                <button
                    key={index}
                    type="button"
                    disabled={!link.url}
                    onClick={() =>
                        link.url &&
                        router.get(
                            link.url,
                            {},
                            {
                                preserveState: true,
                                preserveScroll: true,
                            }
                        )
                    }
                    className={`rounded-lg border px-3 py-2 text-sm ${
                        link.active
                            ? 'bg-slate-900 text-white'
                            : 'bg-white text-slate-700'
                    } disabled:opacity-40`}
                    dangerouslySetInnerHTML={{
                        __html: link.label,
                    }}
                />
            ))}
        </div>
    );
}
