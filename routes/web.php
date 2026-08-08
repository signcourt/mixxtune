<?php

use App\Http\Controllers\Admin\AdminManagementController;
use App\Http\Controllers\Admin\RevenueImportController;

use App\Http\Controllers\Admin\IsrcUpcController;

use App\Http\Controllers\Admin\CatalogueController;

use App\Http\Controllers\Admin\ReleaseDeliveryController;

use App\Http\Controllers\Admin\DistributionStoreController;

use App\Http\Controllers\Admin\ReleaseTrackController;

use App\Http\Controllers\Admin\ArtistController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoyaltyController;
use App\Http\Controllers\Admin\RoyaltyPostingController;
use App\Http\Controllers\Admin\RoyaltyLedgerController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Admin\WithdrawalController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\RevenueLabelMappingController;
use App\Http\Controllers\Auth\AcceptArtistInvitationController;
use App\Http\Controllers\Auth\ArtistPasswordSetupController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Artist\DashboardController as ArtistDashboardController;
use App\Http\Controllers\Artist\WalletController as ArtistWalletController;

/*
|--------------------------------------------------------------------------
| Public Home Website
|--------------------------------------------------------------------------
*/

Route::domain('home.mixxtune.com')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'laravelVersion' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
        ]);
    })->name('home');
});

/*
|--------------------------------------------------------------------------
| Artist Panel
|--------------------------------------------------------------------------
*/

Route::domain('artist.mixxtune.com')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [
            AuthenticatedSessionController::class,
            'create',
        ])->name('artist.login');

        Route::post('/login', [
            AuthenticatedSessionController::class,
            'store',
        ])->name('artist.login.store');
    });

    /*
    |--------------------------------------------------------------------------
    | Artist Invitation
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/invitation/{token}',
        AcceptArtistInvitationController::class
    )->name('artist.invitation.accept');

    Route::get(
        '/set-password',
        [ArtistPasswordSetupController::class, 'create']
    )->name('artist.password.create');

    Route::post(
        '/set-password',
        [ArtistPasswordSetupController::class, 'store']
    )->name('artist.password.store');

    /*
    |--------------------------------------------------------------------------
    | Artist Home
    |--------------------------------------------------------------------------
    */

    Route::get('/', function () {
        if (! auth()->check()) {
            return redirect()->route('artist.login');
        }

        return redirect()->route('artist.dashboard');
    });

    /*
    |--------------------------------------------------------------------------
    | Artist Protected Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth',
        'verified',
        'role:artist',
    ])->group(function () {
        Route::get(
            '/dashboard',
            [ArtistDashboardController::class, 'index']
        )->name('artist.dashboard');


        Route::get(
            '/releases/create',
            [\App\Http\Controllers\Artist\ReleaseController::class, 'create']
        )->name('artist.releases.create');

        Route::get(
            '/releases',
            [\App\Http\Controllers\Artist\ReleaseController::class, 'index']
        )->name('artist.releases.index');

        Route::get(
            '/releases/{release}/edit',
            [\App\Http\Controllers\Artist\ReleaseController::class, 'edit']
        )->name('artist.releases.edit');

        Route::patch(
            '/releases/{release}',
            [\App\Http\Controllers\Artist\ReleaseController::class, 'updateDraft']
        )->name('artist.releases.update-draft');

        Route::post(
            '/releases/{release}/submit',
            [\App\Http\Controllers\Artist\ReleaseController::class, 'submitForReview']
        )->name('artist.releases.submit');


        Route::patch(
            '/releases/{release}/distribution',
            [\App\Http\Controllers\Artist\ReleaseController::class, 'saveDistribution']
        )->name('artist.releases.distribution.update');


        Route::post(
            '/releases/{release}/tracks',
            [\App\Http\Controllers\Artist\ReleaseTrackController::class, 'store']
        )->name('artist.release-tracks.store');

        Route::patch(
            '/tracks/{track}',
            [\App\Http\Controllers\Artist\ReleaseTrackController::class, 'update']
        )->name('artist.release-tracks.update');

        Route::delete(
            '/tracks/{track}',
            [\App\Http\Controllers\Artist\ReleaseTrackController::class, 'destroy']
        )->name('artist.release-tracks.destroy');

        Route::get(
            '/tracks/{track}/download-audio',
            [\App\Http\Controllers\Artist\ReleaseTrackController::class, 'downloadAudio']
        )->name('artist.tracks.download-audio');

        Route::post(
            '/releases',
            [\App\Http\Controllers\Artist\ReleaseController::class, 'store']
        )->name('artist.releases.store');


        Route::get(
            '/wallet',
            [ArtistWalletController::class, 'index']
        )->name('artist.wallet.index');

    });
});

/*
|--------------------------------------------------------------------------
| Admin Panel
|--------------------------------------------------------------------------
*/

Route::domain('admin.mixxtune.com')->group(function () {
    Route::get('/', function () {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        return redirect()->route('admin.dashboard');
    });

    Route::middleware([
        'auth',
        'verified',
        'role:admin',
    ])->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Admin/Dashboard');
        })->name('admin.dashboard');

        Route::get('/releases', function () {
            $releases = \App\Models\Distribution\Release::query()
                ->with('label:id,name')
                ->withCount('tracks')
                ->latest()
                ->paginate(20);

            return Inertia::render('Admin/Releases/Index', [
                'releases' => $releases,
            ]);
        })->name('admin.releases.index');



        Route::get('/releases/{release}/download-artwork', function (
            \App\Models\Distribution\Release $release
        ) {
            abort_unless($release->artwork_path, 404);

            $path = $release->artwork_path;

            abort_unless(
                \Illuminate\Support\Facades\Storage::disk('public')->exists($path),
                404
            );

            $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
            $filename = \Illuminate\Support\Str::slug($release->title)
                . '-cover.'
                . $extension;

            return \Illuminate\Support\Facades\Storage::disk('public')
                ->download($path, $filename);
        })->name('admin.releases.download-artwork');

        Route::get('/tracks/{track}/download-audio', function (
            \App\Models\Distribution\Track $track
        ) {
            abort_unless($track->audio_path, 404);

            abort_unless(
                \Illuminate\Support\Facades\Storage::disk('public')
                    ->exists($track->audio_path),
                404
            );

            $filename = $track->audio_original_name
                ?: \Illuminate\Support\Str::slug($track->title) . '.wav';

            return \Illuminate\Support\Facades\Storage::disk('public')
                ->download($track->audio_path, $filename, [
                    'Content-Type' => $track->audio_mime_type ?: 'audio/wav',
                ]);
        })->name('admin.tracks.download-audio');

        Route::get('/release-reviews', function () {
            $releases = \App\Models\Distribution\Release::query()
                ->with([
                    'label:id,name',
                    'tracks:id,release_id,track_number,title,audio_path,audio_original_name,audio_mime_type,isrc,status',
                ])
                ->whereIn('status', [
                    'submitted',
                    'changes_requested',
                    'approved',
                    'rejected',
                ])
                ->latest('submitted_at')
                ->paginate(20);

            return Inertia::render('Admin/Releases/ReviewQueue', [
                'releases' => $releases,
            ]);
        })->name('admin.releases.review-queue');

        Route::get('/releases/create', function () {
            return Inertia::render('Admin/Releases/Create', [
                'artists' => \App\Models\Core\Artist::query()
                    ->orderBy('stage_name')
                    ->get(['id', 'stage_name']),
                'labels' => \App\Models\Core\Label::query()
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'distributionStores' => \App\Models\DistributionStore::active()
                    ->get([
                        'id',
                        'name',
                        'slug',
                        'logo_path',
                        'default_selected',
                    ]),
            ]);
        })->name('admin.releases.create');


        Route::post('/releases', function (
            \App\Http\Requests\Core\StoreReleaseRequest $request,
            \App\Services\Core\ReleaseService $releaseService
        ) {
            $release = $releaseService->create($request->validated());

            return redirect()
                ->route('admin.releases.edit', $release)
                ->with('success', 'Draft saved successfully. You can now add tracks.');
        })->name('admin.releases.store');


        Route::get('/releases/{release}/edit', function (
            \App\Models\Distribution\Release $release
        ) {
            return Inertia::render('Admin/Releases/Edit', [
                'distributionStores' => \App\Models\DistributionStore::query()
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
                'release' => $release->load([
                    'tracks' => fn ($query) => $query
                        ->orderBy('disc_number')
                        ->orderBy('track_number'),
                ]),
                'artists' => \App\Models\Core\Artist::query()
                    ->orderBy('stage_name')
                    ->get(['id', 'stage_name']),
                'labels' => \App\Models\Core\Label::query()
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ]);
        })->name('admin.releases.edit');


        Route::patch('/releases/{release}', function (
            \App\Http\Requests\Core\UpdateReleaseRequest $request,
            \App\Models\Distribution\Release $release,
            \App\Services\Core\ReleaseService $releaseService
        ) {
            $releaseService->update($release, $request->validated());

            return redirect()
                ->route('admin.releases.index')
                ->with('success', 'Release updated successfully.');
        })->name('admin.releases.update');




        Route::get('/labels', function () {
            return redirect()->route('v2.admin.labels.index');
        })->name('admin.labels.index');
