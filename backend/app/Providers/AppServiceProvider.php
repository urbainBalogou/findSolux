<?php

namespace App\Providers;

use App\Services\Otp\OtpSender;
use App\Services\Otp\Senders\ArrayOtpSender;
use App\Services\Otp\Senders\LogOtpSender;
use App\Services\Places\GooglePlaces;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GooglePlaces::class, fn () => new GooglePlaces(config('services.google_maps.server_key')));

        $this->app->singleton(OtpSender::class, function ($app) {
            $driver = config('otp.driver');

            if ($driver === 'log' && $app->isProduction()) {
                throw new RuntimeException('Le pilote OTP « log » est interdit en production.');
            }

            return match ($driver) {
                'log' => new LogOtpSender,
                'array' => new ArrayOtpSender,
                default => throw new InvalidArgumentException("Pilote OTP inconnu : {$driver}"),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Limites par adresse IP volontairement larges : les opérateurs mobiles
        // partagent une même IP entre de nombreux abonnés (CGNAT). Les vraies
        // protections sont par numéro, dans OtpService.
        RateLimiter::for('otp-request', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('events', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        // Chaque recherche de lieu peut déclencher un appel facturé à Google.
        RateLimiter::for('places', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
