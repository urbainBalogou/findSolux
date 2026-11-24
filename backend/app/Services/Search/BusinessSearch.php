<?php

namespace App\Services\Search;

use App\Models\Business;
use App\Models\BusinessPromotion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Recherche des garages autour d'un point (ADR-011) : préfiltre par rectangle
 * sur l'index latitude/longitude, puis distance exacte avec ST_Distance_Sphere.
 */
class BusinessSearch
{
    private const DISTANCE = 'ST_Distance_Sphere(POINT(businesses.longitude, businesses.latitude), POINT(?, ?))';

    private const OPEN_NOW = '(businesses.is_24_7 = 1 OR EXISTS ('
        .'SELECT 1 FROM opening_hours WHERE opening_hours.business_id = businesses.id '
        .'AND opening_hours.day_of_week = ? AND opening_hours.opens_at <= ? AND opening_hours.closes_at > ?))';

    /**
     * Fiches publiées, de la plus proche à la plus éloignée. Chaque fiche porte
     * `distance_m` (mètres), `is_open_now` et `opening_hours_exists`.
     *
     * @param  array{vehicle_type_id?: ?int, service_id?: ?int, open_now?: bool}  $filters
     * @return Collection<int, Business>
     */
    public function around(float $latitude, float $longitude, int $radiusKm, array $filters = [], int $limit = 30): Collection
    {
        $now = now(config('findsolux.timezone'));
        $openNowBindings = [$now->dayOfWeekIso, $now->format('H:i:s'), $now->format('H:i:s')];
        $point = [$longitude, $latitude];
        $maxServiceRadiusKm = config('findsolux.search.max_service_radius_km');
        $featuredPosition = BusinessPromotion::query()
            ->select('position')
            ->whereColumn('business_id', 'businesses.id')
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $period) => $period->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->limit(1);

        return Business::query()
            ->select('businesses.*')
            ->addSelect(['featured_position' => $featuredPosition])
            ->selectRaw(self::DISTANCE.' AS distance_m', $point)
            ->selectRaw(self::OPEN_NOW.' AS is_open_now', $openNowBindings)
            ->visible()
            ->where(function (Builder $query) use ($latitude, $longitude, $radiusKm, $maxServiceRadiusKm) {
                $this->withinBox($query, $latitude, $longitude, $radiusKm);
                $query->orWhere(fn (Builder $mobile) => $this->withinBox(
                    $mobile->where('is_mobile', true), $latitude, $longitude, $maxServiceRadiusKm,
                ));
            })
            // Un garagiste qui se déplace apparaît aussi au-delà du rayon de
            // recherche, tant que le client est dans sa zone d'intervention.
            ->whereRaw(
                '('.self::DISTANCE.' <= ? OR (businesses.is_mobile = 1 AND '.self::DISTANCE.' <= businesses.service_radius_km * 1000))',
                [...$point, $radiusKm * 1000, ...$point],
            )
            ->when($filters['vehicle_type_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas(
                'vehicleTypes', fn (Builder $types) => $types->whereKey($id),
            ))
            ->when($filters['service_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas(
                'publishedServices', fn (Builder $services) => $services->where('service_id', $id),
            ))
            ->when($filters['open_now'] ?? false, fn (Builder $query) => $query->whereRaw(self::OPEN_NOW, $openNowBindings))
            // R2 : tranche de distance d'abord, puis mise en avant, puis distance exacte.
            ->orderByRaw($this->distanceBandExpression())
            ->orderByRaw('featured_position IS NULL')
            ->orderBy('featured_position')
            ->orderBy('distance_m')
            ->limit($limit)
            ->withExists('openingHours')
            ->with(['vehicleTypes:id', 'publishedServices:id,business_id,service_id,is_published', 'photos'])
            ->get();
    }

    /**
     * Garages visibles qui se déplacent et dont la zone d'intervention couvre
     * ce point : destinataires d'un signal de détresse (C5). Chaque fiche porte
     * `distance_m` et son propriétaire.
     *
     * @return Collection<int, Business>
     */
    public function interveningAt(float $latitude, float $longitude, ?int $exceptOwnerId = null): Collection
    {
        $point = [$longitude, $latitude];

        return Business::query()
            ->select('businesses.*')
            ->selectRaw(self::DISTANCE.' AS distance_m', $point)
            ->visible()
            ->where('businesses.is_mobile', true)
            ->whereNotNull('businesses.service_radius_km')
            ->when($exceptOwnerId, fn (Builder $query, int $ownerId) => $query->where('businesses.owner_user_id', '!=', $ownerId))
            ->where(fn (Builder $query) => $this->withinBox(
                $query, $latitude, $longitude, config('findsolux.search.max_service_radius_km'),
            ))
            ->whereRaw(self::DISTANCE.' <= businesses.service_radius_km * 1000', $point)
            ->orderBy('distance_m')
            ->with('owner')
            ->get();
    }

    /**
     * Rang de la tranche de distance (0–1 km, 1–3 km, 3–7 km, au-delà).
     */
    private function distanceBandExpression(): string
    {
        $cases = collect(config('findsolux.search.distance_bands_m'))
            ->map(fn (int $limit, int $rank) => "WHEN distance_m < {$limit} THEN {$rank}")
            ->implode(' ');
        $lastRank = count(config('findsolux.search.distance_bands_m'));

        return "CASE {$cases} ELSE {$lastRank} END";
    }

    private function withinBox(Builder $query, float $latitude, float $longitude, float $radiusKm): void
    {
        $latitudeDelta = $radiusKm / 111.32;
        $longitudeDelta = $radiusKm / (111.32 * cos(deg2rad($latitude)));

        $query
            ->whereBetween('businesses.latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta])
            ->whereBetween('businesses.longitude', [$longitude - $longitudeDelta, $longitude + $longitudeDelta]);
    }
}