Route::get(
            '/isrc-upc',
            [IsrcUpcController::class, 'index']
        )->name('admin.isrc-upc.index');

        Route::patch(
            '/isrc-upc/tracks/{track}',
            [IsrcUpcController::class, 'updateTrack']
        )->name('admin.isrc-upc.tracks.update');

        Route::patch(
            '/isrc-upc/releases/{release}',
            [IsrcUpcController::class, 'updateRelease']
        )->name('admin.isrc-upc.releases.update');

        Route::post(
            '/isrc-upc/generate-isrc',
            [IsrcUpcController::class, 'bulkGenerateIsrc']
        )->name('admin.isrc-upc.generate-isrc');

        Route::post(
            '/isrc-upc/generate-upc',
            [IsrcUpcController::class, 'bulkGenerateUpc']
        )->name('admin.isrc-upc.generate-upc');

        Route::get(
            '/revenue-imports',
            [RevenueImportController::class, 'index']
        )->name('admin.revenue-imports.index');

        Route::post(
            '/revenue-imports',
            [RevenueImportController::class, 'store']
        )->name('admin.revenue-imports.store');

        Route::get(
            '/revenue-imports/{revenueImport}',
            [RevenueImportController::class, 'show']
        )->name('admin.revenue-imports.show');

        Route::post(
            '/revenue-imports/{revenueImport}/process',
            [RevenueImportController::class, 'process']
        )->name('admin.revenue-imports.process');



        Route::delete(
            '/revenue-imports/{revenueImport}',
            [RevenueImportController::class, 'destroy']
        )->name('admin.revenue-imports.destroy');

        Route::get(
            '/admin-management',
            [AdminManagementController::class, 'index']
        )->name('admin.management.index');

        Route::post(
            '/admin-management',
            [AdminManagementController::class, 'store']
        )->name('admin.management.store');

        Route::patch(
            '/admin-management/{admin}/toggle-status',
            [AdminManagementController::class, 'toggleStatus']
        )->name('admin.management.toggle-status');

        Route::delete(
            '/admin-management/{admin}',
            [AdminManagementController::class, 'destroy']
        )->name('admin.management.destroy');

        Route::get(
            '/catalogue',
            [CatalogueController::class, 'index']
        )->name('admin.catalogue.index');

    Route::get(
        '/reports',
        [ReportController::class, 'index']
    )->name('admin.reports.index');


    Route::get(
        '/royalties',
        [RoyaltyController::class, 'index']
    )->name('admin.royalties.index');




    Route::get(
        '/wallet',
        [WalletController::class, 'index']
    )->name('admin.wallet.index');



    Route::get(
        '/invoices',
        [InvoiceController::class, 'index']
    )->name('admin.invoices.index');

    Route::post(
        '/invoices',
        [InvoiceController::class, 'store']
    )->name('admin.invoices.store');

    Route::get(
        '/invoices/{id}',
        [InvoiceController::class, 'show']
    )
        ->whereNumber('id')
        ->name('admin.invoices.show');

    Route::get(
        '/withdrawals',
        [WithdrawalController::class, 'index']
    )->name('admin.withdrawals.index');

    Route::post(
        '/withdrawals',
        [WithdrawalController::class, 'store']
    )->name('admin.withdrawals.store');


    Route::post(
        '/withdrawals/{id}/approve',
        [WithdrawalController::class, 'approve']
    )
        ->whereNumber('id')
        ->name('admin.withdrawals.approve');

    Route::post(
        '/withdrawals/{id}/processing',
        [WithdrawalController::class, 'processing']
    )
        ->whereNumber('id')
        ->name('admin.withdrawals.processing');

    Route::post(
        '/withdrawals/{id}/reject',
        [WithdrawalController::class, 'reject']
    )
        ->whereNumber('id')
        ->name('admin.withdrawals.reject');

    Route::post(
        '/withdrawals/{id}/paid',
        [WithdrawalController::class, 'paid']
    )
        ->whereNumber('id')
        ->name('admin.withdrawals.paid');

    Route::get(
        '/royalty-ledgers',
        [RoyaltyLedgerController::class, 'index']
    )->name('admin.royalty-ledgers.index');


    Route::get(
        '/royalty-ledgers/{id}',
        [RoyaltyLedgerController::class, 'show']
    )
        ->whereNumber('id')
        ->name('admin.royalty-ledgers.show');


    Route::post(
        '/royalty-ledgers/{id}/approve',
        [RoyaltyLedgerController::class, 'approve']
    )
        ->whereNumber('id')
        ->name('admin.royalty-ledgers.approve');

    Route::post(
        '/royalty-ledgers/{id}/credit-wallet',
        [RoyaltyLedgerController::class, 'creditWallet']
    )
        ->whereNumber('id')
        ->name('admin.royalty-ledgers.credit-wallet');

    Route::post(
        '/royalty-ledgers/{id}/cancel',
        [RoyaltyLedgerController::class, 'cancel']
    )
        ->whereNumber('id')
        ->name('admin.royalty-ledgers.cancel');

    Route::get(
        '/royalties/posting',
        [RoyaltyPostingController::class, 'index']
    )->name('admin.royalty-posting.index');

    Route::post(
        '/royalties/posting',
        [RoyaltyPostingController::class, 'store']
    )->name('admin.royalty-posting.store');


    Route::get(
        '/revenue-label-mappings',
        [RevenueLabelMappingController::class, 'index']
    )->name('admin.revenue-label-mappings.index');

    Route::put(
        '/revenue-label-mappings/{mapping}',
        [RevenueLabelMappingController::class, 'update']
    )->name('admin.revenue-label-mappings.update');


    Route::post(
        '/revenue-label-mappings/create-label',
        [RevenueLabelMappingController::class, 'createLabel']
    )->name('admin.revenue-label-mappings.create-label');


    Route::get(
        '/royalties/labels/{label}',
        [RoyaltyController::class, 'showLabel']
    )->name('admin.royalties.labels.show');


    Route::get(
        '/reports/export',
        [ReportController::class, 'export']
    )->name('admin.reports.export');


    Route::get(
        '/reports/tracks/{isrc}',
        [ReportController::class, 'showTrack']
    )->name('admin.reports.tracks.show');

        Route::get(
            '/delivery-status',
            [ReleaseDeliveryController::class, 'index']
        )->name('admin.delivery-status.index');

        Route::get(
            '/delivery-status/{release}',
            [ReleaseDeliveryController::class, 'show']
        )->name('admin.delivery-status.show');

        Route::patch(
            '/delivery-status/{delivery}',
            [ReleaseDeliveryController::class, 'update']
        )->name('admin.delivery-status.update');

        Route::post(
            '/delivery-status/{release}/sync',
            [ReleaseDeliveryController::class, 'syncStores']
        )->name('admin.delivery-status.sync');

        Route::patch(
            '/delivery-status/{release}/bulk-update',
            [ReleaseDeliveryController::class, 'bulkUpdate']
        )->name('admin.delivery-status.bulk-update');

        Route::get(
            '/settings/distribution-stores',
            [DistributionStoreController::class, 'index']
        )->name('admin.distribution-stores.index');

        Route::post(
            '/settings/distribution-stores',
            [DistributionStoreController::class, 'store']
        )->name('admin.distribution-stores.store');

        Route::post(
            '/settings/distribution-stores/{distributionStore}',
            [DistributionStoreController::class, 'update']
        )->name('admin.distribution-stores.update');

        Route::post(
            '/settings/distribution-stores/{distributionStore}/toggle',
            [DistributionStoreController::class, 'toggle']
        )->name('admin.distribution-stores.toggle');

        Route::delete(
            '/settings/distribution-stores/{distributionStore}',
            [DistributionStoreController::class, 'destroy']
        )->name('admin.distribution-stores.destroy');

        Route::post(
            '/releases/{release}/tracks',
            [ReleaseTrackController::class, 'store']
        )->name('admin.release-tracks.store');

        Route::patch(
            '/tracks/{track}',
            [ReleaseTrackController::class, 'update']
        )->name('admin.release-tracks.update');

        Route::delete(
            '/tracks/{track}',
            [ReleaseTrackController::class, 'destroy']
        )->name('admin.release-tracks.destroy');


        Route::post('/releases/{release}/submit', function (
            \App\Models\Distribution\Release $release
        ) {
            $release->load('tracks');

            $errors = [];

            if (!$release->title) {
                $errors[] = 'Release title is missing.';
            }

            if (!$release->artist_id || !$release->primary_artist_name) {
                $errors[] = 'Primary artist is missing.';
            }

            if (!$release->label_id) {
                $errors[] = 'Label is missing.';
            }

            if (!$release->catalog_number) {
                $errors[] = 'Catalogue number is missing.';
            }

            if (!$release->digital_release_date) {
                $errors[] = 'Digital release date is missing.';
            }

            if ($release->tracks->isEmpty()) {
                $errors[] = 'At least one track is required.';
            }

            if ($release->tracks->contains(
                fn ($track) => !$track->audio_path
            )) {
                $errors[] = 'Every track must have a WAV file.';
            }

            if (empty($release->stores)) {
                $errors[] = 'At least one digital store must be selected.';
            }

            if (
                !$release->worldwide
                && empty($release->territories)
            ) {
                $errors[] = 'Select at least one territory.';
            }

            if (!empty($errors)) {
                return back()->withErrors([
                    'submission' => implode(' ', $errors),
                ]);
            }

            $release->update([
                'status' => 'submitted',
                'wizard_step' => 4,
                'completion_percentage' => 100,
                'submitted_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            return redirect()
                ->route('admin.releases.index')
                ->with('success', 'Release submitted for review successfully.');
        })->name('admin.releases.submit');

        Route::post('/releases/{release}/approve', function (
            \App\Models\Distribution\Release $release
        ) {
            if ($release->status !== 'submitted') {
                return back()->withErrors([
                    'review' => 'Only submitted releases can be approved.',
                ]);
            }

            $release->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => auth()->id(),

                'review_notes' => null,
                'rejection_reason' => null,
                'rejected_at' => null,
                'rejected_by' => null,

                'updated_by' => auth()->id(),
            ]);

            $activeStores = \App\Models\DistributionStore::query()
                ->where('is_active', true)
                ->whereNotIn(
                    'id',
                    $release->excluded_store_ids ?? []
                )
                ->get(['id']);

            foreach ($activeStores as $store) {
                \App\Models\ReleaseStoreDelivery::updateOrCreate(
                    [
                        'release_id' => $release->id,
                        'distribution_store_id' => $store->id,
                    ],
                    [
                        'status' => 'pending',
                        'updated_by' => auth()->id(),
                    ]
                );
            }

            return back()->with('success', 'Release approved successfully.');
        })->name('admin.releases.approve');

        Route::post('/releases/{release}/reject', function (
            \Illuminate\Http\Request $request,
            \App\Models\Distribution\Release $release
        ) {
            $validated = $request->validate([
                'rejection_reason' => ['required', 'string', 'max:2000'],
            ]);

            if (!in_array($release->status, ['submitted', 'approved'], true)) {
                return back()->withErrors([
                    'review' => 'This release cannot be rejected.',
                ]);
            }

            $release->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'rejected_at' => now(),
                'rejected_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            return back()->with('success', 'Release rejected successfully.');
        })->name('admin.releases.reject');

        Route::post('/releases/{release}/request-changes', function (
            \Illuminate\Http\Request $request,
            \App\Models\Distribution\Release $release
        ) {
            $validated = $request->validate([
                'review_notes' => ['required', 'string', 'max:2000'],
            ]);

            $release->update([
                'status' => 'changes_requested',
                'review_notes' => $validated['review_notes'],

                'approved_at' => null,
                'approved_by' => null,
                'rejection_reason' => null,
                'rejected_at' => null,
                'rejected_by' => null,

                'updated_by' => auth()->id(),
            ]);

            return back()->with(
                'success',
                'Release returned for corrections.'
            );
        })->name('admin.releases.request-changes');


        Route::post('/releases/{release}/approve', function (
            \App\Models\Distribution\Release $release
        ) {
            if ($release->status !== 'submitted') {
                return back()->withErrors([
                    'review' => 'Only submitted releases can be approved.',
                ]);
            }

            $release->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            return back()->with('success', 'Release approved successfully.');
        })->name('admin.releases.approve');

        Route::post('/releases/{release}/reject', function (
            \Illuminate\Http\Request $request,
            \App\Models\Distribution\Release $release
        ) {
            $validated = $request->validate([
                'rejection_reason' => ['required', 'string', 'max:2000'],
            ]);

            if (!in_array($release->status, ['submitted', 'approved'], true)) {
                return back()->withErrors([
                    'review' => 'This release cannot be rejected.',
                ]);
            }

            $release->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'rejected_at' => now(),
                'rejected_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            return back()->with('success', 'Release rejected successfully.');
        })->name('admin.releases.reject');

        Route::post('/releases/{release}/request-changes', function (
            \Illuminate\Http\Request $request,
            \App\Models\Distribution\Release $release
        ) {
            $validated = $request->validate([
                'review_notes' => ['required', 'string', 'max:2000'],
            ]);

            $release->update([
                'status' => 'changes_requested',
                'review_notes' => $validated['review_notes'],
                'updated_by' => auth()->id(),
            ]);

            return back()->with(
                'success',
                'Release returned for corrections.'
            );
        })->name('admin.releases.request-changes');


        Route::resource('artists', ArtistController::class)
            ->except(['show'])
            ->names('admin.artists');

        Route::post(
            '/artists/{artist}/resend-invitation',
            [ArtistController::class, 'resendInvitation']
        )->name('admin.artists.resend-invitation');
    });
});

