<?php

namespace App\Http\Controllers\Artist;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreReleaseRequest;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\DistributionStore;
use App\Models\Distribution\Release;
use App\Services\Core\ReleaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ReleaseController extends Controller
{

    public function index(Request $request)
    {
        $artist = DB::table('artists')
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->first();

        abort_if(!$artist, 403, 'Artist profile not linked.');

        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'status' => trim((string) $request->input('status', '')),
            'sort' => trim((string) $request->input('sort', 'latest')),
        ];

        $query = DB::table('releases')
            ->where('artist_id', $artist->id)
            ->whereNull('deleted_at')
            ->select([
                'id',
                'public_id',
                'title',
                'release_type',
                'primary_artist_name',
                'upc',
                'status',
                'artwork_path',
                'digital_release_date',
                'completion_percentage',
                'submitted_at',
                'approved_at',
                'rejected_at',
                'live_at',
                'created_at',
            ]);

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('upc', 'like', "%{$search}%")
                    ->orWhere(
                        'primary_artist_name',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        match ($filters['sort']) {
            'oldest' => $query->orderBy('id'),
            'title_asc' => $query->orderBy('title'),
            'title_desc' => $query->orderByDesc('title'),
            default => $query->orderByDesc('id'),
        };

        return Inertia::render(
            'V2/Shared/Releases/ReleaseIndex',
            [
                'role' => 'artist',
                'permissions' => ['releases.create'],

            'releases' => $query
                ->paginate(20)
                ->withQueryString(),

            'filters' => $filters,

            'statusCounts' => DB::table('releases')
                ->where('artist_id', $artist->id)
                ->whereNull('deleted_at')
                ->select([
                    DB::raw('COUNT(*) as total'),
                    DB::raw(
                        "SUM(status = 'draft') as draft"
                    ),
                    DB::raw(
                        "SUM(status = 'submitted') as submitted"
                    ),
                    DB::raw(
                        "SUM(status = 'approved') as approved"
                    ),
                    DB::raw(
                        "SUM(status = 'rejected') as rejected"
                    ),
                ])
                ->first(),
        ]);
    }

    public function create(Request $request)
    {
        $artist = Artist::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $label = Label::query()
            ->where('id', $artist->label_id)
            ->first();

        return Inertia::render('Artist/Releases/Create', [
            'artist' => [
                'id' => $artist->id,
                'stage_name' => $artist->stage_name,
                'legal_name' => $artist->legal_name,
                'label_id' => $artist->label_id,
            ],

            'label' => $label
                ? [
                    'id' => $label->id,
                    'name' => $label->name,
                ]
                : null,

            'distributionStores' => DistributionStore::query()
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
        ]);
    }


    public function edit(
        Request $request,
        $releaseId
    ) {
        $artist = Artist::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $release = Release::query()
            ->where('id', $releaseId)
            ->where('artist_id', $artist->id)
            ->with([
                'tracks' => function ($query) {
                    $query
                        ->orderBy('disc_number')
                        ->orderBy('track_number')
                        ->orderBy('id');
                },
            ])
            ->firstOrFail();

        abort_unless(
            in_array($release->status, ['draft', 'rejected'], true),
            403,
            'This release has already been submitted and is locked for editing.'
        );

        $label = Label::query()
            ->where('id', $artist->label_id)
            ->first();

        return Inertia::render('Artist/Releases/Create', [
            'artist' => [
                'id' => $artist->id,
                'stage_name' => $artist->stage_name,
                'legal_name' => $artist->legal_name,
                'label_id' => $artist->label_id,
            ],

            'label' => $label
                ? [
                    'id' => $label->id,
                    'name' => $label->name,
                ]
                : null,

            'release' => $release,

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
        ]);
    }



    public function updateDraft(
        Request $request,
        \App\Models\Distribution\Release $release
    ) {
        $artist = Artist::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        abort_if(
            (int) $release->artist_id !== (int) $artist->id,
            403,
            'You cannot manage this release.'
        );

        abort_unless(
            in_array($release->status, ['draft', 'rejected'], true),
            403,
            'This release can no longer be edited.'
        );

        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'release_type' => [
                'required',
                'in:single,ep,album',
            ],

            'version' => [
                'nullable',
                'string',
                'max:255',
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
                'nullable',
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
                'max:5',
            ],

            'completion_percentage' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
        ]);

        if ($request->hasFile('artwork')) {
            if ($release->artwork_path) {
                Storage::disk('public')->delete(
                    $release->artwork_path
                );
            }

            $validated['artwork_path'] =
                $request->file('artwork')->store(
                    'releases/artwork',
                    'public'
                );
        }

        unset($validated['artwork']);

        // Artist and label ownership cannot be changed from frontend.
        $validated['artist_id'] = $artist->id;
        $validated['label_id'] = $artist->label_id;
        $validated['primary_artist_name'] =
            $artist->stage_name;
        $validated['updated_by'] =
            $request->user()->id;

        if ($release->status === 'rejected') {
            $validated['status'] = 'draft';
            $validated['rejection_reason'] = null;
            $validated['rejected_at'] = null;
            $validated['rejected_by'] = null;
        }

        $release->update($validated);

        return back()->with(
            'success',
            'Draft successfully updated.'
        );
    }

    public function saveDistribution(
        Request $request,
        \App\Models\Distribution\Release $release
    ) {
        $artist = Artist::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        abort_if(
            (int) $release->artist_id !== (int) $artist->id,
            403,
            'You cannot manage this release.'
        );

        abort_unless(
            in_array($release->status, ['draft', 'rejected'], true),
            403,
            'Distribution cannot be changed after submission.'
        );

        $validated = $request->validate([
            'stores' => [
                'required',
                'array',
                'min:1',
            ],

            'stores.*' => [
                'required',
                'integer',
                'distinct',
                'exists:distribution_stores,id',
            ],

            'worldwide' => [
                'required',
                'boolean',
            ],

            'territories' => [
                'nullable',
                'array',
            ],

            'territories.*' => [
                'string',
                'size:2',
            ],

            'release_timezone' => [
                'required',
                'string',
                'max:100',
            ],

            'pre_order' => [
                'required',
                'boolean',
            ],
        ]);

        if (
            !$validated['worldwide']
            && empty($validated['territories'])
        ) {
            return back()->withErrors([
                'territories' =>
                    'Select at least one territory or enable Worldwide.',
            ]);
        }

        $activeStoreIds = DistributionStore::query()
            ->where('is_active', true)
            ->whereIn('id', $validated['stores'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (count($activeStoreIds) !== count($validated['stores'])) {
            return back()->withErrors([
                'stores' =>
                    'One or more selected stores are inactive or invalid.',
            ]);
        }

        $release->update([
            'stores' => $activeStoreIds,

            'worldwide' =>
                (bool) $validated['worldwide'],

            'territories' =>
                $validated['worldwide']
                    ? []
                    : array_values(
                        array_unique(
                            array_map(
                                'strtoupper',
                                $validated['territories'] ?? []
                            )
                        )
                    ),

            'release_timezone' =>
                $validated['release_timezone'],

            'pre_order' =>
                (bool) $validated['pre_order'],

            'wizard_step' => max(
                3,
                (int) $release->wizard_step
            ),

            'completion_percentage' => max(
                75,
                (int) $release->completion_percentage
            ),

            'updated_by' =>
                $request->user()->id,
        ]);

        return back()->with(
            'success',
            'Stores and distribution settings saved successfully.'
        );
    }


    public function submitForReview(
        Request $request,
        \App\Models\Distribution\Release $release
    ) {
        $artist = Artist::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        abort_if(
            (int) $release->artist_id !== (int) $artist->id,
            403,
            'You cannot submit this release.'
        );

        abort_unless(
            in_array($release->status, ['draft', 'rejected'], true),
            403,
            'This release has already been submitted.'
        );

        $release->load([
            'tracks' => function ($query) {
                $query
                    ->orderBy('disc_number')
                    ->orderBy('track_number');
            },
        ]);

        $errors = [];

        if (!$release->title) {
            $errors['title'] =
                'Release title is required.';
        }

        if (
            !$release->artist_id
            || !$release->primary_artist_name
        ) {
            $errors['artist'] =
                'Primary artist is required.';
        }

        if (!$release->label_id) {
            $errors['label'] =
                'Label is required.';
        }

        if (!$release->catalog_number) {
            $errors['catalog_number'] =
                'Catalogue number is required.';
        }

        if (!$release->digital_release_date) {
            $errors['digital_release_date'] =
                'Digital release date is required.';
        }

        if (!$release->artwork_path) {
            $errors['artwork'] =
                'Cover artwork is required.';
        }

        if ($release->tracks->isEmpty()) {
            $errors['tracks'] =
                'Add at least one track.';
        }

        foreach ($release->tracks as $index => $track) {
            $trackNumber = $index + 1;

            if (!$track->title) {
                $errors["track_{$track->id}_title"] =
                    "Track {$trackNumber} title is missing.";
            }

            if (!$track->primary_artist_name) {
                $errors["track_{$track->id}_artist"] =
                    "Track {$trackNumber} primary artist is missing.";
            }

            if (!$track->audio_path) {
                $errors["track_{$track->id}_audio"] =
                    "Track {$trackNumber} WAV file is missing.";
            }
        }

        $stores = is_array($release->stores)
            ? array_values(
                array_unique(
                    array_map(
                        'intval',
                        $release->stores
                    )
                )
            )
            : [];

        if (empty($stores)) {
            $errors['stores'] =
                'Select at least one distribution store.';
        } else {
            $validStoreCount = DistributionStore::query()
                ->where('is_active', true)
                ->whereIn('id', $stores)
                ->count();

            if ($validStoreCount !== count($stores)) {
                $errors['stores'] =
                    'One or more selected stores are invalid or inactive.';
            }
        }

        $territories = is_array($release->territories)
            ? $release->territories
            : [];

        if (
            !$release->worldwide
            && empty($territories)
        ) {
            $errors['territories'] =
                'Select at least one territory or enable Worldwide.';
        }

        if (!empty($errors)) {
            return back()->withErrors($errors);
        }

        DB::transaction(function () use (
            $release,
            $request
        ) {
            $release->update([
                'status' => 'submitted',
                'wizard_step' => 5,
                'completion_percentage' => 100,
                'submitted_at' => now(),
                'review_notes' => null,
                'rejection_reason' => null,
                'rejected_at' => null,
                'rejected_by' => null,
                'updated_by' => $request->user()->id,
            ]);

            DB::table('release_status_logs')->insert([
                'public_id' => (string) \Illuminate\Support\Str::ulid(),
                'release_id' => $release->id,
                'old_status' => $release->getOriginal('status') ?: 'draft',
                'new_status' => 'submitted',
                'action' => 'submitted_for_review',
                'remarks' => 'Submitted for admin review by artist.',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'changed_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()
            ->route('artist.releases.index')
            ->with(
                'success',
                "Release '{$release->title}' submitted for review."
            );
    }

    public function store(
        StoreReleaseRequest $request,
        ReleaseService $releaseService
    ) {
        $artist = DB::table('artists')
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->first();

        abort_if(!$artist, 403, 'Artist profile not linked.');

        abort_if(
            !$artist->can_create_releases,
            403,
            'Release creation is disabled for this artist.'
        );

        $data = $request->validated();

        // Artist cannot submit a release for another artist or label.
        $data['artist_id'] = $artist->id;
        $data['label_id'] = $artist->label_id;
        $data['primary_artist_name'] = $artist->stage_name;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        $data['status'] = 'draft';

        $release = $releaseService->create($data);

        return redirect()
            ->route('artist.releases.index')
            ->with(
                'success',
                "Draft '{$release->title}' successfully created."
            );
    }
    public function destroy(
        Request $request,
        \App\Models\Distribution\Release $release
    ) {
        abort_unless(
            $release->status === 'draft',
            403,
            'Only draft releases can be deleted.'
        );

        abort_unless(
            (int) $release->created_by
                === (int) $request->user()->id,
            403,
            'Only the draft creator can delete this release.'
        );

        $artist = Artist::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        abort_unless(
            (int) $release->artist_id === (int) $artist->id,
            403,
            'You cannot delete this release.'
        );

        $title = $release->title;

        $release->update([
            'updated_by' => $request->user()->id,
        ]);

        $release->delete();

        return back()->with(
            'success',
            "Draft '{$title}' deleted successfully."
        );
    }

}
