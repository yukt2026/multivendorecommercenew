<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class FulfilmentOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'fulfilment_order_number',
        'seller_id',
        'store_id',
        'inventory_location_id',
        'fulfilment_type',
        'delivery_otp',
        'accepted_at',
        'packed_at',
        'ready_at',
        'reassigned_from_store_id',
        'status',
        'subtotal',
        'commission_amount',
        'seller_payable',
        'delivery_fee',
        'estimated_delivery_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected $casts = [
        'fulfilment_type' => FulfilmentType::class,
        'subtotal' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'seller_payable' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'accepted_at' => 'datetime',
        'packed_at' => 'datetime',
        'ready_at' => 'datetime',
        'estimated_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function inventoryLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class)->latestOfMany();
    }

    public function deliveryAssignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    public function deliveryAssignment(): HasOne
    {
        return $this->hasOne(DeliveryAssignment::class)->latestOfMany();
    }

    public function commissionEntry(): HasOne
    {
        return $this->hasOne(CommissionEntry::class);
    }

    public function settlementItem(): HasOne
    {
        return $this->hasOne(SettlementItem::class);
    }

    public function reassignedFromStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'reassigned_from_store_id');
    }

    /**
     * Generate or return a 6-digit delivery confirmation OTP.
     */
    public function ensureDeliveryOtp(): string
    {
        if (empty($this->delivery_otp)) {
            $this->delivery_otp = sprintf('%06d', mt_rand(100000, 999999));
            $this->save();
        }

        return $this->delivery_otp;
    }

    /**
     * Calculate elapsed minutes since order package was placed.
     */
    public function minutesSincePlaced(): int
    {
        return (int) round(now()->diffInRealMinutes($this->created_at ?? now()));
    }

    /**
     * Check if this quick order has breached its SLA.
     */
    public function isSlaBreached(): bool
    {
        if ($this->fulfilment_type !== FulfilmentType::QUICK) {
            return false;
        }

        if (in_array($this->status, ['delivered', 'cancelled', 'rejected'], true)) {
            return false;
        }

        // 1. Placed orders waiting acceptance > store timeout
        if ($this->status === 'placed') {
            $timeout = $this->store ? $this->store->effectiveQuickTimeout() : 120;
            return $this->created_at ? $this->created_at->copy()->addSeconds($timeout)->isPast() : false;
        }

        // 2. Active orders past estimated delivery time
        if ($this->estimated_delivery_at && now()->isAfter($this->estimated_delivery_at)) {
            return true;
        }

        return false;
    }

    public function statusHistory(): MorphMany
    {
        return $this->morphMany(OrderStatusHistory::class, 'orderable');
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) ($this->subtotal + $this->delivery_fee);
    }

    public function getTotalAttribute(): float
    {
        return $this->total_amount;
    }

    public function scopeForSeller(Builder $query, int $sellerId): Builder
    {
        return $query->where('seller_id', $sellerId);
    }

    public function scopeQuickCommerce(Builder $query): Builder
    {
        return $query->where('fulfilment_type', FulfilmentType::QUICK);
    }

    public function scopeStandard(Builder $query): Builder
    {
        return $query->where('fulfilment_type', FulfilmentType::STANDARD);
    }
}
