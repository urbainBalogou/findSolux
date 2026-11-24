<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demandes reçues et clients du garage : ouverts au garagiste et aux membres
 * actifs de son équipe. Un membre au-delà du quota des packs est suspendu.
 */
class EnsureUserWorksAtBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $business = $user?->workplace();

        if ($business === null) {
            return response()->json(['message' => "Vous n'avez pas encore inscrit de garage."], 404);
        }

        if ($user->isTeamMember() && ! $business->isActiveTeamMember($user)) {
            return response()->json([
                'message' => 'Votre accès est suspendu : le pack Équipe du garage a expiré. Prévenez le responsable du garage.',
                'code' => 'team_member_suspended',
            ], 403);
        }

        return $next($request);
    }
}
