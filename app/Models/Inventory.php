<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventory';

    protected $fillable = [
        'variant_id',
        'location_id',
        'quantity_on_hand',
        'quantity_reserved',
        'low_stock_threshold',
        'is_quick_available',
    ];

    protected $casts = [
        'quantity_on_hand' => 'integer',
        'quantity_reserved' => 'integer',
        'low_stock_threshold' => 'integer',
        'is_quick_available' => 'boolean',
    ];

    protected $appends = [
        'available_quantity',
    ];

    /**
     * Computed accessor: available_quantity = on_hand - reserved.
     */
    protected function availableQuantity(): Attribute
    {
        return Attribute::make(
            get: fn (): int => (int) ($this->quantity_on_hand - $this->quantity_reserved)
        );
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'inventory_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class, 'inventory_id');
    }

    /**
     * Scope: items where available quantity > 0
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereRaw('(quantity_on_hand - quantity_reserved) > 0');
    }

    /**
     * Scope: items quick available and in stock
     */
    public function scopeQuickAvailable(Builder $query): Builder
    {
        return $query->where('is_quick_available', true)->inStock();
    }

    /**
     * Scope: items where available quantity is less than or equal to low stock threshold
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereRaw('(quantity_on_hand - quantity_reserved) <= low_stock_threshold');
    }
}
