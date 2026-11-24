<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Espace client, portefeuille et gestion de la fiche : réservés aux comptes
 * personnels. Un compte d'équipe ne sert qu'à répondre aux demandes du garage.
 */
class EnsurePersonalAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isTeamMember()) {
            return response()->json([
                'message' => 'Ce compte d’équipe sert uniquement à répondre aux demandes du garage.',
                'code' => 'team_member_account',
            ], 403);
        }

        return $next($request);
    }
}
