<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\GoogleIdTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = 'findsolux-web.apps.googleusercontent.com';

    private const SUBJECT = '109876543210987654321';

    /** 15 janvier 2027 : les dates des jetons sont fixes. */
    private const NOW = 1_800_000_000;

    /** @var array<string, array{key: OpenSSLAsymmetricKey, certificate: string}> */
    private static array $keys = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google_sign_in.client_ids' => [self::CLIENT_ID]]);
        $this->travelTo(Carbon::createFromTimestamp(self::NOW));
        Http::preventStrayRequests();
    }

    /**
     * Clé RSA et certificat auto-signé, à la manière de ceux publiés par Google.
     *
     * @return array{key: OpenSSLAsymmetricKey, certificate: string}
     */
    private static function signingKey(string $keyId): array
    {
        if (! isset(self::$keys[$keyId])) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $csr = openssl_csr_new(['commonName' => $keyId], $key);
            openssl_x509_export(openssl_csr_sign($csr, null, $key, 1), $certificate);

            self::$keys[$keyId] = ['key' => $key, 'certificate' => $certificate];
        }

        return self::$keys[$keyId];
    }

    /**
     * @param  list<string>  $keyIds
     * @return array<string, string>
     */
    private function certificates(array $keyIds = ['key-1']): array
    {
        return collect($keyIds)->mapWithKeys(fn (string $keyId) => [$keyId => self::signingKey($keyId)['certificate']])->all();
    }

    private function fakeGoogleCertificates(): void
    {
        Http::fake([
            GoogleIdTokenVerifier::CERTIFICATES_URL => Http::response($this->certificates(), 200, [
                'Cache-Control' => 'public, max-age=21600, must-revalidate, no-transform',
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $claims  Revendications remplaçant celles par défaut.
     */
    private function idToken(array $claims = [], string $keyId = 'key-1', ?string $signedWith = null): string
    {
        $encode = fn (array $data) => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');

        $header = $encode(['alg' => 'RS256', 'kid' => $keyId, 'typ' => 'JWT']);
        $payload = $encode([
            'iss' => 'https://accounts.google.com',
            'azp' => 'findsolux-android.apps.googleusercontent.com',
            'aud' => self::CLIENT_ID,
            'sub' => self::SUBJECT,
            'email' => 'kossi.mensah@gmail.com',
            'email_verified' => true,
            'name' => 'Kossi Mensah',
            'iat' => self::NOW - 10,
            'exp' => self::NOW + 3590,
            ...$claims,
        ]);

        openssl_sign("{$header}.{$payload}", $signature, self::signingKey($signedWith ?? $keyId)['key'], OPENSSL_ALGO_SHA256);

        return "{$header}.{$payload}.".rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    private function signIn(string $idToken): TestResponse
    {
        return $this->postJson('/api/v1/auth/google', [
            'id_token' => $idToken,
            'device_name' => 'Tecno Spark',
        ]);
    }

    public function test_a_first_google_sign_in_creates_the_account_and_returns_a_token(): void
    {
        $this->fakeGoogleCertificates();

        $response = $this->signIn($this->idToken())
            ->assertOk()
            ->assertJsonPath('user.name', 'Kossi Mensah')
            ->assertJsonPath('user.email', 'kossi.mensah@gmail.com')
            ->assertJsonPath('user.phone', null);

        $user = User::sole();
        $this->assertSame(self::SUBJECT, $user->google_id);
        $this->assertSame('Tecno Spark', $user->tokens()->sole()->name);

        $this->withToken($response->json('token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_signing_in_again_opens_the_same_account_without_overwriting_its_name(): void
    {
        $this->fakeGoogleCertificates();
        $user = User::factory()->create([
            'google_id' => self::SUBJECT,
            'email' => 'ancienne.adresse@gmail.com',
            'name' => 'Kossi',
            'phone' => '+22890123456',
        ]);

        $this->signIn($this->idToken())
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', 'Kossi')
            ->assertJsonPath('user.phone', '+22890123456')
            ->assertJsonPath('user.email', 'kossi.mensah@gmail.com');

        $this->assertSame(1, User::count());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function rejectedClaims(): array
    {
        return [
            'autre application' => [['aud' => 'autre-app.apps.googleusercontent.com']],
            'autre émetteur' => [['iss' => 'https://accounts.example.com']],
            'jeton expiré' => [['exp' => self::NOW - 61]],
            'émis dans le futur' => [['iat' => self::NOW + 61]],
            'adresse non vérifiée' => [['email_verified' => false]],
            'sans identifiant' => [['sub' => '']],
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    #[DataProvider('rejectedClaims')]
    public function test_a_token_with_invalid_claims_is_refused_with_401(array $claims): void
    {
        $this->fakeGoogleCertificates();

        $this->signIn($this->idToken($claims))
            ->assertUnauthorized()
            ->assertJsonPath('message', 'La connexion Google a échoué. Réessayez.');

        $this->assertSame(0, User::count());
    }

    public function test_a_token_not_signed_by_google_is_refused_with_401(): void
    {
        $this->fakeGoogleCertificates();

        $this->signIn($this->idToken(signedWith: 'cle-pirate'))
            ->assertUnauthorized()
            ->assertJsonPath('message', 'La connexion Google a échoué. Réessayez.');

        $this->assertSame(0, User::count());
    }

    public function test_a_malformed_token_is_refused_with_401_without_calling_google(): void
    {
        $this->fakeGoogleCertificates();

        $this->signIn('pas-un-jeton')->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_google_certificates_are_downloaded_once_for_several_sign_ins(): void
    {
        $this->fakeGoogleCertificates();

        $this->signIn($this->idToken())->assertOk();
        $this->signIn($this->idToken())->assertOk();

        Http::assertSentCount(1);
    }

    public function test_a_token_signed_with_a_renewed_google_key_reloads_the_certificates(): void
    {
        Http::fake([
            GoogleIdTokenVerifier::CERTIFICATES_URL => Http::sequence()
                ->push($this->certificates(['key-1']))
                ->push($this->certificates(['key-1', 'key-2'])),
        ]);
        $this->signIn($this->idToken())->assertOk();

        $this->travel(2)->minutes();

        $this->signIn($this->idToken(keyId: 'key-2'))->assertOk();
        Http::assertSentCount(2);
    }

    public function test_sign_in_answers_503_when_google_certificates_are_unreachable(): void
    {
        Http::fake([GoogleIdTokenVerifier::CERTIFICATES_URL => Http::response('Erreur', 500)]);

        $this->signIn($this->idToken())
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'La connexion Google est momentanément indisponible. Réessayez dans quelques instants.');
    }

    public function test_sign_in_answers_503_when_the_server_has_no_google_client_id(): void
    {
        config(['services.google_sign_in.client_ids' => []]);
        $this->fakeGoogleCertificates();

        $this->signIn($this->idToken())->assertServiceUnavailable();

        Http::assertNothingSent();
    }

    public function test_the_google_token_is_required(): void
    {
        $this->postJson('/api/v1/auth/google', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_token' => 'Le champ jeton Google est obligatoire.']);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }
}