/*
|--------------------------------------------------------------------------
| Profile Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/profile', [
        ProfileController::class,
        'edit',
    ])->name('profile.edit');

    Route::patch('/profile', [
        ProfileController::class,
        'update',
    ])->name('profile.update');

    Route::delete('/profile', [
        ProfileController::class,
        'destroy',
    ])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Laravel Authentication Routes
|--------------------------------------------------------------------------
*/



/*
|--------------------------------------------------------------------------
| Mixx Tune dashboard compatibility route
|--------------------------------------------------------------------------
|
| Default Laravel authentication controllers and legacy tests may request
| route("dashboard"). This route safely redirects each authenticated user
| to the correct role-based panel.
|
| mixxtune.dashboard.compatibility
|
*/
Route::middleware('auth')->get('/dashboard', function (
    \Illuminate\Http\Request $request
) {
    $role = strtolower(
        trim((string) $request->user()->role)
    );

    $destination = match ($role) {
        'super_admin', 'super-admin' =>
            '/super-admin/dashboard',

        'admin' =>
            '/admin/dashboard',

        'label' =>
            '/label/dashboard',

        default =>
            '/artist/dashboard',
    };

    return redirect()->to($destination);
})->name('dashboard');


/*
|--------------------------------------------------------------------------
| Mixx Tune Single-Domain Panel Routes
|--------------------------------------------------------------------------
|
| Main domain:
| https://www.mixxtune.com
|
| Public      /
| Artist      /artist/*
| Label       /label/*
| Admin       /admin/*
| Super Admin /super-admin/*
|
| These routes are safe aliases. Existing routes remain active during
| migration and can be removed only after full browser verification.
|
*/

