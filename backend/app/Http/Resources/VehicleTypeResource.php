<?php

namespace App\Http\Resources;

use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VehicleType */
class VehicleTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'icon' => $this->icon,
        ];
    }
}
