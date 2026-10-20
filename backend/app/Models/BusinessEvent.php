<?php

namespace App\Models;

use App\Enums\BusinessEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['business_id', 'type', 'device_id', 'user_id', 'occurred_on'])]
class BusinessEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => BusinessEventType::class,
            'occurred_on' => 'date',
        ];
    }
}
