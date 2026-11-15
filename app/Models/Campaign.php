<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            \Illuminate\Support\Facades\Cache::forget('storefront.active_campaigns');
        });

        static::deleted(function () {
            \Illuminate\Support\Facades\Cache::forget('storefront.active_campaigns');
        });
    }

    protected $appends = [
        'type_label',
    ];

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'flash_sale' => 'Flash Sale',
            'festive_season', 'festive_sale', 'festive' => 'Festive Season',
            'featured_stores' => 'Featured Stores',
            'clearance' => 'Clearance',
            'seasonal' => 'Seasonal Sale',
            default => ucwords(str_replace('_', ' ', (string) $this->type)),
        };
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }
}
