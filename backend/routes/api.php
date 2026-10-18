<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BusinessSearchController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MyBusinessController;
use App\Http\Controllers\Api\V1\MyBusinessOpeningHoursController;
use App\Http\Controllers\Api\V1\MyBusinessPhotoController;
use App\Http\Controllers\Api\V1\MyBusinessServicesController;
use App\Http\Controllers\Api\V1\PlaceSearchController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReferenceDataController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::get('/reference-data', ReferenceDataController::class)
        ->middleware('cache.headers:public;max_age=3600;etag');

    // Recherche (sans compte) : garages proches et lieux pour centrer la carte.
    Route::get('/businesses/search', BusinessSearchController::class)->middleware('throttle:search');
    Route::get('/places/search', PlaceSearchController::class)->middleware('throttle:places');

    Route::prefix('auth')->group(function () {
        Route::post('/otp', [AuthController::class, 'requestCode'])->middleware('throttle:otp-request');
        Route::post('/otp/verify', [AuthController::class, 'verifyCode'])->middleware('throttle:otp-verify');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [ProfileController::class, 'show']);
        Route::patch('/me', [ProfileController::class, 'update']);

        // Espace garagiste : fiche du garage de l'utilisateur connecté.
        Route::get('/my-business', [MyBusinessController::class, 'show']);
        Route::post('/my-business', [MyBusinessController::class, 'store']);

        Route::middleware('has.business')->prefix('my-business')->group(function () {
            Route::patch('/', [MyBusinessController::class, 'update']);
            Route::put('/services', MyBusinessServicesController::class);
            Route::put('/opening-hours', MyBusinessOpeningHoursController::class);
            Route::post('/photos', [MyBusinessPhotoController::class, 'store']);
            Route::put('/photos/order', [MyBusinessPhotoController::class, 'reorder']);
            Route::delete('/photos/{photo}', [MyBusinessPhotoController::class, 'destroy'])->whereNumber('photo');
        });
    });
});
