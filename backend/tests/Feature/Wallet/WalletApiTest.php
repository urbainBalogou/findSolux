<?php

namespace Tests\Feature\Wallet;

use App\Models\User;
use App\Models\WalletTopUp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WalletApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.fedapay.environment' => 'sandbox',
            'services.fedapay.secret_key' => 'sk_sandbox_test',
        ]);
    }

    public function test_wallet_requires_authentication(): void
    {
        $this->getJson('/api/v1/wallet')->assertUnauthorized();
        $this->postJson('/api/v1/wallet/top-ups')->assertUnauthorized();
    }

    public function test_an_empty_wallet_is_created_on_first_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/wallet')
            ->assertOk()
            ->assertJsonPath('data.balance.amount', 0)
            ->assertJsonPath('data.balance.currency', 'XOF')
            ->assertJsonCount(2, 'data.payment_methods')
            ->assertJsonCount(0, 'data.transactions');

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance_xof' => 0,
        ]);
    }

    public function test_a_mobile_money_top_up_is_started_without_storing_the_phone(): void
    {
        Http::fake(fn (ClientRequest $request) => match (true) {
            str_ends_with($request->url(), '/transactions') => Http::response([
                'v1/transaction' => ['id' => 321, 'status' => 'pending'],
            ], 201),
            str_ends_with($request->url(), '/transactions/321/token') => Http::response([
                'token' => 'payment-token',
            ]),
            str_ends_with($request->url(), '/moov_tg') => Http::response([
                'message' => 'success',
            ]),
            default => Http::response([], 404),
        });

        $user = User::factory()->create(['name' => 'Afi Mensah']);
        $response = $this->actingAs($user)->postJson('/api/v1/wallet/top-ups', [
            'amount' => 5_000,
            'payment_method' => 'moov_tg',
            'phone' => '90 12 34 56',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.amount.value', 5_000)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.requires_action', true);

        $topUp = WalletTopUp::firstOrFail();
        $this->assertSame('321', $topUp->provider_transaction_id);
        $this->assertFalse(Schema::hasColumn('wallet_top_ups', 'phone'));
        $this->assertStringNotContainsString('90123456', json_encode($topUp->getAttributes()));

        Http::assertSent(function (ClientRequest $request): bool {
            if (! str_ends_with($request->url(), '/transactions')) {
                return false;
            }

            return $request->data()['customer']['phone_number'] === [
                'number' => '+22890123456',
                'country' => 'tg',
            ];
        });
    }

    public function test_top_up_fields_are_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/wallet/top-ups', [
            'amount' => 50,
            'payment_method' => 'unknown',
            'phone' => '+233241234567',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'payment_method', 'phone']);
    }
}
