<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CommissionEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'fulfilment_order_id',
        'rule_id',
        'base_amount',
        'commission',
        'tax_on_commission',
        'gateway_fee',
        'shipping_adjustment',
        'net_payable',
        'entry_type',
        'notes',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'commission' => 'decimal:2',
        'tax_on_commission' => 'decimal:2',
        'gateway_fee' => 'decimal:2',
        'shipping_adjustment' => 'decimal:2',
        'net_payable' => 'decimal:2',
    ];

    public function fulfilmentOrder(): BelongsTo
    {
        return $this->belongsTo(FulfilmentOrder::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'rule_id');
    }

    public function settlementItem(): HasOne
    {
        return $this->hasOne(SettlementItem::class);
    }
}
