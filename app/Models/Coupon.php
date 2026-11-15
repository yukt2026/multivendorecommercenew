<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'max_discount',
        'min_order',
        'usage_limit',
        'per_user_limit',
        'seller_id',
        'applies_to',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'type' => CouponType::class,
        'value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_order' => 'decimal:2',
        'usage_limit' => 'integer',
        'per_user_limit' => 'integer',
        'applies_to' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'formatted_discount',
        'type_label',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            \Illuminate\Support\Facades\Cache::forget('storefront.public_coupons');
        });

        static::deleted(function () {
            \Illuminate\Support\Facades\Cache::forget('storefront.public_coupons');
        });
    }

    public function getFormattedDiscountAttribute(): string
    {
        if ($this->type === CouponType::PERCENT || $this->type?->value === 'percent') {
            return rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.') . '%';
        }

        return format_currency((float) $this->value);
    }

    public function getTypeLabelAttribute(): string
    {
        if ($this->type instanceof CouponType) {
            return $this->type->label();
        }

        return $this->type === 'percent' ? 'Percentage Discount' : 'Flat Amount Discount';
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
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

    public function calculateDiscount(float $subtotal): float
    {
        if ($subtotal < (float) $this->min_order) {
            return 0.00;
        }

        if ($this->type === CouponType::PERCENT) {
            $discount = round(($subtotal * (float) $this->value) / 100, 2);
            if ($this->max_discount !== null && $discount > (float) $this->max_discount) {
                $discount = (float) $this->max_discount;
            }
            return $discount;
        }

        return min((float) $this->value, $subtotal);
    }
}
