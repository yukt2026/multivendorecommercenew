<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryPartner extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'vehicle',
        'vehicle_number',
        'is_active',
        'status',
        'active_orders_count',
        'current_lat',
        'current_lng',
        'last_location_updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'active_orders_count' => 'integer',
        'current_lat' => 'float',
        'current_lng' => 'float',
        'last_location_updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    public function activeAssignment()
    {
        return $this->hasOne(DeliveryAssignment::class)
            ->whereIn('status', ['assigned', 'accepted', 'picked_up'])
            ->latestOfMany();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('status', 'available');
    }

    /**
     * Compute approximate Haversine distance in km from rider's current location to a given point.
     */
    public function distanceTo(float $targetLat, float $targetLng): float
    {
        if ($this->current_lat === null || $this->current_lng === null) {
            return 999.0;
        }

        $earthRadius = 6371; // km
        $dLat = deg2rad($targetLat - (float) $this->current_lat);
        $dLng = deg2rad($targetLng - (float) $this->current_lng);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad((float) $this->current_lat)) * cos(deg2rad($targetLat)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
