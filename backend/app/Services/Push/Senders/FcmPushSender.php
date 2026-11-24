<?php

namespace App\Services\Push\Senders;

use App\Models\DeviceToken;
use App\Services\Push\PushMessage;
use App\Services\Push\PushSender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Firebase Cloud Messaging, API HTTP v1. Le jeton d'accès OAuth est obtenu
 * avec le compte de service (JWT signé RS256), sans bibliothèque Google.
 */
class FcmPushSender implements PushSender
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * @param  array{project_id: string, client_email: string, private_key: string, token_uri: string}  $credentials
     */
    public function __construct(
        private readonly array $credentials,
        private readonly int $timeout = 10,
    ) {}

    public static function fromFile(string $path, int $timeout = 10): self
    {
        $credentials = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($credentials) || ! isset($credentials['project_id'], $credentials['client_email'], $credentials['private_key'])) {
            throw new RuntimeException('Compte de service Firebase introuvable ou invalide (FIREBASE_CREDENTIALS).');
        }

        $credentials['token_uri'] ??= 'https://oauth2.googleapis.com/token';

        return new self($credentials, $timeout);
    }

    public function send(array $tokens, PushMessage $message): void
    {
        if ($tokens === []) {
            return;
        }

        $accessToken = $this->accessToken();
        $url = "https://fcm.googleapis.com/v1/projects/{$this->credentials['project_id']}/messages:send";

        foreach ($tokens as $token) {
            $response = Http::withToken($accessToken)
                ->timeout($this->timeout)
                ->post($url, ['message' => [
                    'token' => $token,
                    'notification' => ['title' => $message->title, 'body' => $message->body],
                    'data' => $message->data,
                    'android' => [
                        'priority' => 'high',
                        'notification' => ['channel_id' => 'requests', 'sound' => 'default'],
                    ],
                ]]);

            if ($response->successful()) {
                continue;
            }

            $errorCode = $response->json('error.details.0.errorCode') ?? $response->json('error.status');

            // Application désinstallée ou jeton périmé : il ne servira plus.
            if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true)) {
                DeviceToken::query()->where('token', $token)->delete();

                continue;
            }

            Log::warning('Notification push non envoyée.', ['status' => $response->status(), 'error' => $errorCode]);
        }
    }

    private function accessToken(): string
    {
        return Cache::remember('fcm:access-token', now()->addMinutes(50), function (): string {
            $response = Http::asForm()
                ->timeout($this->timeout)
                ->post($this->credentials['token_uri'], [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $this->signedAssertion(),
                ]);

            $token = $response->json('access_token');

            if (! $response->successful() || ! is_string($token)) {
                throw new RuntimeException('Jeton d’accès Firebase refusé.');
            }

            return $token;
        });
    }

    private function signedAssertion(): string
    {
        $now = time();
        $header = $this->base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64Url((string) json_encode([
            'iss' => $this->credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => $this->credentials['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signature = '';

        if (! openssl_sign("{$header}.{$claims}", $signature, $this->credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Signature du jeton Firebase impossible.');
        }

        return "{$header}.{$claims}.{$this->base64Url($signature)}";
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
