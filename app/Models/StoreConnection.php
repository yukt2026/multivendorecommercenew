<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StoreConnectionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StoreConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'type',
        'name',
        'status',
        'api_key',
        'api_secret_hash',
        'config',
        'webhook_url',
        'webhook_secret',
        'last_synced_at',
    ];

    protected $casts = [
        'type' => StoreConnectionType::class,
        'config' => 'encrypted:array',
        'webhook_secret' => 'encrypted',
        'last_synced_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class, 'connection_id')->latest();
    }

    public function woocommerce(): HasOne
    {
        return $this->hasOne(WoocommerceConnection::class, 'store_connection_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function verifyApiSecret(string $plainSecret): bool
    {
        if (empty($this->api_secret_hash)) {
            return false;
        }

        return hash_equals($this->api_secret_hash, hash('sha256', $plainSecret));
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->webhook_secret)) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $this->webhook_secret);
        return hash_equals($expected, $signature);
    }

    public function logSync(string $entity, string $direction, string $status, ?string $message = null, ?array $payload = null): SyncLog
    {
        return $this->syncLogs()->create([
            'entity' => $entity,
            'direction' => $direction,
            'status' => $status,
            'message' => $message,
            'payload' => $payload,
        ]);
    }
}
