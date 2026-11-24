<?php

namespace Tests\Feature\Offers;

use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\CommercialOffer;
use App\Models\Service;
use App\Models\Wallet;
use App\Notifications\SubscriptionEndingSoon;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionRenewed;
use App\Services\Offers\BusinessEntitlements;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProcessSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private CommercialOffer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->travelTo('2026-11-19 10:00:00');
        $this->business = Business::factory()->withoutMembership()->create();
        $this->offer = CommercialOffer::factory()->create(['price_xof' => 5000]);
    }

    private function membershipEndingAt(mixed $endsAt, array $attributes = []): BusinessSubscription
    {
        $subscription = BusinessSubscription::factory()->for($this->business)->for($this->offer)->create([
            'starts_at' => now()->subMonth(),
            'ends_at' => $endsAt,
            ...$attributes,
        ]);
        app(BusinessEntitlements::class)->sync($this->business);

        return $subscription;
    }

    public function test_reminders_are_sent_seven_days_and_one_day_before_the_end_only_once(): void
    {
        Notification::fake();
        $subscription = $this->membershipEndingAt(now()->addDays(6));

        $this->artisan('subscriptions:process')->assertSuccessful();
        $this->artisan('subscriptions:process')->assertSuccessful();

        Notification::assertSentToTimes($this->business->owner, SubscriptionEndingSoon::class, 1);
        Notification::assertSentTo(
            $this->business->owner,
            SubscriptionEndingSoon::class,
            fn (SubscriptionEndingSoon $notification) => $notification->daysBefore === 7 && ! $notification->willRenew,
        );

        $this->travelTo($subscription->ends_at->subHours(20));
        $this->artisan('subscriptions:process')->assertSuccessful();

        Notification::assertSentToTimes($this->business->owner, SubscriptionEndingSoon::class, 2);
        Notification::assertSentTo(
            $this->business->owner,
            SubscriptionEndingSoon::class,
            fn (SubscriptionEndingSoon $notification) => $notification->daysBefore === 1,
        );
    }

    public function test_an_auto_renewed_membership_is_debited_and_continues_without_interruption(): void
    {
        Notification::fake();
        $wallet = Wallet::factory()->for($this->business->owner)->create(['balance_xof' => 6000]);
        $subscription = $this->membershipEndingAt(now()->addMinutes(10), ['auto_renew' => true]);

        $this->artisan('subscriptions:process')->assertSuccessful();

        $renewal = BusinessSubscription::query()->where('renewed_from_id', $subscription->id)->sole();
        $this->assertSame(SubscriptionSource::Renewal, $renewal->source);
        $this->assertTrue($renewal->starts_at->eq($subscription->ends_at));
        $this->assertTrue($renewal->auto_renew);
        $this->assertSame(1000, $wallet->refresh()->balance_xof);
        Notification::assertSentTo($this->business->owner, SubscriptionRenewed::class);

        $this->travel(15)->minutes();
        $this->artisan('subscriptions:process')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Renewed, $subscription->refresh()->status);
        $this->assertTrue($this->business->refresh()->isVisible());
        Notification::assertNotSentTo($this->business->owner, SubscriptionExpired::class);
    }

    public function test_without_enough_balance_the_membership_expires_and_the_business_disappears(): void
    {
        Notification::fake();
        Wallet::factory()->for($this->business->owner)->create(['balance_xof' => 100]);
        $subscription = $this->membershipEndingAt(now()->addMinutes(10), ['auto_renew' => true]);

        $this->artisan('subscriptions:process')->assertSuccessful();
        $this->assertTrue($this->business->refresh()->isVisible());

        $this->travel(11)->minutes();
        $this->artisan('subscriptions:process')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Expired, $subscription->refresh()->status);
        $this->assertFalse($this->business->refresh()->isVisible());
        $this->assertDatabaseCount('business_subscriptions', 1);
        Notification::assertSentTo(
            $this->business->owner,
            SubscriptionExpired::class,
            fn (SubscriptionExpired $notification) => $notification->renewalFailureReason === 'insufficient_balance',
        );
    }

    public function test_an_expired_service_bundle_unpublishes_services_without_deleting_them(): void
    {
        Notification::fake();
        $this->business->services()->create(['service_id' => Service::query()->value('id')]);
        BusinessSubscription::factory()->for($this->business)->serviceBundle(1)->create(['ends_at' => now()->addMinute()]);
        app(BusinessEntitlements::class)->sync($this->business);
        $this->assertSame(1, $this->business->publishedServices()->count());

        $this->travel(2)->minutes();
        $this->artisan('subscriptions:process')->assertSuccessful();

        $this->assertSame(0, $this->business->publishedServices()->count());
        $this->assertSame(1, $this->business->services()->count());
        Notification::assertSentTo($this->business->owner, SubscriptionExpired::class);
    }

    public function test_a_manual_extension_takes_over_without_reminder_or_expiry_notice(): void
    {
        Notification::fake();
        $current = $this->membershipEndingAt(now()->addDays(3));
        BusinessSubscription::factory()->for($this->business)->create([
            'starts_at' => $current->ends_at,
            'ends_at' => $current->ends_at->addMonth(),
        ]);
        app(BusinessEntitlements::class)->sync($this->business);

        $this->artisan('subscriptions:process')->assertSuccessful();
        $this->travelTo($current->ends_at->addMinute());
        $this->artisan('subscriptions:process')->assertSuccessful();

        Notification::assertNothingSentTo($this->business->owner);
        $this->assertTrue($this->business->refresh()->isVisible());
    }
}
