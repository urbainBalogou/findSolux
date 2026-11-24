<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleSignInRequest;
use App\Http\Requests\Auth\TeamMemberLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\GoogleIdTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly GoogleIdTokenVerifier $google) {}

    /**
     * Connexion et inscription ne font qu'un : le compte est créé à la première
     * connexion Google (ADR-017). Le nom Google n'écrase pas celui choisi ensuite.
     */
    public function google(GoogleSignInRequest $request): JsonResponse
    {
        $identity = $this->google->verify($request->validated('id_token'));

        $user = User::query()->where('google_id', $identity->subject)->first() ?? new User;
        $user->forceFill([
            'google_id' => $identity->subject,
            'email' => $identity->email,
            'name' => $user->name ?? $identity->name,
            'last_seen_at' => now(),
        ])->save();

        $token = $user->createToken($request->validated('device_name') ?? 'mobile');

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Membre de l'équipe d'un garage : identifiant et mot de passe créés par
     * le garagiste. Un membre au-delà du quota des packs ne peut pas se connecter.
     */
    public function teamMember(TeamMemberLoginRequest $request): JsonResponse
    {
        $member = User::query()
            ->whereNotNull('team_business_id')
            ->where('username', $request->username())
            ->first();

        if ($member === null || ! Hash::check($request->validated('password'), $member->password)) {
            throw ValidationException::withMessages(['username' => 'Identifiant ou mot de passe incorrect.']);
        }

        if (! $member->teamBusiness->isActiveTeamMember($member)) {
            return response()->json([
                'message' => 'Votre accès est suspendu : le pack Équipe du garage a expiré. Prévenez le responsable du garage.',
                'code' => 'team_member_suspended',
            ], 403);
        }

        $member->forceFill(['last_seen_at' => now()])->save();
        $token = $member->createToken($request->validated('device_name') ?? 'mobile');

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($member),
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
