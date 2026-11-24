<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['path', 'thumbnail_path', 'width', 'height', 'size_bytes', 'position'])]
class BusinessPhoto extends Model
{
    public function url(): string
    {
        return Storage::disk(config('findsolux.photos.disk'))->url($this->path);
    }

    public function thumbnailUrl(): string
    {
        return Storage::disk(config('findsolux.photos.disk'))->url($this->thumbnail_path);
    }

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'size_bytes' => 'integer',
            'position' => 'integer',
        ];
    }
}
