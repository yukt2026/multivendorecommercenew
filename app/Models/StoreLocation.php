<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'address',
        'city',
        'state',
        'pincode',
        'lat',
        'lng',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    /**
     * Scope query to find locations within given radius (km) using Haversine formula.
     * Fallback radius dynamically queried from DB settings per rule 2.
     */
    public function scopeNearby(Builder $query, float $lat, float $lng, ?float $radiusKm = null): Builder
    {
        $lat = (float) $lat;
        $lng = (float) $lng;
        $radius = (float) ($radiusKm ?? setting('default_delivery_radius_km', 8.00));

        $haversine = "(6371 * acos(
            cos(radians({$lat})) * cos(radians(lat)) * 
            cos(radians(lng) - radians({$lng})) + 
            sin(radians({$lat})) * sin(radians(lat))
        ))";

        return $query->select('store_locations.*')
            ->selectRaw("{$haversine} AS distance_km")
            ->whereRaw("{$haversine} <= {$radius}")
            ->orderBy('distance_km', 'asc');
    }

    /**
     * Store relation.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
