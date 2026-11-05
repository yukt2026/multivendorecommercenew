<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFraudAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'risk_score',
        'risk_level',
        'reasons',
        'signals',
        'decision',
        'reviewed_by',
        'reviewed_at',
        'notes',
    ];

    protected $casts = [
        'risk_score' => 'integer',
        'reasons' => 'array',
        'signals' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isHighRisk(): bool
    {
        return $this->risk_level === 'high' || $this->risk_score >= 70;
    }
}
