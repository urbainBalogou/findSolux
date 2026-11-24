<?php

namespace Database\Factories;

use App\Enums\ContactRequestStatus;
use App\Models\Business;
use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContactRequest>
 */
class ContactRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => fn () => (string) Str::uuid(),
            'client_user_id' => User::factory(),
            'business_id' => Business::factory(),
            'message' => 'Bonjour, je voudrais faire une vidange.',
            'status' => ContactRequestStatus::Pending,
            'expires_at' => now()->addDay(),
        ];
    }
}