Route::middleware([
    'auth',
    'verified',
])->group(function () {
    /*
     * Artist panel aliases
     */
    Route::prefix('artist')
        ->name('single.artist.')
        ->middleware('role:artist')
        ->group(function () {
            Route::get('/', function () {
                return redirect()->route(
                    'single.artist.dashboard'
                );
            })->name('home');

            Route::get('/dashboard', function () {
                return redirect()->route(
                    'v2.dashboard'
                );
            })->name('dashboard');

            Route::get('/releases', function () {
                return redirect()->route(
                    'v2.releases.index'
                );
            })->name('releases.index');

            Route::get('/releases/create', function () {
                return redirect()->route(
                    'v2.releases.create'
                );
            })->name('releases.create');

            Route::get('/catalogue', function () {
                return redirect()->route(
                    'v2.catalogue.index'
                );
            })->name('catalogue.index');

            Route::get('/reports', function () {
                return redirect()->route(
                    'v2.reports.index'
                );
            })->name('reports.index');

            Route::get('/royalties', function () {
                return redirect()->route(
                    'v2.royalties.index'
                );
            })->name('royalties.index');

            Route::get('/wallet', function () {
                return redirect()->route(
                    'v2.wallet.index'
                );
            })->name('wallet.index');

            Route::get('/statements', function () {
                return redirect()->route(
                    'v2.statements.index'
                );
            })->name('statements.index');

            Route::get('/invoices', function () {
                return redirect()->route(
                    'v2.invoices.index'
                );
            })->name('invoices.index');

            Route::get('/withdrawals', function () {
                return redirect()->route(
                    'v2.withdrawals.index'
                );
            })->name('withdrawals.index');

            Route::get('/kyc', function () {
                return redirect()->route(
                    'v2.kyc-profile.edit'
                );
            })->name('kyc.edit');

            Route::get('/support', function () {
                return redirect()->route(
                    'v2.support.index'
                );
            })->name('support.index');

            Route::get('/settings', function () {
                return redirect()->route(
                    'profile.edit'
                );
            })->name('settings.index');
        });

    /*
     * Label panel aliases
     */
    Route::prefix('label')
        ->name('single.label.')
        ->middleware('role:label')
        ->group(function () {
            Route::get('/', function () {
                return redirect()->route(
                    'single.label.dashboard'
                );
            })->name('home');

            Route::get('/dashboard', function () {
                return redirect()->route(
                    'v2.label.dashboard'
                );
            })->name('dashboard');

            Route::get('/artists', function () {
                return redirect()->route(
                    'v2.label.artists.index'
                );
            })->name('artists.index');

            Route::get('/releases', function () {
                return redirect()->route(
                    'v2.releases.index'
                );
            })->name('releases.index');

            Route::get('/catalogue', function () {
                return redirect()->route(
                    'v2.catalogue.index'
                );
            })->name('catalogue.index');

            Route::get('/reports', function () {
                return redirect()->route(
                    'v2.reports.index'
                );
            })->name('reports.index');

            Route::get('/royalties', function () {
                return redirect()->route(
                    'v2.royalties.index'
                );
            })->name('royalties.index');

            Route::get('/wallet', function () {
                return redirect()->route(
                    'v2.wallet.index'
                );
            })->name('wallet.index');

            Route::get('/withdrawals', function () {
                return redirect()->route(
                    'v2.withdrawals.index'
                );
            })->name('withdrawals.index');

            Route::get('/support', function () {
                return redirect()->route(
                    'v2.support.index'
                );
            })->name('support.index');

            Route::get('/settings', function () {
                return redirect()->route(
                    'profile.edit'
                );
            })->name('settings.index');
        });

    /*
     * Admin panel aliases
     */
    Route::prefix('admin')
        ->name('single.admin.')
        ->middleware('role:admin')
        ->group(function () {
            Route::get('/', function () {
                return redirect()->route(
                    'single.admin.dashboard'
                );
            })->name('home');

            Route::get('/dashboard', function () {
                return redirect()->route(
                    'v2.admin.dashboard'
                );
            })->name('dashboard');

            Route::get('/artists', function () {
                return redirect()->route(
                    'v2.admin.artists.index'
                );
            })->name('artists.index');

            Route::get('/labels', function () {
                return redirect()->route(
                    'v2.admin.labels.index'
                );
            })->name('labels.index');

            Route::get('/release-reviews', function () {
                return redirect()->route(
                    'v2.admin.release-reviews.index'
                );
            })->name('release-reviews.index');

            Route::get('/distribution', function () {
                return redirect()->route(
                    'v2.admin.delivery-management.index'
                );
            })->name('distribution.index');

            Route::get('/reports', function () {
                return redirect()->route(
                    'v2.admin.reports.index'
                );
            })->name('reports.index');


            Route::get('/reports/imports', function () {
                return redirect()->route(
                    'v2.admin.reports.imports.index'
                );
            })->name('reports.imports');

            Route::post(
                '/reports/imports',
                [\App\Http\Controllers\V2\Admin\ReportImportController::class, 'store']
            )->name('reports.imports.store');



            Route::get('/royalties', function () {
                return redirect()->route(
                    'v2.admin.royalties.index'
                );
            })->name('royalties.index');

            Route::get('/wallet', function () {
                return redirect()->route(
                    'v2.admin.wallet.index'
                );
            })->name('wallet.index');

            Route::get('/withdrawals', function () {
                return redirect()->route(
                    'v2.admin.withdrawals.index'
                );
            })->name('withdrawals.index');

            Route::get('/support', function () {
                return redirect()->route(
                    'v2.admin.support.index'
                );
            })->name('support.index');

            Route::get('/settings', function () {
                return redirect()->route(
                    'v2.admin.settings.index'
                );
            })->name('settings.index');
        });

    /*
     * Super Admin aliases
     *
     * Super Admin currently uses the same V2 admin backend, but gets a
     * separate public URL namespace for future role-specific screens.
     */
    Route::prefix('super-admin')
        ->name('single.super-admin.')
        ->middleware('role:super_admin')
        ->group(function () {
            Route::get('/', function () {
                return redirect()->route(
                    'single.super-admin.dashboard'
                );
            })->name('home');

            Route::get('/dashboard', function () {
                return redirect()->route(
                    'v2.admin.dashboard'
                );
            })->name('dashboard');

            Route::get('/users', function () {
                return redirect()->route(
                    'v2.admin.users.index'
                );
            })->name('users.index');

            Route::get('/admins', function () {
                return redirect()->route(
                    'v2.admin.admins.index'
                );
            })->name('admins.index');

            Route::get('/labels', function () {
                return redirect()->route(
                    'v2.admin.labels.index'
                );
            })->name('labels.index');

            Route::get('/artists', function () {
                return redirect()->route(
                    'v2.admin.artists.index'
                );
            })->name('artists.index');

            Route::get('/finance', function () {
                return redirect()->route(
                    'v2.admin.finance.index'
                );
            })->name('finance.index');

            Route::get('/distribution', function () {
                return redirect()->route(
                    'v2.admin.delivery-management.index'
                );
            })->name('distribution.index');

            Route::get('/logs', function () {
                return redirect()->route(
                    'v2.admin.audit-logs.index'
                );
            })->name('logs.index');

            Route::get('/settings', function () {
                return redirect()->route(
                    'v2.admin.settings.index'
                );
            })->name('settings.index');
        });
});


