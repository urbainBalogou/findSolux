<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Paramètre modifiable depuis le back-office. Lecture typée et valeurs par
 * défaut : App\Services\Settings\PlatformSettings.
 */
#[Fillable(['key', 'value'])]
class PlatformSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';
}
