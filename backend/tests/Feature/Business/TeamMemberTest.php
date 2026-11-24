<?php

namespace Tests\Feature\Business;

use App\Models\Business;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Équipe du garage (employés ou répondeurs) gérée par le garagiste.
 */
class TeamMemberTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->business = Business::factory()->withTeamQuota(2)->create();
    }

    public function test_the_owner_creates_a_member_with_an_identifier_and_a_password(): void
    {
        $this->actingAs($this->business->owner)
            ->postJson('/api/v1/my-business/team-members', [
                'name' => 'Kodjo',
                'username' => ' Kodjo.Paix ',
                'password' => 'atelier2026',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Kodjo')
            ->assertJsonPath('data.username', 'kodjo.paix')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonMissingPath('data.password');

        $member = User::query()->where('username', 'kodjo.paix')->sole();
        $this->assertSame($this->business->id, $member->team_business_id);
        $this->assertTrue(Hash::check('atelier2026', $member->password));

        $this->actingAs($this->business->owner)
            ->getJson('/api/v1/my-business/team-members')
            ->assertOk()
            ->assertJsonPath('data.0.username', 'kodjo.paix')
            ->assertJsonPath('meta', ['quota' => 2, 'count' => 1]);
    }

    public function test_no_member_can_be_added_beyond_the_packs_with_422(): void
    {
        User::factory()->count(2)->teamMember($this->business)->create();

        $this->actingAs($this->business->owner)
            ->postJson('/api/v1/my-business/team-members', ['name' => 'Afi', 'username' => 'afi', 'password' => 'atelier2026'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'team_quota_reached')
            ->assertJsonPath('message', 'Votre équipe est complète (2 membre(s)). Achetez un pack supplémentaire pour en ajouter.');

        $this->assertSame(2, $this->business->teamMembers()->count());
    }

    public function test_without_a_staff_pack_the_owner_is_invited_to_buy_one(): void
    {
        $business = Business::factory()->create();

        $this->actingAs($business->owner)
            ->postJson('/api/v1/my-business/team-members', ['name' => 'Afi', 'username' => 'afi', 'password' => 'atelier2026'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Achetez un pack « Employés et répondeurs » pour ajouter des membres à votre équipe.');
    }

    public function test_an_identifier_already_taken_is_refused(): void
    {
        User::factory()->teamMember(Business::factory()->withTeamQuota(1)->create())->create(['username' => 'kodjo']);

        $this->actingAs($this->business->owner)
            ->postJson('/api/v1/my-business/team-members', ['name' => 'Kodjo', 'username' => 'Kodjo', 'password' => 'atelier2026'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'Cet identifiant est déjà pris']);
    }

    public function test_an_identifier_with_spaces_or_accents_is_refused(): void
    {
        $this->actingAs($this->business->owner)
            ->postJson('/api/v1/my-business/team-members', ['name' => 'Kodjo', 'username' => 'kodjo agbé', 'password' => 'atelier2026'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'L’identifiant ne peut contenir que des lettres sans accent, des chiffres, des points et des tirets.']);
    }

    public function test_a_password_of_less_than_8_characters_is_refused(): void
    {
        $this->actingAs($this->business->owner)
            ->postJson('/api/v1/my-business/team-members', ['name' => 'Kodjo', 'username' => 'kodjo', 'password' => '1234'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_a_new_password_logs_the_member_out_of_every_phone(): void
    {
        $member = User::factory()->teamMember($this->business)->create();
        $member->createToken('mobile');

        $this->actingAs($this->business->owner)
            ->patchJson("/api/v1/my-business/team-members/{$member->id}", ['password' => 'nouveau2026'])
            ->assertOk();

        $this->assertTrue(Hash::check('nouveau2026', $member->refresh()->password));
        $this->assertSame(0, $member->tokens()->count());
    }

    public function test_the_owner_removes_a_member(): void
    {
        $member = User::factory()->teamMember($this->business)->create();
        $member->createToken('mobile');

        $this->actingAs($this->business->owner)
            ->deleteJson("/api/v1/my-business/team-members/{$member->id}")
            ->assertNoContent();

        $this->assertModelMissing($member);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_the_member_of_another_garage_cannot_be_changed_with_404(): void
    {
        $otherMember = User::factory()->teamMember(Business::factory()->withTeamQuota(1)->create())->create();

        $this->actingAs($this->business->owner)
            ->deleteJson("/api/v1/my-business/team-members/{$otherMember->id}")
            ->assertNotFound();

        $this->assertModelExists($otherMember);
    }

    public function test_members_beyond_an_expired_pack_are_listed_as_suspended(): void
    {
        [$first, $second] = User::factory()->count(2)->teamMember($this->business)->create();
        $this->business->forceFill(['team_quota' => 1])->save();

        $this->actingAs($this->business->owner)
            ->getJson('/api/v1/my-business/team-members')
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.1.is_active', false);
    }

    public function test_a_team_member_cannot_manage_the_team_or_the_garage_with_403(): void
    {
        $member = User::factory()->teamMember($this->business)->create();

        $this->actingAs($member)
            ->postJson('/api/v1/my-business/team-members', ['name' => 'Afi', 'username' => 'afi', 'password' => 'atelier2026'])
            ->assertForbidden()
            ->assertJsonPath('code', 'team_member_account');

        $this->actingAs($member)
            ->patchJson('/api/v1/my-business', ['name' => 'Autre nom'])
            ->assertForbidden();

        $this->actingAs($member)->getJson('/api/v1/wallet')->assertForbidden();
    }
}
