<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trade_id', 'slug', 'name', 'icon', 'position', 'is_active'])]
class Service extends Model
{
    use HasActiveScope;

    /**
     * @return BelongsTo<Trade, $this>
     */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
