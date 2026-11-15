<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderWalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'rider_wallet_id',
        'type',
        'amount',
        'balance_after',
        'category',
        'reference_type',
        'reference_id',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(RiderWallet::class, 'rider_wallet_id');
    }
}
