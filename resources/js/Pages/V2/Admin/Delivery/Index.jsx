import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import { useState } from 'react'; /* W220 */

const W220_CAN_LIVE = ['approved', 'processing', 'delivered', 'failed'];

const statusClasses = {
    processing:
        'bg-blue-100 text-blue-700',

    delivered:
        'bg-indigo-100 text-indigo-700',

    live:
        'bg-emerald-100 text-emerald-700',

    failed:
        'bg-red-100 text-red-700',

    takedown_requested:
        'bg-amber-100 text-amber-700',
};

export default function Index({
    role = 'admin',
    releases = {},
    counts = {},
    filters = {},
}) {
    const rows =
        releases.data ?? [];

    /* W220: bulk "Mark live" - tick releases, one e-mailed code for all (Super Admin) */
    const bulkEnabled = role === 'super_admin';
    const liveRows = rows.filter((r) => W220_CAN_LIVE.includes(String(r.status)));
    const [picked, setPicked] = useState([]);
    const [bulkBusy, setBulkBusy] = useState(false);
    const togglePicked = (id) => setPicked((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]));
    const allPicked = liveRows.length > 0 && liveRows.every((r) => picked.includes(r.id));
    const toggleAllPicked = () => setPicked(allPicked ? [] : liveRows.map((r) => r.id));
    const bulkLive = () => {
        if (!picked.length || bulkBusy) return;
        setBulkBusy(true);
        router.post(
            '/admin/releases/bulk-go-live',
            { ids: picked },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setBulkBusy(false),
                onSuccess: (page) => { if (!(page?.props?.errors && Object.keys(page.props.errors).length)) setPicked([]); },
            }
        );
    };

    const openStatus = (
        status
    ) => {
        router.get(
            '/admin/distribution',
            {
                status,
                search:
                    filters.search ?? '',
                per_page: filters.per_page ?? 25,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="DSP Distribution"
            subtitle="Manage store delivery and live status"
        >
            <Head title="DSP Distribution" />

            <div className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        /* W58 */
                        [
                            'approved',
                            'Approved - not started',
                        ],
                        [
                            'processing',
                            'Processing',
                        ],
                        [
                            'delivered',
                            'Delivered',
                        ],
                        [
                            'live',
                            'Live',
                        ],
                        [
                            'store_failed',
                            'A store failed',
                        ],
                        [
                            'store_takedown',
                            'Takedown asked',
                        ],
                        [
                            'all',
                            'All',
                        ],
                    ].map(
                        ([status, label]) => (
                            <button
                                key={status}
                                type="button"
                                onClick={() =>
                                    openStatus(
                                        status
                                    )
                                }
                                className={[
                                    'rounded-2xl border bg-white p-5 text-left shadow-sm transition',
                                    filters.status ===
                                    status
                                        ? 'border-violet-500 ring-2 ring-violet-100'
                                        : 'border-slate-200 hover:border-violet-300',
                                ].join(' ')}
                            >
                                <div className="text-sm text-slate-500">
                                    {label}
                                </div>

                                <div className="mt-2 text-3xl font-bold text-slate-900">
                                    {counts[
                                        status
                                    ] ?? 0}
                                </div>
                            </button>
                        )
                    )}
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-3 border-b border-slate-200 p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Distribution Queue
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Open a release to update
                                individual DSP statuses.
                            </p>
                        </div>

                        <input
                            type="search"
                            defaultValue={
                                filters.search ??
                                ''
                            }
                            placeholder="Search release, artist, UPC..."
                            onKeyDown={(
                                event
                            ) => {
                                if (
                                    event.key ===
                                    'Enter'
                                ) {
                                    router.get(
                                        '/admin/distribution',
                                        {
                                            ...filters,
                                            search:
                                                event
                                                    .currentTarget
                                                    .value,
                                        },
                                        {
                                            preserveState:
                                                true,
                                        }
                                    );
                                }
                            }}
                            className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-violet-500 lg:w-80"
                        />
                    </div>

                    {/* W220: bulk bar */}
                    {bulkEnabled && liveRows.length > 0 && (
                        <div className={`flex flex-wrap items-center gap-3 border-b px-5 py-3 text-sm ${picked.length ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white'}`}>
                            <label className="inline-flex cursor-pointer items-center gap-2 font-semibold text-slate-800">
                                <input type="checkbox" className="h-4 w-4 rounded border-slate-300" checked={allPicked} onChange={toggleAllPicked} />
                                {allPicked ? 'Unselect all' : 'Select all on this page'}
                            </label>
                            <span className="text-slate-500">{picked.length} selected</span>
                            {picked.length > 0 && (
                                <button type="button" onClick={() => setPicked([])} className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Clear</button>
                            )}
                            <select
                                value={filters.per_page ?? 25}
                                onChange={(e) => { setPicked([]); router.get('/admin/distribution', { ...filters, per_page: e.target.value }, { preserveState: true }); }}
                                className="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs font-semibold text-slate-700"
                                aria-label="Rows per page"
                            >
                                {[25, 50, 100].map((n) => <option key={n} value={n}>{n} per page</option>)}
                            </select>
                            <span className="hidden text-xs text-slate-500 lg:inline">One e-mailed code marks every ticked release live in all its stores. No UPC = skipped and listed.</span>
                            <button
                                type="button"
                                disabled={bulkBusy || picked.length === 0}
                                onClick={bulkLive}
                                className="ml-auto inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {bulkBusy ? 'Working…' : `Mark ${picked.length || ''} live`}
                            </button>
                        </div>
                    )}
                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {bulkEnabled && (
                                        <th className="w-10 px-5 py-4">
                                            <input type="checkbox" aria-label="Select all on this page" className="h-4 w-4 rounded border-slate-300" checked={allPicked} onChange={toggleAllPicked} disabled={liveRows.length === 0} />
                                        </th>
                                    )}
                                    {[
                                        'Release',
                                        'Artist',
                                        'UPC',
                                        'Tracks',
                                        'Stores',
                                        'Status',
                                        'Action',
                                    ].map(
                                        (
                                            heading
                                        ) => (
                                            <th
                                                key={
                                                    heading
                                                }
                                                className="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                                            >
                                                {
                                                    heading
                                                }
                                            </th>
                                        )
                                    )}
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {rows.length >
                                0 ? (
                                    rows.map(
                                        (
                                            release
                                        ) => (
                                            <tr
                                                key={
                                                    release.id
                                                }
                                            >
                                                {bulkEnabled && (
                                                    <td className="w-10 px-5 py-4">
                                                        {W220_CAN_LIVE.includes(String(release.status)) && (
                                                            <input type="checkbox" aria-label={`Select ${release.title}`} className="h-4 w-4 rounded border-slate-300" checked={picked.includes(release.id)} onChange={() => togglePicked(release.id)} />
                                                        )}
                                                    </td>
                                                )}
                                                <td className="px-5 py-4">
                                                    <div className="font-semibold text-slate-900">
                                                        {
                                                            release.title
                                                        }
                                                    </div>

                                                    <div className="mt-1 text-xs text-slate-500">
                                                        {release.catalog_number ||
                                                            'No catalogue number'}
                                                    </div>
                                                </td>

                                                <td className="px-5 py-4 text-sm text-slate-600">
                                                    {release.primary_artist_name ||
                                                        '—'}
                                                </td>

                                                <td className="px-5 py-4 text-sm text-slate-600">
                                                    {release.upc ||
                                                        'Pending'}
                                                </td>

                                                <td className="px-5 py-4 text-sm text-slate-600">
                                                    {release.tracks_count ??
                                                        0}
                                                </td>
                                                <td className="px-5 py-4 text-sm text-slate-600">
                                                    {release.stores_total ? (
                                                        <span>
                                                            {release.stores_live}/{release.stores_total} live
                                                            {release.stores_failed > 0 && <span className="ml-1 font-semibold text-rose-600">· {release.stores_failed} failed</span>}
                                                        </span>
                                                    ) : (
                                                        <span className="text-slate-400">not started</span>
                                                    )}
                                                </td>

                                                <td className="px-5 py-4">
                                                    <span
                                                        className={[
                                                            'rounded-full px-3 py-1 text-xs font-semibold capitalize',
                                                            statusClasses[
                                                                release
                                                                    .status
                                                            ] ??
                                                                'bg-slate-100 text-slate-700',
                                                        ].join(
                                                            ' '
                                                        )}
                                                    >
                                                        {release.status.replaceAll(
                                                            '_',
                                                            ' '
                                                        )}
                                                    </span>
                                                </td>

                                                <td className="px-5 py-4">
                                                    <Link
                                                        href={`/admin/distribution/${release.id}`}
                                                        className="rounded-lg border border-violet-300 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50"
                                                    >
                                                        Manage DSPs
                                                    </Link>
                                                </td>
                                            </tr>
                                        )
                                    )
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="8"
                                            className="px-5 py-16 text-center text-sm text-slate-500"
                                        >
                                            No distribution
                                            releases found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {/* W58: pages */}
                    {(releases.links || []).length > 3 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4">
                            <p className="text-sm text-slate-500">{releases.from ?? 0}–{releases.to ?? 0} of {releases.total ?? 0}</p>
                            <div className="flex flex-wrap gap-2">
                                {releases.links.map((link, i) => (
                                    <Link key={`${link.label}-${i}`} href={link.url || '#'} preserveScroll className={`rounded-lg border px-3 py-1.5 text-xs font-semibold ${link.active ? 'border-violet-600 bg-violet-600 text-white' : 'border-slate-300 bg-white text-slate-600'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                                ))}
                            </div>
                        </div>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}
