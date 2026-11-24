<?php

namespace App\Http\Resources;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Droits actuels d'un garage (ADR-015) : visibilité et services publiés.
 *
 * @mixin Business
 */
class EntitlementsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $serviceIds = $this->services->pluck('service_id')->unique();

        return [
            'membership' => [
                'is_active' => $this->hasActiveMembership(),
                'is_unlimited' => $this->has_unlimited_membership,
                'ends_at' => $this->has_unlimited_membership ? null : $this->membership_ends_at?->toIso8601String(),
            ],
            'services' => [
                'quota' => $this->services_quota,
                'count' => $serviceIds->count(),
                'published_count' => $this->services->where('is_published', true)->pluck('service_id')->unique()->count(),
            ],
            // Membres de l'équipe permis par les packs, et membres créés.
            'team' => [
                'quota' => $this->team_quota,
                'count' => $this->teamMembers()->count(),
            ],
            'is_visible' => $this->isVisible(),
        ];
    }
}