/*
|--------------------------------------------------------------------------
| Mixx Tune Public Marketing Pages
|--------------------------------------------------------------------------
*/

Route::get('/distribution', function () {
    return \Inertia\Inertia::render(
        'Public/Distribution'
    );
})->name('public.marketing.distribution');

Route::get('/pricing', function () {
    return \Inertia\Inertia::render(
        'Public/Pricing'
    );
})->name('public.marketing.pricing');

Route::get('/about', function () {
    return \Inertia\Inertia::render(
        'Public/About'
    );
})->name('public.marketing.about');

Route::get('/contact', function () {
    return \Inertia\Inertia::render(
        'Public/Contact'
    );
})->name('public.marketing.contact');


Route::get('/help', function () {
    return \Inertia\Inertia::render(
        'Public/Help'
    );
})->name('public.marketing.help');

Route::get('/privacy', function () {
    return \Inertia\Inertia::render(
        'Public/Privacy'
    );
})->name('public.marketing.privacy');

Route::get('/terms', function () {
    return \Inertia\Inertia::render(
        'Public/Terms'
    );
})->name('public.marketing.terms');


Route::get('/sitemap.xml', function () {
    $urls = [
        '/',
        '/distribution',
        '/pricing',
        '/about',
        '/contact',
        '/help',
        '/privacy',
        '/terms',
    ];

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    foreach ($urls as $url) {
        $xml .= '<url>'
            . '<loc>'
            . e('https://www.mixxtune.com' . $url)
            . '</loc>'
            . '</url>';
    }

    $xml .= '</urlset>';

    return response(
        $xml,
        200,
        [
            'Content-Type' =>
                'application/xml',
        ]
    );
})->name('public.sitemap');

/* End Mixx Tune Single-Domain Panel Routes */


require __DIR__.'/auth.php';


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/dashboard',
        \App\Http\Controllers\V2\DashboardController::class
    )
    ->name('v2.dashboard');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/releases/create',
        [\App\Http\Controllers\V2\ReleaseController::class, 'create']
    )
    ->name('v2.releases.create');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/releases',
        [\App\Http\Controllers\V2\ReleaseController::class, 'store']
    )
    ->name('v2.releases.store');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/releases/{release}',
        [\App\Http\Controllers\V2\ReleaseController::class, 'show']
    )
    ->name('v2.releases.show');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/releases/{release}/edit',
        [\App\Http\Controllers\V2\ReleaseController::class, 'edit']
    )
    ->name('v2.releases.edit');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/releases/{release}',
        [\App\Http\Controllers\V2\ReleaseController::class, 'update']
    )
    ->name('v2.releases.update');


Route::middleware(['auth', 'verified'])
    ->delete(
        '/v2/releases/{release}',
        [\App\Http\Controllers\V2\ReleaseController::class, 'destroy']
    )
    ->name('v2.releases.destroy');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/releases/{release}/tracks',
        [\App\Http\Controllers\V2\ReleaseTrackController::class, 'store']
    )
    ->name('v2.release-tracks.store');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/tracks/{track}',
        [\App\Http\Controllers\V2\ReleaseTrackController::class, 'update']
    )
    ->name('v2.release-tracks.update');


