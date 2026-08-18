<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UnmappedRevenueController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $month = trim((string) $request->input('month', ''));

        $sort = (string) $request->input(
            'sort',
            'revenue'
        );

        $direction = strtolower(
            (string) $request->input(
                'direction',
                'desc'
            )
        );

        $allowedSorts = [
            'track' => 'track_title',
            'label' => 'report_label',
            'rows' => 'rows_count',
            'revenue' => 'revenue',
        ];

        if (!array_key_exists($sort, $allowedSorts)) {
            $sort = 'revenue';
        }

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $base = DB::table('report_rows')
            ->where('mapping_status', 'unmapped')
            ->whereNotNull('isrc')
            ->where('isrc', '!=', '');

        if ($month !== '') {
            $base->where('sale_month', $month);
        }

        if ($search !== '') {
            $base->where(function ($query) use ($search) {
                $like = '%'.$search.'%';

                $query
                    ->where('isrc', 'like', $like)
                    ->orWhere('upc', 'like', $like)
                    ->orWhere('track_title', 'like', $like)
                    ->orWhere('track_artist', 'like', $like)
                    ->orWhere('label_name', 'like', $like);
            });
        }

        /*
         * Keep the filtered base query untouched.
         * Table sorting/grouping must run on its own clone so summary
         * queries do not inherit GROUP BY / ORDER BY aliases.
         */
        /*
         * Group the UI by normalized ISRC, not by the raw report
         * formatting. Therefore:
         *
         * IN-D71-22-11104
         * IN D71 22 11104
         * IND712211104
         *
         * are displayed as one financial identity.
         */
        $normalizedIsrcSql = "
            REGEXP_REPLACE(
                UPPER(TRIM(isrc)),
                '[^A-Z0-9]',
                ''
            )
        ";

        $rows = (clone $base)
            ->selectRaw("
                {$normalizedIsrcSql} as normalized_isrc,
                MIN(isrc) as isrc,
                MAX(upc) as upc,
                MAX(track_title) as track_title,
                MAX(track_artist) as track_artist,
                MAX(label_name) as report_label,
                MIN(sale_month) as first_month,
                MAX(sale_month) as last_month,
                COUNT(*) as rows_count,
                SUM(earnings) as revenue
            ")
            ->groupByRaw($normalizedIsrcSql)
            ->orderBy(
                $allowedSorts[$sort],
                $direction
            )
            ->orderBy('normalized_isrc')
            ->paginate(50)
            ->withQueryString();

        $identifiers = collect($rows->items())
            ->pluck('isrc')
            ->filter()
            ->map(fn ($value) => $this->normalizeCode($value))
            ->values();

        $rules = DB::table('revenue_mapping_rules')
            ->where('identifier_type', 'isrc')
            ->whereIn('identifier_value', $identifiers)
            ->get()
            ->keyBy('identifier_value');

        $rows->setCollection(
            $rows->getCollection()->map(function ($row) use ($rules) {
                $normalized = $this->normalizeCode($row->isrc);

                $row->normalized_isrc = $normalized;
                $row->rule = $rules->get($normalized);

                return $row;
            })
        );

        $labels = DB::table('labels')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'public_id',
                'parent_label_id',
                'label_type',
            ]);

        $artists = DB::table('artists')
            ->whereNull('deleted_at')
            ->orderBy('stage_name')
            ->get([
                'id',
                'stage_name',
                'label_id',
                'public_id',
            ]);

        $months = DB::table('report_rows')
            ->where('mapping_status', 'unmapped')
            ->whereNotNull('sale_month')
            ->distinct()
            ->orderByDesc('sale_month')
            ->pluck('sale_month');

        /*
         * Summary must use exactly the same filters as the table.
         * Clone $base before aggregation so search/month stay consistent.
         */
        $summaryBase = clone $base;

        $summary = [
            'rows' => (clone $summaryBase)->count(),

            'revenue' => (float) (clone $summaryBase)
                ->sum('earnings'),

            'unique_isrc' => (clone $summaryBase)
                ->selectRaw("
                    {$normalizedIsrcSql} as normalized_isrc
                ")
                ->distinct()
                ->count(
                    DB::raw($normalizedIsrcSql)
                ),

            'rules' => DB::table('revenue_mapping_rules')->count(),
        ];

        return Inertia::render(
            'V2/Admin/UnmappedRevenue/Index',
            [
                'rows' => $rows,
                'labels' => $labels,
                'artists' => $artists,
                'months' => $months,
                'summary' => $summary,
                'filters' => [
                    'sort' => $sort,
                    'direction' => $direction,
                    'search' => $search,
                    'month' => $month,
                ],
            ]
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'isrc' => [
                'required',
                'string',
                'max:191',
            ],

            'catalogue_label_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'artist_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $identifier =
            $this->normalizeCode(
                $data['isrc']
            );

        abort_if(
            $identifier === '',
            422,
            'Invalid ISRC.'
        );

        $catalogueLabelId =
            (int) $data[
                'catalogue_label_id'
            ];

        $artistId =
            !empty($data['artist_id'])
                ? (int) $data['artist_id']
                : null;

        $this->validateHierarchyTarget(
            $catalogueLabelId,
            $artistId
        );

        $financialOwnerId =
            app(
                \App\Services\V2\LabelHierarchyService::class
            )->rootLabelId(
                $catalogueLabelId
            );

        DB::transaction(
            function () use (
                $data,
                $identifier,
                $request,
                $catalogueLabelId,
                $artistId,
                $financialOwnerId
            ) {
                $userId =
                    $request->user()?->id;

                DB::table(
                    'revenue_mapping_rules'
                )->updateOrInsert(
                    [
                        'identifier_type' =>
                            'isrc',

                        'identifier_value' =>
                            $identifier,
                    ],
                    [
                        /*
                         * Financial owner is always
                         * the hierarchy root/master.
                         */
                        'owner_type' =>
                            'label',

                        'owner_id' =>
                            $financialOwnerId,

                        /*
                         * Catalogue placement remains
                         * the exact selected level.
                         */
                        'catalogue_label_id' =>
                            $catalogueLabelId,

                        'artist_id' =>
                            $artistId,

                        'source' =>
                            'manual_hierarchy',

                        'notes' =>
                            $data['notes']
                                ?? null,

                        'updated_by' =>
                            $userId,

                        'updated_at' =>
                            now(),

                        'created_by' =>
                            $userId,

                        'created_at' =>
                            now(),
                    ]
                );

                $this->applyIsrcRule(
                    $identifier,
                    'label',
                    $financialOwnerId,
                    $catalogueLabelId,
                    $artistId
                );
            }
        );

        return back()->with(
            'success',
            'ISRC hierarchy mapping saved and existing report rows updated.'
        );
    }

    public function createAndMapLabel(Request $request)
    {
        $data = $request->validate([
            'isrc' => [
                'required',
                'string',
                'max:191',
            ],

            'label_name' => [
                'required',
                'string',
                'max:255',
            ],

            'royalty_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
        ]);

        $clickedIdentifier =
            $this->normalizeCode(
                $data['isrc']
            );

        abort_if(
            $clickedIdentifier === '',
            422,
            'Invalid ISRC.'
        );

        $labelName =
            trim($data['label_name']);

        abort_if(
            $labelName === '',
            422,
            'Label name is required.'
        );

        /*
         * Safety:
         * The clicked row must still exist as unmapped.
         */
        $sourceExists =
            DB::table('report_rows')
                ->where(
                    'mapping_status',
                    'unmapped'
                )
                ->whereRaw(
                    'LOWER(TRIM(label_name)) = ?',
                    [mb_strtolower($labelName)]
                )
                ->whereRaw(
                    "
                    REGEXP_REPLACE(
                        UPPER(TRIM(isrc)),
                        '[^A-Z0-9]',
                        ''
                    ) = ?
                    ",
                    [$clickedIdentifier]
                )
                ->exists();

        abort_unless(
            $sourceExists,
            422,
            'The selected label/ISRC is no longer unmapped.'
        );

        /*
         * Do not create duplicate labels.
         *
         * Existing labels must use the normal hierarchy
         * mapping flow instead.
         */
        $existingLabel =
            DB::table('labels')
                ->whereNull('deleted_at')
                ->whereRaw(
                    'LOWER(TRIM(name)) = ?',
                    [mb_strtolower($labelName)]
                )
                ->first([
                    'id',
                    'name',
                ]);

        if ($existingLabel) {
            return back()->withErrors([
                'label_name' =>
                    'Label already exists as "'.
                    $existingLabel->name.
                    '" (ID '.
                    $existingLabel->id.
                    '). Select the existing label instead.',
            ]);
        }

        /*
         * Collect ALL currently-unmapped ISRCs belonging
         * to this exact report label.
         */
        $sourceRows =
            DB::table('report_rows')
                ->where(
                    'mapping_status',
                    'unmapped'
                )
                ->whereRaw(
                    'LOWER(TRIM(label_name)) = ?',
                    [mb_strtolower($labelName)]
                )
                ->get([
                    'id',
                    'isrc',
                ]);

        abort_if(
            $sourceRows->isEmpty(),
            422,
            'No unmapped report rows remain for this label.'
        );

        $identifiers =
            $sourceRows
                ->pluck('isrc')
                ->map(
                    fn ($value) =>
                        $this->normalizeCode($value)
                )
                ->filter(
                    fn ($value) =>
                        $value !== ''
                )
                ->unique()
                ->values();

        abort_if(
            $identifiers->isEmpty(),
            422,
            'No valid ISRCs were found for this label.'
        );

        /*
         * Critical collision guard.
         *
         * Since this operation creates a NEW label,
         * none of its ISRCs may already have permanent
         * ownership rules.
         */
        $conflictingRules =
            DB::table('revenue_mapping_rules')
                ->where(
                    'identifier_type',
                    'isrc'
                )
                ->whereIn(
                    'identifier_value',
                    $identifiers->all()
                )
                ->get([
                    'identifier_value',
                    'owner_type',
                    'owner_id',
                    'catalogue_label_id',
                    'artist_id',
                ]);

        if ($conflictingRules->isNotEmpty()) {
            $sample =
                $conflictingRules
                    ->pluck('identifier_value')
                    ->take(5)
                    ->implode(', ');

            return back()->withErrors([
                'label_name' =>
                    $conflictingRules->count().
                    ' ISRC(s) already have permanent mapping rules. '.
                    'Nothing was changed. Review conflicts first. '.
                    ($sample !== ''
                        ? 'Example: '.$sample
                        : ''),
            ]);
        }

        $royaltyPercentage =
            isset($data['royalty_percentage'])
                ? (float)
                    $data['royalty_percentage']
                : 100.0;

        $userId =
            $request->user()?->id;

        $result =
            DB::transaction(
                function () use (
                    $labelName,
                    $royaltyPercentage,
                    $userId,
                    $identifiers
                ) {
                    /*
                     * Duplicate-name recheck under lock.
                     */
                    $duplicate =
                        DB::table('labels')
                            ->whereNull(
                                'deleted_at'
                            )
                            ->whereRaw(
                                'LOWER(TRIM(name)) = ?',
                                [
                                    mb_strtolower(
                                        $labelName
                                    ),
                                ]
                            )
                            ->lockForUpdate()
                            ->first([
                                'id',
                                'name',
                            ]);

                    if ($duplicate) {
                        abort(
                            422,
                            'Label already exists. Select the existing label.'
                        );
                    }

                    /*
                     * Rule collision recheck inside
                     * transaction.
                     */
                    $ruleConflict =
                        DB::table(
                            'revenue_mapping_rules'
                        )
                            ->where(
                                'identifier_type',
                                'isrc'
                            )
                            ->whereIn(
                                'identifier_value',
                                $identifiers->all()
                            )
                            ->lockForUpdate()
                            ->exists();

                    abort_if(
                        $ruleConflict,
                        422,
                        'One or more ISRC mapping rules changed. Nothing was created.'
                    );

                    $baseSlug =
                        Str::slug($labelName);

                    if ($baseSlug === '') {
                        $baseSlug = 'label';
                    }

                    $slug = $baseSlug;
                    $counter = 1;

                    while (
                        DB::table('labels')
                            ->where(
                                'slug',
                                $slug
                            )
                            ->exists()
                    ) {
                        $slug =
                            $baseSlug.
                            '-'.
                            $counter;

                        $counter++;
                    }

                    $now = now();

                    $labelId =
                        DB::table('labels')
                            ->insertGetId([
                                'public_id' =>
                                    (string)
                                        Str::ulid(),

                                'label_type' =>
                                    'label',

                                'name' =>
                                    $labelName,

                                'slug' =>
                                    $slug,

                                'royalty_share_percentage' =>
                                    $royaltyPercentage,

                                'parent_commission_percentage' =>
                                    0,

                                'status' =>
                                    'active',

                                'created_at' =>
                                    $now,

                                'updated_at' =>
                                    $now,
                            ]);

                    /*
                     * Build permanent rules for every
                     * unique ISRC belonging to the
                     * report label.
                     */
                    $ruleRows = [];

                    foreach (
                        $identifiers as $identifier
                    ) {
                        $ruleRows[] = [
                            'identifier_type' =>
                                'isrc',

                            'identifier_value' =>
                                $identifier,

                            'owner_type' =>
                                'label',

                            'owner_id' =>
                                $labelId,

                            'catalogue_label_id' =>
                                $labelId,

                            'artist_id' =>
                                null,

                            'track_id' =>
                                null,

                            'release_id' =>
                                null,

                            'source' =>
                                'bulk_create_and_map_label',

                            'notes' =>
                                'Bulk created from Unmapped Revenue label: '.
                                $labelName,

                            'created_by' =>
                                $userId,

                            'updated_by' =>
                                $userId,

                            'created_at' =>
                                $now,

                            'updated_at' =>
                                $now,
                        ];
                    }

                    foreach (
                        array_chunk(
                            $ruleRows,
                            100
                        ) as $chunk
                    ) {
                        DB::table(
                            'revenue_mapping_rules'
                        )->insert($chunk);
                    }

                    $rulesCreated =
                        DB::table(
                            'revenue_mapping_rules'
                        )
                            ->where(
                                'identifier_type',
                                'isrc'
                            )
                            ->whereIn(
                                'identifier_value',
                                $identifiers->all()
                            )
                            ->where(
                                'owner_type',
                                'label'
                            )
                            ->where(
                                'owner_id',
                                $labelId
                            )
                            ->count();

                    if (
                        $rulesCreated !==
                        $identifiers->count()
                    ) {
                        throw new \RuntimeException(
                            'Rule creation verification failed.'
                        );
                    }

                    /*
                     * Map ONLY rows belonging to this
                     * exact report label.
                     *
                     * This intentionally does NOT use
                     * applyIsrcRule(), because that
                     * helper maps every unmapped row
                     * sharing an ISRC regardless of
                     * report label.
                     */
                    $updated =
                        DB::table('report_rows')
                            ->where(
                                'mapping_status',
                                'unmapped'
                            )
                            ->whereRaw(
                                'LOWER(TRIM(label_name)) = ?',
                                [
                                    mb_strtolower(
                                        $labelName
                                    ),
                                ]
                            )
                            ->whereRaw(
                                "
                                REGEXP_REPLACE(
                                    UPPER(TRIM(isrc)),
                                    '[^A-Z0-9]',
                                    ''
                                ) IN (".
                                implode(
                                    ',',
                                    array_fill(
                                        0,
                                        $identifiers
                                            ->count(),
                                        '?'
                                    )
                                ).
                                ")
                                ",
                                $identifiers->all()
                            )
                            ->update([
                                'label_id' =>
                                    $labelId,

                                'artist_id' =>
                                    null,

                                'revenue_owner_type' =>
                                    'label',

                                'revenue_owner_id' =>
                                    $labelId,

                                'mapping_status' =>
                                    'mapped',

                                'mapped_at' =>
                                    $now,

                                'updated_at' =>
                                    $now,
                            ]);

                    if (
                        $updated !==
                        $sourceRowsCount =
                            DB::table(
                                'report_rows'
                            )
                                ->where(
                                    'label_id',
                                    $labelId
                                )
                                ->whereRaw(
                                    'LOWER(TRIM(label_name)) = ?',
                                    [
                                        mb_strtolower(
                                            $labelName
                                        ),
                                    ]
                                )
                                ->where(
                                    'mapping_status',
                                    'mapped'
                                )
                                ->where(
                                    'mapped_at',
                                    $now
                                )
                                ->count()
                    ) {
                        throw new \RuntimeException(
                            'Mapped-row verification failed.'
                        );
                    }

                    return [
                        'label_id' =>
                            $labelId,

                        'rules_created' =>
                            $rulesCreated,

                        'updated' =>
                            $updated,
                    ];
                }
            );

        return back()->with(
            'success',
            'Label "'.
            $labelName.
            '" created (ID '.
            $result['label_id'].
            ') with '.
            $result['rules_created'].
            ' permanent ISRC rule(s), and '.
            $result['updated'].
            ' report row(s) mapped.'
        );
    }

    public function applyAll(Request $request)
    {
        $rules =
            DB::table(
                'revenue_mapping_rules'
            )
                ->where(
                    'identifier_type',
                    'isrc'
                )
                ->orderBy('id')
                ->get();

        $updated = 0;

        DB::transaction(
            function () use (
                $rules,
                &$updated
            ) {
                foreach ($rules as $rule) {
                    $updated +=
                        $this->applyIsrcRule(
                            $rule
                                ->identifier_value,

                            $rule->owner_type,

                            (int)
                                $rule->owner_id,

                            !empty(
                                $rule
                                    ->catalogue_label_id
                            )
                                ? (int)
                                    $rule
                                        ->catalogue_label_id
                                : null,

                            !empty(
                                $rule->artist_id
                            )
                                ? (int)
                                    $rule
                                        ->artist_id
                                : null
                        );
                }
            }
        );

        return back()->with(
            'success',
            $updated.
            ' report rows mapped from saved ISRC rules.'
        );
    }

    private function applyIsrcRule(
        string $identifier,
        string $ownerType,
        int $ownerId,
        ?int $catalogueLabelId = null,
        ?int $artistId = null
    ): int {
        $values = [
            'revenue_owner_type' =>
                $ownerType,

            'revenue_owner_id' =>
                $ownerId,

            'mapping_status' =>
                'mapped',

            'mapped_at' =>
                now(),

            'updated_at' =>
                now(),
        ];

        /*
         * Legacy rules:
         * catalogue_label_id = NULL.
         *
         * They keep their historical behaviour.
         *
         * Hierarchy-aware rules additionally set
         * exact catalogue placement.
         */
        if ($catalogueLabelId !== null) {
            $values['label_id'] =
                $catalogueLabelId;

            $values['artist_id'] =
                $artistId;
        }

        return DB::table('report_rows')
            ->where(
                'mapping_status',
                'unmapped'
            )
            ->whereRaw(
                "
                REGEXP_REPLACE(
                    UPPER(TRIM(isrc)),
                    '[^A-Z0-9]',
                    ''
                ) = ?
                ",
                [$identifier]
            )
            ->update($values);
    }

    private function validateHierarchyTarget(
        int $catalogueLabelId,
        ?int $artistId
    ): void {
        $labelExists =
            DB::table('labels')
                ->where(
                    'id',
                    $catalogueLabelId
                )
                ->whereNull('deleted_at')
                ->exists();

        abort_unless(
            $labelExists,
            422,
            'Selected catalogue level does not exist.'
        );

        if ($artistId === null) {
            return;
        }

        $artist =
            DB::table('artists')
                ->where(
                    'id',
                    $artistId
                )
                ->whereNull('deleted_at')
                ->first([
                    'id',
                    'label_id',
                ]);

        abort_unless(
            $artist,
            422,
            'Selected artist does not exist.'
        );

        $allowedLabelIds =
            app(
                \App\Services\V2\LabelHierarchyService::class
            )->descendantIds(
                $catalogueLabelId,
                true
            );

        $allowedLabelIds =
            collect(
                $allowedLabelIds
            )
                ->map(
                    fn ($id) =>
                        (int) $id
                );

        abort_unless(
            $artist->label_id
            && $allowedLabelIds->contains(
                (int) $artist->label_id
            ),
            422,
            'Selected artist is outside the selected catalogue hierarchy.'
        );
    }

    private function normalizeCode(?string $value): string
    {
        return strtoupper(
            preg_replace(
                '/[^A-Z0-9]/i',
                '',
                trim((string) $value)
            )
        );
    }
}
