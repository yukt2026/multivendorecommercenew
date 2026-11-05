<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NdrCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'reason',
        'attempts',
        'status',
        'customer_action',
        'action_notes',
    ];

    protected $casts = [
        'attempts' => 'integer',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
