import {
    Head,
    Link,
    router,
    usePage,
} from '@inertiajs/react';

import {
    useMemo,
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const money = (value) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(value || 0));

const number = (value) =>
    new Intl.NumberFormat('en-IN').format(
        Number(value || 0)
    );

const monthName = (value) => {
    if (!value) {
        return '—';
    }

    const [year, month] = value.split('-');

    return new Intl.DateTimeFormat('en-IN', {
        month: 'short',
        year: 'numeric',
    }).format(
        new Date(
            Number(year),
            Number(month) - 1,
            1
        )
    );
};

export default function Index({
    rows = {},
    labels = [],
    artists = [],
    months = [],
    summary = {},
    filters = {},
}) {
    const {
        flash = {},
        errors = {},
    } = usePage().props;

    const [search, setSearch] = useState(
        filters.search ?? ''
    );

    const [month, setMonth] = useState(
        filters.month ?? ''
    );

    const sort = filters.sort ?? 'revenue';
    const direction = filters.direction ?? 'desc';

    const [applying, setApplying] =
        useState(false);

    const list = rows?.data ?? [];

    const applyFilters = (event) => {
        event.preventDefault();

        router.get(
            '/super-admin/unmapped-revenue',
            {
                search,
                month,
                sort,
                direction,
            },
            {
                preserveState: false,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const resetFilters = () => {
        setSearch('');
        setMonth('');

        router.get(
            '/super-admin/unmapped-revenue',
            {
                search: '',
                month: '',
                sort: 'revenue',
                direction: 'desc',
            },
            {
                preserveState: false,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const changeSort = (column) => {
        const nextDirection =
            sort === column && direction === 'asc'
                ? 'desc'
                : 'asc';

        router.get(
            '/super-admin/unmapped-revenue',
            {
                search,
                month,
                sort: column,
                direction: nextDirection,
            },
            {
                preserveState: false,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const sortIcon = (column) => {
        const icon =
            sort === column && direction === 'desc'
                ? '↓'
                : '↑';

        return (
            <span className="ml-1 text-violet-600">
                {icon}
            </span>
        );
    };

    const applyAllRules = () => {
        if (
            !window.confirm(
                'Apply all saved persistent mapping rules to currently unmapped report rows?'
            )
        ) {
            return;
        }

        setApplying(true);

        router.post(
            '/super-admin/unmapped-revenue/apply-all',
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setApplying(false);
                },
            }
        );
    };

    return (
        <PanelLayout>
            <Head title="Unmapped Revenue" />

            <div className="space-y-6">
                <header className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="rounded-full bg-violet-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-violet-700 ring-1 ring-inset ring-violet-600/20">
                                Super Admin
                            </span>

                            <span className="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                Revenue Control
                            </span>
                        </div>

                        <h1 className="mt-3 text-3xl font-bold tracking-tight text-slate-950">
                            Unmapped Revenue
                        </h1>

                        <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                            Resolve unmatched report ISRCs through the
                            Master → Catalogue Level → Artist hierarchy.
                            Saved mappings become persistent rules and
                            are reused automatically for future reports.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={applyAllRules}
                        disabled={
                            applying ||
                            Number(summary.rules || 0) === 0
                        }
                        className="inline-flex items-center justify-center rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {applying
                            ? 'Applying Rules...'
                            : 'Apply Saved Rules'}
                    </button>
                </header>

                {flash.success && (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800">
                        {flash.success}
                    </div>
                )}

                {Object.keys(errors || {}).length > 0 && (
                    <div className="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800">
                        {Object.values(errors)
                            .flat()
                            .map((error, index) => (
                                <div key={index}>
                                    {error}
                                </div>
                            ))}
                    </div>
                )}

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Unmapped Rows"
                        value={number(summary.rows)}
                        helper="Report rows awaiting ownership"
                    />

                    <StatCard
                        label="Unmapped Revenue"
                        value={money(summary.revenue)}
                        helper="Revenue not yet allocated"
                    />

                    <StatCard
                        label="Unique ISRC"
                        value={number(summary.unique_isrc)}
                        helper="Identifiers requiring review"
                    />

                    <StatCard
                        label="Saved Rules"
                        value={number(summary.rules)}
                        helper="Persistent ownership mappings"
                    />
                </section>

                <form
                    onSubmit={applyFilters}
                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px_auto]">
                        <div>
                            <label className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">
                                Search
                            </label>

                            <input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(
                                        event.target.value
                                    )
                                }
                                placeholder="ISRC, UPC, track, artist or revenue label"
                                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100"
                            />
                        </div>

                        <div>
                            <label className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">
                                Sale Month
                            </label>

                            <select
                                value={month}
                                onChange={(event) =>
                                    setMonth(
                                        event.target.value
                                    )
                                }
                                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100"
                            >
                                <option value="">
                                    All months
                                </option>

                                {months.map((item) => (
                                    <option
                                        key={item}
                                        value={item}
                                    >
                                        {monthName(item)}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="flex items-end gap-2">
                            <button
                                type="submit"
                                className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700"
                            >
                                Apply
                            </button>

                            <button
                                type="button"
                                onClick={resetFilters}
                                className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </form>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="font-bold text-slate-950">
                                Unresolved Identifiers
                            </h2>

                            <p className="mt-1 text-xs text-slate-500">
                                Click Track, Report Label, Rows or Revenue to change sorting.
                            </p>
                        </div>

                        <div className="text-xs font-semibold text-slate-500">
                            Page {rows?.current_page ?? 1}
                            {' / '}
                            {rows?.last_page ?? 1}
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-[1450px] w-full text-sm">
                            <thead className="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">
                                        Identifier
                                    </th>

                                    <th className="px-4 py-3">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                changeSort('track')
                                            }
                                            className="inline-flex items-center font-bold uppercase tracking-wider transition hover:text-violet-700"
                                            title="Sort by Track"
                                        >
                                            Track
                                            {sortIcon('track')}
                                        </button>
                                    </th>

                                    <th className="px-4 py-3">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                changeSort('label')
                                            }
                                            className="inline-flex items-center font-bold uppercase tracking-wider transition hover:text-violet-700"
                                            title="Sort by Report Label"
                                        >
                                            Report Label
                                            {sortIcon('label')}
                                        </button>
                                    </th>

                                    <th className="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                changeSort('rows')
                                            }
                                            className="ml-auto inline-flex items-center font-bold uppercase tracking-wider transition hover:text-violet-700"
                                            title="Sort by Rows"
                                        >
                                            Rows
                                            {sortIcon('rows')}
                                        </button>
                                    </th>

                                    <th className="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                changeSort('revenue')
                                            }
                                            className="ml-auto inline-flex items-center font-bold uppercase tracking-wider transition hover:text-violet-700"
                                            title="Sort by Revenue"
                                        >
                                            Revenue
                                            {sortIcon('revenue')}
                                        </button>
                                    </th>

                                    <th className="px-4 py-3">
                                        Period
                                    </th>

                                    <th className="px-4 py-3">
                                        Hierarchy Mapping
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {list.map((row) => (
                                    <RevenueRow
                                        key={
                                            row.normalized_isrc ||
                                            row.isrc
                                        }
                                        row={row}
                                        labels={labels}
                                        artists={artists}
                                    />
                                ))}

                                {list.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="7"
                                            className="px-6 py-16 text-center"
                                        >
                                            <div className="text-base font-bold text-slate-800">
                                                No unmapped ISRCs found
                                            </div>

                                            <div className="mt-1 text-sm text-slate-500">
                                                Try changing the
                                                current filters.
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <Pagination links={rows?.links ?? []} />
                </section>
            </div>
        </PanelLayout>
    );
}

function RevenueRow({
    row,
    labels,
    artists,
}) {
    const labelById = useMemo(
        () =>
            new Map(
                labels.map((label) => [
                    Number(label.id),
                    label,
                ])
            ),
        [labels]
    );

    const rootIdFor = (labelId) => {
        let current =
            labelById.get(
                Number(labelId)
            );

        if (!current) {
            return null;
        }

        const visited = new Set();

        while (
            current?.parent_label_id
        ) {
            if (
                visited.has(
                    Number(current.id)
                )
            ) {
                break;
            }

            visited.add(
                Number(current.id)
            );

            const parent =
                labelById.get(
                    Number(
                        current.parent_label_id
                    )
                );

            if (!parent) {
                break;
            }

            current = parent;
        }

        return current
            ? Number(current.id)
            : null;
    };

    const rootLabels = useMemo(
        () =>
            labels
                .filter(
                    (label) =>
                        !label.parent_label_id
                )
                .sort(
                    (a, b) =>
                        String(a.name || '')
                            .localeCompare(
                                String(
                                    b.name || ''
                                )
                            )
                ),
        [labels]
    );

    const existingCatalogueLabelId =
        row?.rule?.catalogue_label_id
            ? String(
                  row.rule
                      .catalogue_label_id
              )
            : '';

    const existingMasterId =
        existingCatalogueLabelId
            ? String(
                  rootIdFor(
                      existingCatalogueLabelId
                  ) || ''
              )
            : '';

    const existingArtistId =
        row?.rule?.artist_id
            ? String(
                  row.rule.artist_id
              )
            : '';

    const [masterId, setMasterId] =
        useState(existingMasterId);

    const [
        catalogueLabelId,
        setCatalogueLabelId,
    ] = useState(
        existingCatalogueLabelId
    );

    const [artistId, setArtistId] =
        useState(existingArtistId);

    const [notes, setNotes] =
        useState(
            row?.rule?.notes ?? ''
        );

    const [saving, setSaving] =
        useState(false);

    const [creatingLabel, setCreatingLabel] =
        useState(false);

    /*
     * All labels whose root is the selected master
     * are valid catalogue destinations.
     *
     * Root itself is included intentionally.
     */
    const levelOptions = useMemo(
        () => {
            if (!masterId) {
                return [];
            }

            const selectedRoot =
                Number(masterId);

            return labels
                .filter(
                    (label) =>
                        rootIdFor(
                            label.id
                        ) ===
                        selectedRoot
                )
                .sort((a, b) => {
                    if (
                        Number(a.id) ===
                        selectedRoot
                    ) {
                        return -1;
                    }

                    if (
                        Number(b.id) ===
                        selectedRoot
                    ) {
                        return 1;
                    }

                    return String(
                        a.name || ''
                    ).localeCompare(
                        String(
                            b.name || ''
                        )
                    );
                });
        },
        [
            labels,
            masterId,
            labelById,
        ]
    );

    /*
     * Artist filtering follows the selected level
     * subtree.
     *
     * Example:
     * X selected -> artists on X, X1, X2...
     * X1 selected -> artists on X1 and descendants.
     */
    const allowedLevelIds =
        useMemo(() => {
            if (!catalogueLabelId) {
                return new Set();
            }

            const target =
                Number(
                    catalogueLabelId
                );

            const allowed =
                new Set();

            labels.forEach(
                (label) => {
                    let current =
                        labelById.get(
                            Number(
                                label.id
                            )
                        );

                    const visited =
                        new Set();

                    while (current) {
                        if (
                            Number(
                                current.id
                            ) === target
                        ) {
                            allowed.add(
                                Number(
                                    label.id
                                )
                            );
                            break;
                        }

                        if (
                            !current
                                .parent_label_id
                        ) {
                            break;
                        }

                        if (
                            visited.has(
                                Number(
                                    current.id
                                )
                            )
                        ) {
                            break;
                        }

                        visited.add(
                            Number(
                                current.id
                            )
                        );

                        current =
                            labelById.get(
                                Number(
                                    current
                                        .parent_label_id
                                )
                            );
                    }
                }
            );

            return allowed;
        }, [
            catalogueLabelId,
            labels,
            labelById,
        ]);

    const artistOptions =
        useMemo(
            () =>
                artists
                    .filter(
                        (artist) =>
                            artist.label_id &&
                            allowedLevelIds.has(
                                Number(
                                    artist.label_id
                                )
                            )
                    )
                    .sort(
                        (a, b) =>
                            String(
                                a.stage_name ||
                                    ''
                            ).localeCompare(
                                String(
                                    b.stage_name ||
                                        ''
                                )
                            )
                    ),
            [
                artists,
                allowedLevelIds,
            ]
        );

    const selectedMaster =
        masterId
            ? labelById.get(
                  Number(masterId)
              )
            : null;

    const selectedLevel =
        catalogueLabelId
            ? labelById.get(
                  Number(
                      catalogueLabelId
                  )
              )
            : null;

    const changeMaster = (
        value
    ) => {
        setMasterId(value);
        setCatalogueLabelId('');
        setArtistId('');
    };

    const changeLevel = (
        value
    ) => {
        setCatalogueLabelId(
            value
        );
        setArtistId('');
    };

    const saveIsrc = () => {
        if (!masterId) {
            window.alert(
                'Please select a Master Label.'
            );
            return;
        }

        if (!catalogueLabelId) {
            window.alert(
                'Please select a Catalogue Level.'
            );
            return;
        }

        if (
            rootIdFor(
                catalogueLabelId
            ) !== Number(masterId)
        ) {
            window.alert(
                'Selected level does not belong to the selected Master Label.'
            );
            return;
        }

        setSaving(true);

        router.post(
            '/super-admin/unmapped-revenue',
            {
                isrc: row.isrc,

                catalogue_label_id:
                    catalogueLabelId,

                artist_id:
                    artistId || null,

                notes,
            },
            {
                preserveScroll: true,

                onFinish: () => {
                    setSaving(false);
                },
            }
        );
    };

    const createAndMapLabel = () => {
        const labelName =
            String(
                row.report_label || ''
            ).trim();

        if (!labelName) {
            window.alert(
                'Report label name is missing.'
            );
            return;
        }

        const confirmed =
            window.confirm(
                `Create label "${labelName}" and permanently map ALL currently unmapped ISRCs for this report label?\n\nThis will create permanent ISRC mapping rules and map all matching unmapped report rows.`
            );

        if (!confirmed) {
            return;
        }

        setCreatingLabel(true);

        router.post(
            '/super-admin/unmapped-revenue/create-and-map-label',
            {
                isrc: row.isrc,
                label_name: labelName,
                royalty_percentage: 100,
            },
            {
                preserveScroll: true,

                onFinish: () => {
                    setCreatingLabel(
                        false
                    );
                },
            }
        );
    };

    return (
        <tr className="align-top transition hover:bg-slate-50/70">
            <td className="px-4 py-4">
                <div className="font-mono text-xs font-bold text-slate-900">
                    {row.isrc || '—'}
                </div>

                <div className="mt-2 text-xs text-slate-500">
                    UPC
                </div>

                <div className="mt-0.5 font-mono text-xs text-slate-700">
                    {row.upc || '—'}
                </div>

                {row.rule && (
                    <span className="mt-2 inline-flex rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                        {row.rule
                            .catalogue_label_id
                            ? 'Hierarchy Rule'
                            : 'Legacy Rule'}
                    </span>
                )}
            </td>

            <td className="px-4 py-4">
                <div className="max-w-[280px] font-bold text-slate-900">
                    {row.track_title ||
                        'Untitled'}
                </div>

                <div className="mt-1 max-w-[280px] text-xs leading-5 text-slate-500">
                    {row.track_artist ||
                        'Artist unavailable'}
                </div>
            </td>

            <td className="px-4 py-4">
                <div className="flex max-w-[240px] flex-col items-start gap-2">
                    <span className="inline-flex max-w-[220px] rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700">
                        {row.report_label ||
                            '—'}
                    </span>

                    {row.report_label && (
                        <button
                            type="button"
                            onClick={
                                createAndMapLabel
                            }
                            disabled={
                                creatingLabel
                            }
                            className="inline-flex items-center rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-[11px] font-bold text-violet-700 transition hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {creatingLabel
                                ? 'Creating & Mapping...'
                                : '+ Create & Map All'}
                        </button>
                    )}
                </div>
            </td>

            <td className="px-4 py-4 text-right font-semibold text-slate-700">
                {number(
                    row.rows_count
                )}
            </td>

            <td className="px-4 py-4 text-right">
                <div className="font-bold text-slate-950">
                    {money(
                        row.revenue
                    )}
                </div>
            </td>

            <td className="px-4 py-4">
                <div className="whitespace-nowrap text-xs font-medium text-slate-700">
                    {monthName(
                        row.first_month
                    )}
                </div>

                <div className="my-1 text-[10px] uppercase text-slate-400">
                    to
                </div>

                <div className="whitespace-nowrap text-xs font-medium text-slate-700">
                    {monthName(
                        row.last_month
                    )}
                </div>
            </td>

            <td className="px-4 py-4">
                <div className="min-w-[500px] space-y-3">
                    <div className="grid gap-2 lg:grid-cols-3">
                        <div>
                            <div className="mb-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                Master
                            </div>

                            <select
                                value={
                                    masterId
                                }
                                onChange={(
                                    event
                                ) =>
                                    changeMaster(
                                        event
                                            .target
                                            .value
                                    )
                                }
                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800"
                            >
                                <option value="">
                                    Select Master
                                </option>

                                {rootLabels.map(
                                    (
                                        label
                                    ) => (
                                        <option
                                            key={
                                                label.id
                                            }
                                            value={
                                                label.id
                                            }
                                        >
                                            {
                                                label.name
                                            }
                                        </option>
                                    )
                                )}
                            </select>
                        </div>

                        <div>
                            <div className="mb-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                Catalogue Level
                            </div>

                            <select
                                value={
                                    catalogueLabelId
                                }
                                disabled={
                                    !masterId
                                }
                                onChange={(
                                    event
                                ) =>
                                    changeLevel(
                                        event
                                            .target
                                            .value
                                    )
                                }
                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 disabled:bg-slate-100 disabled:text-slate-400"
                            >
                                <option value="">
                                    Select Level
                                </option>

                                {levelOptions.map(
                                    (
                                        label
                                    ) => (
                                        <option
                                            key={
                                                label.id
                                            }
                                            value={
                                                label.id
                                            }
                                        >
                                            {Number(
                                                label.id
                                            ) ===
                                            Number(
                                                masterId
                                            )
                                                ? `${label.name} (Master)`
                                                : label.name}
                                        </option>
                                    )
                                )}
                            </select>
                        </div>

                        <div>
                            <div className="mb-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                Artist
                            </div>

                            <select
                                value={
                                    artistId
                                }
                                disabled={
                                    !catalogueLabelId
                                }
                                onChange={(
                                    event
                                ) =>
                                    setArtistId(
                                        event
                                            .target
                                            .value
                                    )
                                }
                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 disabled:bg-slate-100 disabled:text-slate-400"
                            >
                                <option value="">
                                    No Artist / Optional
                                </option>

                                {artistOptions.map(
                                    (
                                        artist
                                    ) => (
                                        <option
                                            key={
                                                artist.id
                                            }
                                            value={
                                                artist.id
                                            }
                                        >
                                            {
                                                artist.stage_name
                                            }
                                        </option>
                                    )
                                )}
                            </select>
                        </div>
                    </div>

                    {selectedMaster &&
                        selectedLevel && (
                            <div className="rounded-lg bg-violet-50 px-3 py-2 text-[11px] leading-5 text-violet-800 ring-1 ring-inset ring-violet-600/10">
                                Financial Owner:{' '}
                                <strong>
                                    {
                                        selectedMaster.name
                                    }
                                </strong>

                                {' • '}

                                Catalogue:{' '}
                                <strong>
                                    {
                                        selectedLevel.name
                                    }
                                </strong>

                                {artistId &&
                                    ' • Artist selected'}
                            </div>
                        )}

                    <div className="flex gap-2">
                        <input
                            type="text"
                            value={notes}
                            onChange={(
                                event
                            ) =>
                                setNotes(
                                    event.target
                                        .value
                                )
                            }
                            placeholder="Optional mapping note"
                            className="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-800"
                        />

                        <button
                            type="button"
                            onClick={
                                saveIsrc
                            }
                            disabled={
                                saving ||
                                !masterId ||
                                !catalogueLabelId
                            }
                            className="rounded-lg bg-violet-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {saving
                                ? 'Mapping...'
                                : 'Map ISRC'}
                        </button>
                    </div>
                </div>
            </td>
        </tr>
    );
}

function StatCard({
    label,
    value,
    helper,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-xs font-bold uppercase tracking-wide text-slate-500">
                {label}
            </div>

            <div className="mt-2 break-words text-2xl font-bold tracking-tight text-slate-950">
                {value}
            </div>

            <div className="mt-2 text-xs leading-5 text-slate-500">
                {helper}
            </div>
        </div>
    );
}

function Pagination({
    links = [],
}) {
    if (!links.length) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4">
            <div className="text-xs text-slate-500">
                Use search and month filters to narrow
                large revenue reports.
            </div>

            <div className="flex flex-wrap gap-1">
                {links.map((link, index) => (
                    link.url ? (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            preserveState
                            className={[
                                'rounded-lg border px-3 py-2 text-xs font-semibold transition',
                                link.active
                                    ? 'border-violet-600 bg-violet-600 text-white'
                                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                            ].join(' ')}
                            dangerouslySetInnerHTML={{
                                __html: link.label,
                            }}
                        />
                    ) : (
                        <span
                            key={index}
                            className="cursor-not-allowed rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-300"
                            dangerouslySetInnerHTML={{
                                __html: link.label,
                            }}
                        />
                    )
                ))}
            </div>
        </div>
    );
}
