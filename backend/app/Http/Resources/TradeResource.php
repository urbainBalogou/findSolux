<?php

namespace App\Http\Resources;

use App\Models\Trade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Trade */
class TradeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'services' => ServiceResource::collection($this->whenLoaded('services')),
        ];
    }
}