Route::middleware(['auth', 'verified'])
    ->delete(
        '/v2/tracks/{track}',
        [\App\Http\Controllers\V2\ReleaseTrackController::class, 'destroy']
    )
    ->name('v2.release-tracks.destroy');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/tracks/{track}/stream',
        [\App\Http\Controllers\V2\ReleaseTrackController::class, 'stream']
    )
    ->name('v2.release-tracks.stream');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/tracks/{track}/download',
        [\App\Http\Controllers\V2\ReleaseTrackController::class, 'download']
    )
    ->name('v2.release-tracks.download');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/releases/{release}/distribution',
        [\App\Http\Controllers\V2\ReleaseController::class, 'saveDistribution']
    )
    ->name('v2.releases.distribution.update');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/releases/{release}/submit',
        [\App\Http\Controllers\V2\ReleaseController::class, 'submitForReview']
    )
    ->name('v2.releases.submit');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/releases',
        [\App\Http\Controllers\V2\ReleaseController::class, 'index']
    )
    ->name('v2.releases.index');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/releases/{release}/submission-checklist',
        [\App\Http\Controllers\V2\ReleaseController::class, 'submissionChecklist']
    )
    ->name('v2.releases.submission-checklist');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/release-reviews',
        [\App\Http\Controllers\V2\Admin\ReleaseReviewController::class, 'index']
    )
    ->name('v2.admin.release-reviews.index');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/release-reviews/{release}',
        [\App\Http\Controllers\V2\Admin\ReleaseReviewController::class, 'show']
    )
    ->name('v2.admin.release-reviews.show');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/release-reviews/{release}/metadata',
        [\App\Http\Controllers\V2\Admin\ReleaseReviewController::class, 'updateMetadata']
    )
    ->name('v2.admin.release-reviews.metadata.update');

Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/release-reviews/{release}/tracks/{track}/metadata',
        [\App\Http\Controllers\V2\Admin\ReleaseReviewController::class, 'updateTrackMetadata']
    )
    ->name('v2.admin.release-reviews.track-metadata.update');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/release-reviews/{release}/approve',
        [\App\Http\Controllers\V2\Admin\ReleaseReviewController::class, 'approve']
    )
    ->name('v2.admin.release-reviews.approve');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/release-reviews/{release}/reject',
        [\App\Http\Controllers\V2\Admin\ReleaseReviewController::class, 'reject']
    )
    ->name('v2.admin.release-reviews.reject');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/release-reviews/{release}/request-changes',
        [\App\Http\Controllers\V2\Admin\ReleaseReviewController::class, 'requestChanges']
    )
    ->name('v2.admin.release-reviews.request-changes');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/release-reviews/{release}/start-processing',
        [\App\Http\Controllers\V2\Admin\ReleaseReviewController::class, 'startProcessing']
    )
    ->name('v2.admin.release-reviews.start-processing');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/releases/{release}/delivery',
        [\App\Http\Controllers\V2\DeliveryController::class, 'show']
    )
    ->name('v2.releases.delivery.show');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/releases/{release}/delivery',
        [\App\Http\Controllers\V2\Admin\DeliveryController::class, 'index']
    )
    ->name('v2.admin.delivery.index');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/releases/{release}/delivery/initialise',
        [\App\Http\Controllers\V2\Admin\DeliveryController::class, 'initialise']
    )
    ->name('v2.admin.delivery.initialise');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/deliveries/{deliveryRecord}',
        [\App\Http\Controllers\V2\Admin\DeliveryController::class, 'update']
    )
    ->name('v2.admin.delivery.update');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/releases/{release}/delivery/bulk',
        [\App\Http\Controllers\V2\Admin\DeliveryController::class, 'bulkUpdate']
    )
    ->name('v2.admin.delivery.bulk-update');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/identifiers/pending',
        [\App\Http\Controllers\V2\Admin\IdentifierController::class, 'pending']
    )
    ->name('v2.admin.identifiers.pending');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/tracks/{track}/isrc/assign',
        [\App\Http\Controllers\V2\Admin\IdentifierController::class, 'assignIsrc']
    )
    ->name('v2.admin.identifiers.isrc.assign');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/tracks/{track}/isrc/generate',
        [\App\Http\Controllers\V2\Admin\IdentifierController::class, 'generateIsrc']
    )
    ->name('v2.admin.identifiers.isrc.generate');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/releases/{release}/upc/assign',
        [\App\Http\Controllers\V2\Admin\IdentifierController::class, 'assignUpc']
    )
    ->name('v2.admin.identifiers.upc.assign');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/releases/{release}/upc/generate',
        [\App\Http\Controllers\V2\Admin\IdentifierController::class, 'generateUpc']
    )
    ->name('v2.admin.identifiers.upc.generate');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/identifiers/isrc/bulk-generate',
        [\App\Http\Controllers\V2\Admin\IdentifierController::class, 'bulkGenerateIsrc']
    )
    ->name('v2.admin.identifiers.isrc.bulk');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/identifiers/upc/bulk-generate',
        [\App\Http\Controllers\V2\Admin\IdentifierController::class, 'bulkGenerateUpc']
    )
    ->name('v2.admin.identifiers.upc.bulk');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/notifications',
        [\App\Http\Controllers\V2\NotificationController::class, 'index']
    )
    ->name('v2.notifications.index');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/notifications/{notification}/read',
        [\App\Http\Controllers\V2\NotificationController::class, 'markRead']
    )
    ->name('v2.notifications.read');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/notifications/read-all',
        [\App\Http\Controllers\V2\NotificationController::class, 'markAllRead']
    )
    ->name('v2.notifications.read-all');


Route::middleware(['auth', 'verified'])
    ->delete(
        '/v2/notifications/{notification}',
        [\App\Http\Controllers\V2\NotificationController::class, 'dismiss']
    )
    ->name('v2.notifications.dismiss');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/catalogue',
        [\App\Http\Controllers\V2\CatalogueController::class, 'index']
    )
    ->name('v2.catalogue.index');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/catalogue/{catalogueItem}',
        [\App\Http\Controllers\V2\CatalogueController::class, 'show']
    )
    ->name('v2.catalogue.show');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/catalogue/releases/{release}/sync',
        [\App\Http\Controllers\V2\Admin\CatalogueController::class, 'syncRelease']
    )
    ->name('v2.admin.catalogue.sync-release');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/catalogue/bulk-sync',
        [\App\Http\Controllers\V2\Admin\CatalogueController::class, 'bulkSync']
    )
    ->name('v2.admin.catalogue.bulk-sync');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/releases/{release}/activity',
        [\App\Http\Controllers\V2\ReleaseActivityController::class, 'index']
    )
    ->name('v2.releases.activity.index');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/tracks/{track}/credits',
        [\App\Http\Controllers\V2\TrackCreditsController::class, 'show']
    )
    ->name('v2.track-credits.show');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/tracks/{track}/contributors',
        [\App\Http\Controllers\V2\TrackCreditsController::class, 'createContributor']
    )
    ->name('v2.track-credits.contributors.create');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/tracks/{track}/contributors/attach',
        [\App\Http\Controllers\V2\TrackCreditsController::class, 'attachContributor']
    )
    ->name('v2.track-credits.contributors.attach');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/track-contributors/{credit}',
        [\App\Http\Controllers\V2\TrackCreditsController::class, 'updateCredit']
    )
    ->name('v2.track-credits.credit.update');


Route::middleware(['auth', 'verified'])
    ->delete(
        '/v2/track-contributors/{credit}',
        [\App\Http\Controllers\V2\TrackCreditsController::class, 'deleteCredit']
    )
    ->name('v2.track-credits.credit.delete');


Route::middleware(['auth', 'verified'])
    ->put(
        '/v2/tracks/{track}/splits',
        [\App\Http\Controllers\V2\TrackCreditsController::class, 'replaceSplits']
    )
    ->name('v2.track-credits.splits.replace');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/tracks/{track}/audio-validation',
        [\App\Http\Controllers\V2\Admin\AudioValidationController::class, 'show']
    )
    ->name('v2.admin.audio-validation.show');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/tracks/{track}/audio-validation',
        [\App\Http\Controllers\V2\Admin\AudioValidationController::class, 'validateTrack']
    )
    ->name('v2.admin.audio-validation.run');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/audio-validation/bulk',
        [\App\Http\Controllers\V2\Admin\AudioValidationController::class, 'bulkValidate']
    )
    ->name('v2.admin.audio-validation.bulk');


