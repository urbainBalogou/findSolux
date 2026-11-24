<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Membre de l'équipe vu par le garagiste. Inactif : au-delà du quota des packs
 * actifs, il ne peut plus se connecter ni recevoir les demandes.
 *
 * @mixin User
 */
class TeamMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'is_active' => $this->teamBusiness->isActiveTeamMember($this->resource),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
