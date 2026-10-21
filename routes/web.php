<?php

use App\Http\Controllers\V2\GeneratedReportController;

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
use App\Http\Controllers\V2\Admin\NotificationManagementController;

/*
|--------------------------------------------------------------------------
| Public Home Website
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Artist Panel
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Admin Panel
|--------------------------------------------------------------------------
*/


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
                return inertia('Profile/Settings');
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

            Route::get('/revenue-sharing', function () {
                return redirect()->route(
                    'v2.label.revenue-sharing.index'
                );
            })->name('revenue-sharing.index');

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
                return inertia('Profile/Settings');
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


            /*
             * ----------------------------------------------------------
             * Operational admin path bridge
             * ----------------------------------------------------------
             *
             * These routes preserve required legacy workflows while
             * moving the browser-facing contract to:
             *
             *     www.mixxtune.com/admin/*
             *
             * No admin subdomain is required for these endpoints.
             */


            /*
             * Delivery Management
             */

            Route::get(
                '/delivery-status',
                [
                    \App\Http\Controllers\Admin\ReleaseDeliveryController::class,
                    'index',
                ]
            )->name('delivery-status.index');

            Route::get(
                '/delivery-status/{release}',
                [
                    \App\Http\Controllers\Admin\ReleaseDeliveryController::class,
                    'show',
                ]
            )->name('delivery-status.show');

            Route::patch(
                '/delivery-status/{delivery}',
                [
                    \App\Http\Controllers\Admin\ReleaseDeliveryController::class,
                    'update',
                ]
            )->name('delivery-status.update');

            Route::post(
                '/delivery-status/{release}/sync',
                [
                    \App\Http\Controllers\Admin\ReleaseDeliveryController::class,
                    'syncStores',
                ]
            )->name('delivery-status.sync');

            Route::patch(
                '/delivery-status/{release}/bulk-update',
                [
                    \App\Http\Controllers\Admin\ReleaseDeliveryController::class,
                    'bulkUpdate',
                ]
            )->name('delivery-status.bulk-update');


            /*
             * ISRC / UPC
             */

            Route::get(
                '/isrc-upc',
                [
                    \App\Http\Controllers\Admin\IsrcUpcController::class,
                    'index',
                ]
            )->name('isrc-upc.index');

            Route::post(
                '/isrc-upc/generate-isrc',
                [
                    \App\Http\Controllers\Admin\IsrcUpcController::class,
                    'bulkGenerateIsrc',
                ]
            )->name('isrc-upc.generate-isrc');

            Route::post(
                '/isrc-upc/generate-upc',
                [
                    \App\Http\Controllers\Admin\IsrcUpcController::class,
                    'bulkGenerateUpc',
                ]
            )->name('isrc-upc.generate-upc');

            Route::patch(
                '/isrc-upc/tracks/{track}',
                [
                    \App\Http\Controllers\Admin\IsrcUpcController::class,
                    'updateTrack',
                ]
            )->name('isrc-upc.tracks.update');

            Route::patch(
                '/isrc-upc/releases/{release}',
                [
                    \App\Http\Controllers\Admin\IsrcUpcController::class,
                    'updateRelease',
                ]
            )->name('isrc-upc.releases.update');


            /*
             * Legacy Revenue Imports
             */

            Route::get(
                '/revenue-imports',
                [
                    \App\Http\Controllers\Admin\RevenueImportController::class,
                    'index',
                ]
            )->name('revenue-imports.index');

            Route::post(
                '/revenue-imports',
                [
                    \App\Http\Controllers\Admin\RevenueImportController::class,
                    'store',
                ]
            )->name('revenue-imports.store');

            Route::get(
                '/revenue-imports/{revenueImport}',
                [
                    \App\Http\Controllers\Admin\RevenueImportController::class,
                    'show',
                ]
            )->name('revenue-imports.show');

            Route::post(
                '/revenue-imports/{revenueImport}/process',
                [
                    \App\Http\Controllers\Admin\RevenueImportController::class,
                    'process',
                ]
            )->name('revenue-imports.process');

            Route::delete(
                '/revenue-imports/{revenueImport}',
                [
                    \App\Http\Controllers\Admin\RevenueImportController::class,
                    'destroy',
                ]
            )->name('revenue-imports.destroy');


            /*
             * Revenue Label Mapping
             */

            Route::get(
                '/revenue-label-mappings',
                [
                    \App\Http\Controllers\Admin\RevenueLabelMappingController::class,
                    'index',
                ]
            )->name('revenue-label-mappings.index');

            Route::post(
                '/revenue-label-mappings/create-label',
                [
                    \App\Http\Controllers\Admin\RevenueLabelMappingController::class,
                    'createLabel',
                ]
            )->name('revenue-label-mappings.create-label');

            Route::put(
                '/revenue-label-mappings/{mapping}',
                [
                    \App\Http\Controllers\Admin\RevenueLabelMappingController::class,
                    'update',
                ]
            )->name('revenue-label-mappings.update');


            /*
             * Royalty Posting
             */

            Route::get(
                '/royalties/posting',
                [
                    \App\Http\Controllers\Admin\RoyaltyPostingController::class,
                    'index',
                ]
            )->name('royalty-posting.index');

            Route::post(
                '/royalties/posting',
                [
                    \App\Http\Controllers\Admin\RoyaltyPostingController::class,
                    'store',
                ]
            )->name('royalty-posting.store');


            /*
             * Royalty Ledgers
             */

            Route::get(
                '/royalty-ledgers',
                [
                    \App\Http\Controllers\Admin\RoyaltyLedgerController::class,
                    'index',
                ]
            )->name('royalty-ledgers.index');

            Route::get(
                '/royalty-ledgers/{id}',
                [
                    \App\Http\Controllers\Admin\RoyaltyLedgerController::class,
                    'show',
                ]
            )->name('royalty-ledgers.show');



            /*
             * Distribution Stores
             */

            Route::get(
                '/settings/distribution-stores',
                [
                    \App\Http\Controllers\Admin\DistributionStoreController::class,
                    'index',
                ]
            )->name('distribution-stores.index');

            Route::post(
                '/settings/distribution-stores',
                [
                    \App\Http\Controllers\Admin\DistributionStoreController::class,
                    'store',
                ]
            )->name('distribution-stores.store');

            Route::post(
                '/settings/distribution-stores/{distributionStore}',
                [
                    \App\Http\Controllers\Admin\DistributionStoreController::class,
                    'update',
                ]
            )->name('distribution-stores.update');

            Route::post(
                '/settings/distribution-stores/{distributionStore}/toggle',
                [
                    \App\Http\Controllers\Admin\DistributionStoreController::class,
                    'toggle',
                ]
            )->name('distribution-stores.toggle');

            Route::delete(
                '/settings/distribution-stores/{distributionStore}',
                [
                    \App\Http\Controllers\Admin\DistributionStoreController::class,
                    'destroy',
                ]
            )->name('distribution-stores.destroy');


            /*
             * Admin Management
             */

            Route::get(
                '/admin-management',
                [
                    \App\Http\Controllers\Admin\AdminManagementController::class,
                    'index',
                ]
            )->name('management.index');

            Route::post(
                '/admin-management',
                [
                    \App\Http\Controllers\Admin\AdminManagementController::class,
                    'store',
                ]
            )->name('management.store');

            Route::patch(
                '/admin-management/{admin}/toggle-status',
                [
                    \App\Http\Controllers\Admin\AdminManagementController::class,
                    'toggleStatus',
                ]
            )->name('management.toggle-status');

            Route::delete(
                '/admin-management/{admin}',
                [
                    \App\Http\Controllers\Admin\AdminManagementController::class,
                    'destroy',
                ]
            )->name('management.destroy');

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

            /*
             * MIXXTUNE_SUPERADMIN_ROYALTY_LEDGER_LIFECYCLE_V1
             *
             * Manager keeps scoped read-only ledger access.
             * Approval/cancellation/wallet posting are Super Admin only.
             */
            Route::get(
                '/royalty-ledgers',
                [
                    \App\Http\Controllers\Admin\RoyaltyLedgerController::class,
                    'index',
                ]
            )->name('royalty-ledgers.index');

            /*
             * FF431_K17C2_SUPERADMIN_LOCAL_PRECEDENCE
             *
             * Static Rights-import batch routes MUST stay above
             * /royalty-ledgers/{id} inside this same Super Admin group.
             */
            /*
             * FF431_K17B_RIGHTS_LEDGER_BATCH_ROUTES
             *
             * Exact Rights-import batch actions.
             * Super Admin only because this block already lives inside
             * the existing super_admin middleware group.
             */
            Route::post(
                '/royalty-ledgers/rights-import/approve',
                [
                    \App\Http\Controllers\Admin\RoyaltyLedgerController::class,
                    'approveRightsImportBatch',
                ]
            )->name(
                'royalty-ledgers.rights-import.approve'
            );

            Route::post(
                '/royalty-ledgers/rights-import/credit-wallet',
                [
                    \App\Http\Controllers\Admin\RoyaltyLedgerController::class,
                    'creditRightsImportBatch',
                ]
            )->name(
                'royalty-ledgers.rights-import.credit-wallet'
            );


            Route::get(
                '/royalty-ledgers/{id}',
                [
                    \App\Http\Controllers\Admin\RoyaltyLedgerController::class,
                    'show',
                ]
            )->name('royalty-ledgers.show');

            Route::post(
                '/royalty-ledgers/{id}/approve',
                [
                    \App\Http\Controllers\Admin\RoyaltyLedgerController::class,
                    'approve',
                ]
            )->name('royalty-ledgers.approve');

            Route::post(
                '/royalty-ledgers/{id}/cancel',
                [
                    \App\Http\Controllers\Admin\RoyaltyLedgerController::class,
                    'cancel',
                ]
            )->name('royalty-ledgers.cancel');

            Route::post(
                '/royalty-ledgers/{id}/credit-wallet',
                [
                    \App\Http\Controllers\Admin\RoyaltyLedgerController::class,
                    'creditWallet',
                ]
            )->name('royalty-ledgers.credit-wallet');



            /*
             * Master Report Analyzer
             *
             * Super Admin only.
             * Raw vendor/master report preprocessing and audit.
             * Completely separate from the existing revenue import/mapping flow.
             */
            Route::get(
                '/master-report-analyzer/batches',
                [
                    \App\Http\Controllers\V2\Admin\MasterReportAnalyzerController::class,
                    'index',
                ]
            )->name('master-report-analyzer.index');

            Route::post(
                '/master-report-analyzer/upload',
                [
                    \App\Http\Controllers\V2\Admin\MasterReportAnalyzerController::class,
                    'upload',
                ]
            )->name('master-report-analyzer.upload');

            Route::get(
                '/master-report-analyzer/{masterReportBatch}/overview',
                [
                    \App\Http\Controllers\V2\Admin\MasterReportAnalyzerController::class,
                    'overview',
                ]
            )->name('master-report-analyzer.overview');

            Route::get(
                '/master-report-analyzer/{masterReportBatch}/stores',
                [
                    \App\Http\Controllers\V2\Admin\MasterReportAnalyzerController::class,
                    'stores',
                ]
            )->name('master-report-analyzer.stores');

            Route::get(
                '/master-report-analyzer/{masterReportBatch}/tracks',
                [
                    \App\Http\Controllers\V2\Admin\MasterReportAnalyzerController::class,
                    'tracks',
                ]
            )->name('master-report-analyzer.tracks');

            Route::get(
                '/master-report-analyzer/{masterReportBatch}/reconciliation',
                [
                    \App\Http\Controllers\V2\Admin\MasterReportAnalyzerController::class,
                    'reconciliation',
                ]
            )->name('master-report-analyzer.reconciliation');

            Route::get(
                '/master-report-analyzer/{masterReportBatch}/filter-options',
                [
                    \App\Http\Controllers\V2\Admin\MasterReportAnalyzerController::class,
                    'filterOptions',
                ]
            )->name('master-report-analyzer.filter-options');

            Route::post(
                '/master-report-analyzer/{masterReportBatch}/export',
                [
                    \App\Http\Controllers\V2\Admin\MasterReportAnalyzerController::class,
                    'export',
                ]
            )->name('master-report-analyzer.export');

            Route::get(
                '/master-report-analyzer/{masterReportBatch}/original',
                [
                    \App\Http\Controllers\V2\Admin\MasterReportAnalyzerController::class,
                    'downloadOriginal',
                ]
            )->name('master-report-analyzer.original');

            Route::delete(
                '/master-report-analyzer/{masterReportBatch}',
                [
                    \App\Http\Controllers\V2\Admin\MasterReportAnalyzerController::class,
                    'destroy',
                ]
            )->name('master-report-analyzer.destroy');

            Route::get(
                '/unmapped-revenue',
                [
                    \App\Http\Controllers\V2\Admin\UnmappedRevenueController::class,
                    'index',
                ]
            )->name('unmapped-revenue.index');

            Route::get(
                '/unmapped-revenue/export-mapping',
                [
                    \App\Http\Controllers\V2\Admin\UnmappedRevenueController::class,
                    'exportMappingWorkbook',
                ]
            )->name('unmapped-revenue.export-mapping');

            Route::post(
                '/unmapped-revenue/preview-mapping',
                [
                    \App\Http\Controllers\V2\Admin\UnmappedRevenueController::class,
                    'previewMappingWorkbook',
                ]
            )->name('unmapped-revenue.preview-mapping');


            Route::post(
                '/unmapped-revenue/apply-mapping',
                [
                    \App\Http\Controllers\V2\Admin\UnmappedRevenueController::class,
                    'applyMappingWorkbook',
                ]
            )->name('unmapped-revenue.apply-mapping');

            Route::post(
                '/unmapped-revenue',
                [
                    \App\Http\Controllers\V2\Admin\UnmappedRevenueController::class,
                    'store',
                ]
            )->name('unmapped-revenue.store');

            /*
             * MIXXTUNE_BULK_SELECTED_MAPPING_BACKEND_V1
             */
            Route::post(
                '/unmapped-revenue/bulk-map-selected',
                [
                    \App\Http\Controllers\V2\Admin\UnmappedRevenueController::class,
                    'bulkMapSelected',
                ]
            )->name(
                'unmapped-revenue.bulk-map-selected'
            );
            Route::post(
                '/unmapped-revenue/create-and-map-label',
                [
                    \App\Http\Controllers\V2\Admin\UnmappedRevenueController::class,
                    'createAndMapLabel',
                ]
            )->name('unmapped-revenue.create-and-map-label');

            /*
             * MIXXTUNE_UNMAPPED_AUTO_RESOLVE_V1
             */
            Route::post(
                '/unmapped-revenue/auto-resolve',
                [
                    \App\Http\Controllers\V2\Admin\UnmappedRevenueController::class,
                    'autoResolve',
                ]
            )->name(
                'unmapped-revenue.auto-resolve'
            );

            Route::post(
                '/unmapped-revenue/apply-all',
                [
                    \App\Http\Controllers\V2\Admin\UnmappedRevenueController::class,
                    'applyAll',
                ]
            )->name('unmapped-revenue.apply-all');


            /*
             * MIXXTUNE_BULK_POSTERS_V1
             * Super Admin: posters for many releases at once
             * (file name = UPC or catalogue number, exact match only).
             */
            Route::get(
                '/posters',
                [\App\Http\Controllers\V2\Admin\BulkPosterController::class, 'index']
            )->name('posters.index');

            Route::get(
                '/posters/missing.csv',
                [\App\Http\Controllers\V2\Admin\BulkPosterController::class, 'missingCsv']
            )->name('posters.missing');

            Route::post(
                '/posters/match',
                [\App\Http\Controllers\V2\Admin\BulkPosterController::class, 'match']
            )->name('posters.match');

            Route::post(
                '/posters/upload',
                [\App\Http\Controllers\V2\Admin\BulkPosterController::class, 'upload']
            )->middleware('throttle:240,1')->name('posters.upload');

            /*
             * MIXXTUNE_CATALOGUE_HEALTH_V1
             * Super Admin, read only: what is missing in the catalogue.
             */
            Route::get(
                '/catalogue-health',
                [\App\Http\Controllers\V2\Admin\CatalogueHealthController::class, 'index']
            )->name('catalogue-health.index');

            Route::get(
                '/catalogue-health/export',
                [\App\Http\Controllers\V2\Admin\CatalogueHealthController::class, 'export']
            )->name('catalogue-health.export');

            /*
             * Legacy Catalogue Bulk Import
             *
             * Super Admin only.
             * Metadata staging/validation only.
             * No audio upload.
             * No automatic UPC/ISRC generation.
             */
            Route::get(
                '/legacy-catalogue-imports',
                [
                    \App\Http\Controllers\V2\Admin\LegacyCatalogueImportController::class,
                    'index',
                ]
            )->name('legacy-catalogue-imports.index');

            Route::post(
                '/legacy-catalogue-imports',
                [
                    \App\Http\Controllers\V2\Admin\LegacyCatalogueImportController::class,
                    'store',
                ]
            )->name('legacy-catalogue-imports.store');

            Route::post(
                '/legacy-catalogue-imports/{legacyCatalogueImport}/dry-run',
                [
                    \App\Http\Controllers\V2\Admin\LegacyCatalogueImportController::class,
                    'dryRun',
                ]
            )->name(
                'legacy-catalogue-imports.dry-run'
            );

            Route::post(
                '/legacy-catalogue-imports/{legacyCatalogueImport}/approve-entities',
                [
                    \App\Http\Controllers\V2\Admin\LegacyCatalogueImportController::class,
                    'approveEntities',
                ]
            )->name(
                'legacy-catalogue-imports.approve-entities'
            );

            Route::post(
                '/legacy-catalogue-imports/{legacyCatalogueImport}/import',
                [
                    \App\Http\Controllers\V2\Admin\LegacyCatalogueImportController::class,
                    'import',
                ]
            )->name(
                'legacy-catalogue-imports.import'
            );

            Route::get(
                '/legacy-catalogue-imports/{legacyCatalogueImport}',
                [
                    \App\Http\Controllers\V2\Admin\LegacyCatalogueImportController::class,
                    'show',
                ]
            )->name('legacy-catalogue-imports.show');

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

            Route::get('/profit', function () {
                return redirect()->route(
                    'v2.admin.profit.index'
                );
            })->name('profit.index');

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
    ->put(
        '/v2/sidebar/navigation-order',
        [\App\Http\Controllers\V2\SidebarPreferenceController::class, 'update']
    )
    ->name('v2.sidebar.navigation-order.update');


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
    ->patch(
        '/v2/notifications/{notification}/star',
        [\App\Http\Controllers\V2\NotificationController::class, 'toggleStar']
    )
    ->name('v2.notifications.star');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/catalogue',
        [\App\Http\Controllers\V2\CatalogueController::class, 'index']
    )
    ->name('v2.catalogue.index');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/catalogue/export',
        [\App\Http\Controllers\V2\CatalogueController::class, 'export']
    )
    ->name('v2.catalogue.export');


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


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/analytics',
        [\App\Http\Controllers\V2\AnalyticsController::class, 'index']
    )
    ->name('v2.analytics.index');

Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/analytics/export',
        [\App\Http\Controllers\V2\AnalyticsController::class, 'export']
    )
    ->name('v2.analytics.export');

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
        '/v2/admin/reports/imports/{reportImport}/view',
        [\App\Http\Controllers\V2\Admin\ReportImportController::class, 'show']
    )
    ->name('v2.admin.reports.imports.show');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/reports/imports/{reportImport}/download',
        [\App\Http\Controllers\V2\Admin\ReportImportController::class, 'download']
    )
    ->name('v2.admin.reports.imports.download');

Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/reports/imports/{reportImport}/re-detect-unmapped',
        [
            \App\Http\Controllers\V2\Admin\ReportImportController::class,
            'reDetectUnmapped'
        ]
    )
    ->name(
        'v2.admin.reports.imports.re-detect-unmapped'
    );


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/reports/imports/{reportImport}/approve-and-schedule',
        [\App\Http\Controllers\V2\Admin\ReportImportController::class, 'approveAndSchedule']
    )
    ->name(
        'v2.admin.reports.imports.approve-and-schedule'
    );

            Route::post(
                '/v2/admin/reports/imports/{reportImport}/missing-revenue-rates',
                [\App\Http\Controllers\V2\Admin\ReportImportController::class, 'saveMissingRevenueRates']
            )->name(
                'v2.admin.reports.imports.missing-revenue-rates'
            );



Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/reports/imports/{reportImport}',
        [\App\Http\Controllers\V2\Admin\ReportImportController::class, 'update']
    )
    ->name('v2.admin.reports.imports.update');

