<?php

namespace Tests\Feature\Auth;

use App\Models\Business;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TeamMemberAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->business = Business::factory()->withTeamQuota(1)->create(['name' => 'Garage de la Paix']);
    }

    private function logIn(string $username, string $password): TestResponse
    {
        return $this->postJson('/api/v1/auth/team-member', [
            'username' => $username,
            'password' => $password,
            'device_name' => 'Itel A70',
        ]);
    }

    public function test_a_member_logs_in_with_the_identifier_created_by_the_owner(): void
    {
        $member = User::factory()->teamMember($this->business)->create(['name' => 'Kodjo', 'username' => 'kodjo']);

        $response = $this->logIn('Kodjo', 'motdepasse')
            ->assertOk()
            ->assertJsonPath('user.name', 'Kodjo')
            ->assertJsonPath('user.team_member', [
                'username' => 'kodjo',
                'business' => ['id' => $this->business->id, 'name' => 'Garage de la Paix'],
            ]);

        $this->assertSame('Itel A70', $member->tokens()->sole()->name);
        $this->withToken($response->json('token'))
            ->getJson('/api/v1/my-business/contact-requests')
            ->assertOk();
    }

    public function test_a_wrong_password_is_refused_without_saying_which_field_is_wrong(): void
    {
        User::factory()->teamMember($this->business)->create(['username' => 'kodjo']);

        $this->logIn('kodjo', 'mauvais-mot')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'Identifiant ou mot de passe incorrect.']);
        $this->logIn('inconnu', 'motdepasse')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'Identifiant ou mot de passe incorrect.']);
    }

    public function test_a_member_beyond_the_packs_cannot_log_in_with_403(): void
    {
        User::factory()->teamMember($this->business)->create(['username' => 'kodjo']);
        User::factory()->teamMember($this->business)->create(['username' => 'afi']);

        $this->logIn('afi', 'motdepasse')
            ->assertForbidden()
            ->assertJsonPath('code', 'team_member_suspended');
    }

    public function test_a_suspended_member_loses_access_to_the_requests_with_403(): void
    {
        $member = User::factory()->teamMember($this->business)->create();
        $token = $member->createToken('mobile')->plainTextToken;
        $this->business->forceFill(['team_quota' => 0])->save();

        $this->withToken($token)
            ->getJson('/api/v1/my-business/distress-signals')
            ->assertForbidden()
            ->assertJsonPath('message', 'Votre accès est suspendu : le pack Équipe du garage a expiré. Prévenez le responsable du garage.');
    }

    public function test_login_attempts_are_limited_per_identifier(): void
    {
        User::factory()->teamMember($this->business)->create(['username' => 'kodjo']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->logIn('kodjo', 'mauvais-mot')->assertUnprocessable();
        }

        $this->logIn('kodjo', 'motdepasse')->assertStatus(429);
    }
}
