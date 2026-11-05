<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RtoCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'reason',
        'status',
        'initiated_at',
        'delivered_at',
    ];

    protected $casts = [
        'initiated_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
