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
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
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

    public function primaryImageUrl(): string
    {
        $item = $this->media->first();
        if ($item && $item->path) {
            if (str_starts_with($item->path, 'http://') || str_starts_with($item->path, 'https://')) {
                return $item->path;
            }
            return asset('storage/' . $item->path);
        }

        $title = rawurlencode(substr($this->title ?? 'OneStall', 0, 22));
        return "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 300' width='300' height='300'><rect width='300' height='300' rx='20' fill='%23f1f5f9'/><circle cx='150' cy='130' r='55' fill='%23e2e8f0'/><text x='150' y='145' font-size='38' text-anchor='middle'>🛍️</text><text x='150' y='230' font-family='sans-serif' font-size='13' font-weight='bold' fill='%23475569' text-anchor='middle'>{$title}</text></svg>";
    }

    public function getEffectivePriceAttribute(): float
    {
        return (float) ($this->defaultVariant?->selling_price ?? $this->variants->min('selling_price') ?? 0);
    }

    public function getEffectiveMrpAttribute(): float
    {
        return (float) ($this->defaultVariant?->mrp ?? $this->variants->max('mrp') ?? 0);
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