Route::middleware(['auth', 'verified'])
    ->delete(
        '/v2/admin/reports/imports/{reportImport}',
        [\App\Http\Controllers\V2\Admin\ReportImportController::class, 'destroy']
    )
    ->name('v2.admin.reports.imports.destroy');


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

/*
 * MIXXTUNE_RIGHTS_ROYALTY_UPLOAD_ROUTES_V1
 *
 * Rights royalty statement processing.
 * No wallet posting at import stage.
 */
/*
 * MIXXTUNE_RIGHTS_SUPERADMIN_LIFECYCLE_V1
 *
 * Raw Rights import inspection and every mutation are restricted
 * to Super Admin. Manager/Label/Artist use scoped royalty views.
 */
Route::middleware([
        'auth',
        'verified',
        'role:super_admin',
    ])
    ->prefix('v2/admin/royalties/rights')
    ->name('v2.admin.royalties.rights.')
    ->group(function () {
        Route::post(
            '/inspect',
            [\App\Http\Controllers\V2\RightsRoyaltyImportController::class, 'inspect']
        )->name('inspect');

        Route::post(
            '/imports',
            [\App\Http\Controllers\V2\RightsRoyaltyImportController::class, 'store']
        )->name('store');

        Route::get(
            '/imports/{import:public_id}',
            [\App\Http\Controllers\V2\RightsRoyaltyImportController::class, 'show']
        )->name('show');

        Route::post(
            '/imports/{import:public_id}/approve',
            [\App\Http\Controllers\V2\RightsRoyaltyImportController::class, 'approve']
        )->name('approve');

        Route::post(
            '/imports/{import:public_id}/publish',
            [\App\Http\Controllers\V2\RightsRoyaltyImportController::class, 'publish']
        )->name('publish');
    });




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



