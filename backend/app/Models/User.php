<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Compte de l'application. Deux sortes :
 * - compte personnel, ouvert avec Google (ADR-017) : client, et garagiste s'il
 *   possède une fiche. Le numéro de téléphone est celui où le joindre, saisi
 *   par l'utilisateur et non vérifié ;
 * - compte d'équipe (employé ou répondeur), créé par le garagiste avec un
 *   identifiant et un mot de passe : il ne sert qu'à répondre aux demandes de
 *   ce garage.
 */
#[Fillable(['phone', 'name'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** @var list<string> */
    protected $hidden = ['password'];

    /**
     * Fiche du garage : un utilisateur est garagiste dès qu'il en possède une.
     *
     * @return HasOne<Business, $this>
     */
    public function business(): HasOne
    {
        return $this->hasOne(Business::class, 'owner_user_id');
    }

    /**
     * Garage dont l'utilisateur est membre de l'équipe (compte d'équipe).
     *
     * @return BelongsTo<Business, $this>
     */
    public function teamBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'team_business_id');
    }

    public function isTeamMember(): bool
    {
        return $this->team_business_id !== null;
    }

    /**
     * Garage pour lequel l'utilisateur répond aux demandes : le sien, ou celui
     * dont il est membre de l'équipe.
     */
    public function workplace(): ?Business
    {
        return $this->isTeamMember() ? $this->teamBusiness : $this->business;
    }

    /** @return HasOne<Wallet, $this> */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /** @return HasMany<DeviceToken, $this> */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Garages avec lesquels l'utilisateur, en tant que client, a échangé ses coordonnées.
     *
     * @return HasMany<BusinessContact, $this>
     */
    public function businessContacts(): HasMany
    {
        return $this->hasMany(BusinessContact::class, 'client_user_id');
    }

    /**
     * Jetons Firebase des appareils de l'utilisateur (canal « push »).
     *
     * @return list<string>
     */
    public function routeNotificationForPush(): array
    {
        return $this->deviceTokens()->pluck('token')->all();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
