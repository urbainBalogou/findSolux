<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Profil de l'utilisateur connecté (adresse e-mail et numéro : jamais exposés
 * à des tiers par cette ressource).
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // Null tant que l'utilisateur ne l'a pas saisi : l'application le demande.
            'phone' => $this->phone,
            // Compte d'équipe : il ne sert qu'à répondre aux demandes de ce garage.
            'team_member' => $this->isTeamMember() ? [
                'username' => $this->username,
                'business' => ['id' => $this->teamBusiness->id, 'name' => $this->teamBusiness->name],
            ] : null,
        ];
    }
}
