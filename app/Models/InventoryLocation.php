<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryLocationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'store_id',
        'seller_id',
        'name',
        'lat',
        'lng',
        'is_active',
    ];

    protected $casts = [
        'type' => InventoryLocationType::class,
        'lat' => 'float',
        'lng' => 'float',
        'is_active' => 'boolean',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'location_id');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'inventory', 'location_id', 'variant_id')
            ->withPivot(['quantity_on_hand', 'quantity_reserved', 'low_stock_threshold', 'is_quick_available'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
