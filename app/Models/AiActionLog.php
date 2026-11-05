<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiActionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'feature',
        'action',
        'user_id',
        'seller_id',
        'prompt_summary',
        'input_payload',
        'output_payload',
        'model_or_driver',
        'tokens_used',
        'latency_ms',
        'status',
        'error_message',
    ];

    protected $casts = [
        'input_payload' => 'array',
        'output_payload' => 'array',
        'tokens_used' => 'integer',
        'latency_ms' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
