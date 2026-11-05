<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QCStatus;
use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturn extends Model
{
    use HasFactory;

    protected $table = 'returns';

    protected $fillable = [
        'return_number',
        'type',
        'order_item_id',
        'reason',
        'customer_notes',
        'photos',
        'status',
        'pickup_shipment_id',
        'pickup_waybill',
        'qc_status',
        'qc_notes',
        'is_resellable',
        'refund_id',
        'refund_mode',
        'refund_amount',
        'rejection_reason',
        'seller_notes',
        'admin_notes',
        'approved_at',
        'picked_up_at',
        'received_at',
        'qc_completed_at',
        'refunded_at',
    ];

    protected $casts = [
        'status' => ReturnStatus::class,
        'qc_status' => QCStatus::class,
        'photos' => 'array',
        'is_resellable' => 'boolean',
        'refund_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'received_at' => 'datetime',
        'qc_completed_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function pickupShipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'pickup_shipment_id');
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    /**
     * Get associated fulfilment order.
     */
    public function getFulfilmentOrderAttribute(): ?FulfilmentOrder
    {
        return $this->orderItem?->fulfilmentOrder;
    }

    /**
     * Get parent order.
     */
    public function getOrderAttribute(): ?Order
    {
        return $this->orderItem?->fulfilmentOrder?->order;
    }

    /**
     * Get merchant seller.
     */
    public function getSellerAttribute(): ?Seller
    {
        return $this->orderItem?->fulfilmentOrder?->seller;
    }

    /**
     * Scope for a specific seller.
     */
    public function scopeForSeller(Builder $query, int $sellerId): Builder
    {
        return $query->whereHas('orderItem.fulfilmentOrder', function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId);
        });
    }

    /**
     * Scope for a specific customer user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->whereHas('orderItem.fulfilmentOrder.order.customer', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }
}