/*
|--------------------------------------------------------------------------
| Super Admin Business Profit
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/profit',
        [
            \App\Http\Controllers\V2\Admin\SuperAdminProfitController::class,
            'index',
        ]
    )
    ->name('v2.admin.profit.index');

Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/profit/export',
        [
            \App\Http\Controllers\V2\Admin\SuperAdminProfitController::class,
            'export',
        ]
    )
    ->name('v2.admin.profit.export');


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


// MIXXTUNE_FINANCE_HOLD_RELEASE_V1
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/finance/holds/{hold}/release',
        [\App\Http\Controllers\V2\Admin\FinanceController::class, 'releaseHold']
    )
    ->whereNumber('hold')
    ->name('v2.admin.finance.hold.release');


Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/kyc-profile',
        [\App\Http\Controllers\V2\PayoutProfileController::class, 'edit']
    )
    ->name('v2.kyc-profile.edit');

Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/kyc-profile/submit',
        [\App\Http\Controllers\V2\PayoutProfileController::class, 'submit']
    )
    ->name('v2.kyc-profile.submit');



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
    ->get(
        '/v2/admin/withdrawals/export',
        [\App\Http\Controllers\V2\Admin\WithdrawalManagementController::class, 'export']
    )
    ->name('v2.admin.withdrawals.export');


/* MIXXTUNE_PAYOUT_FILES_V1: Axis payout file + TDS report (super admin) */
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/payouts/axis-file',
        [\App\Http\Controllers\V2\Admin\PayoutFilesController::class, 'bankFile']
    )
    ->name('v2.admin.payouts.axis-file');

Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/payouts/tds-report',
        [\App\Http\Controllers\V2\Admin\PayoutFilesController::class, 'tdsReport']
    )
    ->name('v2.admin.payouts.tds-report');

