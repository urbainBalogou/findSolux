<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReceivedDistressSignalResource;
use App\Models\Business;
use App\Models\DistressSignal;
use App\Services\Offers\RequestPricing;
use App\Services\Requests\DistressSignalService;
use App\Services\Wallets\WalletService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Signaux de détresse reçus par le garage (P5) : vus et acceptés par le
 * garagiste ou un membre actif de son équipe, débités du portefeuille du garagiste.
 */
class ReceivedDistressSignalController extends Controller
{
    public function index(Request $request, RequestPricing $pricing, WalletService $wallets): AnonymousResourceCollection
    {
        $business = $request->user()->workplace();

        $signals = DistressSignal::query()
            ->whereHas('recipients', fn (Builder $recipients) => $recipients->whereBelongsTo($business))
            ->where('created_at', '>=', now()->subDays(7))
            ->with($this->relationsFor($business))
            ->latest('id')
            ->limit(50)
            ->get();

        return ReceivedDistressSignalResource::collection($signals)->additional(['meta' => [
            'acceptance_price' => ['amount' => $pricing->acceptancePriceXof(), 'currency' => 'XOF'],
            'balance' => ['amount' => $wallets->balanceOf($business->owner), 'currency' => 'XOF'],
        ]]);
    }

    public function accept(Request $request, DistressSignal $distressSignal, DistressSignalService $signals): ReceivedDistressSignalResource
    {
        $business = $this->recipientBusiness($request, $distressSignal);

        return new ReceivedDistressSignalResource(
            $signals->accept($distressSignal, $business, $request->user())->load($this->relationsFor($business)),
        );
    }

    public function decline(Request $request, DistressSignal $distressSignal, DistressSignalService $signals): ReceivedDistressSignalResource
    {
        $business = $this->recipientBusiness($request, $distressSignal);

        return new ReceivedDistressSignalResource(
            $signals->decline($distressSignal, $business)->load($this->relationsFor($business)),
        );
    }

    /**
     * Seuls les garages alertés voient et acceptent un signal.
     */
    private function recipientBusiness(Request $request, DistressSignal $distressSignal): Business
    {
        $business = $request->user()->workplace();

        abort_unless(
            $distressSignal->recipients()->whereBelongsTo($business)->exists(),
            404,
            'Élément introuvable.',
        );

        return $business;
    }

    /**
     * @return array<string, mixed>
     */
    private function relationsFor(Business $business): array
    {
        return [
            'recipients' => fn ($recipients) => $recipients->whereBelongsTo($business),
            'client',
            'acceptedBy',
        ];
    }
}
