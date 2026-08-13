<?php

namespace App\Http\Controllers\V2\Label;

use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Finance\LabelRevenueShare;
use App\Services\V2\LabelRevenueShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RevenueSharingController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $master = $this->masterLabel(
            $request
        );

        /*
         * Revenue Sharing is opt-in.
         *
         * A direct Sub-Label / Artist does NOT become a
         * revenue beneficiary merely because the account exists.
         *
         * Only records explicitly stored in
         * label_revenue_shares are shown as beneficiaries.
         */
        $shares = LabelRevenueShare::query()
            ->where(
                'master_label_id',
                $master->id
            )
            ->orderBy('beneficiary_type')
            ->orderBy('id')
            ->get();

        $configuredLabelIds = $shares
            ->where('beneficiary_type', 'label')
            ->pluck('beneficiary_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $configuredArtistIds = $shares
            ->where('beneficiary_type', 'artist')
            ->pluck('beneficiary_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $configuredLabels = Label::query()
            ->where(
                'parent_label_id',
                $master->id
            )
            ->whereNull('deleted_at')
            ->whereIn(
                'id',
                $configuredLabelIds
            )
            ->get()
            ->keyBy('id');

        $configuredArtists = Artist::query()
            ->where(
                'label_id',
                $master->id
            )
            ->whereNull('deleted_at')
            ->whereIn(
                'id',
                $configuredArtistIds
            )
            ->get()
            ->keyBy('id');

        $beneficiaries = $shares
            ->map(function (
                LabelRevenueShare $share
            ) use (
                $configuredLabels,
                $configuredArtists
            ) {
                if (
                    $share->beneficiary_type === 'label'
                ) {
                    $label = $configuredLabels->get(
                        (int) $share->beneficiary_id
                    );

                    if (! $label) {
                        return null;
                    }

                    return $this->row(
                        'label',
                        $label->id,
                        $label->name,
                        $label->status,
                        $share
                    );
                }

                if (
                    $share->beneficiary_type === 'artist'
                ) {
                    $artist = $configuredArtists->get(
                        (int) $share->beneficiary_id
                    );

                    if (! $artist) {
                        return null;
                    }

                    return $this->row(
                        'artist',
                        $artist->id,
                        $artist->stage_name,
                        $artist->account_status,
                        $share
                    );
                }

                return null;
            })
            ->filter()
            ->values();

        /*
         * Eligible accounts for the Add controls.
         * Already-configured beneficiaries are excluded.
         */
        $availableSubLabels = Label::query()
            ->where(
                'parent_label_id',
                $master->id
            )
            ->whereNull('deleted_at')
            ->whereNotIn(
                'id',
                $configuredLabelIds
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'status',
            ])
            ->map(fn (Label $label) => [
                'id' => $label->id,
                'name' => $label->name,
                'status' => $label->status,
            ])
            ->values();

        $availableArtists = Artist::query()
            ->where(
                'label_id',
                $master->id
            )
            ->whereNull('deleted_at')
            ->whereNotIn(
                'id',
                $configuredArtistIds
            )
            ->orderBy('stage_name')
            ->get([
                'id',
                'stage_name',
                'account_status',
            ])
            ->map(fn (Artist $artist) => [
                'id' => $artist->id,
                'name' => $artist->stage_name,
                'status' => $artist->account_status,
            ])
            ->values();

        $revenueReport =
            $this->buildRevenueReport(
                $master,
                $beneficiaries
            );

        return Inertia::render(
            'V2/Label/RevenueSharing/Index',
            [
                'master' => [
                    'id' => $master->id,
                    'name' => $master->name,
                    'currency' =>
                        $master->currency
                        ?: 'INR',
                ],

                'beneficiaries' =>
                    $beneficiaries,

                'availableSubLabels' =>
                    $availableSubLabels,

                'availableArtists' =>
                    $availableArtists,

                'revenueReport' =>
                    $revenueReport,
            ]
        );
    }

    public function export(
        Request $request,
        ?string $type = null,
        ?int $id = null
    ) {
        $master = $this->masterLabel(
            $request
        );

        if (
            $type !== null
            && ! in_array(
                $type,
                [
                    'artist',
                    'label',
                ],
                true
            )
        ) {
            abort(404);
        }

        $beneficiaries =
            $this->reportBeneficiaries(
                $master
            );

        $filename =
            'revenue-beneficiary-report-'
            .now()->format('Ymd-His')
            .'.csv';

        return response()->streamDownload(
            function () use (
                $master,
                $beneficiaries,
                $type,
                $id
            ) {
                $handle = fopen(
                    'php://output',
                    'w'
                );

                /*
                 * UTF-8 BOM for Excel compatibility.
                 */
                fwrite(
                    $handle,
                    "\xEF\xBB\xBF"
                );

                fputcsv(
                    $handle,
                    [
                        'Sale Date',
                        'Sale Month',
                        'Beneficiary Type',
                        'Beneficiary',
                        'Track',
                        'Track Artist',
                        'ISRC',
                        'UPC',
                        'Platform',
                        'Country',
                        'Currency',
                        'Streams',
                        'Managed Revenue',
                        'Share %',
                        'Beneficiary Payable',
                        'Master Retained',
                    ]
                );

                foreach (
                    $this->reportQuery(
                        $master
                    )->cursor()
                    as $row
                ) {
                    $beneficiary =
                        $this->resolveBeneficiary(
                            $beneficiaries,
                            $row
                        );

                    if (! $beneficiary) {
                        continue;
                    }

                    if (
                        ! $this->shareAppliesToRow(
                            $beneficiary,
                            $row
                        )
                    ) {
                        continue;
                    }

                    if (
                        $type !== null
                        && $beneficiary['type']
                            !== $type
                    ) {
                        continue;
                    }

                    if (
                        $id !== null
                        && (int)
                            $beneficiary['id']
                            !== (int) $id
                    ) {
                        continue;
                    }

                    $calc =
                        $this->calculateRevenueRow(
                            $beneficiary,
                            $row
                        );

                    fputcsv(
                        $handle,
                        [
                            $row->sale_date,
                            $row->sale_month,
                            $beneficiary['type'],
                            $beneficiary['name'],
                            $row->track_title,
                            $row->track_artist,
                            $row->isrc,
                            $row->upc,
                            $row->platform,
                            $row->country_code,
                            $row->currency,
                            $row->streams,
                            number_format(
                                $calc[
                                    'managed_revenue'
                                ],
                                8,
                                '.',
                                ''
                            ),
                            number_format(
                                $calc[
                                    'share_percent'
                                ],
                                4,
                                '.',
                                ''
                            ),
                            number_format(
                                $calc[
                                    'beneficiary_payable'
                                ],
                                8,
                                '.',
                                ''
                            ),
                            number_format(
                                $calc[
                                    'master_retained'
                                ],
                                8,
                                '.',
                                ''
                            ),
                        ]
                    );
                }

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' =>
                    'text/csv; charset=UTF-8',
            ]
        );
    }

    private function buildRevenueReport(
        Label $master,
        $beneficiaries
    ): array {
        $beneficiaryMap =
            $beneficiaries
                ->mapWithKeys(
                    fn (array $item) => [
                        $item['type']
                        .':'
                        .$item['id']
                        => $item,
                    ]
                )
                ->all();

        $summary = [
            'managed_revenue' => 0.0,
            'beneficiary_payable' => 0.0,
            'master_retained' => 0.0,
            'streams' => 0.0,
        ];

        $grouped = [];
        $displayRows = [];

        foreach (
            $this->reportQuery(
                $master
            )->cursor()
            as $row
        ) {
            $beneficiary =
                $this->resolveBeneficiary(
                    $beneficiaryMap,
                    $row
                );

            if (! $beneficiary) {
                continue;
            }

            if (
                ! $this->shareAppliesToRow(
                    $beneficiary,
                    $row
                )
            ) {
                continue;
            }

            $calc =
                $this->calculateRevenueRow(
                    $beneficiary,
                    $row
                );

            $key =
                $beneficiary['type']
                .':'
                .$beneficiary['id'];

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'type' =>
                        $beneficiary['type'],

                    'id' =>
                        (int)
                            $beneficiary['id'],

                    'name' =>
                        $beneficiary['name'],

                    'share_percent' =>
                        $calc[
                            'share_percent'
                        ],

                    'managed_revenue' =>
                        0.0,

                    'beneficiary_payable' =>
                        0.0,

                    'master_retained' =>
                        0.0,

                    'streams' =>
                        0.0,

                    'track_ids' =>
                        [],
                ];
            }

            foreach (
                [
                    'managed_revenue',
                    'beneficiary_payable',
                    'master_retained',
                    'streams',
                ]
                as $field
            ) {
                $summary[$field] +=
                    $calc[$field];

                $grouped[
                    $key
                ][$field] +=
                    $calc[$field];
            }

            if ($row->track_id) {
                $grouped[
                    $key
                ]['track_ids'][
                    (string)
                        $row->track_id
                ] = true;
            }

            /*
             * Browser page: latest 100 rows only.
             * CSV export remains unlimited.
             */
            if (
                count(
                    $displayRows
                ) < 100
            ) {
                $displayRows[] = [
                    'id' =>
                        $row->id,

                    'sale_date' =>
                        $row->sale_date,

                    'sale_month' =>
                        $row->sale_month,

                    'type' =>
                        $beneficiary['type'],

                    'beneficiary_id' =>
                        (int)
                            $beneficiary['id'],

                    'beneficiary' =>
                        $beneficiary['name'],

                    'track_title' =>
                        $row->track_title
                        ?: 'Untitled Track',

                    'track_artist' =>
                        $row->track_artist,

                    'isrc' =>
                        $row->isrc,

                    'upc' =>
                        $row->upc,

                    'platform' =>
                        $row->platform,

                    'country_code' =>
                        $row->country_code,

                    'currency' =>
                        $row->currency
                        ?: (
                            $master->currency
                            ?: 'INR'
                        ),

                    'streams' =>
                        $calc['streams'],

                    'managed_revenue' =>
                        $calc[
                            'managed_revenue'
                        ],

                    'share_percent' =>
                        $calc[
                            'share_percent'
                        ],

                    'beneficiary_payable' =>
                        $calc[
                            'beneficiary_payable'
                        ],

                    'master_retained' =>
                        $calc[
                            'master_retained'
                        ],
                ];
            }
        }

        $beneficiaryRows =
            collect($grouped)
                ->map(
                    function (
                        array $item
                    ) {
                        $item['track_count'] =
                            count(
                                $item[
                                    'track_ids'
                                ]
                            );

                        unset(
                            $item[
                                'track_ids'
                            ]
                        );

                        return $item;
                    }
                )
                ->sortByDesc(
                    'managed_revenue'
                )
                ->values()
                ->all();

        return [
            'summary' =>
                $summary,

            'beneficiaries' =>
                $beneficiaryRows,

            'rows' =>
                $displayRows,

            'display_limit' =>
                100,
        ];
    }

    private function reportBeneficiaries(
        Label $master
    ): array {
        $shares =
            LabelRevenueShare::query()
                ->where(
                    'master_label_id',
                    $master->id
                )
                ->where(
                    'is_active',
                    true
                )
                ->get();

        $labels =
            Label::query()
                ->where(
                    'parent_label_id',
                    $master->id
                )
                ->whereNull(
                    'deleted_at'
                )
                ->get()
                ->keyBy('id');

        $artists =
            Artist::query()
                ->where(
                    'label_id',
                    $master->id
                )
                ->whereNull(
                    'deleted_at'
                )
                ->get()
                ->keyBy('id');

        $result = [];

        foreach ($shares as $share) {
            if (
                $share->beneficiary_type
                === 'artist'
            ) {
                $artist =
                    $artists->get(
                        $share
                            ->beneficiary_id
                    );

                if (! $artist) {
                    continue;
                }

                $name =
                    $artist
                        ->stage_name;
            } elseif (
                $share->beneficiary_type
                === 'label'
            ) {
                $label =
                    $labels->get(
                        $share
                            ->beneficiary_id
                    );

                if (! $label) {
                    continue;
                }

                $name =
                    $label->name;
            } else {
                continue;
            }

            $key =
                $share->beneficiary_type
                .':'
                .$share->beneficiary_id;

            $result[$key] = [
                'type' =>
                    $share
                        ->beneficiary_type,

                'id' =>
                    (int)
                        $share
                            ->beneficiary_id,

                'name' =>
                    $name,

                'revenue_share_percent' =>
                    (float)
                        $share
                            ->revenue_share_percent,

                'is_active' =>
                    (bool)
                        $share
                            ->is_active,

                'effective_from' =>
                    $share
                        ->effective_from
                        ?->format(
                            'Y-m-d'
                        ),

                'effective_to' =>
                    $share
                        ->effective_to
                        ?->format(
                            'Y-m-d'
                        ),
            ];
        }

        return $result;
    }

    private function reportQuery(
        Label $master
    ) {
        return DB::table(
            'report_rows'
        )
            ->where(
                'revenue_owner_type',
                'label'
            )
            ->where(
                'revenue_owner_id',
                $master->id
            )
            ->where(
                'mapping_status',
                'mapped'
            )
            ->select([
                'id',
                'track_id',
                'artist_id',
                'label_id',
                'track_artist',
                'track_title',
                'isrc',
                'upc',
                'platform',
                'currency',
                'country_code',
                'sale_date',
                'sale_month',
                'streams',
                'earnings',
            ])
            ->orderByDesc(
                'sale_date'
            )
            ->orderByDesc(
                'id'
            );
    }

    private function resolveBeneficiary(
        array $beneficiaries,
        object $row
    ): ?array {
        /*
         * Artist takes precedence where the
         * report row is mapped directly to
         * a master-owned Artist.
         */
        if ($row->artist_id) {
            $key =
                'artist:'
                .$row->artist_id;

            if (
                isset(
                    $beneficiaries[
                        $key
                    ]
                )
            ) {
                return
                    $beneficiaries[
                        $key
                    ];
            }
        }

        /*
         * Otherwise check whether revenue
         * belongs to a direct Sub-Label.
         */
        if ($row->label_id) {
            $key =
                'label:'
                .$row->label_id;

            if (
                isset(
                    $beneficiaries[
                        $key
                    ]
                )
            ) {
                return
                    $beneficiaries[
                        $key
                    ];
            }
        }

        return null;
    }

    private function shareAppliesToRow(
        array $beneficiary,
        object $row
    ): bool {
        if (
            empty(
                $beneficiary[
                    'is_active'
                ]
            )
        ) {
            return false;
        }

        /*
         * MIXX TUNE MONTHLY REVENUE RULE
         *
         * DSP royalty reports are allocated by sale month.
         * If sale_month exists, revenue-share effective dates
         * are compared at YYYY-MM level.
         *
         * Example:
         * sale_month     = 2026-08
         * effective_from = 2026-08-11
         *
         * Result: INCLUDED because both belong to August 2026.
         */
        if (! empty($row->sale_month)) {
            $rowMonth = substr(
                (string) $row->sale_month,
                0,
                7
            );

            if (
                ! empty(
                    $beneficiary[
                        'effective_from'
                    ]
                )
            ) {
                $fromMonth = substr(
                    (string)
                        $beneficiary[
                            'effective_from'
                        ],
                    0,
                    7
                );

                if ($rowMonth < $fromMonth) {
                    return false;
                }
            }

            if (
                ! empty(
                    $beneficiary[
                        'effective_to'
                    ]
                )
            ) {
                $toMonth = substr(
                    (string)
                        $beneficiary[
                            'effective_to'
                        ],
                    0,
                    7
                );

                if ($rowMonth > $toMonth) {
                    return false;
                }
            }

            return true;
        }

        /*
         * Fallback for reports without sale_month:
         * use exact sale_date boundaries.
         */
        if (! empty($row->sale_date)) {
            $date = substr(
                (string) $row->sale_date,
                0,
                10
            );

            if (
                ! empty(
                    $beneficiary[
                        'effective_from'
                    ]
                )
                && $date <
                    $beneficiary[
                        'effective_from'
                    ]
            ) {
                return false;
            }

            if (
                ! empty(
                    $beneficiary[
                        'effective_to'
                    ]
                )
                && $date >
                    $beneficiary[
                        'effective_to'
                    ]
            ) {
                return false;
            }
        }

        return true;
    }

    private function calculateRevenueRow(
        array $beneficiary,
        object $row
    ): array {
        $managed =
            (float)
                ($row->earnings ?? 0);

        $share = min(
            100,
            max(
                0,
                (float)
                    $beneficiary[
                        'revenue_share_percent'
                    ]
            )
        );

        $payable = round(
            $managed
            * ($share / 100),
            8
        );

        $retained = round(
            $managed
            - $payable,
            8
        );

        return [
            'managed_revenue' =>
                $managed,

            'share_percent' =>
                $share,

            'beneficiary_payable' =>
                $payable,

            'master_retained' =>
                $retained,

            'streams' =>
                (float)
                    ($row->streams ?? 0),
        ];
    }

    public function update(
        Request $request,
        string $type,
        int $id,
        LabelRevenueShareService $service
    ): RedirectResponse {
        $master = $this->masterLabel(
            $request
        );

        abort_unless(
            in_array(
                $type,
                [
                    'label',
                    'artist',
                ],
                true
            ),
            404
        );

        $validated = $request->validate([
            'revenue_share_percent' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'show_revenue_share' => [
                'required',
                'boolean',
            ],
        ]);

        if ($type === 'label') {
            $child = Label::query()
                ->whereKey($id)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $service->saveForLabel(
                $master,
                $child,
                (float) $validated[
                    'revenue_share_percent'
                ],
                (bool) $validated[
                    'show_revenue_share'
                ],
                $request->user()
            );
        } else {
            $artist = Artist::query()
                ->whereKey($id)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $service->saveForArtist(
                $master,
                $artist,
                (float) $validated[
                    'revenue_share_percent'
                ],
                (bool) $validated[
                    'show_revenue_share'
                ],
                $request->user()
            );
        }

        return back()->with(
            'success',
            'Revenue share updated.'
        );
    }

    public function destroy(
        Request $request,
        LabelRevenueShare $share
    ): RedirectResponse {
        $master = $this->masterLabel(
            $request
        );

        abort_unless(
            (int) $share->master_label_id
                === (int) $master->id,
            403
        );

        $share->delete();

        return back()->with(
            'success',
            'Revenue beneficiary removed.'
        );
    }

    public function toggle(
        Request $request,
        LabelRevenueShare $share
    ): RedirectResponse {
        $master = $this->masterLabel(
            $request
        );

        abort_unless(
            (int) $share->master_label_id
                === (int) $master->id,
            403
        );

        $share->update([
            'is_active' =>
                ! $share->is_active,

            'updated_by' =>
                $request->user()->id,
        ]);

        return back()->with(
            'success',
            $share->is_active
                ? 'Revenue share activated.'
                : 'Revenue share deactivated.'
        );
    }

    private function masterLabel(
        Request $request
    ): Label {
        $user = $request->user();

        abort_unless(
            $user,
            401,
            'Authentication required.'
        );

        abort_unless(
            $user->role === 'label',
            403,
            'Label access required.'
        );

        $label = Label::query()
            ->where(
                'user_id',
                $user->id
            )
            ->whereNull('deleted_at')
            ->firstOrFail();

        abort_if(
            $label->status !== 'active',
            403,
            'Label account is not active.'
        );

        /*
         * Two-tier contract:
         *
         * Master
         *   ├── Sub Label
         *   └── Artist
         *
         * A sub-label can never manage
         * another revenue hierarchy.
         */
        abort_if(
            $label->parent_label_id !== null,
            403,
            'Revenue sharing is available only to master labels.'
        );

        return $label;
    }

    private function row(
        string $type,
        int $id,
        string $name,
        ?string $status,
        ?LabelRevenueShare $share
    ): array {
        $percent = $share
            ? (float)
                $share->revenue_share_percent
            : 0.0;

        return [
            'type' => $type,
            'id' => $id,
            'name' => $name,
            'status' => $status,

            'share_id' =>
                $share?->id,

            'share_configured' =>
                $share !== null,

            'revenue_share_percent' =>
                $percent,

            'master_share_percent' =>
                100 - $percent,

            'show_revenue_share' =>
                $share
                    ? (bool)
                        $share
                            ->show_revenue_share
                    : true,

            'is_active' =>
                $share
                    ? (bool)
                        $share->is_active
                    : false,

            'effective_from' =>
                $share?->effective_from
                    ?->format('Y-m-d'),

            'effective_to' =>
                $share?->effective_to
                    ?->format('Y-m-d'),
        ];
    }
}
