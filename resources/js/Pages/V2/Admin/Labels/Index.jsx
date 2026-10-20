import {
    Head,
    router,
} from '@inertiajs/react';
import {
    useEffect,
    useRef,
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import LabelOwnershipPanel from '@/V2/Admin/LabelOwnershipPanel';
import LabelOwnershipBulkImport from '@/V2/Admin/LabelOwnershipBulkImport';
import LabelRateHistory from '@/V2/Admin/LabelRateHistory';

export default function Index({
    role = 'admin',
    labels = {},
    search = '',
    statusFilter = '',
    withdrawalFilter = '',
    typeFilter = '',
    artistsFilter = '',
    releasesFilter = '',
    rateFilter = '',
    managerFilter = '',
    sortFilter = 'label_asc',
    admins = [],
}) {
    const [searchValue, setSearchValue] =
        useState(search);

    const firstSearchRender =
        useRef(true);

    const [selectedLabelIds, setSelectedLabelIds] =
        useState([]);

    const [bulkAction, setBulkAction] =
        useState('');

    const [bulkRate, setBulkRate] =
        useState('');

    const [bulkManagerId, setBulkManagerId] =
        useState('');

    const [bulkSaving, setBulkSaving] =
        useState(false);

    const applyFilters = (changes = {}) => {
        const values = {
            search: searchValue,
            status: statusFilter,
            withdrawal: withdrawalFilter,
            type: typeFilter,
            artists: artistsFilter,
            releases: releasesFilter,
            rate: rateFilter,
            manager: managerFilter,
            sort: sortFilter,
            ...changes,
        };

        router.get(
            '/v2/admin/labels',
            Object.fromEntries(
                Object.entries(values).filter(
                    ([, value]) =>
                        value !== '' &&
                        value !== null &&
                        value !== undefined
                )
            ),
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const pageLabelIds =
        (labels.data ?? []).map(
            (label) => Number(label.id)
        );

    const allPageSelected =
        pageLabelIds.length > 0 &&
        pageLabelIds.every((id) =>
            selectedLabelIds.includes(id)
        );

    const toggleLabelSelection = (labelId) => {
        const id = Number(labelId);

        setSelectedLabelIds((current) =>
            current.includes(id)
                ? current.filter(
                      (item) => item !== id
                  )
                : [...current, id]
        );
    };

    const toggleCurrentPage = () => {
        if (allPageSelected) {
            setSelectedLabelIds((current) =>
                current.filter(
                    (id) =>
                        !pageLabelIds.includes(id)
                )
            );

            return;
        }

        setSelectedLabelIds((current) =>
            Array.from(
                new Set([
                    ...current,
                    ...pageLabelIds,
                ])
            )
        );
    };

    const runBulkAction = () => {
        if (selectedLabelIds.length === 0) {
            window.alert(
                'Select at least one label.'
            );
            return;
        }

        if (!bulkAction) {
            window.alert(
                'Select a Bulk Action.'
            );
            return;
        }

        const payload = {
            label_ids: selectedLabelIds,
            action: bulkAction,
        };

        if (bulkAction === 'set_rate') {
            const rate = Number(
                String(bulkRate).trim()
            );

            if (
                String(bulkRate).trim() === '' ||
                !Number.isFinite(rate) ||
                rate < 0 ||
                rate > 100
            ) {
                window.alert(
                    'Enter a Label Rate between 0 and 100.'
                );
                return;
            }

            payload.revenue_share_percentage =
                rate;
        }

        if (
            bulkAction === 'assign_manager'
        ) {
            if (!bulkManagerId) {
                window.alert(
                    'Select a Manager.'
                );
                return;
            }

            payload.admin_id =
                Number(bulkManagerId);
        }

        const actionNames = {
            set_rate:
                'Set Label Rate',
            assign_manager:
                'Assign Manager',
            unassign_manager:
                'Unassign Manager',
            enable_withdrawal:
                'Enable Withdrawal',
            suspend_withdrawal:
                'Suspend Withdrawal',
        };

        const actionName =
            actionNames[bulkAction] ??
            'Apply Bulk Action';

        if (
            !window.confirm(
                `${actionName} for ${selectedLabelIds.length} selected label(s)?`
            )
        ) {
            return;
        }

        setBulkSaving(true);

        router.post(
            '/v2/admin/labels/bulk-action',
            payload,
            {
                preserveScroll: true,

                onSuccess: () => {
                    setSelectedLabelIds([]);
                    setBulkAction('');
                    setBulkRate('');
                    setBulkManagerId('');
                },

                onError: (errors) => {
                    if (errors?.action_otp) {
                        return; // MIXXTUNE_TWO_STEP_ACTIONS_V1: code box handles it
                    }

                    const message =
                        Object.values(
                            errors ?? {}
                        )[0] ??
                        'Unable to complete Bulk Action.';

                    window.alert(message);
                },

                onFinish: () =>
                    setBulkSaving(false),
            }
        );
    };

    const clearFilters = () => {
        setSearchValue('');

        router.get(
            '/v2/admin/labels',
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    useEffect(() => {
        if (firstSearchRender.current) {
            firstSearchRender.current = false;
            return;
        }

        const timer = window.setTimeout(
            () => {
                applyFilters({
                    search: searchValue,
                });
            },
            300
        );

        return () =>
            window.clearTimeout(timer);
    }, [searchValue]);

    // MIXXTUNE_LABEL_BULK_SELECTION_RESET_V2
    useEffect(() => {
        setSelectedLabelIds([]);
    }, [
        labels.current_page,
        search,
        statusFilter,
        withdrawalFilter,
        typeFilter,
        artistsFilter,
        releasesFilter,
        rateFilter,
        managerFilter,
        sortFilter,
    ]);

    return (
        <PanelLayout
            role={role}
            title="Labels"
            subtitle="Assigned label accounts"
        >
            <Head title="Labels" />

            <div className="space-y-5">
                {/* MIXXTUNE_LABEL_ADVANCED_FILTER_UI_V2 */}
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <input
                            type="search"
                            value={searchValue}
                            placeholder="Search label, email..."
                            onChange={(event) =>
                                setSearchValue(
                                    event.target.value
                                )
                            }
                            className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm"
                        />

                        <FilterSelect
                            value={typeFilter}
                            onChange={(value) =>
                                applyFilters({
                                    type: value,
                                })
                            }
                            options={[
                                ['', 'All Types'],
                                ['label', 'Label'],
                                [
                                    'sub_label',
                                    'Sub-Label',
                                ],
                            ]}
                        />

                        <FilterSelect
                            value={statusFilter}
                            onChange={(value) =>
                                applyFilters({
                                    status: value,
                                })
                            }
                            options={[
                                ['', 'All Status'],
                                ['active', 'Active'],
                                [
                                    'inactive',
                                    'Inactive',
                                ],
                            ]}
                        />

                        <FilterSelect
                            value={artistsFilter}
                            onChange={(value) =>
                                applyFilters({
                                    artists: value,
                                })
                            }
                            options={[
                                ['', 'All Artists'],
                                [
                                    'has',
                                    'Has Artists',
                                ],
                                [
                                    'zero',
                                    '0 Artists',
                                ],
                            ]}
                        />

                        <FilterSelect
                            value={releasesFilter}
                            onChange={(value) =>
                                applyFilters({
                                    releases: value,
                                })
                            }
                            options={[
                                ['', 'All Releases'],
                                [
                                    'has',
                                    'Has Releases',
                                ],
                                [
                                    'zero',
                                    '0 Releases',
                                ],
                            ]}
                        />

                        <FilterSelect
                            value={rateFilter}
                            onChange={(value) =>
                                applyFilters({
                                    rate: value,
                                })
                            }
                            options={[
                                [
                                    '',
                                    'All Label Rates',
                                ],
                                [
                                    'not_set',
                                    'Rate Not Set',
                                ],
                                ['100', '100%'],
                                ['98', '98%'],
                                ['95', '95%'],
                                ['90', '90%'],
                                ['85', '85%'],
                                ['80', '80%'],
                                ['75', '75%'],
                                ['70', '70%'],
                                ['60', '60%'],
                                ['50', '50%'],
                                ['0', '0%'],
                            ]}
                        />

                        <FilterSelect
                            value={managerFilter}
                            onChange={(value) =>
                                applyFilters({
                                    manager: value,
                                })
                            }
                            options={[
                                [
                                    '',
                                    'All Managers',
                                ],
                                [
                                    'assigned',
                                    'Assigned',
                                ],
                                [
                                    'unassigned',
                                    'Unassigned',
                                ],
                                ...admins.map(
                                    (admin) => [
                                        `manager:${admin.id}`,
                                        admin.name,
                                    ]
                                ),
                            ]}
                        />

                        <FilterSelect
                            value={withdrawalFilter}
                            onChange={(value) =>
                                applyFilters({
                                    withdrawal:
                                        value,
                                })
                            }
                            options={[
                                [
                                    '',
                                    'All Withdrawals',
                                ],
                                [
                                    'enabled',
                                    'Withdrawal Enabled',
                                ],
                                [
                                    'suspended',
                                    'Withdrawal Suspended',
                                ],
                            ]}
                        />
                    </div>

                    <div className="mt-3 flex flex-col gap-3 border-t border-slate-100 pt-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="text-sm text-slate-500">
                            <strong className="text-slate-800">
                                {labels.total ?? 0}
                            </strong>
                            {' '}matching labels
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <FilterSelect
                                value={sortFilter}
                                onChange={(value) =>
                                    applyFilters({
                                        sort: value,
                                    })
                                }
                                options={[
                                    [
                                        'label_asc',
                                        'Label A → Z',
                                    ],
                                    [
                                        'label_desc',
                                        'Label Z → A',
                                    ],
                                    [
                                        'artists_high',
                                        'Artists: High → Low',
                                    ],
                                    [
                                        'artists_low',
                                        'Artists: Low → High',
                                    ],
                                    [
                                        'releases_high',
                                        'Releases: High → Low',
                                    ],
                                    [
                                        'releases_low',
                                        'Releases: Low → High',
                                    ],
                                    [
                                        'rate_high',
                                        'Rate: High → Low',
                                    ],
                                    [
                                        'rate_low',
                                        'Rate: Low → High',
                                    ],
                                ]}
                            />

                            <button
                                type="button"
                                onClick={clearFilters}
                                className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Clear Filters
                            </button>
                        </div>
                    </div>
                </div>

                {/* MIXXTUNE_LABEL_OWNERSHIP_OTP_V1 */}
                {role === 'super_admin' && (
                    <LabelOwnershipPanel
                        selectedLabelIds={selectedLabelIds}
                        pageLabels={labels?.data ?? []}
                        onDone={() => {
                            setSelectedLabelIds([]);
                            router.reload({ preserveScroll: true });
                        }}
                    />
                )}

                {/* MIXXTUNE_LABEL_OWNER_BULK_IMPORT_V1 */}
                {role === 'super_admin' && (
                    <LabelOwnershipBulkImport
                        onDone={() => router.reload({ preserveScroll: true })}
                    />
                )}

                {/* MIXXTUNE_LABEL_RATE_HISTORY_V1 */}
                {role === 'super_admin' && (
                    <div className="flex justify-end">
                        <LabelRateHistory buttonText="All label rate changes" />
                    </div>
                )}

                {/* MIXXTUNE_LABEL_BULK_ACTION_UI_V2 */}
                {role === 'super_admin' && (
                    <div className="rounded-2xl border border-violet-200 bg-violet-50/60 p-4 shadow-sm">
                        <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                            <div>
                                <div className="text-sm font-semibold text-slate-900">
                                    Bulk Actions
                                </div>

                                <div className="mt-1 text-xs text-slate-500">
                                    {selectedLabelIds.length}
                                    {' '}selected
                                    {' · '}
                                    {pageLabelIds.length}
                                    {' '}labels on this page
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                <select
                                    value={bulkAction}
                                    onChange={(event) => {
                                        setBulkAction(
                                            event.target.value
                                        );
                                        setBulkRate('');
                                        setBulkManagerId('');
                                    }}
                                    className="min-w-[210px] rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700"
                                >
                                    <option value="">
                                        Select Bulk Action
                                    </option>

                                    <option value="set_rate">
                                        Set Label Rate
                                    </option>

                                    <option value="assign_manager">
                                        Assign Manager
                                    </option>

                                    <option value="unassign_manager">
                                        Unassign Manager
                                    </option>

                                    <option value="enable_withdrawal">
                                        Enable Withdrawal
                                    </option>

                                    <option value="suspend_withdrawal">
                                        Suspend Withdrawal
                                    </option>
                                </select>

                                {bulkAction === 'set_rate' && (
                                    <div className="relative">
                                        <input
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            value={bulkRate}
                                            onChange={(event) =>
                                                setBulkRate(
                                                    event.target.value
                                                )
                                            }
                                            placeholder="Rate"
                                            className="w-[120px] rounded-xl border border-slate-300 bg-white px-4 py-3 pr-8 text-sm font-semibold text-slate-700"
                                        />

                                        <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">
                                            %
                                        </span>
                                    </div>
                                )}

                                {bulkAction ===
                                    'assign_manager' && (
                                    <select
                                        value={bulkManagerId}
                                        onChange={(event) =>
                                            setBulkManagerId(
                                                event.target.value
                                            )
                                        }
                                        className="min-w-[190px] rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700"
                                    >
                                        <option value="">
                                            Select Manager
                                        </option>

                                        {admins.map(
                                            (admin) => (
                                                <option
                                                    key={admin.id}
                                                    value={admin.id}
                                                >
                                                    {admin.name}
                                                </option>
                                            )
                                        )}
                                    </select>
                                )}

                                <button
                                    type="button"
                                    onClick={runBulkAction}
                                    disabled={
                                        bulkSaving ||
                                        selectedLabelIds.length === 0 ||
                                        !bulkAction
                                    }
                                    className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {bulkSaving
                                        ? 'Applying...'
                                        : 'Apply'}
                                </button>

                                <button
                                    type="button"
                                    onClick={() =>
                                        setSelectedLabelIds([])
                                    }
                                    disabled={
                                        selectedLabelIds.length === 0
                                    }
                                    className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                                >
                                    Clear Selection
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                {role === 'super_admin' && (
                                    <th className="w-12 px-5 py-4">
                                        <input
                                            type="checkbox"
                                            checked={allPageSelected}
                                            onChange={toggleCurrentPage}
                                            aria-label="Select all labels on this page"
                                            className="h-4 w-4 rounded border-slate-300 text-violet-600"
                                        />
                                    </th>
                                )}

                                {[
                                    'Label',
                                    'Type',
                                    'Status',
                                    'Artists',
                                    'Releases',
                                    'Label Rate',
                                    'Assigned Managers',
                                    'Withdrawal',
                                    'Action',
                                ].map((heading) => (
                                    <th
                                        key={heading}
                                        className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                    >
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {(labels.data ?? []).map(
                                (label) => (
                                    <tr
                                        key={label.id}
                                        className={
                                            selectedLabelIds.includes(
                                                Number(label.id)
                                            )
                                                ? 'bg-violet-50/50'
                                                : ''
                                        }
                                    >
                                        {role === 'super_admin' && (
                                            <Cell>
                                                <input
                                                    type="checkbox"
                                                    checked={selectedLabelIds.includes(
                                                        Number(label.id)
                                                    )}
                                                    onChange={() =>
                                                        toggleLabelSelection(
                                                            label.id
                                                        )
                                                    }
                                                    aria-label={`Select ${label.name}`}
                                                    className="h-4 w-4 rounded border-slate-300 text-violet-600"
                                                />
                                            </Cell>
                                        )}

                                        <Cell>
                                            <div className="font-semibold text-slate-900">
                                                {label.name}
                                            </div>

                                            <div className="text-xs text-slate-500">
                                                {label.email}
                                            </div>
                                        </Cell>

                                        <Cell>
                                            {label.label_type}
                                        </Cell>

                                        <Cell>
                                            {label.status}
                                        </Cell>

                                        <Cell>
                                            <a
                                                href={`/v2/admin/artists?label_id=${label.id}`}
                                                className="font-semibold text-violet-600 hover:text-violet-800 hover:underline"
                                                title={`View artists for ${label.name}`}
                                            >
                                                {
                                                    label.artists_count
                                                }
                                            </a>
                                        </Cell>

                                        <Cell>
                                            <a
                                                href={`/v2/releases?label_id=${label.id}`}
                                                className="font-semibold text-violet-600 hover:text-violet-800 hover:underline"
                                                title={`View releases for ${label.name}`}
                                            >
                                                {
                                                    label.releases_count
                                                }
                                            </a>
                                        </Cell>

                                        <Cell>
                                            {role === 'super_admin' ? (
                                                <>
                                                    <LabelRateEditor
                                                        label={label}
                                                    />
                                                    {/* MIXXTUNE_LABEL_RATE_HISTORY_V1 */}
                                                    <LabelRateHistory
                                                        labelId={label.id}
                                                        labelName={label.name}
                                                    />
                                                </>
                                            ) : (
                                                <span className="font-medium text-slate-700">
                                                    {label.revenue_share_percentage === null ||
                                                    label.revenue_share_percentage === undefined
                                                        ? 'Not Set'
                                                        : `${Number(
                                                              label.revenue_share_percentage
                                                          ).toFixed(2)}%`}
                                                </span>
                                            )}
                                        </Cell>

                                        <Cell>
                                            {(
                                                label.assigned_admins ??
                                                []
                                            )
                                                .map(
                                                    (
                                                        admin
                                                    ) =>
                                                        admin.name
                                                )
                                                .join(', ') ||
                                                'Unassigned'}
                                        </Cell>
                                        <Cell>
                                            {role === 'super_admin' ? (
                                                <select
                                                    value={
                                                        label.can_withdraw
                                                            ? '1'
                                                            : '0'
                                                    }
                                                    onChange={(event) => {
                                                        router.patch(
                                                            `/v2/admin/labels/${label.id}/withdrawal-status`,
                                                            {
                                                                can_withdraw:
                                                                    event.target.value ===
                                                                    '1',
                                                            },
                                                            {
                                                                preserveScroll: true,
                                                            }
                                                        );
                                                    }}
                                                    className="min-w-[170px] rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700"
                                                    aria-label={`Withdrawal status for ${label.name}`}
                                                >
                                                    <option value="1">
                                                        Enabled
                                                    </option>
                                                    <option value="0">
                                                        Suspended
                                                    </option>
                                                </select>
                                            ) : (
                                                <span className="text-xs text-slate-400">
                                                    —
                                                </span>
                                            )}
                                        </Cell>

                                        <Cell>
                                            {role === 'super_admin' ? (
                                                <select
                                                    defaultValue={
                                                        label.assigned_admins?.[0]?.id ?? ''
                                                    }
                                                    onChange={(event) => {
                                                        router.patch(
                                                            `/v2/admin/labels/${label.id}/admin`,
                                                            {
                                                                admin_id:
                                                                    event.target.value || null,
                                                            },
                                                            {
                                                                preserveScroll: true,
                                                            }
                                                        );
                                                    }}
                                                    className="min-w-[170px] rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                                >
                                                    <option value="">Unassigned</option>
                                                    {admins.map((admin) => (
                                                        <option
                                                            key={admin.id}
                                                            value={admin.id}
                                                        >
                                                            {admin.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            ) : (
                                                <span className="text-xs text-slate-400">—</span>
                                            )}
                                        </Cell>

                                    </tr>
                                )
                            )}
                        </tbody>
                    </table>
                </section>

                {(labels.links ?? []).length > 3 && (
                    <div className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                        <div className="text-sm text-slate-500">
                            Showing{' '}
                            <span className="font-semibold text-slate-700">
                                {labels.from ?? 0}
                            </span>
                            {' '}to{' '}
                            <span className="font-semibold text-slate-700">
                                {labels.to ?? 0}
                            </span>
                            {' '}of{' '}
                            <span className="font-semibold text-slate-700">
                                {labels.total ?? 0}
                            </span>
                            {' '}labels
                        </div>

                        <Pagination links={labels.links ?? []} />
                    </div>
                )}
            </div>
        </PanelLayout>
    );
}

function FilterSelect({
    value,
    onChange,
    options = [],
}) {
    return (
        <select
            value={value}
            onChange={(event) =>
                onChange(event.target.value)
            }
            className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700"
        >
            {options.map(
                ([optionValue, optionLabel]) => (
                    <option
                        key={`${optionValue}-${optionLabel}`}
                        value={optionValue}
                    >
                        {optionLabel}
                    </option>
                )
            )}
        </select>
    );
}

function LabelRateEditor({ label }) {
    const initialValue =
        label.revenue_share_percentage === null ||
        label.revenue_share_percentage === undefined
            ? ''
            : String(label.revenue_share_percentage);

    const [value, setValue] = useState(initialValue);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setValue(
            label.revenue_share_percentage === null ||
            label.revenue_share_percentage === undefined
                ? ''
                : String(label.revenue_share_percentage)
        );
    }, [
        label.id,
        label.revenue_share_percentage,
    ]);

    const saveRate = () => {
        const trimmed = String(value).trim();

        if (trimmed === '') {
            window.alert(
                'Please enter a Label Rate between 0 and 100.'
            );
            return;
        }

        const rate = Number(trimmed);

        if (
            !Number.isFinite(rate) ||
            rate < 0 ||
            rate > 100
        ) {
            window.alert(
                'Label Rate must be between 0 and 100.'
            );
            return;
        }

        setSaving(true);

        router.patch(
            `/v2/admin/labels/${label.id}/revenue-rate`,
            {
                revenue_share_percentage: rate,
            },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
                onError: (errors) => {
                    if (errors?.action_otp) {
                        return; // MIXXTUNE_TWO_STEP_ACTIONS_V1: code box handles it
                    }

                    const message =
                        errors?.revenue_share_percentage ??
                        'Unable to update Label Rate.';

                    window.alert(message);
                },
            }
        );
    };

    return (
        <div className="flex min-w-[155px] items-center gap-2">
            <div className="relative">
                <input
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    value={value}
                    onChange={(event) =>
                        setValue(event.target.value)
                    }
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            saveRate();
                        }
                    }}
                    className="w-[90px] rounded-lg border border-slate-300 bg-white px-3 py-2 pr-7 text-sm font-semibold text-slate-700"
                    placeholder="Rate"
                    aria-label={`Revenue rate for ${label.name}`}
                />

                <span className="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">
                    %
                </span>
            </div>

            <button
                type="button"
                onClick={saveRate}
                disabled={saving}
                className="rounded-lg bg-violet-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {saving ? 'Saving...' : 'Save'}
            </button>
        </div>
    );
}


function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}

function Pagination({ links = [] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav
            className="flex flex-wrap items-center gap-1"
            aria-label="Labels pagination"
        >
            {links.map((link, index) => {
                const label = String(link.label ?? '')
                    .replace('&laquo;', '«')
                    .replace('&raquo;', '»');

                return link.url ? (
                    <button
                        key={`${label}-${index}`}
                        type="button"
                        onClick={() =>
                            router.visit(link.url, {
                                preserveScroll: true,
                                preserveState: true,
                            })
                        }
                        className={`min-w-10 rounded-lg border px-3 py-2 text-sm font-medium transition ${
                            link.active
                                ? 'border-slate-900 bg-slate-900 text-white'
                                : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                        }`}
                    >
                        {label}
                    </button>
                ) : (
                    <span
                        key={`${label}-${index}`}
                        className="min-w-10 cursor-not-allowed rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-center text-sm text-slate-300"
                    >
                        {label}
                    </span>
                );
            })}
        </nav>
    );
}
