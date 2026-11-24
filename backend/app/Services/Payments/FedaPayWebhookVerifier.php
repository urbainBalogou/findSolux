<?php

namespace App\Services\Payments;

use JsonException;

class FedaPayWebhookVerifier
{
    /**
     * @return array<string, mixed>
     */
    public function verify(string $payload, ?string $signatureHeader): array
    {
        $secret = config('services.fedapay.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            throw new FedaPayException('Le secret du webhook FedaPay est manquant.');
        }

        [$timestamp, $signatures] = $this->parseHeader($signatureHeader);
        $signedPayload = $timestamp.'.'.$payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        $signatureIsValid = false;
        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                $signatureIsValid = true;
                break;
            }
        }

        if (! $signatureIsValid || abs(time() - $timestamp) > 300) {
            throw new FedaPayException('Signature FedaPay invalide.');
        }

        try {
            $event = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new FedaPayException('Payload FedaPay invalide.', previous: $exception);
        }

        if (! is_array($event)) {
            throw new FedaPayException('Payload FedaPay invalide.');
        }

        return $event;
    }

    /**
     * @return array{int, list<string>}
     */
    private function parseHeader(?string $header): array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header ?? '') as $item) {
            [$key, $value] = array_pad(explode('=', trim($item), 2), 2, null);
            if ($key === 't' && is_numeric($value)) {
                $timestamp = (int) $value;
            }
            if ($key === 's' && is_string($value) && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            throw new FedaPayException('Signature FedaPay invalide.');
        }

        return [$timestamp, $signatures];
    }
}
