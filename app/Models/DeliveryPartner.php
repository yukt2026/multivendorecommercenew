<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliveryPartner extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'avatar',
        'vehicle',
        'vehicle_number',
        'is_active',
        'status',
        'approval_status',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'cash_in_hand_limit',
        'current_cash_in_hand',
        'emergency_contact_name',
        'emergency_contact_phone',
        'active_orders_count',
        'current_lat',
        'current_lng',
        'last_location_updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'active_orders_count' => 'integer',
        'current_lat' => 'float',
        'current_lng' => 'float',
        'last_location_updated_at' => 'datetime',
        'approved_at' => 'datetime',
        'cash_in_hand_limit' => 'decimal:2',
        'current_cash_in_hand' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DeliveryDocument::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(RiderEarning::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(RiderWallet::class);
    }

    public function locationsHistory(): HasMany
    {
        return $this->hasMany(RiderLocationHistory::class);
    }

    public function ensureWallet(): RiderWallet
    {
        return $this->wallet()->firstOrCreate([], [
            'balance' => 0.00,
            'pending_payout' => 0.00,
            'total_earned' => 0.00,
        ]);
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', 'approved');
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->whereIn('approval_status', ['pending', 'under_review']);
    }

    public function activeAssignment()
    {
        return $this->hasOne(DeliveryAssignment::class)
            ->whereIn('status', ['assigned', 'accepted', 'picked_up'])
            ->latestOfMany();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('status', 'available');
    }

    /**
     * Get the public URL for the rider's avatar / profile photo.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar) {
            if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
                return $this->avatar;
            }
            return asset('storage/' . $this->avatar);
        }
        return null;
    }

    /**
     * Compute approximate Haversine distance in km from rider's current location to a given point.
     */
    public function distanceTo(float $targetLat, float $targetLng): float
    {
        if ($this->current_lat === null || $this->current_lng === null) {
            return 999.0;
        }

        $earthRadius = 6371; // km
        $dLat = deg2rad($targetLat - (float) $this->current_lat);
        $dLng = deg2rad($targetLng - (float) $this->current_lng);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad((float) $this->current_lat)) * cos(deg2rad($targetLat)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
