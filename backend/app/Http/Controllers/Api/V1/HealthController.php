<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /**
     * Point de contrôle pour la supervision : API et base de données.
     */
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
        } catch (Throwable) {
            return response()->json(['status' => 'error', 'database' => 'unavailable'], 503);
        }

        return response()->json(['status' => 'ok', 'database' => 'ok']);
    }
}
