<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'store_id',
        'file_name',
        'file_path',
        'type',
        'status',
        'total_rows',
        'imported_rows',
        'failed_rows',
        'column_mapping',
        'errors',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'imported_rows' => 'integer',
        'failed_rows' => 'integer',
        'column_mapping' => 'array',
        'errors' => 'array',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getProgressPercentAttribute(): int
    {
        if ($this->total_rows === 0) {
            return $this->status === 'completed' ? 100 : 0;
        }

        return (int) min(100, round((($this->imported_rows + $this->failed_rows) / $this->total_rows) * 100));
    }
}
