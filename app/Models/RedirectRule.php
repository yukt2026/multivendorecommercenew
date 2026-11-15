<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RedirectRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_path',
        'target_path',
        'status_code',
        'is_active',
        'hit_count',
        'last_accessed_at',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'is_active' => 'boolean',
        'hit_count' => 'integer',
        'last_accessed_at' => 'datetime',
    ];

    /**
     * Clear cached lookup mapping whenever rules change.
     */
    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('redirect_rules.map');
        });

        static::deleted(function () {
            Cache::forget('redirect_rules.map');
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Normalize paths so '/old-page/' matches '/old-page'.
     */
    public static function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?? $path;
        $path = '/' . ltrim(rtrim($path, '/'), '/');
        return $path === '' ? '/' : $path;
    }

    /**
     * Record a hit on this redirect rule silently without triggering saved events.
     */
    public function recordHit(): void
    {
        static::withoutEvents(function () {
            $this->increment('hit_count');
            $this->update(['last_accessed_at' => now()]);
        });
    }
}
