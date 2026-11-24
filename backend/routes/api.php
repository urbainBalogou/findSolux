<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BusinessEventController;
use App\Http\Controllers\Api\V1\BusinessSearchController;
use App\Http\Controllers\Api\V1\CommercialOfferController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ContactRequestController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\DistressSignalController;
use App\Http\Controllers\Api\V1\FedaPayWebhookController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MyBusinessClientController;
use App\Http\Controllers\Api\V1\MyBusinessController;
use App\Http\Controllers\Api\V1\MyBusinessOpeningHoursController;
use App\Http\Controllers\Api\V1\MyBusinessPhotoController;
use App\Http\Controllers\Api\V1\MyBusinessServicesController;
use App\Http\Controllers\Api\V1\MyBusinessSubscriptionController;
use App\Http\Controllers\Api\V1\MyBusinessTeamMemberController;
use App\Http\Controllers\Api\V1\PlaceSearchController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PublicBusinessController;
use App\Http\Controllers\Api\V1\ReceivedContactRequestController;
use App\Http\Controllers\Api\V1\ReceivedDistressSignalController;
use App\Http\Controllers\Api\V1\ReferenceDataController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WalletTopUpController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::get('/reference-data', ReferenceDataController::class)
        ->middleware('cache.headers:public;max_age=3600;etag');

    Route::get('/commercial-offers', CommercialOfferController::class)
        ->middleware('cache.headers:public;max_age=3600;etag');

    Route::post('/payments/fedapay/webhook', FedaPayWebhookController::class)
        ->name('api.v1.payments.fedapay.webhook');

    // Recherche (sans compte) : garages proches et lieux pour centrer la carte.
    Route::get('/businesses/search', BusinessSearchController::class)->middleware('throttle:search');
    Route::get('/places/search', PlaceSearchController::class)->middleware('throttle:places');

    // Fiche publique (C3) et vues/clics pour les statistiques du garagiste (P8).
    // Le jeton est facultatif : il fait apparaître le numéro au client déjà en contact.
    Route::get('/businesses/{business:slug}', PublicBusinessController::class);
    Route::post('/businesses/{business:slug}/events', BusinessEventController::class)->middleware('throttle:events');

    Route::prefix('auth')->group(function () {
        Route::post('/google', [AuthController::class, 'google'])->middleware('throttle:google-sign-in');
        // Membres de l'équipe d'un garage : identifiant et mot de passe créés par le garagiste.
        Route::post('/team-member', [AuthController::class, 'teamMember'])->middleware('throttle:team-member-login');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [ProfileController::class, 'show']);
        Route::patch('/me', [ProfileController::class, 'update']);
        Route::post('/me/device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('/me/device-tokens', [DeviceTokenController::class, 'destroy']);

        Route::middleware('personal.account')->group(function () {
            Route::get('/wallet', [WalletController::class, 'show']);
            Route::post('/wallet/top-ups', [WalletTopUpController::class, 'store'])
                ->middleware('throttle:wallet-top-up');
            Route::get('/wallet/top-ups/{walletTopUp}', [WalletTopUpController::class, 'show']);

            // Espace client : signal de détresse (C5), demandes de contact (C4), mes garages (C6).
            Route::get('/distress-signals', [DistressSignalController::class, 'index']);
            Route::post('/distress-signals', [DistressSignalController::class, 'store'])->middleware('throttle:client-requests');
            Route::get('/distress-signals/{distressSignal}', [DistressSignalController::class, 'show']);
            Route::post('/distress-signals/{distressSignal}/cancel', [DistressSignalController::class, 'cancel']);
            Route::get('/contact-requests', [ContactRequestController::class, 'index']);
            Route::post('/businesses/{business:slug}/contact-requests', [ContactRequestController::class, 'store'])
                ->middleware('throttle:client-requests');
            Route::post('/contact-requests/{contactRequest}/cancel', [ContactRequestController::class, 'cancel']);
            Route::get('/contacts', ContactController::class);

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

                // Offres achetées depuis le portefeuille (P4).
                Route::get('/subscriptions', [MyBusinessSubscriptionController::class, 'index']);
                Route::post('/subscriptions', [MyBusinessSubscriptionController::class, 'store']);
                Route::patch('/subscriptions/{subscription}', [MyBusinessSubscriptionController::class, 'update'])
                    ->whereNumber('subscription');

                // Équipe : employés et répondeurs, dans la limite des packs.
                Route::get('/team-members', [MyBusinessTeamMemberController::class, 'index']);
                Route::post('/team-members', [MyBusinessTeamMemberController::class, 'store']);
                Route::patch('/team-members/{member}', [MyBusinessTeamMemberController::class, 'update'])->whereNumber('member');
                Route::delete('/team-members/{member}', [MyBusinessTeamMemberController::class, 'destroy'])->whereNumber('member');
            });
        });

        // Demandes reçues (P5) et clients en contact (P6) : garagiste et membres actifs de son équipe.
        Route::middleware('works.at.business')->prefix('my-business')->group(function () {
            Route::get('/distress-signals', [ReceivedDistressSignalController::class, 'index']);
            Route::post('/distress-signals/{distressSignal}/accept', [ReceivedDistressSignalController::class, 'accept']);
            Route::post('/distress-signals/{distressSignal}/decline', [ReceivedDistressSignalController::class, 'decline']);
            Route::get('/contact-requests', [ReceivedContactRequestController::class, 'index']);
            Route::post('/contact-requests/{contactRequest}/accept', [ReceivedContactRequestController::class, 'accept']);
            Route::post('/contact-requests/{contactRequest}/decline', [ReceivedContactRequestController::class, 'decline']);
            Route::get('/clients', MyBusinessClientController::class);
        });
    });
});
