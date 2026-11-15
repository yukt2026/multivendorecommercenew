<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'seller_id',
        'brand_id',
        'category_id',
        'title',
        'slug',
        'short_desc',
        'description',
        'status',
        'rejection_reason',
        'product_type',
        'hsn_code',
        'tax_percent',
        'return_window_days',
        'is_returnable',
        'is_cod_allowed',
        'is_quick_capable',
        'meta_title',
        'meta_description',
        'og_image',
        'canonical_url',
        'noindex',
    ];

    protected $casts = [
        'status' => ProductStatus::class,
        'product_type' => ProductType::class,
        'tax_percent' => 'decimal:2',
        'return_window_days' => 'integer',
        'is_returnable' => 'boolean',
        'is_cod_allowed' => 'boolean',
        'is_quick_capable' => 'boolean',
        'noindex' => 'boolean',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Alias for category to support both singular and plural eager loads.
     */
    public function categories(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_id');
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order');
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class)->where('is_approved', true);
    }

    public function getPrimaryImageUrlAttribute(): string
    {
        return $this->primaryImageUrl();
    }

    public function primaryImageUrl(): string
    {
        $item = $this->relationLoaded('media') ? $this->media->first() : $this->media()->orderBy('sort_order')->first();
        if (!$item && $this->relationLoaded('variants') && $this->variants->isNotEmpty()) {
            $item = $this->variants->flatMap->media->first();
        }
        if ($item && $item->path) {
            $url = image_url($item->path);
            if ($url) {
                $cleanPath = ltrim($item->path, '/');
                $targetFile = public_path('storage/' . $cleanPath);
                $version = file_exists($targetFile) ? filemtime($targetFile) : 'v2';
                $sep = str_contains($url, '?') ? '&' : '?';
                return $url . $sep . 'v=' . $version;
            }
        }

        return "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 300' width='300' height='300'><rect width='300' height='300' rx='28' fill='%23f8fafc'/><circle cx='150' cy='135' r='52' fill='%23eef2f6'/><path d='M132 118 h36 l6 38 h-48 z' fill='%236366f1' fill-opacity='0.15' stroke='%236366f1' stroke-width='3' stroke-linejoin='round'/><path d='M141 118 v-7 a9 9 0 0 1 18 0 v7' fill='none' stroke='%236366f1' stroke-width='3' stroke-linecap='round'/><circle cx='150' cy='137' r='3' fill='%236366f1'/><text x='150' y='232' font-family='sans-serif' font-size='12' font-weight='800' fill='%2394a3b8' letter-spacing='2' text-anchor='middle'>ONESTALL</text></svg>";
    }

    public function getEffectivePriceAttribute(): float
    {
        if ($this->relationLoaded('defaultVariant') && $this->defaultVariant) {
            return (float) $this->defaultVariant->selling_price;
        }

        if ($this->relationLoaded('variants') && $this->variants->isNotEmpty()) {
            return (float) ($this->variants->firstWhere('is_default', true)?->selling_price ?? $this->variants->min('selling_price') ?? 0);
        }

        return (float) ($this->defaultVariant?->selling_price ?? 0);
    }

    public function getEffectiveMrpAttribute(): float
    {
        if ($this->relationLoaded('defaultVariant') && $this->defaultVariant) {
            return (float) $this->defaultVariant->mrp;
        }

        if ($this->relationLoaded('variants') && $this->variants->isNotEmpty()) {
            return (float) ($this->variants->firstWhere('is_default', true)?->mrp ?? $this->variants->max('mrp') ?? 0);
        }

        return (float) ($this->defaultVariant?->mrp ?? 0);
    }

    public function getDiscountPercentAttribute(): int
    {
        $mrp = $this->effective_mrp;
        $price = $this->effective_price;
        if ($mrp > 0 && $price < $mrp) {
            return (int) round((($mrp - $price) / $mrp) * 100);
        }
        return 0;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('products.status', ProductStatus::ACTIVE);
    }

    public function scopeQuickCapable(Builder $query): Builder
    {
        return $query->where('is_quick_capable', true);
    }
}
