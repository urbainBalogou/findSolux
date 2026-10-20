<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use Carbon\CarbonInterface;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Fiche d'un garage ou d'un mécanicien indépendant.
 */
#[Fillable([
    'trade_id', 'name', 'phone', 'whatsapp_phone', 'description', 'city_id', 'district_id',
    'latitude', 'longitude', 'landmark', 'is_mobile', 'service_radius_km', 'is_24_7',
])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsTo<Trade, $this>
     */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * Véhicules pris en charge.
     *
     * @return BelongsToMany<VehicleType, $this>
     */
    public function vehicleTypes(): BelongsToMany
    {
        return $this->belongsToMany(VehicleType::class);
    }

    /**
     * @return HasMany<BusinessService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(BusinessService::class);
    }

    /**
     * @return HasMany<OpeningHour, $this>
     */
    public function openingHours(): HasMany
    {
        return $this->hasMany(OpeningHour::class)->orderBy('day_of_week')->orderBy('opens_at');
    }

    /**
     * @return HasMany<BusinessPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(BusinessPhoto::class)->orderBy('position');
    }

    /**
     * Ouvert à cet instant (heure de Lomé), d'après les horaires ou le
     * dépannage 24 h/24. Même règle que la recherche (BusinessSearch).
     */
    public function isOpenAt(CarbonInterface $moment): bool
    {
        if ($this->is_24_7) {
            return true;
        }

        $moment = $moment->clone()->setTimezone(config('findsolux.timezone'));
        $time = $moment->format('H:i:s');

        return $this->openingHours->contains(fn (OpeningHour $range) => $range->day_of_week === $moment->dayOfWeekIso
            && $range->opens_at <= $time
            && $range->closes_at > $time);
    }

    /**
     * Slug unique tiré du nom et du quartier : « garage-de-la-paix-adidogome ».
     */
    public static function uniqueSlug(string $name, District $district): string
    {
        $base = Str::limit(Str::slug("{$name} {$district->name}"), 150, '');
        $slug = $base;

        for ($suffix = 2; static::where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_mobile' => 'boolean',
            'service_radius_km' => 'integer',
            'is_24_7' => 'boolean',
            'status' => BusinessStatus::class,
        ];
    }
}