/* MIXXTUNE_INVITATION_REMINDERS_V1: send due reminders now (super admin) */
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/users/invitation-reminders',
        [\App\Http\Controllers\V2\Admin\InvitationReminderController::class, 'store']
    )
    ->name('v2.admin.users.invitation-reminders');


Route::middleware(['auth', 'verified'])
    ->delete(
        '/v2/admin/withdrawals/{withdrawal}',
        [\App\Http\Controllers\V2\Admin\WithdrawalManagementController::class, 'destroy']
    )
    ->name('v2.admin.withdrawals.destroy');


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
    ->get(
        '/v2/admin/kyc',
        [\App\Http\Controllers\V2\Admin\KycReviewController::class, 'index']
    )
    ->name('v2.admin.kyc.index');


Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/kyc/{profile}/verify',
        [\App\Http\Controllers\V2\Admin\KycReviewController::class, 'decide']
    )
    ->name('v2.admin.kyc.verify');

/* MIXXTUNE_KYC_REVIEW_V1 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/v2/admin/kyc/{profile}/history', [\App\Http\Controllers\V2\Admin\KycReviewController::class, 'history'])->whereNumber('profile')->name('v2.admin.kyc.history');
    Route::post('/v2/admin/kyc/{profile}/reveal', [\App\Http\Controllers\V2\Admin\KycReviewController::class, 'reveal'])->whereNumber('profile')->middleware('throttle:30,1')->name('v2.admin.kyc.reveal');
    Route::post('/v2/admin/kyc/remind', [\App\Http\Controllers\V2\Admin\KycReviewController::class, 'remind'])->name('v2.admin.kyc.remind');
});


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
        '/v2/legal-operations',
        [
            \App\Http\Controllers\V2\LegalOperationsController::class,
            'index',
        ]
    )
    ->name('v2.legal-operations.index');


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

/* MIXXTUNE_SUPPORT_DESK_V1 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/v2/support/{ticket}/close', [\App\Http\Controllers\V2\SupportTicketController::class, 'close'])->name('v2.support.close');
    Route::post('/v2/support/{ticket}/reopen', [\App\Http\Controllers\V2\SupportTicketController::class, 'reopen'])->name('v2.support.reopen');
    Route::get('/v2/support/{ticket}/files/{message}/{index}', [\App\Http\Controllers\V2\SupportTicketController::class, 'file'])
        ->whereNumber('index')->name('v2.support.file');
    Route::get('/v2/admin/support/search-users', [\App\Http\Controllers\V2\Admin\SupportManagementController::class, 'searchUsers'])->name('v2.admin.support.search-users');
    Route::post('/v2/admin/support', [\App\Http\Controllers\V2\Admin\SupportManagementController::class, 'store'])->name('v2.admin.support.store');
});


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

Route::middleware([
    'auth',
    'verified',
])->group(function () {
    Route::get(
        '/v2/admin/artists/create',
        [
            \App\Http\Controllers\V2\Admin\AssignmentManagementController::class,
            'createArtist',
        ]
    )->name('v2.admin.artists.create');

    Route::post(
        '/v2/admin/artists',
        [
            \App\Http\Controllers\V2\Admin\AssignmentManagementController::class,
            'storeArtist',
        ]
    )->name('v2.admin.artists.store');

    Route::get(
        '/v2/admin/artists/{artist}',
        [
            \App\Http\Controllers\V2\Admin\AssignmentManagementController::class,
            'showArtist',
        ]
    )->name('v2.admin.artists.show');

    Route::get(
        '/v2/admin/artists/{artist}/edit',
        [
            \App\Http\Controllers\V2\Admin\AssignmentManagementController::class,
            'editArtist',
        ]
    )->name('v2.admin.artists.edit');

    Route::patch(
        '/v2/admin/artists/{artist}',
        [
            \App\Http\Controllers\V2\Admin\AssignmentManagementController::class,
            'updateArtist',
        ]
    )->name('v2.admin.artists.update');

    Route::delete(
        '/v2/admin/artists/{artist}',
        [
            \App\Http\Controllers\V2\Admin\AssignmentManagementController::class,
            'destroyArtist',
        ]
    )->name('v2.admin.artists.destroy');
});



Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/labels/{label}/admin',
        [\App\Http\Controllers\V2\Admin\AssignmentManagementController::class, 'assignLabelAdmin']
    )
    ->name('v2.admin.labels.assign-admin');


/*
 * FF419-C — Canonical Label Revenue Rate
 */
