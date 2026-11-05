<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CODRemittanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodRemittance extends Model
{
    use HasFactory;

    protected $table = 'cod_remittances';

    protected $fillable = [
        'shipment_id',
        'seller_id',
        'amount',
        'status',
        'collected_at',
        'remitted_at',
        'reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => CODRemittanceStatus::class,
        'collected_at' => 'datetime',
        'remitted_at' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
