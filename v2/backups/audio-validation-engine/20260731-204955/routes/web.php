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
            return Inertia::render('Admin/Placeholders/ModulePage', [
                'title' => 'Labels',
                'description' => 'Create and manage record labels.',
            ]);
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