/*
 * MIXXTUNE_LABEL_BULK_ACTION_ROUTE_V2
 */
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/labels/bulk-action',
        [
            \App\Http\Controllers\V2\Admin\AssignmentManagementController::class,
            'bulkLabelAction',
        ]
    )
    ->name('v2.admin.labels.bulk-action');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/labels/{label}/revenue-rate',
        [
            \App\Http\Controllers\V2\Admin\AssignmentManagementController::class,
            'updateLabelRevenueRate',
        ]
    )
    ->name('v2.admin.labels.revenue-rate');


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/labels/{label}/withdrawal-status',
        [
            \App\Http\Controllers\V2\Admin\AssignmentManagementController::class,
            'updateLabelWithdrawalStatus',
        ]
    )
    ->name(
        'v2.admin.labels.withdrawal-status'
    );


Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/artists/{artist}/admin',
        [\App\Http\Controllers\V2\Admin\AssignmentManagementController::class, 'assignArtistAdmin']
    )
    ->name('v2.admin.artists.assign-admin');


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
    ->post(
        '/v2/admin/settings/branding/upload',
        [\App\Http\Controllers\V2\Admin\SystemSettingsController::class, 'uploadBrandingAsset']
    )
    ->name('v2.admin.settings.branding.upload');


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

/*
 * MIXXTUNE_QUICK_LABEL_ROUTE_V2
 */
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/users/{user}/quick-label',
        [
            \App\Http\Controllers\V2\Admin\UserManagementController::class,
            'quickLabel',
        ]
    )
    ->name('v2.admin.users.quick-label');



