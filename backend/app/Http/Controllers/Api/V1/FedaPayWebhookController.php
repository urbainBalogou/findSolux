<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payments\FedaPayException;
use App\Services\Payments\FedaPayWebhookVerifier;
use App\Services\Wallets\WalletTopUpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FedaPayWebhookController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        FedaPayWebhookVerifier $verifier,
        WalletTopUpService $topUps,
    ): JsonResponse|Response {
        $payload = $request->getContent();

        try {
            $event = $verifier->verify(
                $payload,
                $request->header('X-FedaPay-Signature'),
            );
        } catch (FedaPayException) {
            return response()->json(['message' => 'Signature invalide.'], 400);
        }

        $eventName = $event['name'] ?? $event['type'] ?? null;
        if (! is_string($eventName) || ! str_starts_with($eventName, 'transaction.')) {
            return response()->noContent();
        }

        $transactionId = $this->transactionId($event);
        if ($transactionId === null) {
            return response()->noContent();
        }

        try {
            $topUps->synchronizeByProviderId($transactionId);
        } catch (FedaPayException $exception) {
            report($exception);

            return response()->json(['message' => 'Vérification impossible.'], 503);
        }

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function transactionId(array $event): ?string
    {
        $entity = $event['entity'] ?? $event['data'] ?? null;
        if (is_string($entity)) {
            $entity = json_decode($entity, true);
        }

        if (! is_array($entity)) {
            return null;
        }

        $id = $entity['id'] ?? $entity['transaction_id'] ?? null;

        return is_int($id) || is_string($id) ? (string) $id : null;
    }
}
