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

const revenueRateText = (label) => {
    if (
        label?.revenue_share_percentage === null ||
        label?.revenue_share_percentage === undefined ||
        label?.revenue_share_percentage === ''
    ) {
        return 'RATE NOT SET';
    }

    return `${Number(
        label.revenue_share_percentage
    ).toFixed(2)}%`;
};

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
    sourceImport = null,
    mappingPreview = null,
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

    /*
     * MIXXTUNE_UNMAPPED_AUTO_RESOLVE_UI_V1
     */
    const [autoResolving, setAutoResolving] =
        useState(false);

    const list = rows?.data ?? [];

    /*
     * MIXXTUNE_BULK_SELECTED_MAPPING_UI_V1
     */
    const [selectedIsrcs, setSelectedIsrcs] =
        useState([]);

    const [bulkMasterId, setBulkMasterId] =
        useState('');

    const [
        bulkCatalogueLabelId,
        setBulkCatalogueLabelId,
    ] = useState('');

    const [bulkArtistId, setBulkArtistId] =
        useState('');

    const [bulkNotes, setBulkNotes] =
        useState('');

    const [bulkSaving, setBulkSaving] =
        useState(false);

    /*
     * MIXXTUNE_BULK_MAP_VISIBLE_ERROR_V1
     * Keep bulk-map failures visible in the page instead of relying
     * on browser alert/confirm dialogs.
     */
    const [bulkMapError, setBulkMapError] =
        useState('');

    const bulkLabelById = useMemo(
        () =>
            new Map(
                labels.map((label) => [
                    Number(label.id),
                    label,
                ])
            ),
        [labels]
    );

    const bulkRootIdFor = (labelId) => {
        let current =
            bulkLabelById.get(
                Number(labelId)
            );

        if (!current) {
            return null;
        }

        const visited = new Set();

        while (current?.parent_label_id) {
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
                bulkLabelById.get(
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

    const bulkRootLabels = useMemo(
        () =>
            labels
                .filter(
                    (label) =>
                        !label.parent_label_id
                )
                .sort(
                    (a, b) =>
                        String(
                            a.name || ''
                        ).localeCompare(
                            String(
                                b.name || ''
                            )
                        )
                ),
        [labels]
    );

    const bulkCatalogueOptions = useMemo(
        () => {
            if (!bulkMasterId) {
                return [];
            }

            return labels
                .filter(
                    (label) =>
                        bulkRootIdFor(
                            label.id
                        ) ===
                        Number(
                            bulkMasterId
                        )
                )
                .sort((a, b) => {
                    if (
                        Number(a.id) ===
                        Number(bulkMasterId)
                    ) {
                        return -1;
                    }

                    if (
                        Number(b.id) ===
                        Number(bulkMasterId)
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
            bulkMasterId,
            bulkLabelById,
        ]
    );

    const bulkAllowedLabelIds = useMemo(
        () => {
            if (!bulkCatalogueLabelId) {
                return new Set();
            }

            const target =
                Number(
                    bulkCatalogueLabelId
                );

            const allowed = new Set();

            labels.forEach((label) => {
                let current =
                    bulkLabelById.get(
                        Number(label.id)
                    );

                const visited =
                    new Set();

                while (current) {
                    if (
                        Number(current.id) ===
                        target
                    ) {
                        allowed.add(
                            Number(label.id)
                        );
                        break;
                    }

                    if (
                        !current.parent_label_id
                    ) {
                        break;
                    }

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

                    current =
                        bulkLabelById.get(
                            Number(
                                current
                                    .parent_label_id
                            )
                        );
                }
            });

            return allowed;
        },
        [
            labels,
            bulkCatalogueLabelId,
            bulkLabelById,
        ]
    );

    const bulkArtistOptions = useMemo(
        () =>
            artists
                .filter(
                    (artist) =>
                        artist.label_id &&
                        bulkAllowedLabelIds.has(
                            Number(
                                artist.label_id
                            )
                        )
                )
                .sort(
                    (a, b) =>
                        String(
                            a.stage_name || ''
                        ).localeCompare(
                            String(
                                b.stage_name || ''
                            )
                        )
                ),
        [
            artists,
            bulkAllowedLabelIds,
        ]
    );

    const pageIsrcs = useMemo(
        () =>
            list
                .map(
                    (row) =>
                        row.normalized_isrc ||
                        row.isrc
                )
                .filter(Boolean),
        [list]
    );

    const allPageSelected =
        pageIsrcs.length > 0 &&
        pageIsrcs.every(
            (isrc) =>
                selectedIsrcs.includes(
                    isrc
                )
        );

    const toggleSelectedIsrc = (
        isrc
    ) => {
        setSelectedIsrcs(
            (current) =>
                current.includes(isrc)
                    ? current.filter(
                          (value) =>
                              value !== isrc
                      )
                    : [
                          ...current,
                          isrc,
                      ]
        );
    };

    const toggleSelectPage = () => {
        if (allPageSelected) {
            setSelectedIsrcs(
                (current) =>
                    current.filter(
                        (value) =>
                            !pageIsrcs.includes(
                                value
                            )
                    )
            );

            return;
        }

        setSelectedIsrcs(
            (current) =>
                Array.from(
                    new Set([
                        ...current,
                        ...pageIsrcs,
                    ])
                )
        );
    };

    const clearBulkSelection = () => {
        setSelectedIsrcs([]);
    };

    const changeBulkMaster = (
        value
    ) => {
        setBulkMasterId(value);
        setBulkCatalogueLabelId('');
        setBulkArtistId('');
    };

    const changeBulkCatalogueLabel = (
        value
    ) => {
        setBulkCatalogueLabelId(
            value
        );
        setBulkArtistId('');
    };

    const mapSelectedIsrcs = () => {
        /*
         * MIXXTUNE_BULK_MAP_FRONTEND_TRACE_V1
         * Temporary production diagnostic.
         */
        const traceSelectedLabel =
            bulkLabelById.get(
                Number(bulkCatalogueLabelId)
            );

        const traceRootId =
            bulkCatalogueLabelId
                ? bulkRootIdFor(
                    bulkCatalogueLabelId
                )
                : null;

        setBulkMapError(
            `TRACE: click received | selected=${selectedIsrcs.length} | master=${bulkMasterId || 'EMPTY'} | catalogue=${bulkCatalogueLabelId || 'EMPTY'} | root=${traceRootId ?? 'NULL'} | rate=${traceSelectedLabel?.revenue_share_percentage ?? 'NULL'} | posting...`
        );

        if (selectedIsrcs.length < 1) {
            setBulkMapError(
                'Select at least one ISRC.'
            );
            return;
        }

        if (!bulkMasterId) {
            setBulkMapError(
                'Please select a Master Label.'
            );
            return;
        }

        if (!bulkCatalogueLabelId) {
            setBulkMapError(
                'Please select a Catalogue Label.'
            );
            return;
        }

        if (
            bulkRootIdFor(
                bulkCatalogueLabelId
            ) !== Number(bulkMasterId)
        ) {
            setBulkMapError(
                'Selected Catalogue Label does not belong to the selected Master Label.'
            );
            return;
        }

        const selectedBulkLevel =
            bulkLabelById.get(
                Number(bulkCatalogueLabelId)
            );

        if (
            !selectedBulkLevel ||
            selectedBulkLevel.revenue_share_percentage === null ||
            selectedBulkLevel.revenue_share_percentage === undefined ||
            selectedBulkLevel.revenue_share_percentage === ''
        ) {
            setBulkMapError(
                `Revenue Rate is not set for "${selectedBulkLevel?.name || 'selected label'}". Set the Label Rate in Accounts & Access → Labels first.`
            );
            return;
        }

        setBulkSaving(true);

        router.post(
            '/super-admin/unmapped-revenue/bulk-map-selected',
            {
                isrcs: selectedIsrcs,
                catalogue_label_id:
                    bulkCatalogueLabelId,
                artist_id:
                    bulkArtistId || null,
                notes:
                    bulkNotes,
                report_import_id:
                    reportImportId || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedIsrcs([]);
                    setBulkNotes('');
                    setBulkMapError('');
                },
                onError: (errors) => {
                    const firstError =
                        Object.values(
                            errors || {}
                        ).flat()[0];

                    setBulkMapError(
                        firstError ||
                            'Mapping failed. Please review the selected hierarchy and try again.'
                    );
                },
                onFinish: () => {
                    setBulkSaving(false);
                },
            }
        );
    };

    const applyFilters = (event) => {
        event.preventDefault();

        router.get(
            '/super-admin/unmapped-revenue',
            {
                search,
                month,
                sort,
                direction,
                report_import_id:
                    filters.report_import_id ?? '',
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
                report_import_id:
                    filters.report_import_id ?? '',
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
                report_import_id:
                    filters.report_import_id ?? '',
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

    /*
     * MIXXTUNE_BULK_MAPPING_XLSX_UI_V1
     *
     * Download + validation preview only.
     * No mapping is applied from this UI.
     */
    const [mappingFile, setMappingFile] =
        useState(null);

    const [validatingMapping, setValidatingMapping] =
        useState(false);

    const reportImportId =
        filters.report_import_id ?? '';

    const exportMappingUrl =
        '/super-admin/unmapped-revenue/export-mapping' +
        (
            reportImportId
                ? `?report_import_id=${encodeURIComponent(
                    reportImportId
                )}`
                : ''
        );

    /*
     * MIXXTUNE_BULK_MAPPING_XLSX_APPLY_UI_V1
     */
    const [applyingMapping, setApplyingMapping] =
        useState(false);

    const applyMappingWorkbook = () => {
        if (!mappingFile) {
            window.alert(
                'Please choose the validated Excel mapping file.'
            );
            return;
        }

        if (
            !mappingPreview ||
            Number(mappingPreview.valid_rows || 0) < 1
        ) {
            window.alert(
                'Validate the Excel file first.'
            );
            return;
        }

        if (
            Number(mappingPreview.invalid_rows || 0) > 0
        ) {
            window.alert(
                'Resolve all invalid Excel rows before applying.'
            );
            return;
        }

        const validCount =
            Number(mappingPreview.valid_rows || 0);

        const confirmed =
            window.confirm(
                `Apply ${validCount.toLocaleString()} validated ISRC mapping(s)?\n\n` +
                'This will save persistent ISRC rules and map matching review-pending report rows.\n\n' +
                'Wallets will NOT be credited and revenue will NOT be published.'
            );

        if (!confirmed) {
            return;
        }

        setApplyingMapping(true);

        router.post(
            '/super-admin/unmapped-revenue/apply-mapping',
            {
                file: mappingFile,
                report_import_id:
                    reportImportId || null,
            },
            {
                forceFormData: true,
                preserveScroll: true,

                onSuccess: () => {
                    setMappingFile(null);
                },

                onFinish: () => {
                    setApplyingMapping(false);
                },
            }
        );
    };

    const validateMappingWorkbook = (event) => {
        event.preventDefault();

        if (!mappingFile) {
            window.alert(
                'Please choose the filled Excel mapping file.'
            );
            return;
        }

        setValidatingMapping(true);

        router.post(
            '/super-admin/unmapped-revenue/preview-mapping',
            {
                file: mappingFile,
                report_import_id:
                    reportImportId || null,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setValidatingMapping(false);
                },
            }
        );
    };

    const autoResolve = () => {
        if (!reportImportId) {
            window.alert(
                'Open Unmapped Revenue from a specific report before using Auto Resolve.'
            );
            return;
        }

        const confirmed =
            window.confirm(
                `Auto Resolve currently unmapped ISRCs for Report Import #${reportImportId}?\n\nOnly unique exact existing-label matches with a valid revenue rate will be accepted.\n\nA permanent ISRC rule will be saved for every accepted match.\n\nDuplicate, missing and missing-rate labels will remain unmapped.\n\nWallets will NOT be credited and the report will NOT be published.`
            );

        if (!confirmed) {
            return;
        }

        setAutoResolving(true);

        router.post(
            '/super-admin/unmapped-revenue/auto-resolve',
            {
                report_import_id:
                    Number(reportImportId),
            },
            {
                preserveScroll: true,

                onFinish: () => {
                    setAutoResolving(false);
                },
            }
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
            {
                report_import_id:
                    reportImportId || null,
            },
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
                            Master → Catalogue Label → Artist hierarchy.
                            Saved mappings become persistent rules and
                            are reused automatically for future reports.
                        </p>

                        {sourceImport && (
                            <div className="mt-4 inline-flex flex-wrap items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                <span className="font-semibold">
                                    Source Report:
                                </span>

                                <span>
                                    {sourceImport.original_filename}
                                </span>

                                <span className="text-amber-400">
                                    •
                                </span>

                                <span>
                                    {monthName(
                                        sourceImport.reporting_month
                                    )}
                                </span>

                                <span className="text-amber-400">
                                    •
                                </span>

                                <span className="font-medium">
                                    {number(summary.unique_isrc || 0)} Unmapped ISRC
                                </span>

                                <a
                                    href="/v2/admin/reports/imports"
                                    className="ml-1 font-semibold text-violet-700 hover:underline"
                                >
                                    Back to Reports
                                </a>
                            </div>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        {/* MIXXTUNE_AUTO_RESOLVE_NATIVE_FORM_V1 */}
<form
    method="POST"
    action="/super-admin/unmapped-revenue/auto-resolve"
    onSubmit={(event) => {
        const ok = window.confirm(
            'Run Auto Resolve in background for this report?\n\n' +
            'Only safe unresolved ISRCs will be processed.\n' +
            'Wallets will NOT be credited and the report will NOT be published.'
        );

        if (!ok) {
            event.preventDefault();
        }
    }}
    className="inline"
>
    <input
        type="hidden"
        name="_token"
        value={
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') || ''
        }
    />

    <input
        type="hidden"
        name="report_import_id"
        value={sourceImport?.id || filters?.report_import_id || ''}
    />

    <button
        type="submit" className="inline-flex items-center justify-center rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
        disabled={
            !(
                sourceImport?.id ||
                filters?.report_import_id
            )
        }
    >
        Auto Resolve
    </button>
</form>

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
                    </div>
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

                <section className="rounded-2xl border border-violet-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="text-lg font-bold text-slate-950">
                                    Bulk Excel Mapping
                                </h2>

                                <span className="rounded-full bg-violet-50 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-violet-700">
                                    Validate First
                                </span>
                            </div>

                            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                                Download unresolved ISRCs, fill only the mapping ID columns,
                                then upload the workbook for validation. Collected Revenue
                                and other financial values in Excel are reference-only and
                                are never used as the financial source.
                            </p>

                            {reportImportId && (
                                <div className="mt-2 text-xs font-semibold text-violet-700">
                                    Scoped to Report Import #{reportImportId}
                                </div>
                            )}
                        </div>

                        <a
                            href={exportMappingUrl}
                            className="inline-flex shrink-0 items-center justify-center rounded-xl border border-violet-200 bg-violet-50 px-5 py-3 text-sm font-semibold text-violet-700 transition hover:bg-violet-100"
                        >
                            Download Mapping Excel
                        </a>
                    </div>

                    <form
                        onSubmit={validateMappingWorkbook}
                        className="mt-5 grid gap-4 border-t border-slate-100 pt-5 lg:grid-cols-[minmax(0,1fr)_auto]"
                    >
                        <div>
                            <label className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">
                                Filled Mapping Excel
                            </label>

                            <input
                                type="file"
                                accept=".xlsx,.xls"
                                onChange={(event) =>
                                    setMappingFile(
                                        event.target.files?.[0] ??
                                            null
                                    )
                                }
                                className="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200"
                            />

                            {mappingFile && (
                                <div className="mt-2 text-xs font-medium text-slate-500">
                                    Selected: {mappingFile.name}
                                </div>
                            )}
                        </div>

                        <div className="flex items-end">
                            <button
                                type="submit"
                                disabled={
                                    validatingMapping ||
                                    !mappingFile
                                }
                                className="inline-flex w-full items-center justify-center rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50 lg:w-auto"
                            >
                                {validatingMapping
                                    ? 'Validating Excel...'
                                    : 'Validate Excel'}
                            </button>
                        </div>
                    </form>

                    <div className="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800">
                        Validation does not apply mappings. Database mapping changes
                        will require a separate Confirm &amp; Apply step.
                    </div>
                </section>

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

                {mappingPreview && (
                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-5 py-4">
                            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <h2 className="font-bold text-slate-950">
                                        Excel Mapping Preview
                                    </h2>

                                    <p className="mt-1 text-xs text-slate-500">
                                        Validation result only — no mapping has been applied.
                                    </p>
                                </div>

                                <div className="flex flex-wrap gap-2 text-xs font-bold">
                                    <span className="rounded-full bg-slate-100 px-3 py-1.5 text-slate-700">
                                        Total {number(mappingPreview.total_rows)}
                                    </span>

                                    <span className="rounded-full bg-emerald-50 px-3 py-1.5 text-emerald-700">
                                        Valid {number(mappingPreview.valid_rows)}
                                    </span>

                                    <span className="rounded-full bg-rose-50 px-3 py-1.5 text-rose-700">
                                        Invalid {number(mappingPreview.invalid_rows)}
                                    </span>

                                    <span className="rounded-full bg-amber-50 px-3 py-1.5 text-amber-700">
                                        Skipped {number(mappingPreview.skipped_rows)}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-[1200px] w-full text-sm">
                                <thead className="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3">
                                            Excel Row
                                        </th>

                                        <th className="px-4 py-3">
                                            ISRC
                                        </th>

                                        <th className="px-4 py-3">
                                            Master
                                        </th>

                                        <th className="px-4 py-3">
                                            Catalogue
                                        </th>

                                        <th className="px-4 py-3">
                                            Artist
                                        </th>

                                        <th className="px-4 py-3 text-right">
                                            Matching Rows
                                        </th>

                                        <th className="px-4 py-3 text-right">
                                            Collected Revenue
                                        </th>

                                        <th className="px-4 py-3">
                                            Status
                                        </th>

                                        <th className="px-4 py-3">
                                            Validation
                                        </th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-slate-100">
                                    {(mappingPreview.rows ?? []).map(
                                        (item, index) => (
                                            <tr
                                                key={`${item.excel_row}-${item.isrc}-${index}`}
                                                className="align-top"
                                            >
                                                <td className="px-4 py-3 text-xs font-semibold text-slate-500">
                                                    {item.excel_row}
                                                </td>

                                                <td className="px-4 py-3 font-mono text-xs font-bold text-slate-900">
                                                    {item.isrc || '—'}
                                                </td>

                                                <td className="px-4 py-3">
                                                    <div className="font-semibold text-slate-800">
                                                        {item.master_label_name ||
                                                            '—'}
                                                    </div>

                                                    {item.master_label_id && (
                                                        <div className="mt-1 text-xs text-slate-400">
                                                            ID {item.master_label_id}
                                                        </div>
                                                    )}
                                                </td>

                                                <td className="px-4 py-3">
                                                    <div className="font-semibold text-slate-800">
                                                        {item.catalogue_label_name ||
                                                            '—'}
                                                    </div>

                                                    {item.catalogue_label_id && (
                                                        <div className="mt-1 text-xs text-slate-400">
                                                            ID {item.catalogue_label_id}
                                                        </div>
                                                    )}
                                                </td>

                                                <td className="px-4 py-3">
                                                    <div className="font-semibold text-slate-800">
                                                        {item.artist_name ||
                                                            '—'}
                                                    </div>

                                                    {item.artist_id && (
                                                        <div className="mt-1 text-xs text-slate-400">
                                                            ID {item.artist_id}
                                                        </div>
                                                    )}
                                                </td>

                                                <td className="px-4 py-3 text-right font-semibold text-slate-700">
                                                    {number(
                                                        item.matching_rows
                                                    )}
                                                </td>

                                                <td className="px-4 py-3 text-right font-semibold text-slate-900">
                                                    {item.collected_revenue !==
                                                    undefined
                                                        ? money(
                                                            item.collected_revenue
                                                        )
                                                        : '—'}
                                                </td>

                                                <td className="px-4 py-3">
                                                    <span
                                                        className={
                                                            item.status ===
                                                            'valid'
                                                                ? 'inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700'
                                                                : item.status ===
                                                                    'invalid'
                                                                  ? 'inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700'
                                                                  : 'inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700'
                                                        }
                                                    >
                                                        {item.status}
                                                    </span>
                                                </td>

                                                <td className="max-w-sm px-4 py-3 text-xs leading-5 text-slate-600">
                                                    {item.message || '—'}
                                                </td>
                                            </tr>
                                        )
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {Number(mappingPreview.valid_rows || 0) >
                            0 && (
                            <div className="border-t border-slate-200 bg-violet-50 px-5 py-4">
                                <div className="text-sm font-semibold text-violet-900">
                                    {number(
                                        mappingPreview.valid_rows
                                    )}{' '}
                                    mapping(s) passed validation.
                                </div>

                                <div className="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="text-xs leading-5 text-violet-700">
                                        Apply revalidates the uploaded workbook server-side.
                                        Only review-pending report rows can be changed.
                                    </div>

                                    <button
                                        type="button"
                                        onClick={applyMappingWorkbook}
                                        disabled={
                                            applyingMapping ||
                                            Number(
                                                mappingPreview.invalid_rows || 0
                                            ) > 0
                                        }
                                        className="inline-flex shrink-0 items-center justify-center rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {applyingMapping
                                            ? 'Applying Mapping...'
                                            : `Confirm & Apply ${number(
                                                mappingPreview.valid_rows
                                            )} Mapping(s)`}
                                    </button>
                                </div>
                            </div>
                        )}
                    </section>
                )}

                {/* MIXXTUNE_BULK_SELECTED_MAPPING_BAR_V1 */}
                {selectedIsrcs.length > 0 && (
                    <section className="mb-4 rounded-2xl border border-violet-200 bg-violet-50 p-4 shadow-sm">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div className="text-sm font-bold text-violet-950">
                                    {selectedIsrcs.length.toLocaleString()}{' '}
                                    ISRC Selected
                                </div>

                                <div className="mt-1 text-xs text-violet-700">
                                    Apply one hierarchy mapping to all selected identifiers.
                                </div>
                            </div>

                            <button
                                type="button"
                                onClick={clearBulkSelection}
                                className="rounded-lg border border-violet-200 bg-white px-3 py-2 text-xs font-bold text-violet-700 hover:bg-violet-100"
                            >
                                Clear Selection
                            </button>
                        </div>

                        <div className="grid gap-3 xl:grid-cols-4">
                            <div>
                                <div className="mb-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                    Master
                                </div>

                                <select
                                    value={bulkMasterId}
                                    onChange={(event) =>
                                        changeBulkMaster(
                                            event.target.value
                                        )
                                    }
                                    className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs"
                                >
                                    <option value="">
                                        Select Master
                                    </option>

                                    {bulkRootLabels.map(
                                        (label) => (
                                            <option
                                                key={label.id}
                                                value={label.id}
                                            >
                                                {label.name}
                                                {' — ID '}
                                                {label.id}
                                                {label.account_name
                                                    ? ` — ${label.account_name}`
                                                    : ''}
                                            </option>
                                        )
                                    )}
                                </select>
                            </div>

                            <div>
                                <div className="mb-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                    Catalogue Label
                                </div>

                                <select
                                    value={bulkCatalogueLabelId}
                                    disabled={!bulkMasterId}
                                    onChange={(event) =>
                                        changeBulkCatalogueLabel(
                                            event.target.value
                                        )
                                    }
                                    className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs disabled:bg-slate-100"
                                >
                                    <option value="">
                                        Select Catalogue Label
                                    </option>

                                    {bulkCatalogueOptions.map(
                                        (label) => (
                                            <option
                                                key={label.id}
                                                value={label.id}
                                            >
                                                {Number(label.id) ===
                                                Number(bulkMasterId)
                                                    ? `${label.name} (Master)`
                                                    : label.name}
                                                {' — '}
                                                {revenueRateText(label)}
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
                                    value={bulkArtistId}
                                    disabled={
                                        !bulkCatalogueLabelId
                                    }
                                    onChange={(event) =>
                                        setBulkArtistId(
                                            event.target.value
                                        )
                                    }
                                    className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs disabled:bg-slate-100"
                                >
                                    <option value="">
                                        No Artist / Optional
                                    </option>

                                    {bulkArtistOptions.map(
                                        (artist) => (
                                            <option
                                                key={artist.id}
                                                value={artist.id}
                                            >
                                                {artist.stage_name}
                                            </option>
                                        )
                                    )}
                                </select>
                            </div>

                            <div>
                                <div className="mb-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                    Mapping Note
                                </div>

                                <input
                                    type="text"
                                    value={bulkNotes}
                                    onChange={(event) =>
                                        setBulkNotes(
                                            event.target.value
                                        )
                                    }
                                    placeholder="Optional note"
                                    className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs"
                                />
                            </div>
                        </div>

                        {bulkMapError && (
                            <div className="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">
                                {bulkMapError}
                            </div>
                        )}

                        <div className="mt-4 flex justify-end">
                            <button
                                type="button"
                                disabled={bulkSaving}
                                onClick={mapSelectedIsrcs}
                                className="rounded-lg bg-violet-700 px-5 py-2.5 text-xs font-bold text-white hover:bg-violet-800 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {bulkSaving
                                    ? 'Mapping...'
                                    : `Map ${selectedIsrcs.length.toLocaleString()} Selected`}
                            </button>
                        </div>
                    </section>
                )}

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
                                    {/* MIXXTUNE_BULK_SELECTED_CHECKBOX_V1 */}
                                    <th className="w-12 px-4 py-3">
                                        <input
                                            type="checkbox"
                                            checked={allPageSelected}
                                            onChange={toggleSelectPage}
                                            aria-label="Select all ISRCs on this page"
                                            className="h-4 w-4 rounded border-slate-300"
                                        />
                                    </th>

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
                                        reportImportId={reportImportId}
                                        selected={
                                            selectedIsrcs.includes(
                                                row.normalized_isrc ||
                                                row.isrc
                                            )
                                        }
                                        onToggleSelect={() =>
                                            toggleSelectedIsrc(
                                                row.normalized_isrc ||
                                                row.isrc
                                            )
                                        }
                                    />
                                ))}

                                {list.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="8"
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
    reportImportId = '',
    selected = false,
    onToggleSelect,
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
                'Please select a Catalogue Label.'
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

        if (
            selectedLevel?.revenue_share_percentage === null ||
            selectedLevel?.revenue_share_percentage === undefined ||
            selectedLevel?.revenue_share_percentage === ''
        ) {
            window.alert(
                `Revenue Rate is not set for "${selectedLevel?.name || 'selected label'}".\n\nGo to Accounts & Access → Labels, set the Label Rate, then return here and map the ISRC.`
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
                report_import_id:
                    reportImportId || null,
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

        /*
         * MIXXTUNE_CREATE_MAP_RATE_REQUIRED_V1
         *
         * A newly-created financial label must have
         * an explicit Revenue Rate.
         */
        const rateInput =
            window.prompt(
                `Revenue Rate (%) for "${labelName}"\n\nExample: Enter 80 for 80%.`,
                '80'
            );

        if (rateInput === null) {
            return;
        }

        const revenueRate =
            Number(
                String(
                    rateInput
                ).trim()
            );

        if (
            !Number.isFinite(
                revenueRate
            ) ||
            revenueRate < 0 ||
            revenueRate > 100
        ) {
            window.alert(
                'Revenue Rate must be a number between 0 and 100.'
            );
            return;
        }

        const confirmed =
            window.confirm(
                `Create label "${labelName}"?\n\n` +
                `Revenue Rate: ${revenueRate}%\n` +
                `Clicked ISRC: ${row.isrc || '—'}\n\n` +
                `ALL currently unmapped ISRCs under this exact Report Label will be permanently mapped.\n\n` +
                `Wallet will NOT be credited and the report will NOT be published.`
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
                royalty_percentage:
                    revenueRate,
                report_import_id:
                    reportImportId || null,
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
        <tr
            className={
                `align-top transition hover:bg-slate-50/70 ${
                    selected
                        ? 'bg-violet-50/60'
                        : ''
                }`
            }
        >
            <td className="w-12 px-4 py-4">
                <input
                    type="checkbox"
                    checked={selected}
                    onChange={onToggleSelect}
                    aria-label={`Select ${row.isrc}`}
                    className="h-4 w-4 rounded border-slate-300"
                />
            </td>

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
                                            {label.name}
                                            {' — ID '}
                                            {label.id}
                                            {label.account_name
                                                ? ` — ${label.account_name}`
                                                : ''}
                                        </option>
                                    )
                                )}
                            </select>
                        </div>

                        <div>
                            <div className="mb-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                Catalogue Label
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
                                    Select Catalogue Label
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
                                            {' — '}
                                            {revenueRateText(label)}
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

                                {' • Revenue Rate: '}

                                <strong>
                                    {revenueRateText(
                                        selectedLevel
                                    )}
                                </strong>
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
