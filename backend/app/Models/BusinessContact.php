<?php

namespace App\Models;

use App\Enums\ContactSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contact échangé entre un client et un garage : chacun voit le numéro de
 * l'autre, sans nouveau débit pour une demande de contact.
 */
#[Fillable(['business_id', 'client_user_id', 'source'])]
class BusinessContact extends Model
{
    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    protected function casts(): array
    {
        return ['source' => ContactSource::class];
    }
}
