<?php

namespace App\Services\Otp;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(private readonly OtpSender $sender) {}

    /**
     * Envoie un nouveau code au numéro (E.164). Les codes encore valides pour
     * ce numéro sont invalidés : seul le dernier code reçu fonctionne.
     *
     * @throws OtpThrottledException
     */
    public function send(string $phone, ?string $ipAddress = null): void
    {
        $this->ensureCanSend($phone);

        OtpCode::where('phone', $phone)->pending()->update(['expires_at' => now()]);

        $code = $this->generateCode();

        OtpCode::create([
            'phone' => $phone,
            'code_hash' => $this->hash($phone, $code),
            'channel' => $this->sender->channel(),
            'expires_at' => now()->addSeconds(config('otp.ttl')),
            'ip_address' => $ipAddress,
        ]);

        $this->sender->send($phone, $code);
    }

    /**
     * Consomme le code s'il est valide.
     *
     * @throws ValidationException
     */
    public function verify(string $phone, string $code): void
    {
        $otp = OtpCode::where('phone', $phone)->pending()->latest('id')->first();
        $maxAttempts = config('otp.max_attempts');

        if (! $otp) {
            $this->fail('Ce code a expiré. Demandez un nouveau code.');
        }

        if ($otp->attempts >= $maxAttempts) {
            $this->fail("Trop d'essais avec ce code. Demandez un nouveau code.");
        }

        if (! hash_equals($otp->code_hash, $this->hash($phone, $code))) {
            $otp->increment('attempts');
            $remaining = $maxAttempts - $otp->attempts;

            $this->fail($remaining > 0
                ? "Code incorrect. Il vous reste {$remaining} essai(s)."
                : 'Code incorrect. Demandez un nouveau code.');
        }

        // Consommation atomique : un code ne sert qu'une fois, même si deux
        // requêtes arrivent en même temps.
        $consumed = OtpCode::whereKey($otp->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        if ($consumed !== 1) {
            $this->fail('Ce code a déjà été utilisé. Demandez un nouveau code.');
        }
    }

    /**
     * @throws OtpThrottledException
     */
    private function ensureCanSend(string $phone): void
    {
        $secondsUntilTomorrow = (int) ceil(now()->diffInSeconds(now()->endOfDay())) + 1;

        if (OtpCode::where('created_at', '>=', today())->count() >= config('otp.max_per_day')) {
            Log::critical("Plafond journalier d'envoi de codes de connexion atteint.");

            throw new OtpThrottledException(
                "Le service d'envoi de codes est momentanément saturé. Réessayez plus tard.",
                $secondsUntilTomorrow,
            );
        }

        $sentToday = OtpCode::where('phone', $phone)->where('created_at', '>=', today())->count();

        if ($sentToday >= config('otp.max_per_phone_per_day')) {
            throw new OtpThrottledException(
                "Trop de codes demandés pour ce numéro aujourd'hui. Réessayez demain.",
                $secondsUntilTomorrow,
            );
        }

        $lastSentAt = OtpCode::where('phone', $phone)->latest('id')->first()?->created_at;
        $wait = $lastSentAt
            ? (int) ceil(config('otp.resend_cooldown') - $lastSentAt->diffInSeconds(now()))
            : 0;

        if ($wait > 0) {
            throw new OtpThrottledException(
                "Patientez {$wait} seconde(s) avant de demander un nouveau code.",
                $wait,
            );
        }
    }

    private function generateCode(): string
    {
        $length = config('otp.length');

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }

    private function hash(string $phone, string $code): string
    {
        return hash_hmac('sha256', "{$phone}|{$code}", config('app.key'));
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['code' => $message]);
    }
}
