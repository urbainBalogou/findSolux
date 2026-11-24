<?php

namespace App\Services\Payments;

use App\Models\User;
use App\Models\WalletTopUp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class FedaPayClient
{
    private const BASE_URLS = [
        'sandbox' => 'https://sandbox-api.fedapay.com/v1',
        'live' => 'https://api.fedapay.com/v1',
    ];

    /** @return array<string, mixed> */
    public function createTransaction(
        WalletTopUp $topUp,
        User $user,
        string $phone,
    ): array {
        [$firstName, $lastName] = $this->customerName($user);
        $data = $this->request('post', 'transactions', [
            'description' => 'Recharge du portefeuille MecaDispo',
            'amount' => $topUp->amount_xof,
            'currency' => ['iso' => 'XOF'],
            'merchant_reference' => $topUp->public_id,
            'custom_metadata' => [
                'wallet_top_up_id' => $topUp->public_id,
                'mecadispo_user_id' => $user->getKey(),
            ],
            'customer' => [
                'firstname' => $firstName,
                'lastname' => $lastName,
                'email' => $this->customerEmail($user, $topUp),
                'phone_number' => ['number' => $phone, 'country' => 'tg'],
            ],
        ]);

        return $this->unwrap($data, 'v1/transaction', 'transaction');
    }

    public function generatePaymentToken(string $transactionId): string
    {
        $data = $this->request('post', 'transactions/'.$transactionId.'/token');
        $tokenData = $this->unwrap($data, 'v1/token', 'payment_link');
        $token = $tokenData['token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new FedaPayException('FedaPay n’a pas renvoyé de jeton de paiement.');
        }

        return $token;
    }

    public function sendMobileMoney(string $paymentMethod, string $token, string $phone): void
    {
        $this->request('post', $paymentMethod, [
            'token' => $token,
            'phone_number' => ['number' => $phone, 'country' => 'tg'],
        ]);
    }

    /** @return array<string, mixed> */
    public function retrieveTransaction(string $transactionId): array
    {
        $data = $this->request('get', 'transactions/'.$transactionId);

        return $this->unwrap($data, 'v1/transaction', 'transaction');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $options = $payload === [] ? [] : ['json' => $payload];

        try {
            $response = $this->http()->send(
                strtoupper($method),
                ltrim($path, '/'),
                $options,
            );
        } catch (ConnectionException $exception) {
            throw new FedaPayException(
                'FedaPay est momentanément indisponible. Réessayez dans quelques instants.',
                previous: $exception,
            );
        }

        if (! $response->successful()) {
            throw new FedaPayException($this->errorMessage($response), $response->status());
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    private function http(): PendingRequest
    {
        $apiKey = config('services.fedapay.secret_key');
        $environment = config('services.fedapay.environment', 'sandbox');
        $baseUrl = self::BASE_URLS[$environment] ?? null;

        if (! is_string($apiKey) || $apiKey === '' || $baseUrl === null) {
            throw new FedaPayException('La recharge Mobile Money est momentanément indisponible.');
        }

        return Http::baseUrl($baseUrl.'/')
            ->acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->timeout((int) config('services.fedapay.timeout', 100));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function unwrap(array $data, string ...$keys): array
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }

        return $data;
    }

    /** @return array{string, string} */
    private function customerName(User $user): array
    {
        $name = trim($user->name ?: 'Client MecaDispo');
        $parts = preg_split('/\s+/', $name, 2) ?: [];

        return [$parts[0] ?? 'Client', $parts[1] ?? 'MecaDispo'];
    }

    private function customerEmail(User $user, WalletTopUp $topUp): string
    {
        $domain = trim((string) config(
            'services.fedapay.customer_email_domain',
            'mecadispo.com',
        ));

        return 'wallet+'.$user->getKey().'.'.$topUp->public_id.'@'.$domain;
    }

    private function errorMessage(Response $response): string
    {
        $message = $response->json('message')
            ?? $response->json('error')
            ?? $response->json('description');

        return is_string($message) && $message !== ''
            ? $message
            : 'La demande de paiement FedaPay a échoué.';
    }
}