/*
 * MIXXTUNE_QUICK_EDIT_FIELD_CONFIG_ROUTE_V1
 */
Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/users/{user}/quick-edit-fields',
        [
            \App\Http\Controllers\V2\Admin\UserManagementController::class,
            'updateQuickEditFields',
        ]
    )
    ->name('v2.admin.users.quick-edit-fields');


/*
 * MIXXTUNE_USER_QUICK_EDIT_ROUTE_V1
 */
Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/users/{user}/quick-update',
        [
            \App\Http\Controllers\V2\Admin\UserManagementController::class,
            'quickUpdate',
        ]
    )
    ->name('v2.admin.users.quick-update');



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

/*
|--------------------------------------------------------------------------
| Mixx Tune V2 — Label User Access
|--------------------------------------------------------------------------
|
| SECURITY:
| UserAccessController performs an additional owner/master-label boundary
| check. These endpoints are never linked from Artist/Admin/Super Admin UI.
|
*/

Route::middleware(['auth', 'role:label'])
    ->prefix('v2/label/user-access')
    ->name('v2.label.user-access.')
    ->group(function () {
        Route::get(
            '/',
            [
                \App\Http\Controllers\V2\Label\UserAccessController::class,
                'index'
            ]
        )->name('index');

        Route::post(
            '/',
            [
                \App\Http\Controllers\V2\Label\UserAccessController::class,
                'store'
            ]
        )->name('store');

        Route::put(
            '/{member}',
            [
                \App\Http\Controllers\V2\Label\UserAccessController::class,
                'update'
            ]
        )->name('update');

        Route::patch(
            '/{member}/status',
            [
                \App\Http\Controllers\V2\Label\UserAccessController::class,
                'status'
            ]
        )->name('status');

        Route::delete(
            '/{member}',
            [
                \App\Http\Controllers\V2\Label\UserAccessController::class,
                'destroy'
            ]
        )->name('destroy');
    });

