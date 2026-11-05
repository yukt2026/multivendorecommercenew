<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionRuleScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'scope',
        'category_id',
        'seller_id',
        'percent',
        'fixed_fee',
        'starts_at',
        'ends_at',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'scope' => CommissionRuleScope::class,
        'percent' => 'decimal:2',
        'fixed_fee' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class, 'rule_id');
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

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->where('scope', CommissionRuleScope::GLOBAL);
    }

    public function scopeForCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('scope', CommissionRuleScope::CATEGORY)->where('category_id', $categoryId);
    }

    public function scopeForSeller(Builder $query, int $sellerId): Builder
    {
        return $query->where('scope', CommissionRuleScope::SELLER)->where('seller_id', $sellerId);
    }
}
