<?php

namespace App\Services\Auth;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Vérifie le jeton d'identité (JWT RS256) obtenu par l'application avec
 * Google Sign-In (ADR-017) : signature contrôlée avec les certificats publics
 * de Google, sans bibliothèque Google, comme pour FCM.
 */
class GoogleIdTokenVerifier
{
    public const CERTIFICATES_URL = 'https://www.googleapis.com/oauth2/v1/certs';

    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    private const CACHE_KEY = 'google-sign-in:certificates';

    /** Décalage d'horloge toléré entre Google et le serveur, en secondes. */
    private const CLOCK_SKEW = 60;

    /** Délai minimal entre deux rechargements forcés des certificats, en secondes. */
    private const REFRESH_COOLDOWN = 60;

    /**
     * @param  list<string>  $clientIds  Identifiants client OAuth acceptés comme destinataire (« aud »).
     */
    public function __construct(
        private readonly array $clientIds,
        private readonly int $timeout = 10,
    ) {}

    /**
     * @throws GoogleSignInException
     */
    public function verify(string $idToken): GoogleIdentity
    {
        if ($this->clientIds === []) {
            throw GoogleSignInException::unavailable();
        }

        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            throw GoogleSignInException::invalidToken();
        }

        [$encodedHeader, $encodedClaims, $encodedSignature] = $parts;
        $header = $this->decodeJson($encodedHeader);
        $claims = $this->decodeJson($encodedClaims);
        $signature = $this->base64UrlDecode($encodedSignature);

        if ($header === null || $claims === null || $signature === null
            || ($header['alg'] ?? null) !== 'RS256' || ! is_string($header['kid'] ?? null)) {
            throw GoogleSignInException::invalidToken();
        }

        $certificate = $this->certificate($header['kid']);

        if ($certificate === null
            || openssl_verify("{$encodedHeader}.{$encodedClaims}", $signature, $certificate, OPENSSL_ALGO_SHA256) !== 1) {
            throw GoogleSignInException::invalidToken();
        }

        return $this->identity($claims);
    }

    /**
     * @param  array<string, mixed>  $claims
     *
     * @throws GoogleSignInException
     */
    private function identity(array $claims): GoogleIdentity
    {
        $now = now()->getTimestamp();

        $isValid = in_array($claims['iss'] ?? null, self::ISSUERS, true)
            && in_array($claims['aud'] ?? null, $this->clientIds, true)
            && is_int($claims['exp'] ?? null) && $now <= $claims['exp'] + self::CLOCK_SKEW
            && is_int($claims['iat'] ?? null) && $now >= $claims['iat'] - self::CLOCK_SKEW
            && is_string($claims['sub'] ?? null) && $claims['sub'] !== ''
            && is_string($claims['email'] ?? null) && $claims['email'] !== ''
            && in_array($claims['email_verified'] ?? null, [true, 'true'], true);

        if (! $isValid) {
            throw GoogleSignInException::invalidToken();
        }

        $name = is_string($claims['name'] ?? null) ? trim($claims['name']) : '';

        return new GoogleIdentity(
            subject: $claims['sub'],
            email: $claims['email'],
            name: $name === '' ? null : mb_substr($name, 0, 100),
        );
    }

    /**
     * Certificat PEM correspondant à la clé qui a signé le jeton. Une clé
     * inconnue provoque un rechargement : Google renouvelle ses clés.
     *
     * @throws GoogleSignInException
     */
    private function certificate(string $keyId): ?string
    {
        $cached = Cache::get(self::CACHE_KEY);
        $certificates = is_array($cached) ? $cached['certificates'] : null;

        $canRefresh = ! is_array($cached) || $cached['fetched_at'] <= now()->getTimestamp() - self::REFRESH_COOLDOWN;

        if (! isset($certificates[$keyId]) && $canRefresh) {
            $certificates = $this->fetchCertificates();
        }

        $certificate = $certificates[$keyId] ?? null;

        return is_string($certificate) ? $certificate : null;
    }

    /**
     * @return array<string, string>
     *
     * @throws GoogleSignInException
     */
    private function fetchCertificates(): array
    {
        try {
            $response = Http::acceptJson()->timeout($this->timeout)->get(self::CERTIFICATES_URL);
        } catch (ConnectionException $exception) {
            throw GoogleSignInException::unavailable($exception);
        }

        $certificates = $response->json();

        if (! $response->successful() || ! is_array($certificates) || $certificates === []) {
            throw GoogleSignInException::unavailable();
        }

        $maxAge = preg_match('/max-age=(\d+)/', $response->header('Cache-Control'), $match) === 1
            ? (int) $match[1]
            : 3600;

        Cache::put(
            self::CACHE_KEY,
            ['fetched_at' => now()->getTimestamp(), 'certificates' => $certificates],
            now()->addSeconds(max(self::REFRESH_COOLDOWN, min($maxAge, 86400))),
        );

        return $certificates;
    }

    /** @return array<string, mixed>|null */
    private function decodeJson(string $segment): ?array
    {
        $json = $this->base64UrlDecode($segment);
        $data = $json === null ? null : json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    private function base64UrlDecode(string $segment): ?string
    {
        $decoded = base64_decode(strtr($segment, '-_', '+/'), true);

        return $decoded === false || $decoded === '' ? null : $decoded;
    }
}
