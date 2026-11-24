<?php

namespace Tests\Feature\Requests;

use App\Enums\DistressSignalStatus;
use App\Models\Business;
use App\Models\DistressSignal;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\DistressSignalAccepted;
use App\Notifications\DistressSignalReceived;
use App\Notifications\DistressSignalUnanswered;
use App\Services\Settings\PlatformSettings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DistressSignalTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 6.1319;

    private const LNG = 1.2228;

    /** Un kilomètre en degrés de latitude. */
    private const KM = 1 / 111.32;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->travelTo('2026-11-19 10:00:00');
        $this->client = User::factory()->create(['name' => 'Kossi', 'phone' => '+22891000000']);
        app(PlatformSettings::class)->update([PlatformSettings::REQUEST_ACCEPTANCE_PRICE_XOF => 500]);
    }

    private function garageAt(float $km, int $radiusKm, array $attributes = [], int $balance = 2000): Business
    {
        $business = Business::factory()->travelling($radiusKm)->create([
            'latitude' => self::LAT + $km * self::KM,
            'longitude' => self::LNG,
            ...$attributes,
        ]);
        Wallet::factory()->for($business->owner)->create(['balance_xof' => $balance]);

        return $business;
    }

    private function sendSignal(array $data = []): TestResponse
    {
        return $this->actingAs($this->client)->postJson('/api/v1/distress-signals', [
            'message' => 'Crevaison du pneu avant',
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            ...$data,
        ]);
    }

    public function test_sending_a_signal_requires_an_account(): void
    {
        $this->postJson('/api/v1/distress-signals')->assertUnauthorized();
    }

    public function test_a_client_without_a_phone_number_cannot_send_a_signal_with_422(): void
    {
        Notification::fake();
        $this->garageAt(1, 5);
        $this->client->forceFill(['phone' => null])->save();

        $this->sendSignal()
            ->assertUnprocessable()
            ->assertJsonPath('code', 'phone_required');

        $this->assertDatabaseCount('distress_signals', 0);
        Notification::assertNothingSent();
    }

    public function test_the_signal_reaches_every_visible_garage_whose_area_covers_the_client(): void
    {
        Notification::fake();
        $near = $this->garageAt(2, 5);
        $wideArea = $this->garageAt(12, 15);
        $tooFar = $this->garageAt(8, 5);
        $notTravelling = $this->garageAt(1, 5, ['is_mobile' => false]);
        $noMembership = $this->garageAt(1, 5, ['membership_ends_at' => null]);

        $response = $this->sendSignal()
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.recipients_count', 2)
            ->assertJsonPath('data.expires_at', '2026-11-19T10:10:00+00:00')
            ->assertJsonPath('data.garage', null);

        $signal = DistressSignal::where('public_id', $response->json('data.id'))->sole();
        $this->assertEqualsCanonicalizing(
            [$near->id, $wideArea->id],
            $signal->recipients()->pluck('business_id')->all(),
        );
        Notification::assertSentTo($near->owner, DistressSignalReceived::class);
        Notification::assertSentTo($wideArea->owner, DistressSignalReceived::class);
        Notification::assertNotSentTo([$tooFar->owner, $notTravelling->owner, $noMembership->owner], DistressSignalReceived::class);
    }

    public function test_the_wait_time_comes_from_the_platform_settings(): void
    {
        $this->garageAt(1, 5);
        app(PlatformSettings::class)->update([PlatformSettings::DISTRESS_SIGNAL_WAIT_MINUTES => 3]);

        $this->sendSignal()->assertCreated()->assertJsonPath('data.expires_at', '2026-11-19T10:03:00+00:00');
    }

    public function test_a_signal_is_refused_when_no_garage_covers_the_position(): void
    {
        $this->garageAt(20, 5);

        $this->sendSignal()
            ->assertUnprocessable()
            ->assertJsonPath('code', 'no_recipient');
        $this->assertDatabaseCount('distress_signals', 0);
    }

    public function test_a_client_has_only_one_open_signal_at_a_time(): void
    {
        $this->garageAt(1, 5);
        $this->sendSignal()->assertCreated();

        $this->sendSignal()->assertConflict()->assertJsonPath('code', 'already_open');

        $this->travel(11)->minutes();
        $this->sendSignal()->assertCreated();
    }

    public function test_the_message_and_position_are_validated(): void
    {
        $this->sendSignal(['message' => '', 'latitude' => 48.85])
            ->assertJsonValidationErrors([
                'message' => 'Décrivez votre problème en quelques mots.',
                'latitude' => 'Votre position doit se trouver au Togo.',
            ]);
    }

    public function test_the_first_garage_to_accept_is_debited_and_contacts_are_exchanged(): void
    {
        Notification::fake();
        $first = $this->garageAt(2, 5, ['name' => 'Garage Rapide', 'phone' => '+22890111111']);
        $second = $this->garageAt(3, 5);
        $signalId = $this->sendSignal()->json('data.id');

        $this->actingAs($first->owner)
            ->postJson("/api/v1/my-business/distress-signals/{$signalId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.client.phone', '+22891000000')
            ->assertJsonPath('data.client.latitude', self::LAT);

        $this->actingAs($second->owner)
            ->postJson("/api/v1/my-business/distress-signals/{$signalId}/accept")
            ->assertConflict()
            ->assertJsonPath('code', 'not_available');

        $this->assertSame(1500, $first->owner->wallet->balance_xof);
        $this->assertSame(2000, $second->owner->wallet->balance_xof);
        $this->assertDatabaseHas('business_contacts', [
            'business_id' => $first->id,
            'client_user_id' => $this->client->id,
            'source' => 'distress_signal',
        ]);
        Notification::assertSentTo($this->client, DistressSignalAccepted::class);

        $this->actingAs($this->client)
            ->getJson("/api/v1/distress-signals/{$signalId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.garage.name', 'Garage Rapide')
            ->assertJsonPath('data.garage.phone', '+22890111111');

        $this->actingAs($second->owner)
            ->getJson('/api/v1/my-business/distress-signals')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'taken')
            ->assertJsonPath('data.0.client', null);
    }

    public function test_an_open_signal_hides_the_client_details_from_garages(): void
    {
        $garage = $this->garageAt(2, 5);
        $this->sendSignal();

        $this->actingAs($garage->owner)
            ->getJson('/api/v1/my-business/distress-signals')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'open')
            ->assertJsonPath('data.0.message', 'Crevaison du pneu avant')
            ->assertJsonPath('data.0.client', null)
            ->assertJsonPath('meta.acceptance_price.amount', 500)
            ->assertJsonPath('meta.balance.amount', 2000);
    }

    public function test_accepting_without_enough_balance_leaves_the_signal_open(): void
    {
        $garage = $this->garageAt(2, 5, balance: 100);
        $signalId = $this->sendSignal()->json('data.id');

        $this->actingAs($garage->owner)
            ->postJson("/api/v1/my-business/distress-signals/{$signalId}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'insufficient_balance');

        $this->assertSame(DistressSignalStatus::Open, DistressSignal::sole()->status);
        $this->assertDatabaseCount('business_contacts', 0);
    }

    public function test_a_garage_whose_membership_ended_cannot_accept(): void
    {
        $garage = $this->garageAt(2, 5);
        $signalId = $this->sendSignal()->json('data.id');
        $garage->forceFill(['membership_ends_at' => now()->subMinute()])->save();

        $this->actingAs($garage->owner)
            ->postJson("/api/v1/my-business/distress-signals/{$signalId}/accept")
            ->assertForbidden()
            ->assertJsonPath('code', 'membership_required');
        $this->assertSame(2000, $garage->owner->wallet->balance_xof);
    }

    public function test_a_garage_that_was_not_alerted_cannot_see_or_accept_the_signal(): void
    {
        $this->garageAt(2, 5);
        $outsider = $this->garageAt(30, 5);
        $signalId = $this->sendSignal()->json('data.id');

        $this->actingAs($outsider->owner)
            ->postJson("/api/v1/my-business/distress-signals/{$signalId}/accept")
            ->assertNotFound();
        $this->actingAs($outsider->owner)
            ->getJson('/api/v1/my-business/distress-signals')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_another_client_cannot_read_or_cancel_the_signal(): void
    {
        $this->garageAt(2, 5);
        $signalId = $this->sendSignal()->json('data.id');
        $otherClient = User::factory()->create();

        $this->actingAs($otherClient)->getJson("/api/v1/distress-signals/{$signalId}")->assertNotFound();
        $this->actingAs($otherClient)->postJson("/api/v1/distress-signals/{$signalId}/cancel")->assertNotFound();
        $this->assertSame(DistressSignalStatus::Open, DistressSignal::sole()->status);
    }

    public function test_the_client_is_told_at_once_when_every_garage_declines(): void
    {
        Notification::fake();
        $first = $this->garageAt(2, 5);
        $second = $this->garageAt(3, 5);
        $signalId = $this->sendSignal()->json('data.id');

        $this->actingAs($first->owner)->postJson("/api/v1/my-business/distress-signals/{$signalId}/decline")
            ->assertOk()
            ->assertJsonPath('data.status', 'declined');
        Notification::assertNotSentTo($this->client, DistressSignalUnanswered::class);

        $this->actingAs($second->owner)->postJson("/api/v1/my-business/distress-signals/{$signalId}/decline")->assertOk();

        $this->assertSame(DistressSignalStatus::Expired, DistressSignal::sole()->status);
        Notification::assertSentTo($this->client, DistressSignalUnanswered::class);
    }

    public function test_unanswered_signals_are_closed_at_the_end_of_the_wait(): void
    {
        Notification::fake();
        $this->garageAt(2, 5);
        $this->sendSignal();

        $this->travel(11)->minutes();
        $this->artisan('requests:expire')->assertSuccessful();

        $this->assertSame(DistressSignalStatus::Expired, DistressSignal::sole()->status);
        Notification::assertSentTo($this->client, DistressSignalUnanswered::class);
    }

    public function test_the_client_can_cancel_an_open_signal(): void
    {
        $garage = $this->garageAt(2, 5);
        $signalId = $this->sendSignal()->json('data.id');

        $this->actingAs($this->client)
            ->postJson("/api/v1/distress-signals/{$signalId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->actingAs($garage->owner)
            ->postJson("/api/v1/my-business/distress-signals/{$signalId}/accept")
            ->assertConflict();
    }
}
