<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'user_id',
        'session_id',
        'is_active',
        'coupon_code',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function activeItems(): HasMany
    {
        return $this->hasMany(CartItem::class)->where('is_saved_for_later', false);
    }

    public function savedItems(): HasMany
    {
        return $this->hasMany(CartItem::class)->where('is_saved_for_later', true);
    }

    public function totalAmount(): float
    {
        $items = $this->relationLoaded('activeItems')
            ? $this->activeItems
            : $this->activeItems()->with('variant')->get();

        return (float) $items->sum(function ($item) {
            $price = $item->price_snapshot ?? ($item->relationLoaded('variant') ? $item->variant?->selling_price : 0) ?? 0;
            return (float) ($price * $item->quantity);
        });
    }
}
