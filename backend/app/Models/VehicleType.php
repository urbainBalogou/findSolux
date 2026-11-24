<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'name', 'icon', 'position', 'is_active'])]
class VehicleType extends Model
{
    use HasActiveScope;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
