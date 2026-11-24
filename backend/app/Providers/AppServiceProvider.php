<?php

namespace App\Providers;

use App\Services\Auth\GoogleIdTokenVerifier;
use App\Services\Places\GooglePlaces;
use App\Services\Push\PushSender;
use App\Services\Push\Senders\ArrayPushSender;
use App\Services\Push\Senders\FcmPushSender;
use App\Services\Push\Senders\LogPushSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GooglePlaces::class, fn () => new GooglePlaces(config('services.google_maps.server_key')));

        $this->app->singleton(GoogleIdTokenVerifier::class, fn () => new GoogleIdTokenVerifier(
            config('services.google_sign_in.client_ids'),
            (int) config('services.google_sign_in.timeout'),
        ));

        $this->app->singleton(PushSender::class, function () {
            $driver = config('services.push.driver');

            return match ($driver) {
                'log' => new LogPushSender,
                'array' => new ArrayPushSender,
                'fcm' => FcmPushSender::fromFile(
                    (string) config('services.firebase.credentials'),
                    (int) config('services.firebase.timeout'),
                ),
                default => throw new InvalidArgumentException("Pilote push inconnu : {$driver}"),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Limites par adresse IP volontairement larges : les opérateurs mobiles
        // partagent une même IP entre de nombreux abonnés (CGNAT).
        RateLimiter::for('google-sign-in', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        // Mot de passe choisi par le garagiste : essais limités par identifiant.
        RateLimiter::for('team-member-login', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::lower($request->string('username')->toString()).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('events', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        // Chaque recherche de lieu peut déclencher un appel facturé à Google.
        RateLimiter::for('places', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('wallet-top-up', fn (Request $request) => Limit::perMinute(5)->by(
            (string) ($request->user()?->getKey() ?? $request->ip()),
        ));
        RateLimiter::for('client-requests', fn (Request $request) => Limit::perMinute(10)->by(
            (string) ($request->user()?->getKey() ?? $request->ip()),
        ));
        RateLimiter::for('admin-login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower($request->string('email')->toString()).'|'.$request->ip(),
        ));
    }
}
