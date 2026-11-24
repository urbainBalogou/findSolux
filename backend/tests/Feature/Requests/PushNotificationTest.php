<?php

namespace Tests\Feature\Requests;

use App\Models\Business;
use App\Models\ContactRequest;
use App\Models\User;
use App\Notifications\ContactRequestReceived;
use App\Services\Push\PushSender;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_tokens_are_registered_and_removed_by_their_owner(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)->postJson('/api/v1/me/device-tokens', ['token' => 'abc', 'platform' => 'android'])->assertNoContent();
        // Le même appareil passe au dernier compte connecté.
        $this->actingAs($user)->postJson('/api/v1/me/device-tokens', ['token' => 'abc', 'platform' => 'android'])->assertNoContent();
        $this->assertDatabaseHas('device_tokens', ['token' => 'abc', 'user_id' => $user->id]);
        $this->assertDatabaseCount('device_tokens', 1);

        $this->actingAs($user)->deleteJson('/api/v1/me/device-tokens', ['token' => 'abc'])->assertNoContent();
        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_a_notification_is_pushed_to_every_device_of_the_recipient(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $business = Business::factory()->create();
        $business->owner->deviceTokens()->createMany([
            ['token' => 'phone', 'platform' => 'android'],
            ['token' => 'tablet', 'platform' => 'android'],
        ]);
        $contactRequest = ContactRequest::factory()->for($business)->create(['message' => 'Vidange ?']);

        $business->owner->notify(new ContactRequestReceived($contactRequest));

        $sent = app(PushSender::class)->sent;
        $this->assertCount(1, $sent);
        $this->assertSame(['phone', 'tablet'], $sent[0]['tokens']);
        $this->assertSame('Nouvelle demande de contact', $sent[0]['message']->title);
        $this->assertSame(['type' => 'contact_request', 'id' => $contactRequest->public_id], $sent[0]['message']->data);
    }
}
