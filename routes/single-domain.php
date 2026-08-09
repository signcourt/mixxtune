<?php

/*
|--------------------------------------------------------------------------
| MIXX_TUNE_SINGLE_DOMAIN_ARTIST_LOGIN
|--------------------------------------------------------------------------
| Artist login on the main Mixx Tune domain.
*/
\Illuminate\Support\Facades\Route::middleware('guest')->group(function () {
    \Illuminate\Support\Facades\Route::get(
        '/artist/login',
        [
            \App\Http\Controllers\Auth\AuthenticatedSessionController::class,
            'create',
        ]
    )->name('single.artist.login');

    \Illuminate\Support\Facades\Route::post(
        '/artist/login',
        [
            \App\Http\Controllers\Auth\AuthenticatedSessionController::class,
            'store',
        ]
    )->name('single.artist.login.store');
});



/*
|--------------------------------------------------------------------------
| MIXX_TUNE_ARTIST_RELEASE_ACTION_ROUTES
|--------------------------------------------------------------------------
| These routes must be declared before generic artist redirect routes.
*/
\Illuminate\Support\Facades\Route::middleware([
    'auth',
    'verified',
    'role:artist',
])
    ->prefix('artist')
    ->name('single.artist.actions.')
    ->group(function () {
        \Illuminate\Support\Facades\Route::post(
            '/releases',
            [
                \App\Http\Controllers\Artist\ReleaseController::class,
                'store',
            ]
        )->name('releases.store');

        \Illuminate\Support\Facades\Route::patch(
            '/releases/{release}',
            [
                \App\Http\Controllers\Artist\ReleaseController::class,
                'updateDraft',
            ]
        )->name('releases.update');

        \Illuminate\Support\Facades\Route::post(
            '/releases/{release}/tracks',
            [
                \App\Http\Controllers\Artist\ReleaseTrackController::class,
                'store',
            ]
        )->name('release-tracks.store');

        \Illuminate\Support\Facades\Route::patch(
            '/releases/{release}/distribution',
            [
                \App\Http\Controllers\Artist\ReleaseController::class,
                'saveDistribution',
            ]
        )->name('releases.distribution');

        \Illuminate\Support\Facades\Route::post(
            '/releases/{release}/submit',
            [
                \App\Http\Controllers\Artist\ReleaseController::class,
                'submitForReview',
            ]
        )->name('releases.submit');

        \Illuminate\Support\Facades\Route::patch(
            '/tracks/{track}',
            [
                \App\Http\Controllers\Artist\ReleaseTrackController::class,
                'update',
            ]
        )->name('tracks.update');

        \Illuminate\Support\Facades\Route::delete(
            '/tracks/{track}',
            [
                \App\Http\Controllers\Artist\ReleaseTrackController::class,
                'destroy',
            ]
        )->name('tracks.destroy');

        \Illuminate\Support\Facades\Route::get(
            '/tracks/{track}/download-audio',
            [
                \App\Http\Controllers\Artist\ReleaseTrackController::class,
                'downloadAudio',
            ]
        )->name('tracks.download-audio');
    });



use App\Http\Controllers\Auth\AcceptUserInvitationController;
use App\Http\Controllers\Auth\UserInvitationPasswordController;
use App\Http\Controllers\V2\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\V2\DashboardController as SharedDashboardController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Mixx Tune Single Domain Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('single.home');

/*
|--------------------------------------------------------------------------
| Unified Invitation
|--------------------------------------------------------------------------
*/

Route::get(
    '/invitation/{token}',
    AcceptUserInvitationController::class
)->name('single.invitation.accept');

Route::get(
    '/invitation-set-password',
    [UserInvitationPasswordController::class, 'create']
)->name('single.invitation.password.create');

Route::post(
    '/invitation-set-password',
    [UserInvitationPasswordController::class, 'store']
)->name('single.invitation.password.store');

Route::get(
    '/invitation-invalid',
    fn () => Inertia::render(
        'Auth/Login',
        [
            'status' =>
                'Invitation is invalid, expired, or already used.',
            'canResetPassword' => true,
        ]
    )
)->name('single.invitation.invalid');

