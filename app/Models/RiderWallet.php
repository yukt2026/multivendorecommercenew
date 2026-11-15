<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiderWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_partner_id',
        'balance',
        'pending_payout',
        'total_earned',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'pending_payout' => 'decimal:2',
        'total_earned' => 'decimal:2',
    ];

    public function deliveryPartner(): BelongsTo
    {
        return $this->belongsTo(DeliveryPartner::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(RiderWalletTransaction::class);
    }
}