Route::domain('admin.mixxtune.com')
    ->middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/dashboard',
        [\App\Http\Controllers\V2\Admin\DashboardController::class, 'index']
    )
    ->name('v2.admin.dashboard');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/processing',
        [\App\Http\Controllers\V2\Admin\ProcessingController::class, 'index']
    )
    ->name('v2.admin.processing.index');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/processing/{release}',
        [\App\Http\Controllers\V2\Admin\ProcessingController::class, 'show']
    )
    ->name('v2.admin.processing.show');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/processing/{release}/generate-identifiers',
        [\App\Http\Controllers\V2\Admin\ProcessingController::class, 'generateIdentifiers']
    )
    ->name('v2.admin.processing.identifiers');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/processing/{release}/validate-audio',
        [\App\Http\Controllers\V2\Admin\ProcessingController::class, 'validateAudio']
    )
    ->name('v2.admin.processing.audio');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/processing/{release}/initialise-delivery',
        [\App\Http\Controllers\V2\Admin\ProcessingController::class, 'initialiseDelivery']
    )
    ->name('v2.admin.processing.delivery');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/distribution',
        [\App\Http\Controllers\V2\Admin\DeliveryManagementController::class, 'index']
    )
    ->name('v2.admin.delivery-management.index');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/distribution/{release}',
        [\App\Http\Controllers\V2\Admin\DeliveryManagementController::class, 'show']
    )
    ->name('v2.admin.delivery-management.show');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/distribution/{release}/initialise',
        [\App\Http\Controllers\V2\Admin\DeliveryManagementController::class, 'initialise']
    )
    ->name('v2.admin.delivery-management.initialise');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/distribution/deliveries/{deliveryRecord}',
        [\App\Http\Controllers\V2\Admin\DeliveryManagementController::class, 'update']
    )
    ->name('v2.admin.delivery-management.update');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/distribution/{release}/bulk',
        [\App\Http\Controllers\V2\Admin\DeliveryManagementController::class, 'bulkUpdate']
    )
    ->name('v2.admin.delivery-management.bulk');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/reports',
        [\App\Http\Controllers\V2\ReportController::class, 'index']
    )
    ->name('v2.reports.index');

Route::middleware(['auth','verified'])
    ->get(
        '/v2/admin/reports',
        [\App\Http\Controllers\V2\ReportController::class,'index']
    )
    ->name('v2.admin.reports.index');



Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/reports/imports',
        [\App\Http\Controllers\V2\Admin\ReportImportController::class, 'index']
    )
    ->name('v2.admin.reports.imports.index');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/reports/imports',
        [\App\Http\Controllers\V2\Admin\ReportImportController::class, 'store']
    )
    ->name('v2.admin.reports.imports.store');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/royalties',
        [\App\Http\Controllers\V2\RoyaltyController::class, 'index']
    )
    ->name('v2.royalties.index');

Route::middleware(['auth','verified'])
    ->get(
        '/v2/admin/royalties',
        [\App\Http\Controllers\V2\RoyaltyController::class,'index']
    )
    ->name('v2.admin.royalties.index');



Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/wallet',
        [\App\Http\Controllers\V2\WalletController::class, 'index']
    )
    ->name('v2.wallet.index');

Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/wallet',
        [\App\Http\Controllers\V2\WalletController::class, 'index']
    )
    ->name('v2.admin.wallet.index');



Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/finance',
        [\App\Http\Controllers\V2\Admin\FinanceController::class, 'index']
    )
    ->name('v2.admin.finance.index');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/finance/statements/generate',
        [\App\Http\Controllers\V2\Admin\FinanceController::class, 'generate']
    )
    ->name('v2.admin.finance.generate');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/finance/statements/{statement}/approve',
        [\App\Http\Controllers\V2\Admin\FinanceController::class, 'approve']
    )
    ->name('v2.admin.finance.approve');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/finance/statements/{statement}/available',
        [\App\Http\Controllers\V2\Admin\FinanceController::class, 'makeAvailable']
    )
    ->name('v2.admin.finance.available');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/kyc-profile',
        [\App\Http\Controllers\V2\PayoutProfileController::class, 'edit']
    )
    ->name('v2.kyc-profile.edit');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/kyc-profile',
        [\App\Http\Controllers\V2\PayoutProfileController::class, 'update']
    )
    ->name('v2.kyc-profile.update');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/withdrawals',
        [\App\Http\Controllers\V2\WithdrawalController::class, 'index']
    )
    ->name('v2.withdrawals.index');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/withdrawals',
        [\App\Http\Controllers\V2\WithdrawalController::class, 'store']
    )
    ->name('v2.withdrawals.store');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/withdrawals',
        [\App\Http\Controllers\V2\Admin\WithdrawalManagementController::class, 'index']
    )
    ->name('v2.admin.withdrawals.index');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/withdrawals/{withdrawal}/approve',
        [\App\Http\Controllers\V2\Admin\WithdrawalManagementController::class, 'approve']
    )
    ->name('v2.admin.withdrawals.approve');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/withdrawals/{withdrawal}/reject',
        [\App\Http\Controllers\V2\Admin\WithdrawalManagementController::class, 'reject']
    )
    ->name('v2.admin.withdrawals.reject');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/withdrawals/{withdrawal}/paid',
        [\App\Http\Controllers\V2\Admin\WithdrawalManagementController::class, 'markPaid']
    )
    ->name('v2.admin.withdrawals.paid');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/kyc/{profile}/verify',
        [\App\Http\Controllers\V2\Admin\WithdrawalManagementController::class, 'verifyKyc']
    )
    ->name('v2.admin.kyc.verify');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/statements',
        [\App\Http\Controllers\V2\StatementController::class, 'index']
    )
    ->name('v2.statements.index');

