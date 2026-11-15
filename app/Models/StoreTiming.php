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
        'open_time',
        'close_time',
        'is_closed',
    ];

    public function setOpenTimeAttribute(?string $value): void
    {
        $this->attributes['opens_at'] = $value;
    }

    public function setCloseTimeAttribute(?string $value): void
    {
        $this->attributes['closes_at'] = $value;
    }

    public function getOpenTimeAttribute(): ?string
    {
        return $this->opens_at;
    }

    public function getCloseTimeAttribute(): ?string
    {
        return $this->closes_at;
    }

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_closed' => 'boolean',
        ];
    }

    /**
     * Check if this day's timing is set to 24 hours round-the-clock.
     */
    public function is24Hours(): bool
    {
        if ($this->is_closed || !$this->opens_at || !$this->closes_at) {
            return false;
        }

        $open = substr($this->opens_at, 0, 5);
        $close = substr($this->closes_at, 0, 5);

        return ($open === '00:00' && in_array($close, ['00:00', '23:59', '23:59:59'])) || ($open === $close);
    }

    /**
     * Check if the store is currently open based on local time.
     * Supports standard same-day hours, overnight cross-midnight hours, and 24x7.
     */
    public function isOpenAt(string $time): bool
    {
        if ($this->is_closed || !$this->opens_at || !$this->closes_at) {
            return false;
        }

        if ($this->is24Hours()) {
            return true;
        }

        $open = substr($this->opens_at, 0, 5);
        $close = substr($this->closes_at, 0, 5);
        $check = substr($time, 0, 5);

        // Overnight hours (e.g. 20:00 to 04:00)
        if ($open > $close) {
            return $check >= $open || $check <= $close;
        }

        // Standard daytime hours (e.g. 08:00 to 22:00)
        return $check >= $open && $check <= $close;
    }

    /**
     * Store relation.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
