<?php

namespace Tests\Feature\Requests;

use App\Enums\ContactRequestStatus;
use App\Enums\ContactSource;
use App\Models\Business;
use App\Models\ContactRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\ContactRequestAnswered;
use App\Notifications\ContactRequestReceived;
use App\Services\Settings\PlatformSettings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ContactRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private Business $business;

    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->travelTo('2026-11-19 10:00:00');
        $this->client = User::factory()->create(['phone' => '+22891000000']);
        $this->business = Business::factory()->create(['phone' => '+22890111111', 'whatsapp_phone' => '+22890111111']);
        $this->wallet = Wallet::factory()->for($this->business->owner)->create(['balance_xof' => 2000]);
        app(PlatformSettings::class)->update([PlatformSettings::REQUEST_ACCEPTANCE_PRICE_XOF => 500]);
    }

    private function requestContact(?User $client = null): string
    {
        return $this->actingAs($client ?? $this->client)
            ->postJson("/api/v1/businesses/{$this->business->slug}/contact-requests", ['message' => 'Vidange demain ?'])
            ->assertCreated()
            ->json('data.id');
    }

    public function test_a_contact_request_requires_an_account(): void
    {
        $this->postJson("/api/v1/businesses/{$this->business->slug}/contact-requests")->assertUnauthorized();
    }

    public function test_a_client_without_a_phone_number_cannot_request_a_contact_with_422(): void
    {
        Notification::fake();
        $client = User::factory()->withoutPhone()->create();

        $this->actingAs($client)
            ->postJson("/api/v1/businesses/{$this->business->slug}/contact-requests", ['message' => 'Vidange demain ?'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'phone_required');

        $this->assertDatabaseCount('contact_requests', 0);
        Notification::assertNothingSent();
    }

    public function test_the_garage_is_alerted_and_a_second_request_returns_the_pending_one(): void
    {
        Notification::fake();

        $requestId = $this->requestContact();

        Notification::assertSentTo($this->business->owner, ContactRequestReceived::class);
        $this->actingAs($this->client)
            ->postJson("/api/v1/businesses/{$this->business->slug}/contact-requests")
            ->assertOk()
            ->assertJsonPath('data.id', $requestId)
            ->assertJsonPath('data.garage.phone', null);
        $this->assertDatabaseCount('contact_requests', 1);

        $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/v1/businesses/{$this->business->slug}")
            ->assertJsonPath('data.contact', null)
            ->assertJsonPath('data.pending_contact_request.id', $requestId);
    }

    public function test_accepting_debits_the_garage_once_and_saves_the_contact_for_both(): void
    {
        Notification::fake();
        $requestId = $this->requestContact();

        $this->actingAs($this->business->owner)
            ->postJson("/api/v1/my-business/contact-requests/{$requestId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.client.phone', '+22891000000');

        $this->assertSame(1500, $this->wallet->refresh()->balance_xof);
        $this->assertDatabaseHas('wallet_entries', ['type' => 'request_acceptance', 'amount_xof' => 500]);
        Notification::assertSentTo(
            $this->client,
            ContactRequestAnswered::class,
            fn (ContactRequestAnswered $notification) => $notification->contactRequest->status === ContactRequestStatus::Accepted,
        );

        $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/v1/businesses/{$this->business->slug}")
            ->assertJsonPath('data.contact.phone', '+22890111111');
        $this->actingAs($this->client)
            ->getJson('/api/v1/contacts')
            ->assertJsonPath('data.0.garage.phone', '+22890111111');
        $this->actingAs($this->business->owner)
            ->getJson('/api/v1/my-business/clients')
            ->assertJsonPath('data.0.client.phone', '+22891000000');

        $this->actingAs($this->client)
            ->postJson("/api/v1/businesses/{$this->business->slug}/contact-requests")
            ->assertConflict()
            ->assertJsonPath('code', 'already_in_contact');
    }

    public function test_no_debit_when_the_contact_was_already_exchanged_by_a_distress_signal(): void
    {
        $requestId = $this->requestContact();
        $this->business->contacts()->create(['client_user_id' => $this->client->id, 'source' => ContactSource::DistressSignal]);

        $this->actingAs($this->business->owner)
            ->postJson("/api/v1/my-business/contact-requests/{$requestId}/accept")
            ->assertOk();

        $this->assertSame(2000, $this->wallet->refresh()->balance_xof);
        $this->assertDatabaseCount('wallet_entries', 0);
    }

    public function test_accepting_without_enough_balance_keeps_the_request_pending(): void
    {
        $this->wallet->update(['balance_xof' => 100]);
        $requestId = $this->requestContact();

        $this->actingAs($this->business->owner)
            ->postJson("/api/v1/my-business/contact-requests/{$requestId}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'insufficient_balance');

        $this->assertSame(ContactRequestStatus::Pending, ContactRequest::sole()->status);
        $this->assertDatabaseCount('business_contacts', 0);
    }

    public function test_a_declined_request_does_not_reveal_any_number(): void
    {
        Notification::fake();
        $requestId = $this->requestContact();

        $this->actingAs($this->business->owner)
            ->postJson("/api/v1/my-business/contact-requests/{$requestId}/decline")
            ->assertOk()
            ->assertJsonPath('data.status', 'declined')
            ->assertJsonPath('data.client.phone', null);

        $this->assertSame(2000, $this->wallet->refresh()->balance_xof);
        Notification::assertSentTo($this->client, ContactRequestAnswered::class);
        $this->actingAs($this->business->owner)
            ->postJson("/api/v1/my-business/contact-requests/{$requestId}/accept")
            ->assertConflict();
    }

    public function test_another_garage_cannot_answer_the_request(): void
    {
        $requestId = $this->requestContact();
        $otherBusiness = Business::factory()->create();

        $this->actingAs($otherBusiness->owner)
            ->postJson("/api/v1/my-business/contact-requests/{$requestId}/accept")
            ->assertNotFound();
    }

    public function test_a_garage_cannot_request_its_own_contact_or_a_hidden_garage(): void
    {
        $this->actingAs($this->business->owner)
            ->postJson("/api/v1/businesses/{$this->business->slug}/contact-requests")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'own_business');

        $hidden = Business::factory()->withoutMembership()->create();
        $this->actingAs($this->client)
            ->postJson("/api/v1/businesses/{$hidden->slug}/contact-requests")
            ->assertNotFound();
    }

    public function test_unanswered_requests_expire_after_the_configured_delay(): void
    {
        Notification::fake();
        $this->requestContact();

        $this->travel(25)->hours();
        $this->artisan('requests:expire')->assertSuccessful();

        $this->assertSame(ContactRequestStatus::Expired, ContactRequest::sole()->status);
        Notification::assertSentTo($this->client, ContactRequestAnswered::class);
    }
}
