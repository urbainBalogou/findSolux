<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreDistressSignalRequest;
use App\Http\Resources\DistressSignalResource;
use App\Models\DistressSignal;
use App\Services\Requests\DistressSignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Signaux de détresse du client connecté (C5).
 */
class DistressSignalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $signals = DistressSignal::query()
            ->where('client_user_id', $request->user()->getKey())
            ->with('acceptedBusiness')
            ->withCount('recipients')
            ->latest('id')
            ->limit(20)
            ->get();

        return DistressSignalResource::collection($signals);
    }

    public function store(StoreDistressSignalRequest $request, DistressSignalService $signals): JsonResponse
    {
        $signal = $signals->send($request->user(), $request->signalData());

        return (new DistressSignalResource($signal->loadCount('recipients')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, DistressSignal $distressSignal): DistressSignalResource
    {
        $this->ensureOwnedBy($request, $distressSignal);

        return new DistressSignalResource($distressSignal->load('acceptedBusiness')->loadCount('recipients'));
    }

    public function cancel(Request $request, DistressSignal $distressSignal, DistressSignalService $signals): DistressSignalResource
    {
        $this->ensureOwnedBy($request, $distressSignal);

        return new DistressSignalResource($signals->cancel($distressSignal)->loadCount('recipients'));
    }

    private function ensureOwnedBy(Request $request, DistressSignal $distressSignal): void
    {
        abort_unless($distressSignal->client_user_id === $request->user()->getKey(), 404, 'Élément introuvable.');
    }
}
