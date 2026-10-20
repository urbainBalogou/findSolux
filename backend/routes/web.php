<?php

use App\Http\Controllers\GaragePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Page web publique d'un garage (R4), partagée depuis l'application.
Route::get('/garages/{business:slug}', GaragePageController::class)->name('garages.show');
