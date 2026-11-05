<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SellerDocumentStatus;
use App\Enums\SellerDocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'type',
        'file_path',
        'status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'type' => SellerDocumentType::class,
            'status' => SellerDocumentStatus::class,
        ];
    }

    /**
     * Scope query to approved documents.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', SellerDocumentStatus::APPROVED);
    }

    /**
     * Scope query to pending documents.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SellerDocumentStatus::PENDING);
    }

    /**
     * Seller profile relation.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
