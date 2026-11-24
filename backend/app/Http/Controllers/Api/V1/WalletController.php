<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WalletResource;
use App\Services\Wallets\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request, WalletService $wallets): JsonResponse
    {
        $wallet = $wallets->walletFor($request->user());
        $wallet->setRelation(
            'entries',
            $wallet->entries()->latest()->limit(20)->get(),
        );
        $wallet->setRelation(
            'topUps',
            $wallet->topUps()->latest()->limit(5)->get(),
        );

        return (new WalletResource($wallet))
            ->response()
            ->setStatusCode(200);
    }
}
