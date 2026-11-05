<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreTiming extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'day_of_week',
        'opens_at',
        'closes_at',
        'is_closed',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_closed' => 'boolean',
        ];
    }

    /**
     * Check if the store is currently open based on local time.
     */
    public function isOpenAt(string $time): bool
    {
        if ($this->is_closed || !$this->opens_at || !$this->closes_at) {
            return false;
        }

        return $time >= $this->opens_at && $time <= $this->closes_at;
    }

    /**
     * Store relation.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
