<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SellerStatus;
use App\Enums\SellerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seller extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'business_name',
        'legal_name',
        'seller_type',
        'category_focus',
        'pan',
        'gstin',
        'status',
        'onboarding_step',
        'rejection_reason',
        'suspension_reason',
        'commission_override',
        'submitted_at',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'seller_type' => SellerType::class,
            'status' => SellerStatus::class,
            'pan' => 'encrypted',
            'gstin' => 'encrypted',
            'onboarding_step' => 'integer',
            'commission_override' => 'float',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function getCompanyNameAttribute(): string
    {
        return $this->business_name ?? '';
    }

    public function isEditingLocked(): bool
    {
        return in_array($this->status, [SellerStatus::KYC_SUBMITTED, SellerStatus::APPROVED]);
    }

    public function isKycSubmitted(): bool
    {
        return $this->status === SellerStatus::KYC_SUBMITTED;
    }

    public function isApproved(): bool
    {
        return $this->status === SellerStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === SellerStatus::REJECTED;
    }

    /**
     * Scope query to approved sellers.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', SellerStatus::APPROVED);
    }

    /**
     * Scope query to pending KYC sellers.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [SellerStatus::PENDING, SellerStatus::KYC_SUBMITTED]);
    }

    /**
     * Get effective commission rate for this seller.
     * Uses seller override if set, otherwise falls back to platform settings.
     */
    public function effectiveCommissionRate(): float
    {
        return $this->commission_override ?? (float) setting('default_commission_pct', 10.00);
    }

    public function getCommissionOverridePctAttribute(): ?float
    {
        return $this->commission_override !== null ? (float) $this->commission_override : null;
    }

    public function setCommissionOverridePctAttribute($value): void
    {
        $this->attributes['commission_override'] = $value;
    }

    /**
     * User account relation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Admin user who approved the seller.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Stores owned by the seller.
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /**
     * Uploaded KYC verification documents.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(SellerDocument::class);
    }

    /**
     * Payout bank accounts.
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(SellerBankAccount::class);
    }

    /**
     * Primary payout bank account.
     */
    public function primaryBankAccount(): HasOne
    {
        return $this->hasOne(SellerBankAccount::class)->where('is_primary', true);
    }

    /**
     * Products catalog owned by the seller.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Brands owned or registered by the seller.
     */
    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    /**
     * Inventory fulfillment locations owned by the seller.
     */
    public function inventoryLocations(): HasMany
    {
        return $this->hasMany(InventoryLocation::class);
    }

    /**
     * Fulfilment orders allocated to this seller.
     */
    public function fulfilmentOrders(): HasMany
    {
        return $this->hasMany(FulfilmentOrder::class);
    }

    /**
     * Payout settlements for this seller.
     */
    public function settlements(): HasMany
    {
        return $this->hasMany(SellerSettlement::class);
    }

    /**
     * COD cash remittances for this seller.
     */
    public function codRemittances(): HasMany
    {
        return $this->hasMany(CodRemittance::class);
    }
}
