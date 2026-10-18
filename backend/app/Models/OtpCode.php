<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['phone', 'code_hash', 'channel', 'expires_at', 'ip_address'])]
class OtpCode extends Model
{
    /**
     * Codes encore utilisables : ni consommés, ni expirés.
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
