<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertJsonValidationErrors(['name' => 'Le champ nom est obligatoire.']);
    }

    public function test_the_phone_number_is_stored_in_international_format_without_changing_the_name(): void
    {
        $user = User::factory()->withoutPhone()->create(['name' => 'Afi Mensah']);

        $this->actingAs($user)
            ->patchJson('/api/v1/me', ['phone' => '90 12 34 56'])
            ->assertOk()
            ->assertJsonPath('data.phone', '+22890123456')
            ->assertJsonPath('data.name', 'Afi Mensah');

        $this->assertSame('+22890123456', $user->fresh()->phone);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedPhoneNumbers(): array
    {
        return [
            'trop court' => ['90 12'],
            'numéro fixe' => ['22 21 00 00'],
            'numéro ghanéen' => ['+233 24 123 4567'],
        ];
    }

    #[DataProvider('rejectedPhoneNumbers')]
    public function test_a_phone_number_that_is_not_a_togolese_mobile_is_rejected(string $phone): void
    {
        $user = User::factory()->withoutPhone()->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/me', ['phone' => $phone])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => 'Saisissez un numéro de téléphone mobile valide.']);

        $this->assertNull($user->fresh()->phone);
    }

    public function test_the_phone_number_cannot_be_erased(): void
    {
        $user = User::factory()->create(['phone' => '+22890123456']);

        $this->actingAs($user)
            ->patchJson('/api/v1/me', ['phone' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => 'Le champ numéro de téléphone est obligatoire.']);
    }
}
