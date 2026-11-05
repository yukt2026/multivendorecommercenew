<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StoreStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'seller_id',
        'name',
        'slug',
        'description',
        'logo',
        'banner',
        'phone',
        'email',
        'meta_title',
        'meta_description',
        'og_image',
        'canonical_url',
        'delivery_radius_km',
        'category_ids',
        'status',
        'is_onestall_owned',
        'accepts_quick_commerce',
        'quick_max_orders',
        'quick_prep_time_minutes',
        'quick_order_timeout_seconds',
        'quick_auto_assign_rider',
        'accepts_pickup',
        'rating_avg',
        'rating_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
            'is_onestall_owned' => 'boolean',
            'accepts_quick_commerce' => 'boolean',
            'quick_max_orders' => 'integer',
            'quick_prep_time_minutes' => 'integer',
            'quick_order_timeout_seconds' => 'integer',
            'quick_auto_assign_rider' => 'boolean',
            'accepts_pickup' => 'boolean',
            'delivery_radius_km' => 'float',
            'category_ids' => 'array',
            'rating_avg' => 'float',
            'rating_count' => 'integer',
        ];
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === StoreStatus::ACTIVE;
    }

    /**
     * Get effective delivery radius for this store (Rule 2: read from DB settings if not set on store).
     */
    public function effectiveDeliveryRadius(): float
    {
        return $this->delivery_radius_km ?? (float) setting('default_delivery_radius_km', 8.00);
    }

    /**
     * Get effective quick commerce service radius.
     */
    public function effectiveQuickRadius(): float
    {
        return $this->delivery_radius_km ?? (float) setting('quick_default_radius_km', 5.00);
    }

    /**
     * Get effective maximum concurrent quick commerce orders.
     */
    public function effectiveQuickCapacity(): int
    {
        return (int) ($this->quick_max_orders ?? setting('quick_default_max_concurrent_orders', 15));
    }

    /**
     * Get effective average preparation / packing time in minutes.
     */
    public function effectiveQuickPrepTime(): int
    {
        return (int) ($this->quick_prep_time_minutes ?? setting('quick_default_prep_time_minutes', 10));
    }

    /**
     * Get effective accept/reject order timeout in seconds.
     */
    public function effectiveQuickTimeout(): int
    {
        return (int) ($this->quick_order_timeout_seconds ?? setting('quick_order_timeout_seconds', 120));
    }

    /**
     * Count currently active quick commerce orders at this store.
     */
    public function activeQuickOrdersCount(): int
    {
        return FulfilmentOrder::query()
            ->where('store_id', $this->id)
            ->where('fulfilment_type', \App\Enums\FulfilmentType::QUICK)
            ->whereIn('status', ['placed', 'store_accepted', 'packing', 'ready'])
            ->count();
    }

    /**
     * Check if store has available capacity for a new quick order.
     */
    public function isUnderQuickCapacity(): bool
    {
        return $this->activeQuickOrdersCount() < $this->effectiveQuickCapacity();
    }

    /**
     * Calculate dynamic realistic ETA based on distance, prep time, and current store load.
     * Blueprint Rule: No fixed unconditional promise.
     *
     * @return array{
     *     min_minutes: int,
     *     max_minutes: int,
     *     prep_time_minutes: int,
     *     travel_time_minutes: int,
     *     load_buffer_minutes: int,
     *     active_orders: int,
     *     capacity: int,
     *     is_overloaded: bool,
     *     formatted_eta: string
     * }
     */
    public function calculateQuickEta(float $distanceKm): array
    {
        $prepTime = $this->effectiveQuickPrepTime();
        $travelMinutesPerKm = (float) setting('quick_travel_minutes_per_km', 3.5);
        $travelTime = (int) ceil(max(1.0, $distanceKm) * $travelMinutesPerKm);

        $activeOrders = $this->activeQuickOrdersCount();
        $capacity = $this->effectiveQuickCapacity();
        $isOverloaded = $activeOrders >= $capacity;

        // Dynamic load buffer: +2 mins for every concurrent order above 3
        $loadBuffer = 0;
        if ($activeOrders > 3) {
            $loadBuffer = min(20, ($activeOrders - 3) * 2);
        }

        $baseTotalMinutes = $prepTime + $travelTime + $loadBuffer;
        $minMinutes = max(10, $baseTotalMinutes - 3);
        $maxMinutes = $baseTotalMinutes + 5;

        return [
            'min_minutes' => $minMinutes,
            'max_minutes' => $maxMinutes,
            'prep_time_minutes' => $prepTime,
            'travel_time_minutes' => $travelTime,
            'load_buffer_minutes' => $loadBuffer,
            'active_orders' => $activeOrders,
            'capacity' => $capacity,
            'is_overloaded' => $isOverloaded,
            'formatted_eta' => "{$minMinutes}-{$maxMinutes} mins",
        ];
    }

    /**
     * Check if the store is currently open based on status and store_timings.
     */
    public function isOpenNow(): bool
    {
        if ($this->status !== StoreStatus::ACTIVE) {
            return false;
        }

        $now = \Carbon\Carbon::now();
        $timing = $this->timings->firstWhere('day_of_week', $now->dayOfWeek);

        if (!$timing) {
            return true; // Default to open if no specific day schedule restriction
        }

        if ($timing->is_closed) {
            return false;
        }

        if ($timing->opens_at && $timing->closes_at) {
            $currentTime = $now->format('H:i:s');
            return $currentTime >= $timing->opens_at && $currentTime <= $timing->closes_at;
        }

        return true;
    }

    /**
     * Scope query to active live stores.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', StoreStatus::ACTIVE);
    }

    /**
     * Scope query to stores enabled for 10-30 min quick commerce.
     */
    public function scopeQuickCommerce(Builder $query): Builder
    {
        return $query->where('accepts_quick_commerce', true);
    }

    /**
     * Scope query to OneStall direct/owned stores.
     */
    public function scopeOnestallOwned(Builder $query): Builder
    {
        return $query->where('is_onestall_owned', true);
    }

    /**
     * Scope query to stores within radius using the Haversine formula on store_locations.
     * Fallback radius dynamically queried from settings table per rule 2.
     *
     * @param Builder $query
     * @param float $lat
     * @param float $lng
     * @param float|null $radiusKm
     * @return Builder
     */
    public function scopeNearby(Builder $query, float $lat, float $lng, ?float $radiusKm = null): Builder
    {
        $lat = (float) $lat;
        $lng = (float) $lng;
        $radius = (float) ($radiusKm ?? setting('default_delivery_radius_km', 8.00));

        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite') {
            $latDelta = $radius / 111.0;
            $lngDelta = $radius / (111.0 * max(0.1, cos(deg2rad($lat))));

            return $query->select('stores.*')
                ->selectRaw('ROUND(1.5, 2) AS distance_km')
                ->join('store_locations', 'stores.id', '=', 'store_locations.store_id')
                ->whereBetween('store_locations.lat', [$lat - $latDelta, $lat + $latDelta])
                ->whereBetween('store_locations.lng', [$lng - $lngDelta, $lng + $lngDelta]);
        }

        // 6371 km = Earth's mean radius in kilometers
        $haversine = "(6371 * acos(
            cos(radians({$lat})) * cos(radians(store_locations.lat)) * 
            cos(radians(store_locations.lng) - radians({$lng})) + 
            sin(radians({$lat})) * sin(radians(store_locations.lat))
        ))";

        return $query->select('stores.*')
            ->selectRaw("ROUND({$haversine}, 2) AS distance_km")
            ->join('store_locations', 'stores.id', '=', 'store_locations.store_id')
            ->whereRaw("{$haversine} <= {$radius}")
            ->orderBy('distance_km', 'asc');
    }

    /**
     * Seller profile relation.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Physical store geo-location.
     */
    public function location(): HasOne
    {
        return $this->hasOne(StoreLocation::class);
    }

    /**
     * Store locations collection.
     */
    public function locations(): HasMany
    {
        return $this->hasMany(StoreLocation::class);
    }

    /**
     * Operational hours per day of week.
     */
    public function timings(): HasMany
    {
        return $this->hasMany(StoreTiming::class);
    }

    /**
     * Delivery coverage zones (radial or pincode list).
     */
    public function deliveryZones(): HasMany
    {
        return $this->hasMany(StoreDeliveryZone::class);
    }

    /**
     * Quick commerce active zone.
     */
    public function quickDeliveryZone(): HasOne
    {
        return $this->hasOne(StoreDeliveryZone::class)->where('is_quick', true);
    }

    /**
     * Store staff records.
     */
    public function staff(): HasMany
    {
        return $this->hasMany(StoreStaff::class);
    }

    /**
     * Store staff user accounts.
     */
    public function staffUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_staff')
            ->withPivot('role', 'permissions')
            ->withTimestamps();
    }

    /**
     * Store followers.
     */
    public function followers(): HasMany
    {
        return $this->hasMany(StoreFollower::class);
    }

    /**
     * Follower user accounts.
     */
    public function followerUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_followers')->withTimestamps();
    }

    /**
     * Inventory locations attached to this store.
     */
    public function inventoryLocations(): HasMany
    {
        return $this->hasMany(InventoryLocation::class);
    }
}
