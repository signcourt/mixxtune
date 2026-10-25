<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Services\V2\IsrcService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\UpcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IdentifierController extends Controller
{
    public function pending(
        Request $request,
        PermissionService $permissions
    ): Response {
        $permissions->authorize(
            $request->user(),
            'identifiers.view'
        );

        /*
         * W58: "new" = releases made in this panel (codes may be generated);
         * "old" = old catalogue (WordPress / Legacy Import) - its real codes
         * must be typed in, never generated. Plus a search box.
         */
        $scope = $request->query('scope') === 'old' ? 'old' : 'new';
        $q = trim((string) $request->query('q', ''));
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
        $legacy = function ($w) {
            $w->where('releases.catalog_number', 'like', 'WP-%')
                ->orWhereExists(function ($e) {
                    $e->selectRaw('1')->from('legacy_catalogue_import_rows as lcr')->whereColumn('lcr.release_id', 'releases.id');
                });
        };
        $notLegacy = function ($w) {
            $w->where(function ($x) {
                $x->whereNull('releases.catalog_number')->orWhere('releases.catalog_number', 'not like', 'WP-%');
            })->whereNotExists(function ($e) {
                $e->selectRaw('1')->from('legacy_catalogue_import_rows as lcr')->whereColumn('lcr.release_id', 'releases.id');
            });
        };

        $trackQuery = Track::query()
            ->with([
                'release:id,title,version,status,artist_id,label_id,catalog_number,release_type,primary_artist_name,digital_release_date',
                'release.label:id,name',
            ])
            ->whereNull('isrc')
            ->whereNull('deleted_at')
            ->whereHas('release', fn ($r) => $r->where($scope === 'old' ? $legacy : $notLegacy))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->whereRaw("tracks.title LIKE ? ESCAPE '!'", [$like])
                ->orWhereHas('release', fn ($r) => $r->whereRaw("releases.title LIKE ? ESCAPE '!'", [$like]))));

        $releaseQuery = Release::query()
            ->with([
                'artist:id,stage_name',
                'label:id,name',
            ])
            ->withCount(['tracks', 'tracks as tracks_with_isrc_count' => fn ($t) => $t->whereNotNull('isrc')])
            ->whereNull('upc')
            ->whereNull('deleted_at')
            ->where($scope === 'old' ? $legacy : $notLegacy)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->whereRaw("releases.title LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("releases.catalog_number LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("releases.primary_artist_name LIKE ? ESCAPE '!'", [$like])));

        $pendingIsrc = $trackQuery
            ->orderByDesc('id')
            ->paginate(
                25,
                ['*'],
                'isrc_page'
            )
            ->withQueryString()
            ->through(fn (Track $t) => $this->trackRow($t));

        $pendingUpc = $releaseQuery
            ->orderByDesc('id')
            ->paginate(
                25,
                ['*'],
                'upc_page'
            )
            ->withQueryString()
            ->through(fn (Release $r) => $this->releaseRow($r));

        return Inertia::render(
            'V2/Admin/Identifiers/Index',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'pendingIsrc' =>
                    $pendingIsrc,

                'pendingUpc' =>
                    $pendingUpc,

                'scope' => $scope,
                'q' => $q,
            ]
        );
    }

    /* W98 MIXXTUNE_IDENTIFIER_DETAILS: everything needed to tell the songs apart */
    private function trackRow(Track $t): array
    {
        $r = $t->release;
        $secs = (int) ($t->audio_duration_seconds ?: $t->duration_seconds ?: 0);

        return [
            'id' => $t->id,
            'title' => $t->title,
            'version' => $t->version,
            'track_number' => $t->track_number,
            'disc_number' => $t->disc_number,
            'primary_artist_name' => $t->primary_artist_name,
            'featuring_artist_name' => $t->featuring_artist_name,
            'author_name' => $t->author_name,
            'composer_name' => $t->composer_name,
            'language' => $t->language,
            'audio_original_name' => $t->audio_original_name,
            'has_audio' => !empty($t->audio_path),
            'duration' => $secs > 0 ? sprintf('%d:%02d', intdiv($secs, 60), $secs % 60) : null,
            'audio_validation_status' => $t->audio_validation_status,
            'release' => $r ? [
                'id' => $r->id,
                'title' => $r->title,
                'version' => $r->version,
                'release_type' => $r->release_type,
                'status' => $r->status,
                'catalog_number' => $r->catalog_number,
                'primary_artist_name' => $r->primary_artist_name,
                'label' => $r->label?->name,
                'release_date' => optional($r->digital_release_date)->toDateString(),
            ] : null,
        ];
    }

    private function releaseRow(Release $r): array
    {
        return [
            'id' => $r->id,
            'title' => $r->title,
            'version' => $r->version,
            'release_type' => $r->release_type,
            'catalog_number' => $r->catalog_number,
            'primary_artist_name' => $r->primary_artist_name ?: $r->artist?->stage_name,
            'featuring_artist_name' => $r->featuring_artist_name,
            'label' => $r->label?->name,
            'status' => $r->status,
            'release_date' => optional($r->digital_release_date)->toDateString(),
            'submitted_at' => optional($r->submitted_at)->toDateString(),
            'tracks_count' => (int) $r->tracks_count,
            'tracks_with_isrc_count' => (int) $r->tracks_with_isrc_count,
        ];
    }

    public function assignIsrc(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access,
        IsrcService $isrc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.assign'
        );

        $track->loadMissing('release');

        $access->authorizeView(
            $request->user(),
            $track->release
        );

        $validated = $request->validate([
            'isrc' => [
                'required',
                'string',
                'max:20',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $track = $isrc->assignManual(
            $track,
            $validated['isrc'],
            $request->user(),
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' =>
                'ISRC assigned successfully.',

            'track' => $track,
        ]);
    }

    public function generateIsrc(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access,
        IsrcService $isrc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.generate'
        );

        $track->loadMissing('release');

        $access->authorizeView(
            $request->user(),
            $track->release
        );

        $validated = $request->validate([
            'country_code' => [
                'nullable',
                'string',
                'size:2',
            ],

            'registrant_code' => [
                'nullable',
                'string',
                'size:3',
            ],

            'reference_year' => [
                'nullable',
                'integer',
                'min:2000',
                'max:2100',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $track = $isrc->generate(
            $track,
            $request->user(),
            $validated['country_code']
                ?? 'IN',
            $validated['registrant_code']
                ?? 'MXT',
            $validated['reference_year']
                ?? null,
            $validated['notes']
                ?? null
        );

        return response()->json([
            'message' =>
                'ISRC generated successfully.',

            'track' => $track,
        ]);
    }

    public function assignUpc(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        UpcService $upc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.assign'
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $validated = $request->validate([
            'upc' => [
                'required',
                'string',
                'max:20',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $release = $upc->assignManual(
            $release,
            $validated['upc'],
            $request->user(),
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' =>
                'UPC assigned successfully.',

            'release' => $release,
        ]);
    }

    public function generateUpc(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        UpcService $upc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.generate'
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $validated = $request->validate([
            'prefix' => [
                'nullable',
                'string',
                'min:6',
                'max:10',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $release = $upc->generate(
            $release,
            $request->user(),
            $validated['prefix']
                ?? '890000',
            $validated['notes']
                ?? null
        );

        return response()->json([
            'message' =>
                'UPC generated successfully.',

            'release' => $release,
        ]);
    }

    public function bulkGenerateIsrc(
        Request $request,
        PermissionService $permissions,
        IsrcService $isrc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.bulk_assign'
        );

        $validated = $request->validate([
            'track_ids' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],

            'track_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:tracks,id',
            ],

            'country_code' => [
                'nullable',
                'string',
                'size:2',
            ],

            'registrant_code' => [
                'nullable',
                'string',
                'size:3',
            ],

            'reference_year' => [
                'nullable',
                'integer',
                'min:2000',
                'max:2100',
            ],
        ]);

        $tracks = Track::query()
            ->whereIn(
                'id',
                $validated['track_ids']
            )
            ->whereNull('isrc')
            ->get();

        $assigned = [];
        $skipped = 0;

        foreach ($tracks as $track) {
            /* W58: old catalogue never gets generated codes */
            if (\App\Support\LegacyRelease::is($track->release()->first())) {
                $skipped++;

                continue;
            }

            $assigned[] = $isrc->generate(
                $track,
                $request->user(),
                $validated['country_code']
                    ?? 'IN',
                $validated['registrant_code']
                    ?? 'MXT',
                $validated['reference_year']
                    ?? null
            );
        }

        return response()->json([
            'message' =>
                count($assigned)
                . ' ISRC codes generated.'
                . ($skipped ? ' '.$skipped.' old catalogue track(s) skipped - enter their existing ISRC.' : ''),

            'tracks' => $assigned,
        ]);
    }

    public function bulkGenerateUpc(
        Request $request,
        PermissionService $permissions,
        UpcService $upc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.bulk_assign'
        );

        $validated = $request->validate([
            'release_ids' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],

            'release_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:releases,id',
            ],

            'prefix' => [
                'nullable',
                'string',
                'min:6',
                'max:10',
            ],
        ]);

        $releases = Release::query()
            ->whereIn(
                'id',
                $validated['release_ids']
            )
            ->whereNull('upc')
            ->get();

        $assigned = [];
        $skipped = 0;

        foreach ($releases as $release) {
            if (\App\Support\LegacyRelease::is($release)) {
                $skipped++;

                continue;
            }

            $assigned[] = $upc->generate(
                $release,
                $request->user(),
                $validated['prefix']
                    ?? '890000'
            );
        }

        return response()->json([
            'message' =>
                count($assigned)
                . ' UPC codes generated.'
                . ($skipped ? ' '.$skipped.' old catalogue release(s) skipped - enter their existing UPC.' : ''),

            'releases' => $assigned,
        ]);
    }
}
