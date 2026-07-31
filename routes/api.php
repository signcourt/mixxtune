<?php

use App\Http\Controllers\Api\Core\ReleaseController;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Core\LabelController;
use App\Http\Controllers\Api\Core\ContributorController;
use App\Http\Controllers\Api\Core\ArtistController;
use App\Http\Controllers\Api\Distribution\TrackSplitController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

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
