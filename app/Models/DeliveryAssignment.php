<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryAssignmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliveryAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'fulfilment_order_id',
        'shipment_id',
        'delivery_partner_id',
        'status',
        'otp_verified',
        'pickup_otp',
        'pickup_otp_verified',
        'notes',
        'rejection_reason',
        'rejected_at',
        'assigned_at',
        'accepted_at',
        'picked_up_at',
        'delivered_at',
        'failed_at',
        'failure_reason',
        'failure_notes',
        'proof_of_delivery_image',
        'cod_amount_collected',
        'cod_collected_at',
        'rider_earnings_amount',
    ];

    protected $casts = [
        'status' => DeliveryAssignmentStatus::class,
        'otp_verified' => 'boolean',
        'pickup_otp_verified' => 'boolean',
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'rejected_at' => 'datetime',
        'failed_at' => 'datetime',
        'cod_collected_at' => 'datetime',
        'cod_amount_collected' => 'decimal:2',
        'rider_earnings_amount' => 'decimal:2',
    ];

    public function fulfilmentOrder(): BelongsTo
    {
        return $this->belongsTo(FulfilmentOrder::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function deliveryPartner(): BelongsTo
    {
        return $this->belongsTo(DeliveryPartner::class);
    }

    public function earnings(): HasOne
    {
        return $this->hasOne(RiderEarning::class, 'delivery_assignment_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(RiderLocationHistory::class, 'delivery_assignment_id');
    }
}
