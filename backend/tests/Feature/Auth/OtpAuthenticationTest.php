<?php

namespace Tests\Feature\Auth;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\Otp\OtpSender;
use App\Services\Otp\Senders\ArrayOtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+22890123456';

    private function sender(): ArrayOtpSender
    {
        return $this->app->make(OtpSender::class);
    }

    private function requestCode(string $phone = '90 12 34 56'): TestResponse
    {
        return $this->postJson('/api/v1/auth/otp', ['phone' => $phone]);
    }

    private function verifyCode(string $code, string $phone = '90123456'): TestResponse
    {
        return $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $phone,
            'code' => $code,
            'device_name' => 'Tecno Spark',
        ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function acceptedPhoneFormats(): array
    {
        return [
            'local avec espaces' => ['90 12 34 56'],
            'international' => ['+228 90 12 34 56'],
            'préfixe 00' => ['0022890123456'],
        ];
    }

    #[DataProvider('acceptedPhoneFormats')]
    public function test_a_code_is_sent_to_the_normalized_number(string $input): void
    {
        $this->requestCode($input)
            ->assertStatus(202)
            ->assertJson(['expires_in' => 300, 'resend_in' => 60])
            ->assertJsonMissingPath('otp');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->sender()->lastCodeFor(self::PHONE));
        $this->assertDatabaseMissing('otp_codes', ['code_hash' => $this->sender()->lastCodeFor(self::PHONE)]);
    }

    public function test_the_code_is_returned_only_when_show_otp_is_enabled(): void
    {
        config(['otp.show_otp' => true]);

        $response = $this->requestCode()->assertStatus(202);

        $response->assertJsonPath('otp', $this->sender()->lastCodeFor(self::PHONE));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedPhoneNumbers(): array
    {
        return [
            'trop court' => ['12345'],
            'numéro ghanéen' => ['+233 24 123 4567'],
            'texte' => ['bonjour'],
        ];
    }

    #[DataProvider('rejectedPhoneNumbers')]
    public function test_invalid_or_foreign_numbers_are_rejected(string $input): void
    {
        $this->requestCode($input)->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_a_new_code_cannot_be_requested_during_the_cooldown(): void
    {
        $this->requestCode()->assertStatus(202);

        $this->travel(30)->seconds();

        $this->requestCode()
            ->assertStatus(429)
            ->assertHeader('Retry-After', '30')
            ->assertJsonPath('retry_after', 30);

        $this->assertSame(1, $this->sender()->sentCount(self::PHONE));
    }

    public function test_codes_per_number_are_capped_each_day(): void
    {
        // Départ en matinée : la boucle ne doit pas franchir minuit.
        $this->travelTo(today()->setTime(8, 0));

        for ($i = 0; $i < 5; $i++) {
            $this->requestCode()->assertStatus(202);
            $this->travel(61)->seconds();
        }

        $this->requestCode()->assertStatus(429);

        $this->travelTo(today()->addDay()->setTime(8, 0));

        $this->requestCode()->assertStatus(202);
    }

    public function test_the_daily_global_cap_stops_all_sending(): void
    {
        config(['otp.max_per_day' => 2]);

        $this->requestCode('90 00 00 01')->assertStatus(202);
        $this->requestCode('90 00 00 02')->assertStatus(202);
        $this->requestCode('90 00 00 03')->assertStatus(429);
    }

    public function test_a_valid_code_creates_the_account_and_returns_a_token(): void
    {
        $this->requestCode();

        $response = $this->verifyCode($this->sender()->lastCodeFor(self::PHONE))
            ->assertOk()
            ->assertJsonPath('user.phone', self::PHONE)
            ->assertJsonPath('user.name', null);

        $user = User::firstWhere('phone', self::PHONE);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertSame('Tecno Spark', $user->tokens()->first()->name);

        $this->withToken($response->json('token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.phone', self::PHONE);
    }

    public function test_an_existing_user_logs_back_into_the_same_account(): void
    {
        $user = User::factory()->create(['phone' => self::PHONE, 'name' => 'Kossi']);

        $this->requestCode();
        $this->verifyCode($this->sender()->lastCodeFor(self::PHONE))
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', 'Kossi');

        $this->assertSame(1, User::count());
    }

    public function test_a_wrong_code_counts_as_an_attempt(): void
    {
        $this->requestCode();
        $code = $this->sender()->lastCodeFor(self::PHONE);
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->verifyCode($wrong)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'Il vous reste 4 essai(s)']);

        $this->verifyCode($code)->assertOk();
    }

    public function test_the_code_is_blocked_after_too_many_attempts(): void
    {
        $this->requestCode();
        $code = $this->sender()->lastCodeFor(self::PHONE);
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->verifyCode($wrong)->assertUnprocessable();
        }

        $this->verifyCode($code)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => "Trop d'essais"]);
        $this->assertSame(0, User::count());
    }

    public function test_an_expired_code_is_refused(): void
    {
        $this->requestCode();
        $code = $this->sender()->lastCodeFor(self::PHONE);

        $this->travel(301)->seconds();

        $this->verifyCode($code)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'expiré']);
    }

    public function test_a_code_can_only_be_used_once(): void
    {
        $this->requestCode();
        $code = $this->sender()->lastCodeFor(self::PHONE);

        $this->verifyCode($code)->assertOk();
        $this->verifyCode($code)->assertUnprocessable();
    }

    public function test_requesting_a_new_code_invalidates_the_previous_one(): void
    {
        $this->requestCode();
        $first = $this->sender()->lastCodeFor(self::PHONE);

        $this->travel(61)->seconds();
        $this->requestCode();
        $second = $this->sender()->lastCodeFor(self::PHONE);

        if ($first !== $second) {
            $this->verifyCode($first)->assertUnprocessable();
        }
        $this->verifyCode($second)->assertOk();
        $this->assertSame(1, OtpCode::whereNotNull('consumed_at')->count());
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_the_profile_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_the_user_can_set_their_name(): void
    {
        $user = User::factory()->create(['name' => null]);

        $this->actingAs($user)
            ->patchJson('/api/v1/me', ['name' => 'Afi Mensah'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Afi Mensah');

        $this->actingAs($user)
            ->patchJson('/api/v1/me', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'obligatoire']);
    }
}