Route::middleware(['auth','verified'])
    ->get(
        '/v2/admin/statements',
        [\App\Http\Controllers\V2\StatementController::class,'index']
    )
    ->name('v2.admin.statements.index');



Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/statements/{statement}/download',
        [\App\Http\Controllers\V2\StatementController::class, 'download']
    )
    ->name('v2.statements.download');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/invoices',
        [\App\Http\Controllers\V2\InvoiceController::class, 'index']
    )
    ->name('v2.invoices.index');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/invoices/{invoice}/download',
        [\App\Http\Controllers\V2\InvoiceController::class, 'download']
    )
    ->name('v2.invoices.download');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/invoices',
        [\App\Http\Controllers\V2\Admin\InvoiceManagementController::class, 'index']
    )
    ->name('v2.admin.invoices.index');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/invoices/statements/{statement}/generate',
        [\App\Http\Controllers\V2\Admin\InvoiceManagementController::class, 'generate']
    )
    ->name('v2.admin.invoices.generate');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/support',
        [\App\Http\Controllers\V2\SupportTicketController::class, 'index']
    )
    ->name('v2.support.index');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/support/create',
        [\App\Http\Controllers\V2\SupportTicketController::class, 'create']
    )
    ->name('v2.support.create');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/support',
        [\App\Http\Controllers\V2\SupportTicketController::class, 'store']
    )
    ->name('v2.support.store');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/support/{ticket}',
        [\App\Http\Controllers\V2\SupportTicketController::class, 'show']
    )
    ->name('v2.support.show');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/support/{ticket}/reply',
        [\App\Http\Controllers\V2\SupportTicketController::class, 'reply']
    )
    ->name('v2.support.reply');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/support',
        [\App\Http\Controllers\V2\Admin\SupportManagementController::class, 'index']
    )
    ->name('v2.admin.support.index');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/support/{ticket}',
        [\App\Http\Controllers\V2\Admin\SupportManagementController::class, 'show']
    )
    ->name('v2.admin.support.show');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/support/{ticket}/reply',
        [\App\Http\Controllers\V2\Admin\SupportManagementController::class, 'reply']
    )
    ->name('v2.admin.support.reply');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/support/{ticket}',
        [\App\Http\Controllers\V2\Admin\SupportManagementController::class, 'update']
    )
    ->name('v2.admin.support.update');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/admins',
        [\App\Http\Controllers\V2\Admin\AssignmentManagementController::class, 'admins']
    )
    ->name('v2.admin.admins.index');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/admins/{admin}',
        [\App\Http\Controllers\V2\Admin\AssignmentManagementController::class, 'showAdmin']
    )
    ->name('v2.admin.admins.show');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/admins/{admin}/assignments',
        [\App\Http\Controllers\V2\Admin\AssignmentManagementController::class, 'updateAssignments']
    )
    ->name('v2.admin.admins.assignments');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/artists',
        [\App\Http\Controllers\V2\Admin\AssignmentManagementController::class, 'artists']
    )
    ->name('v2.admin.artists.index');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/labels',
        [\App\Http\Controllers\V2\Admin\AssignmentManagementController::class, 'labels']
    )
    ->name('v2.admin.labels.index');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/settings',
        [\App\Http\Controllers\V2\Admin\SystemSettingsController::class, 'index']
    )
    ->name('v2.admin.settings.index');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/settings',
        [\App\Http\Controllers\V2\Admin\SystemSettingsController::class, 'update']
    )
    ->name('v2.admin.settings.update');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/audit-logs',
        [\App\Http\Controllers\V2\Admin\AuditLogController::class, 'index']
    )
    ->name('v2.admin.audit-logs.index');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/users',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'index']
    )
    ->name('v2.admin.users.index');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/users/create',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'create']
    )
    ->name('v2.admin.users.create');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/users',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'store']
    )
    ->name('v2.admin.users.store');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/users/{user}/toggle-status',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'toggleStatus']
    )
    ->name('v2.admin.users.toggle-status');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/users/{user}/resend-invitation',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'resendInvitation']
    )
    ->name('v2.admin.users.resend-invitation');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/users/{user}/edit',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'edit']
    )
    ->name('v2.admin.users.edit');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/users/{user}',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'update']
    )
    ->name('v2.admin.users.update');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/users/{user}/reset-password',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'resetPassword']
    )
    ->name('v2.admin.users.reset-password');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/users/{user}/invitation-link',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'invitationLink']
    )
    ->name('v2.admin.users.invitation-link');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/users/{user}/impersonate',
        [\App\Http\Controllers\V2\Admin\UserImpersonationController::class, 'start']
    )
    ->name('v2.admin.users.impersonate');


Route::middleware(['auth'])
    ->post(
        '/v2/impersonation/stop',
        [\App\Http\Controllers\V2\Admin\UserImpersonationController::class, 'stop']
    )
    ->name('v2.impersonation.stop');


Route::middleware(['auth', 'verified'])
    ->prefix('v2/admin/stores')
    ->name('v2.admin.stores.')
    ->group(function () {
        Route::get(
            '/',
            [\App\Http\Controllers\V2\Admin\DistributionStoreController::class, 'index']
        )->name('index');

        Route::post(
            '/',
            [\App\Http\Controllers\V2\Admin\DistributionStoreController::class, 'store']
        )->name('store');

        Route::post(
            '/{distributionStore}',
            [\App\Http\Controllers\V2\Admin\DistributionStoreController::class, 'update']
        )->name('update');

        Route::post(
            '/{distributionStore}/toggle',
            [\App\Http\Controllers\V2\Admin\DistributionStoreController::class, 'toggle']
        )->name('toggle');

        Route::delete(
            '/{distributionStore}',
            [\App\Http\Controllers\V2\Admin\DistributionStoreController::class, 'destroy']
        )->name('destroy');
    });

/*
|--------------------------------------------------------------------------
| V2 OWNERSHIP ENGINE ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])
    ->prefix('v2/admin/ownership')
    ->name('v2.admin.ownership.')
    ->group(function () {
        Route::get(
            '/',
            [
                \App\Http\Controllers\V2\Admin\OwnershipTransferController::class,
                'index',
            ]
        )->name('index');

        Route::post(
            '/release',
            [
                \App\Http\Controllers\V2\Admin\OwnershipTransferController::class,
                'transferRelease',
            ]
        )->name('release');

        Route::post(
            '/track',
            [
                \App\Http\Controllers\V2\Admin\OwnershipTransferController::class,
                'transferTrack',
            ]
        )->name('track');

        Route::post(
            '/label-user',
            [
                \App\Http\Controllers\V2\Admin\OwnershipTransferController::class,
                'transferLabelUser',
            ]
        )->name('label-user');

        Route::post(
            '/artist-user',
            [
                \App\Http\Controllers\V2\Admin\OwnershipTransferController::class,
                'transferArtistUser',
            ]
        )->name('artist-user');
    });

/*
|--------------------------------------------------------------------------
| Unified User Invitation V2
|--------------------------------------------------------------------------
|
| Public invitation acceptance flow for Admin, Label and Artist accounts.
| Invitation tokens are stored as SHA-256 hashes.
|
*/

Route::domain('admin.mixxtune.com')
    ->group(function () {
        Route::get(
            '/invitation/{token}',
            \App\Http\Controllers\Auth\AcceptUserInvitationController::class
        )->name('user.invitation.accept');

        Route::get(
            '/invitation-set-password',
            [
                \App\Http\Controllers\Auth\UserInvitationPasswordController::class,
                'create',
            ]
        )->name('user.invitation.password.create');

        Route::post(
            '/invitation-set-password',
            [
                \App\Http\Controllers\Auth\UserInvitationPasswordController::class,
                'store',
            ]
        )->name('user.invitation.password.store');

        Route::get(
            '/invitation-invalid',
            fn () => \Inertia\Inertia::render(
                'Auth/Login',
                [
                    'status' =>
                        'Invitation is invalid, expired, or already used.',
                    'canResetPassword' => true,
                ]
            )
        )->name('user.invitation.invalid');
    });

/*
|--------------------------------------------------------------------------
| Unified User Invitation V2
|--------------------------------------------------------------------------
|
| Public invitation acceptance flow for Admin, Label and Artist accounts.
| Invitation tokens are stored as SHA-256 hashes.
|
*/

require __DIR__.'/single-domain.php';
