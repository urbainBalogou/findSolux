<?php

namespace App\Models;

use App\Enums\ContactRequestStatus;
use Database\Factories\ContactRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande de contact d'un client depuis la fiche d'un garage (C4).
 */
#[Fillable([
    'public_id', 'client_user_id', 'business_id', 'message', 'status', 'expires_at',
    'responded_at', 'price_xof', 'wallet_entry_id', 'accepted_by_user_id',
])]
class ContactRequest extends Model
{
    /** @use HasFactory<ContactRequestFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Garagiste ou membre de son équipe qui a accepté.
     *
     * @return BelongsTo<User, $this>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    /**
     * En attente et pas encore échue (la tâche planifiée peut ne pas être passée).
     */
    public function isPending(): bool
    {
        return $this->status === ContactRequestStatus::Pending && $this->expires_at->isFuture();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected function casts(): array
    {
        return [
            'status' => ContactRequestStatus::class,
            'expires_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
            'price_xof' => 'integer',
        ];
    }
}
