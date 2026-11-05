<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryZoneType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreDeliveryZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'type',
        'radius_km',
        'pincodes',
        'min_order_value',
        'delivery_fee',
        'is_quick',
    ];

    protected function casts(): array
    {
        return [
            'type' => DeliveryZoneType::class,
            'radius_km' => 'float',
            'pincodes' => 'array',
            'min_order_value' => 'float',
            'delivery_fee' => 'float',
            'is_quick' => 'boolean',
        ];
    }

    /**
     * Scope query to quick commerce delivery zones.
     */
    public function scopeQuick(Builder $query): Builder
    {
        return $query->where('is_quick', true);
    }

    /**
     * Check if zone serves given postal pincode.
     */
    public function servesPincode(string $pincode): bool
    {
        if ($this->type !== DeliveryZoneType::PINCODE_LIST || empty($this->pincodes)) {
            return false;
        }

        return in_array($pincode, $this->pincodes, true);
    }

    /**
     * Store relation.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
