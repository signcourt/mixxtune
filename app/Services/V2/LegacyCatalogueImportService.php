<?php

namespace App\Services\V2;

use App\Models\Catalogue\LegacyCatalogueImport;
use App\Models\Catalogue\LegacyCatalogueImportRow;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\Core\Label;
use App\Models\Core\Artist;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LegacyCatalogueImportService
{
    private const REQUIRED_HEADERS = [
        'label_name',
        'release_title',
        'release_type',
        'primary_artist',
        'track_title',
        'isrc',
        'upc',
    ];

    public function stage(
        UploadedFile $file,
        $user
    ): LegacyCatalogueImport {
        $extension = strtolower(
            $file->getClientOriginalExtension()
        );

        if (!in_array($extension, ['csv', 'xlsx', 'xls'], true)) {
            throw new \RuntimeException(
                'Only CSV, XLSX and XLS files are allowed.'
            );
        }

        $storedPath = $file->store(
            'legacy-catalogue/imports',
            'local'
        );

        $import = LegacyCatalogueImport::query()->create([
            'public_id' => (string) Str::ulid(),
            'original_filename' =>
                $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'status' => 'validating',
            'total_rows' => 0,
            'ready_rows' => 0,
            'blocked_rows' => 0,
            'warning_rows' => 0,
            'imported_rows' => 0,
            'failed_rows' => 0,
            'uploaded_by' => $user->id,
        ]);

        try {
            $this->parseAndValidate($import);

            return $import->fresh();
        } catch (\Throwable $e) {
            $import->update([
                'status' => 'failed',
                'failure_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function revalidate(
        LegacyCatalogueImport $import
    ): LegacyCatalogueImport {
        $import->refresh();

        if ((int) $import->imported_rows > 0) {
            throw new \RuntimeException(
                'Cannot revalidate an import that has already imported catalogue rows.'
            );
        }

        if (
            $import->rows()
                ->whereNotNull('release_id')
                ->exists()
            ||
            $import->rows()
                ->whereNotNull('track_id')
                ->exists()
        ) {
            throw new \RuntimeException(
                'Cannot revalidate an import with existing release/track mappings.'
            );
        }

        if (
            $import->rows()
                ->where('import_status', '!=', 'pending')
                ->exists()
        ) {
            throw new \RuntimeException(
                'Cannot revalidate an import containing non-pending staging rows.'
            );
        }

        if (
            blank($import->stored_path)
            ||
            !Storage::disk('local')
                ->exists($import->stored_path)
        ) {
            throw new \RuntimeException(
                'Original legacy catalogue import file is missing.'
            );
        }

        DB::transaction(function () use ($import) {
            $import->rows()->delete();

            $import->forceFill([
                'status' => 'validating',
                'total_rows' => 0,
                'ready_rows' => 0,
                'blocked_rows' => 0,
                'warning_rows' => 0,
                'imported_rows' => 0,
                'failed_rows' => 0,
                'summary' => null,
                'failure_message' => null,
                'validated_at' => null,
                'import_started_at' => null,
                'import_completed_at' => null,
            ])->save();
        });

        try {
            $this->parseAndValidate(
                $import->fresh()
            );
        } catch (\Throwable $e) {
            $import->refresh();

            $import->forceFill([
                'status' => 'failed',
                'failure_message' =>
                    $e->getMessage(),
            ])->save();

            throw $e;
        }

        return $import->fresh();
    }

    private function parseAndValidate(
        LegacyCatalogueImport $import
    ): void {
        $fullPath = Storage::disk('local')->path(
            $import->stored_path
        );

        $spreadsheet = IOFactory::load($fullPath);

        $sheet = $spreadsheet->getActiveSheet();

        $data = $sheet->toArray(
            null,
            true,
            true,
            false
        );

        if (count($data) < 2) {
            throw new \RuntimeException(
                'Import file contains no catalogue rows.'
            );
        }

        $headers = array_map(
            fn ($value) => $this->normalizeHeader($value),
            array_shift($data)
        );

        $this->validateHeaders($headers);

        $total = 0;
        $ready = 0;
        $blocked = 0;
        $warning = 0;

        DB::transaction(function () use (
            $import,
            $data,
            $headers,
            &$total,
            &$ready,
            &$blocked,
            &$warning
        ) {
            foreach ($data as $index => $values) {
                if ($this->rowIsEmpty($values)) {
                    continue;
                }

                $rowNumber = $index + 2;

                $raw = [];

                foreach ($headers as $column => $header) {
                    if ($header === '') {
                        continue;
                    }

                    $raw[$header] =
                        $this->clean($values[$column] ?? null);
                }

                $result = $this->validateRow(
                    $raw,
                    $rowNumber,
                      $import->id
                );

                $resolvedLabelId =
                    $this->resolveLabelId(
                        $raw['label_name'] ?? null
                    );

                $resolvedArtistId =
                    $this->resolveArtistId(
                        $raw['primary_artist'] ?? null,
                        $resolvedLabelId
                    );

                $resolutionWarnings = [];

                if (!$resolvedLabelId) {
                    $resolutionWarnings[] =
                        'NEW LABEL NEEDED — no exact existing label match.';
                }

                if (
                    $resolvedLabelId
                    && !$resolvedArtistId
                ) {
                    $resolutionWarnings[] =
                        'NEW ARTIST NEEDED — no exact artist match under resolved label.';
                }

                $result['warnings'] = array_values(
                    array_unique(
                        array_merge(
                            $result['warnings'],
                            $resolutionWarnings
                        )
                    )
                );

                if (
                    $result['status'] !== 'blocked'
                    && $result['warnings'] !== []
                ) {
                    $result['status'] = 'warning';
                }

                LegacyCatalogueImportRow::query()->create([
                    'legacy_catalogue_import_id' =>
                        $import->id,

                    'row_number' => $rowNumber,

                    'label_name' =>
                        $raw['label_name'] ?? null,

                    'release_title' =>
                        $raw['release_title'] ?? null,

                    'release_type' =>
                        $raw['release_type'] ?? null,

                    'primary_artist' =>
                        $raw['primary_artist'] ?? null,

                    'featuring_artists' =>
                        $raw['featuring_artists'] ?? null,

                    'track_title' =>
                        $raw['track_title'] ?? null,

                    'disc_number' =>
                        $this->integerOrDefault(
                            $raw['disc_number'] ?? null,
                            1
                        ),

                    'track_number' =>
                        $this->integerOrDefault(
                            $raw['track_number'] ?? null,
                            1
                        ),

                    'isrc' =>
                        $this->identifier(
                            $raw['isrc'] ?? null
                        ),

                    'upc' =>
                        $this->identifier(
                            $raw['upc'] ?? null
                        ),

                    'language' =>
                        $raw['language'] ?? null,

                    'primary_genre' =>
                        $raw['primary_genre'] ?? null,

                    'sub_genre' =>
                        $raw['sub_genre'] ?? null,

                    'original_release_date' =>
                        $this->dateOrNull(
                            $raw['original_release_date']
                                ?? null
                        ),

                    'digital_release_date' =>
                        $this->dateOrNull(
                            $raw['digital_release_date']
                                ?? null
                        ),

                    'copyright_owner' =>
                        $raw['copyright_owner'] ?? null,

                    'copyright_year' =>
                        $this->integerOrNull(
                            $raw['copyright_year'] ?? null
                        ),

                    'phonographic_owner' =>
                        $raw['phonographic_owner'] ?? null,

                    'phonographic_year' =>
                        $this->integerOrNull(
                            $raw['phonographic_year'] ?? null
                        ),

                    'artwork_filename' =>
                        $raw['artwork_filename'] ?? null,

                    'territory' =>
                        $raw['territory'] ?? 'Worldwide',

                    'validation_status' =>
                        $result['status'],

                    'validation_errors' =>
                        $result['errors'],

                    'validation_warnings' =>
                        $result['warnings'],

                    'resolved_label_id' =>
                        $resolvedLabelId,

                    'resolved_artist_id' =>
                        $resolvedArtistId,

                    'import_status' => 'pending',

                    'raw_data' => $raw,
                ]);

                $total++;

                if ($result['status'] === 'blocked') {
                    $blocked++;
                } elseif ($result['status'] === 'warning') {
                    $warning++;
                } else {
                    $ready++;
                }
            }

            $import->update([
                'status' =>
                    $blocked > 0
                        ? 'validated_with_blocks'
                        : 'validated',

                'total_rows' => $total,
                'ready_rows' => $ready,
                'blocked_rows' => $blocked,
                'warning_rows' => $warning,

                'summary' => [
                    'audio_import' => false,
                    'identifier_generation' => false,
                    'missing_isrc_policy' => 'warning_keep_null',
                    'missing_upc_policy' => 'warning_keep_null',
                    'missing_artwork_policy' => 'warning',
                ],

                'validated_at' => now(),
            ]);
        });
    }

    private function validateHeaders(array $headers): void
    {
        $missing = array_values(
            array_diff(self::REQUIRED_HEADERS, $headers)
        );

        if ($missing !== []) {
            throw new \RuntimeException(
                'Missing required columns: '
                . implode(', ', $missing)
            );
        }
    }

    private function validateRow(
        array $row,
        int $rowNumber,
        int $importId
    ): array {
        $errors = [];
        $warnings = [];

        foreach ([
            'label_name',
            'release_title',
            'primary_artist',
            'track_title',
        ] as $field) {
            if (blank($row[$field] ?? null)) {
                $errors[] =
                    "Missing required field: {$field}";
            }
        }

        $isrc = $this->identifier(
            $row['isrc'] ?? null
        );

        $upc = $this->identifier(
            $row['upc'] ?? null
        );

        /*
         * IMPORTANT:
         * Legacy identifiers are NEVER generated.
         */
        if (!$isrc) {
            $warnings[] =
                'ISRC MISSING — manual assignment required.';
        }

        if (!$upc) {
            $warnings[] =
                'UPC MISSING — manual assignment required.';
        }

        if ($isrc) {
            $existingTrack = Track::query()
                ->where('isrc', $isrc)
                ->exists();

            if ($existingTrack) {
                $errors[] =
                    "ISRC already exists in catalogue: {$isrc}";
            }

            $duplicateStage =
                LegacyCatalogueImportRow::query()
                    ->where('legacy_catalogue_import_id', $importId)
                    ->where('isrc', $isrc)
                    ->exists();

            if ($duplicateStage) {
                $errors[] =
                    "Duplicate ISRC in legacy staging: {$isrc}";
            }
        }

        if ($upc) {
            /*
             * Same UPC is allowed across multiple tracks
             * belonging to one release.
             *
             * Existing release UPC is blocked because
             * this importer must not silently overwrite
             * an existing catalogue release.
             */
            $existingRelease = Release::query()
                ->where('upc', $upc)
                ->exists();

            if ($existingRelease) {
                $errors[] =
                    "UPC already exists in catalogue: {$upc}";
            }
        }

        $artworkFilename = trim(
            (string) ($row['artwork_filename'] ?? '')
        );

        if ($artworkFilename === '') {
            $warnings[] =
                'Artwork missing — may be added manually later.';
        } else {
            $artworkCandidate =
                'releases/artwork/'
                .basename($artworkFilename);

            if (
                !Storage::disk('public')
                    ->exists($artworkCandidate)
            ) {
                $warnings[] =
                    'Artwork file not found — may be added manually later.';
            }
        }

        $releaseType = strtolower(
            trim((string) ($row['release_type'] ?? ''))
        );

        if (
            $releaseType !== ''
            && !in_array(
                $releaseType,
                ['single', 'ep', 'album'],
                true
            )
        ) {
            $errors[] =
                'release_type must be single, ep or album.';
        }

        if ($errors !== []) {
            $status = 'blocked';
        } elseif ($warnings !== []) {
            $status = 'warning';
        } else {
            $status = 'ready';
        }

        return [
            'status' => $status,
            'errors' => $errors,
            'warnings' => $warnings,
            'row_number' => $rowNumber,
        ];
    }

    /**
     * Resolve a legacy label by normalized exact name.
     *
     * IMPORTANT:
     * This method never creates a label.
     */

    public function dryRunImport(
        LegacyCatalogueImport $import
    ): array {
        $rows = $import->rows()
            ->orderBy('row_number')
            ->get();

        $result = [
            'total_rows' => $rows->count(),

            'release_groups' => 0,
            'track_candidates' => 0,

            'existing_releases' => 0,
            'new_releases' => 0,

            'existing_tracks' => 0,
            'new_tracks' => 0,

            'missing_upc_rows' => 0,
            'missing_isrc_rows' => 0,

            'unresolved_label_rows' => 0,
            'unresolved_artist_rows' => 0,

            'artwork_matched_groups' => 0,
            'artwork_missing_groups' => 0,

            'upc_conflicts' => [],
            'isrc_conflicts' => [],

            'release_preview' => [],
        ];

        /*
         * Track-level analysis.
         */
        foreach ($rows as $row) {
            if (!$row->upc) {
                $result['missing_upc_rows']++;
            }

            if (!$row->isrc) {
                $result['missing_isrc_rows']++;
            }

            if (!$row->resolved_label_id) {
                $result['unresolved_label_rows']++;
            }

            if (!$row->resolved_artist_id) {
                $result['unresolved_artist_rows']++;
            }

            if ($row->isrc) {
                $existingTrack = Track::query()
                    ->where(
                        'isrc',
                        $row->isrc
                    )
                    ->first();

                if ($existingTrack) {
                    $result['existing_tracks']++;
                } else {
                    $result['new_tracks']++;
                }
            } else {
                /*
                 * Missing ISRC remains manual.
                 * Track can still be represented in dry run.
                 */
                $result['new_tracks']++;
            }

            $result['track_candidates']++;
        }

        /*
         * Detect duplicate staged ISRC values.
         */
        $duplicateIsrcs = $rows
            ->filter(
                fn ($row) =>
                    !blank($row->isrc)
            )
            ->groupBy(
                fn ($row) =>
                    strtoupper(
                        trim((string) $row->isrc)
                    )
            )
            ->filter(
                fn ($group) =>
                    $group->count() > 1
            );

        foreach (
            $duplicateIsrcs as $isrc => $group
        ) {
            $result['isrc_conflicts'][] = [
                'isrc' => $isrc,
                'rows' =>
                    $group
                        ->pluck('row_number')
                        ->values()
                        ->all(),
            ];
        }

        /*
         * Release grouping.
         *
         * Primary key:
         *   UPC
         *
         * Missing UPC fallback:
         *   resolved label + artist + release title
         *
         * UPC itself remains NULL and is never generated.
         */
        /*
         * LEGACY HISTORICAL IMPORT POLICY:
         *
         * One spreadsheet row represents one historical release.
         * UPC must NOT be used as the release grouping key because
         * legacy source data can reuse the same UPC across different
         * release titles.
         *
         * UPC is preserved as metadata only.
         */
        $groups = $rows->groupBy(
            fn ($row) =>
                'ROW:'
                .$row->id
                .':'
                .$row->row_number
        );

        $result['release_groups'] =
            $groups->count();

        foreach ($groups as $key => $group) {
            $first = $group->first();

            $upcs = $group
                ->pluck('upc')
                ->filter()
                ->map(
                    fn ($value) =>
                        strtoupper(
                            trim(
                                (string) $value
                            )
                        )
                )
                ->unique()
                ->values();

            $titles = $group
                ->pluck('release_title')
                ->filter()
                ->map(
                    fn ($value) =>
                        mb_strtolower(
                            trim(
                                (string) $value
                            ),
                            'UTF-8'
                        )
                )
                ->unique()
                ->values();

            $labels = $group
                ->pluck('resolved_label_id')
                ->filter()
                ->unique()
                ->values();

            /*
             * Same UPC must never describe
             * multiple releases/labels.
             */
            $hasConflict =
                $upcs->count() > 1
                || $titles->count() > 1
                || $labels->count() > 1;

            if ($hasConflict) {
                $result['upc_conflicts'][] = [
                    'group_key' => $key,
                    'upcs' => $upcs->all(),
                    'titles' => $titles->all(),
                    'label_ids' => $labels->all(),
                    'rows' =>
                        $group
                            ->pluck('row_number')
                            ->values()
                            ->all(),
                ];
            }

            $upc = $upcs->first();

            $existingRelease = null;

            if ($upc) {
                $existingRelease =
                    Release::query()
                        ->where(
                            'upc',
                            $upc
                        )
                        ->first();
            }

            if ($existingRelease) {
                $result['existing_releases']++;
            } else {
                $result['new_releases']++;
            }

            /*
             * Artwork match.
             *
             * Excel value must correspond to an
             * actual filename already present in:
             * storage/app/public/releases/artwork
             */
            $artworkFilename =
                trim(
                    (string) (
                        $first->artwork_filename
                        ?? ''
                    )
                );

            $artworkPath = null;

            if ($artworkFilename !== '') {
                $candidate =
                    'releases/artwork/'
                    .basename(
                        $artworkFilename
                    );

                if (
                    \Illuminate\Support\Facades\Storage
                        ::disk('public')
                        ->exists($candidate)
                ) {
                    $artworkPath = $candidate;
                    $result[
                        'artwork_matched_groups'
                    ]++;
                } else {
                    $result[
                        'artwork_missing_groups'
                    ]++;
                }
            } else {
                $result[
                    'artwork_missing_groups'
                ]++;
            }

            $result['release_preview'][] = [
                'group_key' => $key,

                'upc' => $upc,

                'release_title' =>
                    $first->release_title,

                'release_type' =>
                    $first->release_type,

                'label_id' =>
                    $first->resolved_label_id,

                'artist_id' =>
                    $first->resolved_artist_id,

                'primary_artist' =>
                    $first->primary_artist,

                'track_count' =>
                    $group->count(),

                'artwork_filename' =>
                    $artworkFilename
                    ?: null,

                'artwork_path' =>
                    $artworkPath,

                'existing_release_id' =>
                    $existingRelease?->id,

                'status' =>
                    $hasConflict
                        ? 'conflict'
                        : (
                            $existingRelease
                                ? 'existing'
                                : 'new'
                        ),
            ];
        }

        return $result;
    }

    public function approveMissingEntities(
        LegacyCatalogueImport $import,
        $user
    ): array {
        $createdLabels = 0;
        $createdArtists = 0;

        DB::transaction(function () use (
            $import,
            $user,
            &$createdLabels,
            &$createdArtists
        ) {
            $rows = $import->rows()
                ->orderBy('row_number')
                ->lockForUpdate()
                ->get();

            /*
             * PASS 1: labels
             */
            foreach ($rows as $row) {
                if (!$row->label_name) {
                    continue;
                }

                $labelId = $this->resolveLabelId(
                    $row->label_name
                );

                if (!$labelId) {
                    $label = $this->createCatalogueOnlyLabel(
                        $row->label_name,
                        (int) $user->id
                    );

                    $createdLabels++;
                    $labelId = (int) $label->id;
                }

                $row->resolved_label_id = $labelId;
                $row->save();
            }

            /*
             * PASS 2: artists
             */
            foreach ($rows as $row) {
                if (
                    !$row->primary_artist
                    || !$row->resolved_label_id
                ) {
                    continue;
                }

                $artistId = $this->resolveArtistId(
                    $row->primary_artist,
                    (int) $row->resolved_label_id
                );

                if (!$artistId) {
                    $artist =
                        $this->createCatalogueOnlyArtist(
                            $row->primary_artist,
                            (int) $row->resolved_label_id,
                            (int) $user->id
                        );

                    $createdArtists++;
                    $artistId = (int) $artist->id;
                }

                $row->resolved_artist_id = $artistId;
                $row->save();
            }

            /*
             * PASS 3: refresh validation warnings.
             * Do not alter hard validation errors.
             */
            foreach ($rows as $row) {
                $warnings = collect(
                    $row->validation_warnings ?? []
                )
                    ->reject(
                        fn ($message) =>
                            str_contains(
                                (string) $message,
                                'NEW LABEL NEEDED'
                            )
                            || str_contains(
                                (string) $message,
                                'NEW ARTIST NEEDED'
                            )
                    )
                    ->values()
                    ->all();

                $errors =
                    $row->validation_errors ?? [];

                if ($errors !== []) {
                    $status = 'blocked';
                } elseif ($warnings !== []) {
                    $status = 'warning';
                } else {
                    $status = 'ready';
                }

                $row->update([
                    'validation_warnings' => $warnings,
                    'validation_status' => $status,
                ]);
            }

            $import->refresh();

            $import->update([
                'ready_rows' =>
                    $import->rows()
                        ->where(
                            'validation_status',
                            'ready'
                        )
                        ->count(),

                'warning_rows' =>
                    $import->rows()
                        ->where(
                            'validation_status',
                            'warning'
                        )
                        ->count(),

                'blocked_rows' =>
                    $import->rows()
                        ->where(
                            'validation_status',
                            'blocked'
                        )
                        ->count(),
            ]);
        });

        return [
            'created_labels' => $createdLabels,
            'created_artists' => $createdArtists,
        ];
    }

    private function createCatalogueOnlyLabel(
        string $name,
        int $userId
    ): Label {
        $existing = $this->resolveLabelId($name);

        if ($existing) {
            return Label::query()->findOrFail($existing);
        }

        $slugBase = Str::slug($name);

        if ($slugBase === '') {
            $slugBase = 'legacy-label';
        }

        $slug = $slugBase;

        $suffix = 1;

        while (
            Label::withTrashed()
                ->where('slug', $slug)
                ->exists()
        ) {
            $suffix++;
            $slug = $slugBase.'-'.$suffix;
        }

        do {
            $publicId =
                'LBL-'.Str::upper(
                    Str::random(12)
                );
        } while (
            Label::withTrashed()
                ->where(
                    'public_id',
                    $publicId
                )
                ->exists()
        );

        return Label::query()->create([
            'user_id' => null,
            'parent_label_id' => null,
            'label_type' => 'label',
            'public_id' => $publicId,
            'name' => trim($name),
            'legal_name' => trim($name),
            'slug' => $slug,
            'email' => null,
            'phone' => null,
            'country' => 'India',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'payout_cycle' => 'monthly',
            'minimum_withdrawal_amount' => 5000,
            'royalty_share_percentage' => 100,
            'parent_commission_percentage' => 0,
            'status' => 'active',
            'can_access_catalogue' => true,
            'can_access_royalties' => true,
            'can_access_reports' => true,
            'can_access_wallet' => true,
            'can_withdraw' => false,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }


    /**
     * Permanently import a previously validated legacy catalogue.
     *
     * Safety:
     * - only processing imports may run
     * - blocked rows stop the import
     * - one database transaction
     * - staging rows retain created IDs
     * - missing UPC/ISRC remain null
     * - no audio is created
     * - repeated execution is rejected
     */
    public function importValidatedCatalogue(
        LegacyCatalogueImport $import,
        $user
    ): LegacyCatalogueImport {
        if ($import->status !== 'processing') {
            throw new \RuntimeException(
                'Only processing imports may run.'
            );
        }

        $activeBlockedRows =
            LegacyCatalogueImportRow::query()
                ->where(
                    'legacy_catalogue_import_id',
                    $import->id
                )
                ->where('is_excluded', false)
                ->where('validation_status', 'blocked')
                ->count();

        if ($activeBlockedRows > 0) {
            throw new \RuntimeException(
                'Active blocked rows exist. Import stopped.'
            );
        }

        if ((int) $import->imported_rows > 0) {
            throw new \RuntimeException(
                'Catalogue already imported.'
            );
        }

        $userId = (int) $user->id;

        return DB::transaction(function () use (
            $import,
            $userId
        ) {
            $lockedImport =
                LegacyCatalogueImport::query()
                ->whereKey($import->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedImport->status !== 'processing') {
                throw new \RuntimeException(
                    'Import state changed.'
                );
            }

            if ((int) $lockedImport->imported_rows > 0) {
                throw new \RuntimeException(
                    'Catalogue already imported.'
                );
            }

            $rows =
                LegacyCatalogueImportRow::query()
                ->where(
                    'legacy_catalogue_import_id',
                    $lockedImport->id
                )
                ->where('is_excluded', false)
                ->orderBy('row_number')
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                throw new \RuntimeException(
                    'No staging rows found.'
                );
            }

            /*
             * Never run if staging already points
             * to catalogue records.
             */
            foreach ($rows as $row) {
                if (
                    $row->release_id !== null
                    || $row->track_id !== null
                    || $row->import_status !== 'pending'
                ) {
                    throw new \RuntimeException(
                        'Row already processed: '
                        .$row->row_number
                    );
                }

                if (
                    $row->validation_status === 'blocked'
                ) {
                    throw new \RuntimeException(
                        'Blocked row encountered: '
                        .$row->row_number
                    );
                }

                if (
                    !in_array(
                        $row->validation_status,
                        ['ready', 'warning'],
                        true
                    )
                ) {
                    throw new \RuntimeException(
                        'Invalid validation state at row '
                        .$row->row_number
                    );
                }
            }

            $releaseCache = [];
            $importedRows = 0;

            foreach ($rows as $row) {
                $releaseTitle =
                    trim(
                        (string) $row->release_title
                    );

                $trackTitle =
                    trim(
                        (string) $row->track_title
                    );

                $artistName =
                    trim(
                        (string) $row->primary_artist
                    );

                if (
                    $releaseTitle === ''
                    || $trackTitle === ''
                    || $artistName === ''
                ) {
                    throw new \RuntimeException(
                        'Required metadata missing at row '
                        .$row->row_number
                    );
                }

                $labelId =
                    $row->resolved_label_id
                        ? (int) $row->resolved_label_id
                        : null;

                $artistId =
                    $row->resolved_artist_id
                        ? (int) $row->resolved_artist_id
                        : null;

                if (!$labelId || !$artistId) {
                    throw new \RuntimeException(
                        'Label/artist unresolved at row '
                        .$row->row_number
                    );
                }

                /*
                 * NEVER GENERATE UPC.
                 */
                $upc = trim(
                    (string) ($row->upc ?? '')
                );

                /*
                 * Group tracks belonging to the same
                 * release.
                 *
                 * UPC is strongest key when available.
                 * Missing UPC uses stable metadata key.
                 */
                /*
                 * LEGACY HISTORICAL IMPORT POLICY:
                 *
                 * One staging row = one Release.
                 * UPC is metadata only and never merges rows.
                 */
                $releaseKey =
                    'row:'
                    .$row->id
                    .':'
                    .$row->row_number;

                if (
                    !isset(
                        $releaseCache[$releaseKey]
                    )
                ) {
                    /*
                     * Artwork is metadata-only.
                     * Missing file remains null.
                     */
                    $artworkPath = null;

                    $artworkFilename = trim(
                        (string) (
                            $row->artwork_filename
                            ?? ''
                        )
                    );

                    if ($artworkFilename !== '') {
                        $candidate =
                            'releases/artwork/'
                            .basename(
                                $artworkFilename
                            );

                        if (
                            Storage::disk('public')
                                ->exists($candidate)
                        ) {
                            $artworkPath =
                                $candidate;
                        }
                    }

                    do {
                        $catalogNumber =
                            'LEG-'
                            .Str::upper(
                                Str::random(12)
                            );
                    } while (
                        Release::withTrashed()
                            ->where(
                                'catalog_number',
                                $catalogNumber
                            )
                            ->exists()
                    );

                    $release =
                        Release::query()->create([
                            'public_id' =>
                                (string) Str::ulid(),

                            'catalog_number' =>
                                $catalogNumber,

                            'artist_id' =>
                                $artistId,

                            'label_id' =>
                                $labelId,

                            'release_type' =>
                                trim(
                                    (string) (
                                        $row->release_type
                                        ?: 'single'
                                    )
                                ),

                            'title' =>
                                $releaseTitle,

                            'primary_artist_name' =>
                                $artistName,

                            'primary_artists' => [
                                [
                                    'name' =>
                                        $artistName,

                                    'spotify_url' =>
                                        null,

                                    'apple_music_url' =>
                                        null,

                                    'youtube_topic_url' =>
                                        null,
                                ],
                            ],

                            'featuring_artist_name' =>
                                $row->featuring_artists
                                ?: null,

                            'featuring_artists' =>
                                $row->featuring_artists
                                    ? [
                                        [
                                            'name' =>
                                                trim(
                                                    (string)
                                                    $row
                                                    ->featuring_artists
                                                ),
                                        ],
                                    ]
                                    : [],

                            'language' =>
                                $row->language
                                ?: null,

                            'primary_genre' =>
                                $row->primary_genre
                                ?: null,

                            'sub_genre' =>
                                $row->sub_genre
                                ?: null,

                            /*
                             * Missing UPC stays NULL.
                             */
                            'upc' =>
                                $upc !== ''
                                    ? $upc
                                    : null,

                            'upc_is_auto_generated' =>
                                false,

                            'original_release_date' =>
                                $row
                                ->original_release_date,

                            'digital_release_date' =>
                                $row
                                ->digital_release_date,

                            'copyright_owner' =>
                                $row->copyright_owner
                                ?: null,

                            'copyright_year' =>
                                $row->copyright_year
                                ?: null,

                            'phonographic_owner' =>
                                $row
                                ->phonographic_owner
                                ?: null,

                            'phonographic_year' =>
                                $row
                                ->phonographic_year
                                ?: null,

                            'artwork_path' =>
                                $artworkPath,

                            /*
                             * LEGACY CATALOGUE POLICY:
                             * Historical catalogue is already released.
                             * Missing artwork/audio/identifiers may be
                             * completed manually by Super Admin later.
                             */
                            'status' =>
                                'live',

                            'approved_at' =>
                                now(),

                            'approved_by' =>
                                $userId,

                            'live_at' =>
                                now(),

                            'worldwide' =>
                                true,

                            'release_timezone' =>
                                'Asia/Kolkata',

                            'pre_order' =>
                                false,

                            'wizard_step' =>
                                1,

                            'completion_percentage' =>
                                0,

                            'created_by' =>
                                $userId,

                            'updated_by' =>
                                $userId,
                        ]);

                    $releaseCache[
                        $releaseKey
                    ] = $release;
                } else {
                    $release =
                        $releaseCache[
                            $releaseKey
                        ];
                }

                /*
                 * NEVER GENERATE ISRC.
                 */
                $isrc = trim(
                    (string) ($row->isrc ?? '')
                );

                $track =
                    Track::query()->create([
                        'public_id' =>
                            (string) Str::ulid(),

                        'release_id' =>
                            $release->id,

                        'disc_number' =>
                            max(
                                1,
                                (int)
                                $row->disc_number
                            ),

                        'track_number' =>
                            max(
                                1,
                                (int)
                                $row->track_number
                            ),

                        'title' =>
                            $trackTitle,

                        'track_type' =>
                            'original',

                        'primary_artist_name' =>
                            $artistName,

                        'featuring_artist_name' =>
                            $row->featuring_artists
                            ?: null,

                        'isrc' =>
                            $isrc !== ''
                                ? $isrc
                                : null,

                        'isrc_is_auto_generated' =>
                            false,

                        'language' =>
                            $row->language
                            ?: null,

                        'genre' =>
                            $row->primary_genre
                            ?: null,

                        'sub_genre' =>
                            $row->sub_genre
                            ?: null,

                        /*
                         * LEGACY AUDIO POLICY:
                         * absolutely no audio attachment.
                         */
                        'audio_path' =>
                            null,

                        'audio_original_name' =>
                            null,

                        'audio_mime_type' =>
                            null,

                        'audio_size_bytes' =>
                            null,

                        /*
                         * Missing legacy audio is allowed.
                         * Super Admin may attach the master later.
                         */
                        'audio_validation_status' =>
                            'pending',

                        /*
                         * Track belongs to an already-released
                         * historical catalogue item.
                         */
                        'status' =>
                            'live',

                        'created_by' =>
                            $userId,

                        'updated_by' =>
                            $userId,
                    ]);

                /*
                 * Existing staging audit columns.
                 */
                $row->forceFill([
                    'release_id' =>
                        $release->id,

                    'track_id' =>
                        $track->id,

                    'import_status' =>
                        'imported',

                    'import_error' =>
                        null,

                    'imported_at' =>
                        now(),
                ])->save();

                $importedRows++;
            }

            /*
             * LEGACY CATALOGUE SYNC
             *
             * All tracks have now been created. Sync each unique
             * release exactly once so CatalogueItem receives the
             * final track count / ISRC count and becomes visible.
             */
            $catalogueService =
                app(CatalogueService::class);

            foreach ($releaseCache as $release) {
                $catalogueService->syncRelease(
                    $release->fresh(),
                    \App\Models\User::find($userId)
                );
            }

            $lockedImport->forceFill([
                'status' =>
                    'imported',

                'imported_rows' =>
                    $importedRows,

                'failed_rows' =>
                    0,
            ])->save();

            return $lockedImport->fresh();
        });
    }

    private function createCatalogueOnlyArtist(
        string $name,
        int $labelId,
        int $userId
    ): Artist {
        $existing = $this->resolveArtistId(
            $name,
            $labelId
        );

        if ($existing) {
            return Artist::query()->findOrFail($existing);
        }

        $slugBase = Str::slug($name);

        if ($slugBase === '') {
            $slugBase = 'legacy-artist';
        }

        $slug = $slugBase;

        $suffix = 1;

        while (
            Artist::withTrashed()
                ->where('slug', $slug)
                ->exists()
        ) {
            $suffix++;
            $slug = $slugBase.'-'.$suffix;
        }

        do {
            $publicId =
                'ART-'.Str::upper(
                    Str::random(12)
                );
        } while (
            Artist::withTrashed()
                ->where(
                    'public_id',
                    $publicId
                )
                ->exists()
        );

        return Artist::query()->create([
            'public_id' => $publicId,
            'user_id' => null,
            'label_id' => $labelId,
            'stage_name' => trim($name),
            'legal_name' => trim($name),
            'slug' => $slug,
            'email' => null,
            'phone' => null,
            'country' => 'India',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'account_status' => 'active',
            'kyc_status' => 'pending',
            'can_receive_splits' => true,
            'can_create_releases' => false,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function resolveLabelId(
        ?string $name
    ): ?int {
        $normalized = $this->normalizeEntityName(
            $name
        );

        if ($normalized === '') {
            return null;
        }

        $matches = Label::query()
            ->whereNull('deleted_at')
            ->get([
                'id',
                'name',
            ])
            ->filter(
                fn (Label $label) =>
                    $this->normalizeEntityName(
                        $label->name
                    ) === $normalized
            )
            ->values();

        /*
         * Ambiguous names must never be guessed.
         */
        if ($matches->count() !== 1) {
            return null;
        }

        return (int) $matches->first()->id;
    }

    /**
     * Resolve artist inside the resolved label.
     *
     * This prevents an artist with the same stage
     * name under another label from being linked
     * accidentally.
     *
     * IMPORTANT:
     * This method never creates an artist.
     */
    private function resolveArtistId(
        ?string $name,
        ?int $labelId
    ): ?int {
        $normalized = $this->normalizeEntityName(
            $name
        );

        if (
            $normalized === ''
            || !$labelId
        ) {
            return null;
        }

        $matches = Artist::query()
            ->whereNull('deleted_at')
            ->where(
                'label_id',
                $labelId
            )
            ->get([
                'id',
                'stage_name',
            ])
            ->filter(
                fn (Artist $artist) =>
                    $this->normalizeEntityName(
                        $artist->stage_name
                    ) === $normalized
            )
            ->values();

        if ($matches->count() !== 1) {
            return null;
        }

        return (int) $matches->first()->id;
    }

    private function normalizeEntityName(
        ?string $value
    ): string {
        $value = trim(
            mb_strtolower(
                (string) $value,
                'UTF-8'
            )
        );

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        );

        return $value ?: '';
    }

    private function normalizeHeader($value): string
    {
        $value = strtolower(
            trim((string) $value)
        );

        $value = preg_replace(
            '/[^a-z0-9]+/',
            '_',
            $value
        );

        return trim(
            (string) $value,
            '_'
        );
    }

    private function identifier($value): ?string
    {
        $value = strtoupper(
            trim((string) $value)
        );

        if ($value === '') {
            return null;
        }

        /*
         * Spreadsheet applications may expose digit-only
         * identifiers as values such as:
         *
         *     1200214642594.00
         *
         * Remove only an all-zero decimal suffix.
         *
         * IMPORTANT:
         * Do not cast identifiers to int/float because UPCs
         * may legitimately contain leading zeroes.
         */
        if (preg_match('/^[0-9]+\.0+$/', $value)) {
            $value = preg_replace(
                '/\.0+$/',
                '',
                $value
            );
        }

        $value = preg_replace(
            '/[\s\-]+/',
            '',
            $value
        );

        return $value !== '' ? $value : null;
    }

    private function clean($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (
                $value !== null
                && trim((string) $value) !== ''
            ) {
                return false;
            }
        }

        return true;
    }

    private function integerOrDefault(
        $value,
        int $default
    ): int {
        if ($value === null || $value === '') {
            return $default;
        }

        return max(1, (int) $value);
    }

    private function integerOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function dateOrNull($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date
                    ::excelToDateTimeObject($value)
                    ->format('Y-m-d');
            }

            return \Carbon\Carbon::parse(
                (string) $value
            )->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