Route::middleware('auth')->group(function () {
    Route::get(
        '/v2/admin/notification-management',
        [NotificationManagementController::class, 'index']
    )->name('v2.admin.notification-management.index');

    Route::post(
        '/v2/admin/notification-management',
        [NotificationManagementController::class, 'store']
    )->name('v2.admin.notification-management.store');

    /* MIXXTUNE_NOTIFY_TARGETED_V1 */
    Route::post(
        '/v2/admin/notification-management/preview',
        [NotificationManagementController::class, 'preview']
    )->name('v2.admin.notification-management.preview');

    Route::get(
        '/v2/admin/notification-management/search-users',
        [NotificationManagementController::class, 'searchUsers']
    )->name('v2.admin.notification-management.search-users');

    Route::get(
        '/v2/admin/notification-management/search-labels',
        [NotificationManagementController::class, 'searchLabels']
    )->name('v2.admin.notification-management.search-labels');

    Route::get(
        '/v2/admin/notification-management/{broadcast}/recipients',
        [NotificationManagementController::class, 'recipientsList']
    )->name('v2.admin.notification-management.recipients');
});

Route::middleware(['auth'])->group(function () {
    Route::get(
        '/v2/generated-reports',
        [GeneratedReportController::class, 'index']
    )->name('v2.generated-reports.index');

    Route::post(
        '/v2/generated-reports',
        [GeneratedReportController::class, 'store']
    )->name('v2.generated-reports.store');

    Route::get(
        '/v2/generated-reports/{publicId}/download',
        [GeneratedReportController::class, 'download']
    )->name('v2.generated-reports.download');

    Route::get(
        '/v2/generated-reports/{publicId}/pdf',
        [GeneratedReportController::class, 'pdf']
    )->name('v2.generated-reports.pdf');

    Route::get(
        '/v2/reports/automatic/{month}/download',
        [GeneratedReportController::class, 'automaticDownload']
    )
        ->where(
            'month',
            '\\d{4}-\\d{2}'
        )
        ->name('v2.reports.automatic.download');

    Route::get(
        '/v2/reports/automatic/{month}/pdf',
        [GeneratedReportController::class, 'automaticPdf']
    )
        ->where(
            'month',
            '\\d{4}-\\d{2}'
        )
        ->name('v2.reports.automatic.pdf');

    Route::delete(
        '/v2/generated-reports/{publicId}',
        [GeneratedReportController::class, 'destroy']
    )->name('v2.generated-reports.destroy');
});


/* MIXXTUNE_LABEL_HIERARCHY_ROUTES */
Route::middleware([
    'auth',
    'verified',
    'role:super_admin',
])
    ->prefix('super-admin')
    ->group(function () {
        Route::get(
            '/label-hierarchy',
            [
                \App\Http\Controllers\V2\Admin\LabelHierarchyController::class,
                'index',
            ]
        )->name('v2.admin.label-hierarchy.index');

        Route::post(
            '/label-hierarchy',
            [
                \App\Http\Controllers\V2\Admin\LabelHierarchyController::class,
                'store',
            ]
        )->name('v2.admin.label-hierarchy.store');

        Route::put(
            '/label-hierarchy/{label}',
            [
                \App\Http\Controllers\V2\Admin\LabelHierarchyController::class,
                'update',
            ]
        )->name('v2.admin.label-hierarchy.update');

        Route::delete(
            '/label-hierarchy/{label}',
            [
                \App\Http\Controllers\V2\Admin\LabelHierarchyController::class,
                'destroy',
            ]
        )->name('v2.admin.label-hierarchy.destroy');
    });

/*
|--------------------------------------------------------------------------
| Finance V3 - Financial Adjustments
|--------------------------------------------------------------------------
| Super Admin only.
| Immutable ledger entries. Corrections are posted as reversals.
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth',
    'verified',
    'role:super_admin',
])
    ->prefix('super-admin/finance/adjustments')
    ->name('single.super-admin.finance.adjustments.')
    ->group(function () {
        Route::get(
            '/',
            [
                \App\Http\Controllers\V3\SuperAdmin\FinancialAdjustmentController::class,
                'index',
            ]
        )->name('index');

        Route::post(
            '/',
            [
                \App\Http\Controllers\V3\SuperAdmin\FinancialAdjustmentController::class,
                'store',
            ]
        )->name('store');

        Route::post(
            '/{adjustment}/reverse',
            [
                \App\Http\Controllers\V3\SuperAdmin\FinancialAdjustmentController::class,
                'reverse',
            ]
        )->name('reverse');

        /* MIXXTUNE_ADJUSTMENTS_V2 */
        Route::get('/owners', [\App\Http\Controllers\V3\SuperAdmin\FinancialAdjustmentController::class, 'owners'])->name('owners');
        Route::get('/export', [\App\Http\Controllers\V3\SuperAdmin\FinancialAdjustmentController::class, 'export'])->name('export');
    });

