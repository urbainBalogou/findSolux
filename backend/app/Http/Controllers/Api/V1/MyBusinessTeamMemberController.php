<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\StoreTeamMemberRequest;
use App\Http\Requests\Business\UpdateTeamMemberRequest;
use App\Http\Resources\TeamMemberResource;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Équipe du garage (employés ou répondeurs) : le garagiste crée lui-même leur
 * identifiant et leur mot de passe, dans la limite de ses packs.
 */
class MyBusinessTeamMemberController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $business = $request->user()->business;

        return TeamMemberResource::collection($business->teamMembers()->get())
            ->additional(['meta' => $this->meta($business)]);
    }

    public function store(StoreTeamMemberRequest $request): JsonResponse
    {
        $business = $request->user()->business;

        $member = DB::transaction(function () use ($business, $request): ?User {
            $locked = Business::query()->lockForUpdate()->findOrFail($business->getKey());

            if ($locked->teamMembers()->count() >= $locked->team_quota) {
                return null;
            }

            $member = new User;
            $member->forceFill([
                'team_business_id' => $locked->getKey(),
                'name' => $request->validated('name'),
                'username' => $request->validated('username'),
                'password' => $request->validated('password'),
            ])->save();

            return $member;
        });

        if ($member === null) {
            return response()->json([
                'message' => $business->team_quota === 0
                    ? 'Achetez un pack « Employés et répondeurs » pour ajouter des membres à votre équipe.'
                    : "Votre équipe est complète ({$business->team_quota} membre(s)). Achetez un pack supplémentaire pour en ajouter.",
                'code' => 'team_quota_reached',
            ], 422);
        }

        return (new TeamMemberResource($member))->response()->setStatusCode(201);
    }

    /**
     * Un nouveau mot de passe déconnecte le membre de tous ses téléphones.
     */
    public function update(UpdateTeamMemberRequest $request, int $member): TeamMemberResource
    {
        $teamMember = $this->teamMember($request, $member);
        $teamMember->forceFill($request->validated())->save();

        if ($request->has('password')) {
            $teamMember->tokens()->delete();
            $teamMember->deviceTokens()->delete();
        }

        return new TeamMemberResource($teamMember);
    }

    public function destroy(Request $request, int $member): Response
    {
        $teamMember = $this->teamMember($request, $member);
        $teamMember->tokens()->delete();
        $teamMember->delete();

        return response()->noContent();
    }

    private function teamMember(Request $request, int $id): User
    {
        return $request->user()->business->teamMembers()->findOrFail($id);
    }

    /**
     * @return array{quota: int, count: int}
     */
    private function meta(Business $business): array
    {
        return [
            'quota' => $business->team_quota,
            'count' => $business->teamMembers()->count(),
        ];
    }
}
