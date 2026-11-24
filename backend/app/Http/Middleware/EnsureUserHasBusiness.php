<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes de gestion de la fiche : réservées aux utilisateurs qui en ont une.
 */
class EnsureUserHasBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->business === null) {
            return response()->json(['message' => "Vous n'avez pas encore inscrit de garage."], 404);
        }

        return $next($request);
    }
}
