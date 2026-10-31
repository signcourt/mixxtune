<?php

use App\Http\Controllers\Api\Core\ReleaseController;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Core\LabelController;
use App\Http\Controllers\Api\Core\ContributorController;
use App\Http\Controllers\Api\Core\ArtistController;
use App\Http\Controllers\Api\Distribution\TrackSplitController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

/* W115 MIXXTUNE_RAZORPAY_PAYOUTS: RazorpayX payout status (checked by signature, no login) */
Route::post('/webhooks/razorpayx', \App\Http\Controllers\V2\RazorpayWebhookController::class)->middleware('throttle:600,1,rzp-webhook')->name('webhooks.razorpayx');

/* W128 MIXXTUNE_OFFICE_MONITOR: the desktop activity agent (device token, not a user session) */
Route::prefix('office/agent')->group(function () {
    Route::post('/pair', [\App\Http\Controllers\V2\Office\AgentController::class, 'pair'])->middleware('throttle:30,1,office-pair');
    Route::middleware('office.agent')->group(function () {
        Route::post('/heartbeat', [\App\Http\Controllers\V2\Office\AgentController::class, 'heartbeat'])->middleware('throttle:120,1,office-hb');
        Route::post('/screenshot', [\App\Http\Controllers\V2\Office\AgentController::class, 'screenshot'])->middleware('throttle:60,1,office-shot');
        Route::get('/settings', [\App\Http\Controllers\V2\Office\AgentController::class, 'settings']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post(
        '/tracks/{track}/splits',
        [TrackSplitController::class, 'store']
    );

    Route::get('/labels', [LabelController::class, 'index']);
    Route::post('/labels', [LabelController::class, 'store']);
    Route::get('/labels/{label}', [LabelController::class, 'show']);
    Route::put('/labels/{label}', [LabelController::class, 'update']);
    Route::delete('/labels/{label}', [LabelController::class, 'destroy']);
    Route::post('/labels/{label}/restore', [LabelController::class, 'restore']);

    Route::get('/artists', [ArtistController::class, 'index']);
    Route::post('/artists', [ArtistController::class, 'store']);
    Route::get('/artists/{artist}', [ArtistController::class, 'show']);
    Route::put('/artists/{artist}', [ArtistController::class, 'update']);
    Route::delete('/artists/{artist}', [ArtistController::class, 'destroy']);
    Route::post('/artists/{artist}/restore', [ArtistController::class, 'restore']);


    Route::get('/contributors', [ContributorController::class, 'index']);
    Route::post('/contributors', [ContributorController::class, 'store']);
    Route::get('/contributors/{contributor}', [ContributorController::class, 'show']);
    Route::put('/contributors/{contributor}', [ContributorController::class, 'update']);
    Route::delete('/contributors/{contributor}', [ContributorController::class, 'destroy']);
    Route::post('/contributors/{contributor}/restore', [ContributorController::class, 'restore']);


    Route::apiResource('releases', ReleaseController::class);

});
