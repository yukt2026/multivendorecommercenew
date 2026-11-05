<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SettlementStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'settlement_number',
        'period_start',
        'period_end',
        'gross',
        'commission',
        'tax_on_commission',
        'cod_collected',
        'adjustments',
        'net',
        'status',
        'hold_reason',
        'notes',
        'paid_at',
        'reference',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'gross' => 'decimal:2',
        'commission' => 'decimal:2',
        'tax_on_commission' => 'decimal:2',
        'cod_collected' => 'decimal:2',
        'adjustments' => 'decimal:2',
        'net' => 'decimal:2',
        'status' => SettlementStatus::class,
        'paid_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SettlementStatus::PENDING);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->whereIn('status', [SettlementStatus::PAID, SettlementStatus::SETTLED]);
    }
}
