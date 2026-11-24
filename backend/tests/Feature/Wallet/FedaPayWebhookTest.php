<?php

namespace Tests\Feature\Wallet;

use App\Enums\WalletPaymentMethod;
use App\Enums\WalletTopUpStatus;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Models\WalletTopUp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FedaPayWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'wh_sandbox_test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.fedapay.environment' => 'sandbox',
            'services.fedapay.secret_key' => 'sk_sandbox_test',
            'services.fedapay.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
    }

    public function test_an_approved_webhook_credits_the_wallet_only_once(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $topUp = WalletTopUp::factory()->for($wallet)->create([
            'amount_xof' => 5_000,
            'status' => WalletTopUpStatus::Pending,
            'payment_method' => WalletPaymentMethod::MoovTogo,
            'provider_transaction_id' => '321',
        ]);

        Http::fake([
            'https://sandbox-api.fedapay.com/v1/transactions/321' => Http::response([
                'v1/transaction' => [
                    'id' => 321,
                    'status' => 'approved',
                    'amount' => 5_000,
                    'currency' => ['iso' => 'XOF'],
                    'merchant_reference' => $topUp->public_id,
                ],
            ]),
        ]);

        $payload = json_encode([
            'id' => 'event-1',
            'name' => 'transaction.approved',
            'entity' => ['id' => 321],
        ], JSON_THROW_ON_ERROR);

        $this->postSignedWebhook($payload)->assertOk();
        $this->postSignedWebhook($payload)->assertOk();

        $this->assertSame(5_000, $wallet->refresh()->balance_xof);
        $this->assertSame(1, WalletEntry::count());
        $this->assertSame(
            WalletTopUpStatus::Succeeded,
            $topUp->refresh()->status,
        );
    }

    public function test_a_webhook_with_an_invalid_signature_is_rejected(): void
    {
        $this->call(
            'POST',
            '/api/v1/payments/fedapay/webhook',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_FEDAPAY_SIGNATURE' => 't=1,s=invalid',
            ],
            content: '{}',
        )->assertBadRequest();
    }

    private function postSignedWebhook(string $payload): TestResponse
    {
        $timestamp = time();
        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$payload,
            self::WEBHOOK_SECRET,
        );

        return $this->call(
            'POST',
            '/api/v1/payments/fedapay/webhook',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_FEDAPAY_SIGNATURE' => 't='.$timestamp.',s='.$signature,
            ],
            content: $payload,
        );
    }
}
