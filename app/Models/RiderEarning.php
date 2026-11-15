<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderEarning extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_partner_id',
        'delivery_assignment_id',
        'fulfilment_order_id',
        'base_payout',
        'distance_km',
        'distance_payout',
        'surge_bonus',
        'customer_tip',
        'total_payout',
        'status',
        'settled_at',
    ];

    protected $casts = [
        'base_payout' => 'decimal:2',
        'distance_km' => 'float',
        'distance_payout' => 'decimal:2',
        'surge_bonus' => 'decimal:2',
        'customer_tip' => 'decimal:2',
        'total_payout' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    public function deliveryPartner(): BelongsTo
    {
        return $this->belongsTo(DeliveryPartner::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(DeliveryAssignment::class, 'delivery_assignment_id');
    }

    public function fulfilmentOrder(): BelongsTo
    {
        return $this->belongsTo(FulfilmentOrder::class);
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->total_payout;
    }
}
