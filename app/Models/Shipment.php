<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'fulfilment_order_id',
        'shipment_number',
        'provider',
        'awb',
        'courier',
        'service',
        'status',
        'pickup_status',
        'label_url',
        'cod_amount',
        'weight',
        'charges',
    ];

    protected $casts = [
        'status' => ShipmentStatus::class,
        'cod_amount' => 'decimal:2',
        'weight' => 'decimal:2',
        'charges' => 'decimal:2',
    ];

    public function fulfilmentOrder(): BelongsTo
    {
        return $this->belongsTo(FulfilmentOrder::class);
    }

    public function tracking(): HasMany
    {
        return $this->hasMany(ShipmentTracking::class)->orderBy('event_time', 'desc');
    }

    public function deliveryAssignment(): HasOne
    {
        return $this->hasOne(DeliveryAssignment::class);
    }

    public function ndrCases(): HasMany
    {
        return $this->hasMany(NdrCase::class);
    }

    public function rtoCases(): HasMany
    {
        return $this->hasMany(RtoCase::class);
    }

    public function codRemittance(): HasOne
    {
        return $this->hasOne(CodRemittance::class);
    }

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->whereIn('status', [ShipmentStatus::IN_TRANSIT, ShipmentStatus::OUT_FOR_DELIVERY]);
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', ShipmentStatus::DELIVERED);
    }
}
