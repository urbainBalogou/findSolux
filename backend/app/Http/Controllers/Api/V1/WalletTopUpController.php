<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\StoreWalletTopUpRequest;
use App\Http\Resources\WalletTopUpResource;
use App\Models\WalletTopUp;
use App\Services\Payments\FedaPayException;
use App\Services\Wallets\WalletTopUpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletTopUpController extends Controller
{
    public function store(
        StoreWalletTopUpRequest $request,
        WalletTopUpService $topUps,
    ): WalletTopUpResource|JsonResponse {
        try {
            $topUp = $topUps->start(
                $request->user(),
                $request->integer('amount'),
                $request->paymentMethod(),
                $request->string('phone')->toString(),
            );
        } catch (FedaPayException $exception) {
            report($exception);

            return response()->json([
                'message' => 'La recharge n’a pas pu être lancée. Réessayez dans un instant.',
            ], 503);
        }

        return (new WalletTopUpResource($topUp))
            ->response()
            ->setStatusCode(202);
    }

    public function show(
        Request $request,
        WalletTopUp $walletTopUp,
        WalletTopUpService $topUps,
    ): WalletTopUpResource {
        abort_unless(
            $walletTopUp->wallet()->where('user_id', $request->user()->getKey())->exists(),
            404,
        );

        try {
            $walletTopUp = $topUps->synchronize($walletTopUp);
        } catch (FedaPayException $exception) {
            report($exception);
        }

        return new WalletTopUpResource($walletTopUp);
    }
}
