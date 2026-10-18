<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['day_of_week', 'opens_at', 'closes_at'])]
#[WithoutTimestamps]
class OpeningHour extends Model
{
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }
}
