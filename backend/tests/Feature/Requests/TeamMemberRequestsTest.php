<?php

namespace Tests\Feature\Requests;

use App\Models\Business;
use App\Models\ContactRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\ContactRequestReceived;
use App\Notifications\DistressSignalReceived;
use App\Services\Settings\PlatformSettings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Demandes reçues par un garage et traitées par un membre de son équipe.
 */
class TeamMemberRequestsTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Wallet $wallet;

    private User $member;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->travelTo('2026-11-24 10:00:00');
        app(PlatformSettings::class)->update([PlatformSettings::REQUEST_ACCEPTANCE_PRICE_XOF => 500]);
        $this->business = Business::factory()->travelling(5)->withTeamQuota(1)->create([
            'latitude' => 6.1319,
            'longitude' => 1.2228,
        ]);
        $this->wallet = Wallet::factory()->for($this->business->owner)->create(['balance_xof' => 2000]);
        $this->member = User::factory()->teamMember($this->business)->create(['name' => 'Kodjo']);
        $this->client = User::factory()->create(['name' => 'Afi', 'phone' => '+22891000000']);
    }

    private function sendContactRequest(): ContactRequest
    {
        $this->actingAs($this->client)
            ->postJson("/api/v1/businesses/{$this->business->slug}/contact-requests", ['message' => 'Vidange demain ?'])
            ->assertCreated();

        return ContactRequest::query()->sole();
    }

    public function test_a_new_contact_request_alerts_the_owner_and_active_members_only(): void
    {
        $suspended = User::factory()->teamMember($this->business)->create();
        Notification::fake();

        $this->sendContactRequest();

        Notification::assertSentTo([$this->business->owner, $this->member], ContactRequestReceived::class);
        Notification::assertNotSentTo($suspended, ContactRequestReceived::class);
    }

    public function test_a_distress_signal_alerts_the_whole_active_team(): void
    {
        Notification::fake();

        $this->actingAs($this->client)->postJson('/api/v1/distress-signals', [
            'message' => 'Crevaison du pneu avant',
            'latitude' => 6.1319,
            'longitude' => 1.2228,
        ])->assertCreated();

        Notification::assertSentTo([$this->business->owner, $this->member], DistressSignalReceived::class);
    }

    public function test_a_member_accepts_a_contact_request_and_the_garage_wallet_pays(): void
    {
        $contactRequest = $this->sendContactRequest();

        $this->actingAs($this->member)
            ->postJson("/api/v1/my-business/contact-requests/{$contactRequest->public_id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.client.phone', '+22891000000')
            ->assertJsonPath('data.accepted_by', ['id' => $this->member->id, 'name' => 'Kodjo']);

        $this->assertSame(1500, $this->wallet->refresh()->balance_xof);
        $this->assertDatabaseHas('wallet_entries', ['amount_xof' => 500, 'description' => 'Demande de contact acceptée par Kodjo']);

        $this->actingAs($this->business->owner)
            ->getJson('/api/v1/my-business/contact-requests')
            ->assertOk()
            ->assertJsonPath('data.0.accepted_by.name', 'Kodjo')
            ->assertJsonPath('meta.balance.amount', 1500);
    }

    public function test_a_member_accepts_a_distress_signal_for_the_garage(): void
    {
        $signalId = $this->actingAs($this->client)->postJson('/api/v1/distress-signals', [
            'message' => 'Crevaison du pneu avant',
            'latitude' => 6.1319,
            'longitude' => 1.2228,
        ])->json('data.id');

        $this->actingAs($this->member)
            ->postJson("/api/v1/my-business/distress-signals/{$signalId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.accepted_by.name', 'Kodjo');

        $this->assertSame(1500, $this->wallet->refresh()->balance_xof);
        $this->assertDatabaseHas('distress_signals', ['public_id' => $signalId, 'accepted_by_user_id' => $this->member->id]);
    }

    public function test_a_member_cannot_use_the_client_space_with_403(): void
    {
        $this->actingAs($this->member)
            ->postJson('/api/v1/distress-signals', ['message' => 'Panne', 'latitude' => 6.1319, 'longitude' => 1.2228])
            ->assertForbidden()
            ->assertJsonPath('code', 'team_member_account');
    }
}
