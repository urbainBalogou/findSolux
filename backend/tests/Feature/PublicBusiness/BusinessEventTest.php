<?php

namespace Tests\Feature\PublicBusiness;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\BusinessEvent;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BusinessEventTest extends TestCase
{
    use RefreshDatabase;

    private const DEVICE = '3f2a9c4e-1b7d-4e8a-9c21-5d6f7a8b9c0d';

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->business = Business::factory()->create();
    }

    private function record(string $type, string $device = self::DEVICE, array $headers = []): TestResponse
    {
        return $this->postJson(
            "/api/v1/businesses/{$this->business->slug}/events",
            ['type' => $type, 'device_id' => $device],
            $headers,
        );
    }

    public function test_views_and_clicks_are_recorded(): void
    {
        $this->record('view')->assertNoContent();
        $this->record('call')->assertNoContent();
        $this->record('whatsapp')->assertNoContent();

        $this->assertEqualsCanonicalizing(
            ['view', 'call', 'whatsapp'],
            BusinessEvent::pluck('type')->map->value->all(),
        );
    }

    public function test_a_device_counts_once_per_day_and_action(): void
    {
        $this->record('view');
        $this->record('view');
        $this->record('view', 'autre-appareil-42');
        $this->assertSame(2, BusinessEvent::count());

        $this->travel(1)->day();
        $this->record('view');

        $this->assertSame(3, BusinessEvent::count());
    }

    public function test_the_owner_does_not_inflate_his_statistics(): void
    {
        $token = $this->business->owner->createToken('mobile')->plainTextToken;

        $this->record('view', headers: ['Authorization' => "Bearer {$token}"])->assertNoContent();

        $this->assertSame(0, BusinessEvent::count());
    }

    public function test_a_signed_in_client_is_recorded_with_his_account(): void
    {
        $client = User::factory()->create();
        $token = $client->createToken('mobile')->plainTextToken;

        $this->record('call', headers: ['Authorization' => "Bearer {$token}"])->assertNoContent();

        $this->assertSame($client->id, BusinessEvent::first()->user_id);
    }

    public function test_search_engine_robots_are_ignored(): void
    {
        $this->record('view', headers: ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->assertNoContent();

        $this->assertSame(0, BusinessEvent::count());
    }

    public function test_events_are_validated(): void
    {
        $this->record('like')->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->record('view', 'x')->assertUnprocessable()->assertJsonValidationErrors('device_id');
    }

    public function test_suspended_businesses_do_not_collect_events(): void
    {
        $this->business->forceFill(['status' => BusinessStatus::Suspended])->save();

        $this->record('view')->assertNotFound();
    }
}
