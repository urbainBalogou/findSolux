<?php

use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\BusinessPromotionController;
use App\Http\Controllers\Admin\CommercialOfferController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PlatformSettingController;
use App\Http\Controllers\Admin\RequestAcceptancePriceController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\GaragePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Page web publique d'un garage (R4), partagée depuis l'application.
Route::get('/garages/{business:slug}', GaragePageController::class)->name('garages.show');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('/connexion', [SessionController::class, 'create'])->name('login');
        Route::post('/connexion', [SessionController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::delete('/session', [SessionController::class, 'destroy'])->name('logout');
        Route::resource('commercial-offers', CommercialOfferController::class)
            ->only(['index', 'store', 'update']);
        Route::put('/tarifs/acceptation', RequestAcceptancePriceController::class)->name('request-acceptance-price.update');
        Route::resource('business-promotions', BusinessPromotionController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::get('/garages', [BusinessController::class, 'index'])->name('businesses.index');
        Route::post('/garages/{business}/offres', [BusinessController::class, 'grant'])->name('businesses.grant');
        Route::get('/parametres', [PlatformSettingController::class, 'edit'])->name('settings.edit');
        Route::put('/parametres', [PlatformSettingController::class, 'update'])->name('settings.update');
    });
});
