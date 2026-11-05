<?php

declare(strict_types=1);

namespace App\Services\QuickCommerce;

use App\Enums\DeliveryAssignmentStatus;
use App\Enums\FulfilmentType;
use App\Enums\StoreStatus;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Models\FulfilmentOrder;
use App\Models\Product;
use App\Models\Store;
use App\Services\Order\OrderStateMachine;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuickCommerceService
{
    public function __construct(
        protected OrderStateMachine $stateMachine
    ) {}

    /**
     * Compute realistic dynamic ETA based on distance, store prep time, and current live load.
     * Blueprint Rule: Realistic ETA with no unconditional promise.
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
    public function computeEta(Store $store, float $distanceKm): array
    {
        return $store->calculateQuickEta($distanceKm);
    }

    /**
     * Discover nearby active stores offering Quick Commerce within their service radius.
     * Uses Haversine distance with bounding box indexing.
     *
     * @return \Illuminate\Support\Collection<int, Store>
     */
    public function getNearbyQuickStores(float $lat, float $lng, ?float $radiusKm = null)
    {
        $maxRadius = $radiusKm ?? (float) setting('quick_default_radius_km', 8.00);

        // Fetch active quick commerce stores with locations
        $stores = Store::query()
            ->with(['location', 'timings'])
            ->where('accepts_quick_commerce', true)
            ->where('status', StoreStatus::ACTIVE)
            ->get();

        $nearby = collect();

        foreach ($stores as $store) {
            $loc = $store->location;
            if (!$loc || $loc->lat === null || $loc->lng === null) {
                continue;
            }

            $dist = $this->calculateHaversineDistance($lat, $lng, (float) $loc->lat, (float) $loc->lng);
            $storeRadius = $store->effectiveQuickRadius();

            if ($dist <= $storeRadius && $dist <= $maxRadius) {
                $store->distance_km = round($dist, 2);
                $store->is_open_now = $store->isOpenNow();
                $store->eta = $store->calculateQuickEta($dist);
                $store->is_overloaded = $store->eta['is_overloaded'];
                $nearby->push($store);
            }
        }

        return $nearby->sortBy('distance_km')->values();
    }

    /**
     * Get Quick Commerce eligible products available from nearby open stores with dynamic ETA attached.
     */
    public function getQuickEligibleProducts(
        float $lat,
        float $lng,
        ?string $categorySlug = null,
        ?string $search = null,
        int $limit = 30
    ) {
        $nearbyStores = $this->getNearbyQuickStores($lat, $lng);
        if ($nearbyStores->isEmpty()) {
            return collect();
        }

        $storeIds = $nearbyStores->pluck('id')->all();

        $query = Product::query()
            ->with(['variants.inventories.location', 'seller', 'media', 'category'])
            ->where('is_quick_capable', true)
            ->where('status', \App\Enums\ProductStatus::ACTIVE);

        if ($categorySlug) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('tags', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        // Must have inventory at one of the nearby stores
        $query->whereHas('variants.inventories.location', function ($q) use ($storeIds) {
            $q->whereIn('store_id', $storeIds)
              ->where('quantity_on_hand', '>', 0);
        });

        $products = $query->limit($limit)->get();

        // Attach nearest store and ETA to each product
        return $products->map(function ($product) use ($nearbyStores) {
            $matchingStore = null;

            foreach ($nearbyStores as $store) {
                $hasStock = $product->variants->contains(function ($variant) use ($store) {
                    return $variant->inventories->contains(function ($inv) use ($store) {
                        return $inv->location && $inv->location->store_id === $store->id && ($inv->quantity_on_hand - $inv->quantity_reserved) > 0;
                    });
                });

                if ($hasStock) {
                    $matchingStore = $store;
                    break;
                }
            }

            $product->nearest_store = $matchingStore ?: $nearbyStores->first();
            $product->dynamic_eta = $product->nearest_store ? $product->nearest_store->eta['formatted_eta'] : '15-25 mins';
            $product->is_available_quick = $product->nearest_store && $product->nearest_store->is_open_now && !$product->nearest_store->is_overloaded;

            return $product;
        });
    }

    /**
     * Automatically assign the nearest available delivery partner to a ready quick fulfilment order.
     */
    public function autoAssignRider(FulfilmentOrder $fulfilmentOrder): ?DeliveryPartner
    {
        $store = $fulfilmentOrder->store;
        $storeLat = $store?->location?->lat ?? 12.9716;
        $storeLng = $store?->location?->lng ?? 77.5946;

        // Find available delivery partners
        $availablePartners = DeliveryPartner::query()
            ->where('is_active', true)
            ->where('status', 'available')
            ->get();

        if ($availablePartners->isEmpty()) {
            // Fallback: any active partner with fewer than 2 active assignments
            $availablePartners = DeliveryPartner::query()
                ->where('is_active', true)
                ->where('active_orders_count', '<', 2)
                ->get();
        }

        if ($availablePartners->isEmpty()) {
            Log::warning("QuickCommerce: No available delivery partners found for FO #{$fulfilmentOrder->fulfilment_order_number}");
            return null;
        }

        // Rank by distance to store
        $bestPartner = $availablePartners->sortBy(fn ($partner) => $partner->distanceTo((float) $storeLat, (float) $storeLng))->first();

        if ($bestPartner) {
            $this->assignPartnerToOrder($fulfilmentOrder, $bestPartner, 'Auto-assigned by Quick Commerce dispatch engine');
        }

        return $bestPartner;
    }

    /**
     * Manually or automatically attach a delivery partner to a fulfilment order.
     */
    public function assignPartnerToOrder(
        FulfilmentOrder $fo,
        DeliveryPartner $partner,
        string $note = 'Assigned delivery partner'
    ): DeliveryAssignment {
        return DB::transaction(function () use ($fo, $partner, $note) {
            // Ensure delivery OTP exists for this package
            $fo->ensureDeliveryOtp();

            $assignment = DeliveryAssignment::updateOrCreate(
                ['fulfilment_order_id' => $fo->id],
                [
                    'delivery_partner_id' => $partner->id,
                    'status' => DeliveryAssignmentStatus::ASSIGNED,
                    'assigned_at' => now(),
                    'notes' => $note,
                ]
            );

            // Update partner status and active count
            $partner->status = 'busy';
            $partner->active_orders_count = max(1, $partner->active_orders_count + 1);
            $partner->save();

            // Transition FO status if eligible
            if ($this->stateMachine->canTransitionFulfilment($fo, 'rider_assigned')) {
                $this->stateMachine->transitionFulfilmentOrder(
                    $fo,
                    'rider_assigned',
                    $note . " (#{$partner->name})",
                    auth()->id()
                );
            }

            Log::info("Rider #{$partner->id} ({$partner->name}) assigned to Quick Order #{$fo->fulfilment_order_number}");

            return $assignment;
        });
    }

    /**
     * Verify customer OTP and complete rider delivery.
     *
     * @return array{success: bool, message: string}
     */
    public function verifyDeliveryOtp(FulfilmentOrder $fo, string $enteredOtp): array
    {
        $cleanOtp = trim($enteredOtp);
        $expectedOtp = trim((string) $fo->delivery_otp);

        if (empty($expectedOtp) || $cleanOtp !== $expectedOtp) {
            return [
                'success' => false,
                'message' => 'Invalid delivery OTP. Please verify the code displayed on customer\'s order tracking screen.',
            ];
        }

        DB::transaction(function () use ($fo) {
            // Update DeliveryAssignment
            $assignment = $fo->deliveryAssignment;
            if ($assignment) {
                $assignment->status = DeliveryAssignmentStatus::DELIVERED;
                $assignment->otp_verified = true;
                $assignment->delivered_at = now();
                $assignment->save();

                // Free up the delivery partner
                if ($assignment->deliveryPartner) {
                    $partner = $assignment->deliveryPartner;
                    $partner->active_orders_count = max(0, $partner->active_orders_count - 1);
                    if ($partner->active_orders_count === 0) {
                        $partner->status = 'available';
                    }
                    $partner->save();
                }
            }

            // Transition FulfilmentOrder to delivered
            $this->stateMachine->transitionFulfilmentOrder(
                $fo,
                'delivered',
                'Package delivered successfully with Customer OTP verification.',
                auth()->id()
            );
        });

        return [
            'success' => true,
            'message' => 'Order marked delivered successfully!',
        ];
    }

    /**
     * Compute data for Admin Quick Commerce Realtime Ops Dashboard.
     */
    public function getOpsDashboardData(): array
    {
        // 1. Live Orders
        $activeOrders = FulfilmentOrder::query()
            ->with([
                'store.location',
                'order.customerAddress',
                'deliveryAssignment.deliveryPartner',
                'items',
            ])
            ->where('fulfilment_type', FulfilmentType::QUICK)
            ->whereNotIn('status', ['delivered', 'cancelled', 'rejected'])
            ->latest('id')
            ->get();

        // 2. SLA Breaches
        $breachedOrders = $activeOrders->filter(fn ($fo) => $fo->isSlaBreached());

        // 3. Store Capacities & Live Loads
        $stores = Store::query()
            ->with(['location'])
            ->where('accepts_quick_commerce', true)
            ->where('status', StoreStatus::ACTIVE)
            ->get()
            ->map(function ($store) {
                $activeCount = $store->activeQuickOrdersCount();
                $capacity = $store->effectiveQuickCapacity();
                $loadPct = $capacity > 0 ? round(($activeCount / $capacity) * 100) : 0;

                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'address' => $store->location?->address ?? 'N/A',
                    'active_orders' => $activeCount,
                    'capacity' => $capacity,
                    'load_percentage' => $loadPct,
                    'status' => $loadPct >= 90 ? 'overloaded' : ($loadPct >= 60 ? 'busy' : 'optimal'),
                    'lat' => $store->location?->lat,
                    'lng' => $store->location?->lng,
                ];
            });

        // 4. Riders Status
        $riders = DeliveryPartner::query()->get();
        $activeRidersCount = $riders->where('is_active', true)->count();
        $availableRidersCount = $riders->where('status', 'available')->count();
        $busyRidersCount = $riders->where('status', 'busy')->count();

        // 5. KPIs Today
        $todayDelivered = FulfilmentOrder::query()
            ->where('fulfilment_type', FulfilmentType::QUICK)
            ->where('status', 'delivered')
            ->whereDate('delivered_at', Carbon::today())
            ->count();

        $todayTotal = FulfilmentOrder::query()
            ->where('fulfilment_type', FulfilmentType::QUICK)
            ->whereDate('created_at', Carbon::today())
            ->count();

        return [
            'active_orders' => $activeOrders,
            'active_orders_count' => $activeOrders->count(),
            'breached_orders' => $breachedOrders->values(),
            'breached_count' => $breachedOrders->count(),
            'stores_load' => $stores,
            'riders_summary' => [
                'total' => $riders->count(),
                'active' => $activeRidersCount,
                'available' => $availableRidersCount,
                'busy' => $busyRidersCount,
            ],
            'today_total' => $todayTotal,
            'today_delivered' => $todayDelivered,
        ];
    }

    /**
     * Compute approximate Haversine distance in km between two lat/lng coordinates.
     */
    protected function calculateHaversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
