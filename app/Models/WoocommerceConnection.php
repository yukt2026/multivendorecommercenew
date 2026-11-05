<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WoocommerceConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_connection_id',
        'store_id',
        'store_url',
        'consumer_key',
        'consumer_secret',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'consumer_secret' => 'encrypted',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function storeConnection(): BelongsTo
    {
        return $this->belongsTo(StoreConnection::class, 'store_connection_id');
    }
}