/*
|--------------------------------------------------------------------------
| Panel Entry Points
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'verified',
])->group(function () {
    Route::middleware('role:admin')->get(
        '/admin/dashboard',
        [AdminDashboardController::class, 'index']
    )->name('single.admin.dashboard');

    Route::middleware('role:super_admin')->get(
        '/super-admin/dashboard',
        [AdminDashboardController::class, 'index']
    )->name('single.super-admin.dashboard');

    Route::middleware('role:artist')->get(
        '/artist/dashboard',
        SharedDashboardController::class
    )->name('single.artist.dashboard');

    Route::middleware('role:label')->get(
        '/label/dashboard',
        SharedDashboardController::class
    )->name('single.label.dashboard');
});

Route::get('/admin', function () {
    return auth()->check()
        ? redirect('/admin/dashboard')
        : redirect('/login');
});

Route::get('/super-admin', function () {
    return auth()->check()
        ? redirect('/super-admin/dashboard')
        : redirect('/login');
});

Route::get('/artist', function () {
    return auth()->check()
        ? redirect('/artist/dashboard')
        : redirect('/login');
});

Route::get('/label', function () {
    return auth()->check()
        ? redirect('/label/dashboard')
        : redirect('/login');
});

/*
|--------------------------------------------------------------------------
| Artist Legacy URL Redirects
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'verified',
    'role:artist',
])->prefix('artist')->group(function () {
    Route::redirect(
        '/releases',
        '/v2/releases'
    )->name('single.artist.releases.redirect');

    Route::redirect(
        '/releases/create',
        '/v2/releases/create'
    )->name('single.artist.releases.create.redirect');

    Route::redirect(
        '/wallet',
        '/v2/wallet'
    )->name('single.artist.wallet.redirect');

    Route::redirect(
        '/catalogue',
        '/v2/catalogue'
    )->name('single.artist.catalogue.redirect');

    Route::redirect(
        '/royalties',
        '/v2/royalties'
    )->name('single.artist.royalties.redirect');

    Route::redirect(
        '/withdrawals',
        '/v2/withdrawals'
    )->name('single.artist.withdrawals.redirect');

    Route::redirect(
        '/kyc',
        '/v2/kyc-profile'
    )->name('single.artist.kyc.redirect');
});

/*
|--------------------------------------------------------------------------
| Label Artist Management
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
])->group(function () {
    Route::get(
        '/v2/label/artists',
        [
            \App\Http\Controllers\V2\Label\ArtistManagementController::class,
            'index',
        ]
    )->name('v2.label.artists.index');
});

Route::middleware([
    'auth',
])->group(function () {
    Route::get(
        '/v2/label/artists/create',
        [
            \App\Http\Controllers\V2\Label\ArtistManagementController::class,
            'create',
        ]
    )->name('v2.label.artists.create');

    Route::post(
        '/v2/label/artists',
        [
            \App\Http\Controllers\V2\Label\ArtistManagementController::class,
            'store',
        ]
    )->name('v2.label.artists.store');
});

Route::middleware([
    'auth',
])->group(function () {
    Route::get(
        '/v2/label/artists/{artist}/edit',
        [
            \App\Http\Controllers\V2\Label\ArtistManagementController::class,
            'edit',
        ]
    )->name('v2.label.artists.edit');

    Route::patch(
        '/v2/label/artists/{artist}',
        [
            \App\Http\Controllers\V2\Label\ArtistManagementController::class,
            'update',
        ]
    )->name('v2.label.artists.update');

    Route::post(
        '/v2/label/artists/{artist}/toggle-status',
        [
            \App\Http\Controllers\V2\Label\ArtistManagementController::class,
            'toggleStatus',
        ]
    )->name('v2.label.artists.toggle-status');

    Route::post(
        '/v2/label/artists/{artist}/resend-invitation',
        [
            \App\Http\Controllers\V2\Label\ArtistManagementController::class,
            'resendInvitation',
        ]
    )->name('v2.label.artists.resend-invitation');

    Route::post(
        '/v2/label/artists/{artist}/reset-password',
        [
            \App\Http\Controllers\V2\Label\ArtistManagementController::class,
            'resetPassword',
        ]
    )->name('v2.label.artists.reset-password');
});

Route::middleware([
    'auth',
])->group(function () {
    Route::get(
        '/v2/label/artists/{artist}',
        [
            \App\Http\Controllers\V2\Label\ArtistManagementController::class,
            'show',
        ]
    )->whereNumber('artist')
     ->name('v2.label.artists.show');
});


/*
|--------------------------------------------------------------------------
| MIXX TUNE V1.3 — MASTER LABEL REVENUE SHARING
|--------------------------------------------------------------------------
|
| Strict two-tier hierarchy:
|
| Master Label
|   -> unlimited direct Sub-Labels
|   -> unlimited direct Artists
|
| Sub-Labels / Artists cannot create another hierarchy.
|
*/

\Illuminate\Support\Facades\Route::middleware([
    'auth',
    'verified',
    'role:label',
])->group(function () {
    \Illuminate\Support\Facades\Route::get(
        '/v2/label/revenue-sharing',
        [
            \App\Http\Controllers\V2\Label\RevenueSharingController::class,
            'index',
        ]
    )->name(
        'v2.label.revenue-sharing.index'
    );

    \Illuminate\Support\Facades\Route::patch(
        '/v2/label/revenue-sharing/{type}/{id}',
        [
            \App\Http\Controllers\V2\Label\RevenueSharingController::class,
            'update',
        ]
    )
        ->whereIn(
            'type',
            [
                'label',
                'artist',
            ]
        )
        ->whereNumber('id')
        ->name(
            'v2.label.revenue-sharing.update'
        );

    \Illuminate\Support\Facades\Route::patch(
        '/v2/label/revenue-sharing/{share}/toggle',
        [
            \App\Http\Controllers\V2\Label\RevenueSharingController::class,
            'toggle',
        ]
    )
        ->whereNumber('share')
        ->name(
            'v2.label.revenue-sharing.toggle'
        );
});
