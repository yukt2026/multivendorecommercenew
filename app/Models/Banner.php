<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'position',
        'image',
        'link',
        'sort',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'sort' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            \Illuminate\Support\Facades\Cache::forget('storefront.hero_banners');
            \Illuminate\Support\Facades\Cache::forget('storefront.promo_banners');
        });

        static::deleted(function () {
            \Illuminate\Support\Facades\Cache::forget('storefront.hero_banners');
            \Illuminate\Support\Facades\Cache::forget('storefront.promo_banners');
        });
    }

    protected $appends = [
        'image_url',
        'position_label',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        $clean = ltrim($this->image, '/');
        $relativePath = str_starts_with($clean, 'storage/') ? '/' . $clean : '/storage/' . $clean;
        $targetFile = public_path(ltrim($relativePath, '/'));
        $version = file_exists($targetFile) ? filemtime($targetFile) : time();

        return $relativePath . '?v=' . $version;
    }

    public function getPositionLabelAttribute(): string
    {
        return match ($this->position) {
            'home_hero', 'hero', 'hero_carousel', 'top_slider' => 'Hero Carousel',
            'home_sub', 'deal_banner', 'homepage_strip', 'promo_strip' => 'Deal & Promo Strip',
            'category_strip', 'category_top' => 'Category Strip',
            'sidebar' => 'Sidebar Widget',
            default => ucwords(str_replace('_', ' ', (string) $this->position)),
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

    public function scopeForPosition(Builder $query, string $position): Builder
    {
        if (in_array($position, ['hero', 'home_hero', 'hero_carousel', 'top_slider'], true)) {
            return $query->whereIn('position', ['home_hero', 'hero', 'hero_carousel', 'top_slider'])->orderBy('sort');
        }
        if (in_array($position, ['deal_banner', 'home_sub', 'homepage_strip', 'promo_strip'], true)) {
            return $query->whereIn('position', ['home_sub', 'deal_banner', 'homepage_strip', 'promo_strip'])->orderBy('sort');
        }
        if (in_array($position, ['category_strip', 'category_top'], true)) {
            return $query->whereIn('position', ['category_strip', 'category_top'])->orderBy('sort');
        }

        return $query->where('position', $position)->orderBy('sort');
    }
}