/*
|--------------------------------------------------------------------------
| Finance V3 - Recoupment Management
|--------------------------------------------------------------------------
| Super Admin only.
| Documents are stored privately and served only through authenticated
| controller downloads.
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth',
    'verified',
    'role:super_admin',
])
    ->prefix('super-admin/finance/recoupment')
    ->name('single.super-admin.finance.recoupment.')
    ->group(function () {
        Route::get(
            '/',
            [
                \App\Http\Controllers\V3\SuperAdmin\RecoupmentController::class,
                'index',
            ]
        )->name('index');

        Route::post(
            '/',
            [
                \App\Http\Controllers\V3\SuperAdmin\RecoupmentController::class,
                'store',
            ]
        )->name('store');

        Route::get(
            '/{plan}',
            [
                \App\Http\Controllers\V3\SuperAdmin\RecoupmentController::class,
                'details',
            ]
        )->name('details');

        Route::post(
            '/{plan}/expenses',
            [
                \App\Http\Controllers\V3\SuperAdmin\RecoupmentController::class,
                'addExpense',
            ]
        )->name('expenses.store');

        Route::post(
            '/{plan}/documents',
            [
                \App\Http\Controllers\V3\SuperAdmin\RecoupmentController::class,
                'uploadDocument',
            ]
        )->name('documents.store');

        /* MIXXTUNE_FINANCE_CLEANUP_V1: stop recovering an advance (code + audit) */
        Route::post(
            '/{plan}/cancel',
            [
                \App\Http\Controllers\V3\SuperAdmin\RecoupmentController::class,
                'cancel',
            ]
        )->name('cancel');

        Route::get(
            '/{plan}/documents/{document}/download',
            [
                \App\Http\Controllers\V3\SuperAdmin\RecoupmentController::class,
                'downloadDocument',
            ]
        )->name('documents.download');
    });

/*
 * MIXXTUNE_LABEL_OWNERSHIP_OTP_V1
 * Super-admin only: assign / transfer label ownership with e-mail OTP confirmation.
 */
Route::middleware(['auth', 'verified'])
    ->prefix('v2/admin/labels/ownership')
    ->name('v2.admin.labels.ownership.')
    ->group(function () {
        Route::get('users', [\App\Http\Controllers\V2\Admin\LabelOwnershipController::class, 'users'])->name('users');
        Route::get('labels', [\App\Http\Controllers\V2\Admin\LabelOwnershipController::class, 'labels'])->name('labels');
        Route::post('otp', [\App\Http\Controllers\V2\Admin\LabelOwnershipController::class, 'sendOtp'])->middleware('throttle:10,1')->name('otp');
        Route::post('apply', [\App\Http\Controllers\V2\Admin\LabelOwnershipController::class, 'apply'])->middleware('throttle:20,1')->name('apply');
    });

/* MIXXTUNE_USER_REQUIRED_FIELDS_V1 */
Route::middleware(['auth', 'verified', 'throttle:30,1'])
    ->post(
        '/v2/admin/user-form/required-fields',
        [\App\Http\Controllers\V2\Admin\UserRequiredFieldsController::class, 'update']
    )
    ->name('v2.admin.user-form.required-fields');

/* MIXXTUNE_LABEL_OWNER_BULK_IMPORT_V1 */
Route::middleware(['auth', 'verified'])
    ->prefix('v2/admin/labels/ownership/bulk')
    ->name('v2.admin.labels.ownership.bulk.')
    ->group(function () {
        Route::get('template', [\App\Http\Controllers\V2\Admin\LabelOwnershipBulkController::class, 'template'])->name('template');
        Route::post('preview', [\App\Http\Controllers\V2\Admin\LabelOwnershipBulkController::class, 'preview'])->middleware('throttle:20,1')->name('preview');
        Route::post('otp', [\App\Http\Controllers\V2\Admin\LabelOwnershipBulkController::class, 'sendOtp'])->middleware('throttle:10,1')->name('otp');
        Route::post('confirm', [\App\Http\Controllers\V2\Admin\LabelOwnershipBulkController::class, 'confirm'])->middleware('throttle:20,1')->name('confirm');
        Route::post('run', [\App\Http\Controllers\V2\Admin\LabelOwnershipBulkController::class, 'run'])->middleware('throttle:120,1')->name('run');
    });

/* MIXXTUNE_LABEL_RATE_HISTORY_V1 */
Route::middleware(['auth', 'verified'])
    ->get('/v2/admin/labels/rate-history', [\App\Http\Controllers\V2\Admin\LabelRateHistoryController::class, 'show'])
    ->name('v2.admin.labels.rate-history');

/* MIXXTUNE_OWNER_STATEMENT_V1 */
Route::middleware(['auth', 'verified'])
    ->prefix('v2/admin/statement-emails')
    ->name('v2.admin.statement-emails.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\V2\Admin\StatementEmailController::class, 'index'])->name('index');
        Route::post('send', [\App\Http\Controllers\V2\Admin\StatementEmailController::class, 'send'])->middleware('throttle:60,1')->name('send');
        Route::get('preview', [\App\Http\Controllers\V2\Admin\StatementEmailController::class, 'preview'])->middleware('throttle:30,1')->name('preview');
    });

/* MIXXTUNE_BANK_CHANGE_PROTECTION_V1 */
Route::middleware(['auth', 'verified'])
    ->prefix('v2/admin/bank-change')
    ->name('v2.admin.bank-change.')
    ->group(function () {
        Route::post('unlock', [\App\Http\Controllers\V2\Admin\BankChangeController::class, 'unlock'])->middleware('throttle:20,1')->name('unlock');
        Route::post('resend', [\App\Http\Controllers\V2\Admin\BankChangeController::class, 'resend'])->middleware('throttle:10,1')->name('resend');
    });

/* MIXXTUNE_TWO_STEP_ACTIONS_V1 */
Route::middleware(['auth', 'verified'])
    ->prefix('v2/admin/action-otp')
    ->name('v2.admin.action-otp.')
    ->group(function () {
        Route::post('unlock', [\App\Http\Controllers\V2\Admin\ActionOtpController::class, 'unlock'])->middleware('throttle:20,1')->name('unlock');
        Route::post('resend', [\App\Http\Controllers\V2\Admin\ActionOtpController::class, 'resend'])->middleware('throttle:10,1')->name('resend');
    });
