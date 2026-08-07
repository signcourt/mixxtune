<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\DistributionStore;
use App\Services\V2\PermissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ReleaseController extends Controller
{
    public function create(
        Request $request,
        PermissionService $permissions
    ): Response {
        $permissions->authorize(
            $request->user(),
            'releases.create'
        );

        $context = $this->formContext(
            $request,
            $permissions
        );

        return Inertia::render(
            'V2/Releases/Create',
            [
                ...$context,
                'release' => null,
                'title' => 'Create Release',
            ]
        );
    }

    public function store(
        Request $request,
        PermissionService $permissions
    ): RedirectResponse {
        $permissions->authorize(
            $request->user(),
            'releases.create'
        );

        $validated = $this->validateDraft($request);

        [$artist, $label] = $this->resolveOwnership(
            $request,
            $permissions,
            $validated
        );

        $artworkPath = null;

        if ($request->hasFile('artwork')) {
            $artworkPath = $request
                ->file('artwork')
                ->store(
                    'releases/artwork',
                    'public'
                );
        }

        $release = DB::transaction(function () use (
            $request,
            $validated,
            $artist,
            $label,
            $artworkPath
        ) {
            return Release::query()->create([
                'public_id' => (string) Str::ulid(),
                'catalog_number' =>
                    $validated['catalog_number'],

                'artist_id' => $artist->id,
                'label_id' => $label?->id,

                'release_type' =>
                    $validated['release_type'],

                'title' => $validated['title'],

                'primary_artist_name' =>
                    $artist->stage_name
                    ?: $artist->legal_name,

                'featuring_artist_name' =>
                    $validated[
                        'featuring_artist_name'
                    ] ?? null,

                'language' =>
                    $validated['language'] ?? null,

                'primary_genre' =>
                    $validated[
                        'primary_genre'
                    ] ?? null,

                'sub_genre' =>
                    $validated['sub_genre'] ?? null,

                'upc' =>
                    $validated['upc'] ?? null,

                'original_release_date' =>
                    $validated[
                        'original_release_date'
                    ] ?? null,

                'digital_release_date' =>
                    $validated[
                        'digital_release_date'
                    ] ?? null,

                'copyright_owner' =>
                    $validated[
                        'copyright_owner'
                    ] ?? null,

                'copyright_year' =>
                    $validated[
                        'copyright_year'
                    ] ?? null,

                'phonographic_owner' =>
                    $validated[
                        'phonographic_owner'
                    ] ?? null,

                'phonographic_year' =>
                    $validated[
                        'phonographic_year'
                    ] ?? null,

                'artwork_path' => $artworkPath,

                'status' => 'draft',
                'wizard_step' => 1,
                'completion_percentage' => 20,

                'created_by' =>
                    $request->user()->id,

                'updated_by' =>
                    $request->user()->id,
            ]);
        });

        return redirect()
            ->route(
                'v2.releases.edit',
                $release
            )
            ->with(
                'success',
                "Draft '{$release->title}' created."
            );
    }

    public function edit(
        Request $request,
        Release $release,
        PermissionService $permissions
    ): Response {
        $permissions->authorize(
            $request->user(),
            'releases.update'
        );

        $this->authorizeRelease(
            $request,
            $release,
            $permissions
        );

        abort_unless(
            in_array(
                $release->status,
                [
                    'draft',
                    'changes_requested',
                    'rejected',
                ],
                true
            ),
            403,
            'This release is locked for editing.'
        );

        $release->load([
            'tracks' => function ($query) {
                $query
                    ->orderBy('disc_number')
                    ->orderBy('track_number')
                    ->orderBy('id');
            },
        ]);

        $context = $this->formContext(
            $request,
            $permissions
        );

        return Inertia::render(
            'V2/Releases/Create',
            [
                ...$context,
                'release' => $release,
                'title' => 'Edit Release',
            ]
        );
    }

    public function update(
        Request $request,
        Release $release,
        PermissionService $permissions
    ): RedirectResponse {
        $permissions->authorize(
            $request->user(),
            'releases.update'
        );

        $this->authorizeRelease(
            $request,
            $release,
            $permissions
        );

        abort_unless(
            in_array(
                $release->status,
                [
                    'draft',
                    'changes_requested',
                    'rejected',
                ],
                true
            ),
            403,
            'This release is locked for editing.'
        );

        $validated = $this->validateDraft($request);

        [$artist, $label] = $this->resolveOwnership(
            $request,
            $permissions,
            $validated,
            $release
        );

        if ($request->hasFile('artwork')) {
            if ($release->artwork_path) {
                Storage::disk('public')->delete(
                    $release->artwork_path
                );
            }

            $validated['artwork_path'] = $request
                ->file('artwork')
                ->store(
                    'releases/artwork',
                    'public'
                );
        }

        unset($validated['artwork']);

        $release->update([
            'catalog_number' =>
                $validated['catalog_number'],

            'artist_id' => $artist->id,
            'label_id' => $label?->id,

            'release_type' =>
                $validated['release_type'],

            'title' => $validated['title'],

            'primary_artist_name' =>
                $artist->stage_name
                ?: $artist->legal_name,

            'featuring_artist_name' =>
                $validated[
                    'featuring_artist_name'
                ] ?? null,

            'language' =>
                $validated['language'] ?? null,

            'primary_genre' =>
                $validated['primary_genre'] ?? null,

            'sub_genre' =>
                $validated['sub_genre'] ?? null,

            'upc' =>
                $validated['upc'] ?? null,

            'original_release_date' =>
                $validated[
                    'original_release_date'
                ] ?? null,

            'digital_release_date' =>
                $validated[
                    'digital_release_date'
                ] ?? null,

            'copyright_owner' =>
                $validated[
                    'copyright_owner'
                ] ?? null,

            'copyright_year' =>
                $validated[
                    'copyright_year'
                ] ?? null,

            'phonographic_owner' =>
                $validated[
                    'phonographic_owner'
                ] ?? null,

            'phonographic_year' =>
                $validated[
                    'phonographic_year'
                ] ?? null,

            'artwork_path' =>
                $validated['artwork_path']
                ?? $release->artwork_path,

            'status' =>
                $release->status === 'rejected'
                    ? 'draft'
                    : $release->status,

            'wizard_step' => max(
                1,
                min(
                    4,
                    (int) (
                        $validated['wizard_step']
                        ?? $release->wizard_step
                        ?? 1
                    )
                )
            ),

            'completion_percentage' => max(
                20,
                min(
                    100,
                    (int) (
                        $validated[
                            'completion_percentage'
                        ]
                        ?? $release
                            ->completion_percentage
                        ?? 20
                    )
                )
            ),

            'rejection_reason' => null,
            'rejected_at' => null,
            'rejected_by' => null,

            'updated_by' =>
                $request->user()->id,
        ]);

        return back()->with(
            'success',
            'Draft updated successfully.'
        );
    }

    private function validateDraft(
        Request $request
    ): array {
        return $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'release_type' => [
                'required',
                'in:single,ep,album',
            ],

            'artist_id' => [
                'nullable',
                'integer',
                'exists:artists,id',
            ],

            'label_id' => [
                'nullable',
                'integer',
                'exists:labels,id',
            ],

            'featuring_artist_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'catalog_number' => [
                'required',
                'string',
                'max:100',
            ],

            'upc' => [
                'nullable',
                'string',
                'max:20',
            ],

            'language' => [
                'nullable',
                'string',
                'max:100',
            ],

            'primary_genre' => [
                'nullable',
                'string',
                'max:100',
            ],

            'sub_genre' => [
                'nullable',
                'string',
                'max:100',
            ],

            'original_release_date' => [
                'nullable',
                'date',
            ],

            'digital_release_date' => [
                'required',
                'date',
            ],

            'copyright_owner' => [
                'nullable',
                'string',
                'max:255',
            ],

            'copyright_year' => [
                'nullable',
                'integer',
                'min:1900',
                'max:2100',
            ],

            'phonographic_owner' => [
                'nullable',
                'string',
                'max:255',
            ],

            'phonographic_year' => [
                'nullable',
                'integer',
                'min:1900',
                'max:2100',
            ],

            'artwork' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png',
                'max:20480',
            ],

            'wizard_step' => [
                'nullable',
                'integer',
                'min:1',
                'max:4',
            ],

            'completion_percentage' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
        ]);
    }

    private function resolveOwnership(
        Request $request,
        PermissionService $permissions,
        array $validated,
        ?Release $release = null
    ): array {
        $role = $permissions->role(
            $request->user()
        );

        if ($role === 'artist') {
            $artist = Artist::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->firstOrFail();

            $label = $artist->label_id
                ? Label::query()->find(
                    $artist->label_id
                )
                : null;

            return [$artist, $label];
        }

        if ($role === 'label') {
            $label = Label::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->firstOrFail();

            $artistId =
                $validated['artist_id']
                ?? $release?->artist_id;

            abort_unless(
                $artistId,
                422,
                'Select an artist.'
            );

            $artist = Artist::query()
                ->where('id', $artistId)
                ->where(
                    'label_id',
                    $label->id
                )
                ->whereNull('deleted_at')
                ->firstOrFail();

            return [$artist, $label];
        }

        $artistId =
            $validated['artist_id']
            ?? $release?->artist_id;

        abort_unless(
            $artistId,
            422,
            'Select an artist.'
        );

        $artist = Artist::query()
            ->where('id', $artistId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $labelId =
            $validated['label_id']
            ?? $artist->label_id
            ?? $release?->label_id;

        $label = $labelId
            ? Label::query()->findOrFail(
                $labelId
            )
            : null;

        if (
            $role === 'admin'
            && Schema::hasColumn(
                'artists',
                'assigned_admin_id'
            )
        ) {
            abort_unless(
                (int) $artist->assigned_admin_id
                    === (int) $request->user()->id,
                403,
                'This artist is not assigned to you.'
            );
        }

        return [$artist, $label];
    }

    private function authorizeRelease(
        Request $request,
        Release $release,
        PermissionService $permissions
    ): void {
        $role = $permissions->role(
            $request->user()
        );

        if ($role === 'super_admin') {
            return;
        }

        if ($role === 'artist') {
            $artist = Artist::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->firstOrFail();

            abort_unless(
                (int) $release->artist_id
                    === (int) $artist->id,
                403,
                'You cannot manage this release.'
            );

            return;
        }

        if ($role === 'label') {
            $label = Label::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->firstOrFail();

            abort_unless(
                (int) $release->label_id
                    === (int) $label->id,
                403,
                'You cannot manage this release.'
            );

            return;
        }

        if (
            $role === 'admin'
            && Schema::hasColumn(
                'artists',
                'assigned_admin_id'
            )
        ) {
            $artist = Artist::query()->find(
                $release->artist_id
            );

            abort_unless(
                $artist
                && (int) $artist->assigned_admin_id
                    === (int) $request->user()->id,
                403,
                'This release is not assigned to you.'
            );
        }
    }

    private function formContext(
        Request $request,
        PermissionService $permissions
    ): array {
        $role = $permissions->role(
            $request->user()
        );

        $artist = null;
        $label = null;
        $availableArtists = collect();
        $availableLabels = collect();

        if ($role === 'artist') {
            $artist = Artist::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->first();

            if ($artist?->label_id) {
                $label = Label::query()->find(
                    $artist->label_id
                );
            }
        }

        if ($role === 'label') {
            $label = Label::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->first();

            if ($label) {
                $availableArtists = Artist::query()
                    ->where(
                        'label_id',
                        $label->id
                    )
                    ->whereNull('deleted_at')
                    ->orderBy('stage_name')
                    ->get([
                        'id',
                        'stage_name',
                        'legal_name',
                        'label_id',
                    ]);
            }
        }

        if (
            in_array(
                $role,
                ['admin', 'super_admin'],
                true
            )
        ) {
            $artistsQuery = Artist::query()
                ->whereNull('deleted_at');

            if (
                $role === 'admin'
                && Schema::hasColumn(
                    'artists',
                    'assigned_admin_id'
                )
            ) {
                $artistsQuery->where(
                    'assigned_admin_id',
                    $request->user()->id
                );
            }

            $availableArtists = $artistsQuery
                ->orderBy('stage_name')
                ->get([
                    'id',
                    'stage_name',
                    'legal_name',
                    'label_id',
                ]);

            $labelsQuery = Label::query()
                ->whereNull('deleted_at');

            if (
                $role === 'admin'
                && Schema::hasColumn(
                    'labels',
                    'assigned_admin_id'
                )
            ) {
                $labelsQuery->where(
                    'assigned_admin_id',
                    $request->user()->id
                );
            }

            $availableLabels = $labelsQuery
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                ]);
        }

        return [
            'role' => $role,

            'permissions' =>
                $permissions->permissions(
                    $request->user()
                ),

            'artist' => $artist
                ? [
                    'id' => $artist->id,
                    'stage_name' =>
                        $artist->stage_name,
                    'legal_name' =>
                        $artist->legal_name,
                    'label_id' =>
                        $artist->label_id,
                ]
                : null,

            'label' => $label
                ? [
                    'id' => $label->id,
                    'name' => $label->name,
                ]
                : null,

            'availableArtists' =>
                $availableArtists,

            'availableLabels' =>
                $availableLabels,

            'distributionStores' =>
                DistributionStore::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'slug',
                        'logo_path',
                        'default_selected',
                    ]),
        ];
    }
}
