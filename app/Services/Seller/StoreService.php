<?php

declare(strict_types=1);

namespace App\Services\Seller;

use App\Enums\DeliveryZoneType;
use App\Enums\InventoryLocationType;
use App\Enums\ProductStatus;
use App\Enums\ReviewStatus;
use App\Enums\SellerStatus;
use App\Enums\StoreStaffRole;
use App\Enums\StoreStatus;
use App\Http\Requests\Seller\UpdateStoreRequest;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\FulfilmentOrder;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreDeliveryZone;
use App\Models\StoreLocation;
use App\Models\StoreStaff;
use App\Models\StoreTiming;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreService
{
    /**
     * Get or initialize seller's active store profile.
     */
    public function getSellerStore(Seller $seller, ?int $storeId = null): Store
    {
        $store = $storeId ? $seller->stores()->find($storeId) : $seller->stores()->first();

        if (!$store) {
            $baseSlug = Str::slug($seller->business_name ?: 'store');
            $slug = $baseSlug . '-' . $seller->id;

            $store = $seller->stores()->create([
                'name' => $seller->business_name ?: 'Default Storefront',
                'slug' => $slug,
                'description' => 'Official OneStall storefront for ' . ($seller->business_name ?: 'merchant partner'),
                'status' => StoreStatus::ACTIVE,
                'accepts_quick_commerce' => true,
                'accepts_pickup' => true,
                'delivery_radius_km' => (float) setting('default_delivery_radius_km', 8.00),
            ]);
        }

        $store->loadMissing(['location', 'timings', 'deliveryZones']);

        return $store;
    }

    /**
     * Live availability and reserved words check for storefront slug.
     *
     * @return array{available: bool, message: string}
     */
    public function checkSlugAvailability(string $slug, ?int $ignoreStoreId = null): array
    {
        $cleanSlug = strtolower(trim($slug));

        if (empty($cleanSlug)) {
            return ['available' => false, 'message' => 'Slug cannot be blank.'];
        }

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $cleanSlug)) {
            return ['available' => false, 'message' => 'Slug must only contain lowercase letters, numbers, and hyphens.'];
        }

        if (in_array($cleanSlug, UpdateStoreRequest::RESERVED_SLUGS, true)) {
            return ['available' => false, 'message' => "Slug '{$cleanSlug}' is a reserved platform keyword."];
        }

        $query = Store::where('slug', $cleanSlug);
        if ($ignoreStoreId) {
            $query->where('id', '!=', $ignoreStoreId);
        }

        if ($query->exists()) {
            return ['available' => false, 'message' => "Store URL '{$cleanSlug}' is already claimed."];
        }

        return ['available' => true, 'message' => "Store URL 'onestall.in/store/{$cleanSlug}' is available!"];
    }

    /**
     * Update complete store attributes, timings, location, and delivery zones.
     *
     * @param array<string, mixed> $data
     */
    public function updateStore(Store $store, array $data, ?UploadedFile $logo = null, ?UploadedFile $banner = null): Store
    {
        return DB::transaction(function () use ($store, $data, $logo, $banner) {
            // 1. Handle Logo and Banner uploads
            if ($logo) {
                $logoExt = $logo->getClientOriginalExtension() ?: 'png';
                $logoName = 'logo-' . $store->id . '-' . time() . '.' . $logoExt;
                $logoPath = $logo->storeAs('stores/logos', $logoName, 'public');
                $data['logo'] = Storage::url($logoPath);
            }

            if ($banner) {
                $bannerExt = $banner->getClientOriginalExtension() ?: 'jpg';
                $bannerName = 'banner-' . $store->id . '-' . time() . '.' . $bannerExt;
                $bannerPath = $banner->storeAs('stores/banners', $bannerName, 'public');
                $data['banner'] = Storage::url($bannerPath);
            }

            // 2. Status determination
            $newStatus = !empty($data['is_active']) ? StoreStatus::ACTIVE : StoreStatus::INACTIVE;

            $storeUpdateData = [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'delivery_radius_km' => !empty($data['delivery_radius_km']) ? (float) $data['delivery_radius_km'] : null,
                'category_ids' => $data['category_ids'] ?? [],
                'accepts_quick_commerce' => (bool) ($data['accepts_quick_commerce'] ?? false),
                'accepts_pickup' => (bool) ($data['accepts_pickup'] ?? false),
                'status' => $newStatus,
            ];

            if (isset($data['logo'])) {
                $storeUpdateData['logo'] = $data['logo'];
            }

            if (isset($data['banner'])) {
                $storeUpdateData['banner'] = $data['banner'];
            }

            $store->update($storeUpdateData);

            // 3. Location update
            StoreLocation::updateOrCreate(
                ['store_id' => $store->id],
                [
                    'address' => $data['address'],
                    'city' => $data['city'],
                    'state' => $data['state'],
                    'pincode' => $data['pincode'],
                    'lat' => $data['lat'],
                    'lng' => $data['lng'],
                ]
            );

            // 4. Timings schedule update for all 7 days (0: Sun to 6: Sat)
            if (isset($data['timings']) && is_array($data['timings'])) {
                foreach ($data['timings'] as $t) {
                    if (isset($t['day_of_week'])) {
                        $day = (int) $t['day_of_week'];
                        $isClosed = !empty($t['is_closed']);

                        StoreTiming::updateOrCreate(
                            [
                                'store_id' => $store->id,
                                'day_of_week' => $day,
                            ],
                            [
                                'opens_at' => $isClosed ? null : ($t['opens_at'] ?? '08:00'),
                                'closes_at' => $isClosed ? null : ($t['closes_at'] ?? '22:00'),
                                'is_closed' => $isClosed,
                            ]
                        );
                    }
                }
            }

            // 5. Delivery Zone update
            $deliveryFee = isset($data['delivery_fee']) ? (float) $data['delivery_fee'] : (float) setting('default_delivery_fee', 30.00);
            $minOrder = isset($data['min_order_value']) ? (float) $data['min_order_value'] : (float) setting('min_order_value', 99.00);
            $radius = $store->effectiveDeliveryRadius();

            StoreDeliveryZone::updateOrCreate(
                [
                    'store_id' => $store->id,
                    'is_quick' => true,
                ],
                [
                    'type' => DeliveryZoneType::RADIUS,
                    'radius_km' => $radius,
                    'min_order_value' => $minOrder,
                    'delivery_fee' => $deliveryFee,
                ]
            );

            return $store->fresh(['location', 'timings', 'deliveryZones']);
        });
    }

    /**
     * Gather complete public storefront payload with products, coupons, and SEO Schema.
     *
     * @return array<string, mixed>
     */
    public function getPublicStorefrontData(Store $store, ?string $categorySlug = null, ?string $sort = null): array
    {
        $seller = $store->seller;

        // Base products query for this merchant
        $productsBase = Product::query()
            ->with(['variants', 'media', 'brand', 'category'])
            ->where('seller_id', $seller->id)
            ->where('status', ProductStatus::ACTIVE);

        // Featured products (quick-capable items or top sellers)
        $featuredProducts = (clone $productsBase)
            ->where('is_quick_capable', true)
            ->limit(8)
            ->get();

        // New arrivals
        $newArrivals = (clone $productsBase)
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get();

        // All products filtered by category and sort
        $allProductsQuery = clone $productsBase;

        if ($categorySlug) {
            $allProductsQuery->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        match ($sort) {
            'price_asc' => $allProductsQuery->orderBy(
                ProductVariant::select('selling_price')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->orderBy('selling_price', 'asc')
                    ->limit(1),
                'asc'
            ),
            'price_desc' => $allProductsQuery->orderBy(
                ProductVariant::select('selling_price')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->orderBy('selling_price', 'desc')
                    ->limit(1),
                'desc'
            ),
            'name_asc' => $allProductsQuery->orderBy('title', 'asc'),
            default => $allProductsQuery->orderBy('created_at', 'desc'),
        };

        $allProducts = $allProductsQuery->paginate(12)->withQueryString();

        // Applicable offers and coupons
        $coupons = Coupon::query()
            ->where('is_active', true)
            ->where(function ($q) use ($seller) {
                $q->where('seller_id', $seller->id)
                    ->orWhereNull('seller_id');
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', Carbon::now());
            })
            ->limit(6)
            ->get();

        // Approved reviews for this seller
        $reviews = Review::query()
            ->with(['user', 'product'])
            ->whereHas('product', fn ($q) => $q->where('seller_id', $seller->id))
            ->where('status', ReviewStatus::APPROVED)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Merchant store categories for tab filter
        $availableCategories = Category::query()
            ->whereHas('products', fn ($q) => $q->where('seller_id', $seller->id)->where('status', ProductStatus::ACTIVE))
            ->get(['id', 'name', 'slug']);

        // Schema.org LocalBusiness JSON-LD markup
        $schemaJson = [
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            'name' => $store->name,
            'description' => $store->description ?? "Shop online from {$store->name} on OneStall.in",
            'url' => url("/store/{$store->slug}"),
            'telephone' => $store->phone,
            'image' => $store->logo ? url($store->logo) : url('/images/placeholder-store.png'),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $store->location?->address ?? '',
                'addressLocality' => $store->location?->city ?? 'Bengaluru',
                'addressRegion' => $store->location?->state ?? 'Karnataka',
                'postalCode' => $store->location?->pincode ?? '560001',
                'addressCountry' => 'IN',
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => $store->location?->lat ?? 12.9716,
                'longitude' => $store->location?->lng ?? 77.5946,
            ],
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => $store->rating_avg ?: 5.0,
                'reviewCount' => max($store->rating_count, 1),
            ],
        ];

        return [
            'store' => $store,
            'seller' => $seller,
            'location' => $store->location,
            'timings' => $store->timings,
            'deliveryZones' => $store->deliveryZones,
            'isOpenNow' => $store->isOpenNow(),
            'featuredProducts' => $featuredProducts,
            'newArrivals' => $newArrivals,
            'allProducts' => $allProducts,
            'coupons' => $coupons,
            'reviews' => $reviews,
            'availableCategories' => $availableCategories,
            'selectedCategory' => $categorySlug,
            'selectedSort' => $sort,
            'schemaJson' => $schemaJson,
        ];
    }

    /**
     * Create a new physical store outlet for a seller with location, inventory location, timings, and zones.
     */
    public function createStore(Seller $seller, array $data): Store
    {
        return DB::transaction(function () use ($seller, $data) {
            $name = trim((string) $data['name']);
            $baseSlug = !empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($name);
            $slug = $baseSlug;
            $counter = 1;
            while (Store::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $store = $seller->stores()->create([
                'name' => $name,
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'phone' => $data['phone'] ?? $seller->user?->phone,
                'email' => $data['email'] ?? $seller->user?->email,
                'delivery_radius_km' => (float) ($data['delivery_radius_km'] ?? setting('default_delivery_radius_km', 8.00)),
                'status' => isset($data['status']) ? (is_string($data['status']) ? StoreStatus::tryFrom($data['status']) ?? StoreStatus::ACTIVE : $data['status']) : StoreStatus::ACTIVE,
                'accepts_quick_commerce' => (bool) ($data['accepts_quick_commerce'] ?? true),
                'quick_max_orders' => (int) ($data['quick_max_orders'] ?? setting('quick_default_max_concurrent_orders', 15)),
                'quick_prep_time_minutes' => (int) ($data['quick_prep_time_minutes'] ?? setting('quick_default_prep_time_minutes', 10)),
                'quick_order_timeout_seconds' => (int) ($data['quick_order_timeout_seconds'] ?? setting('quick_order_timeout_seconds', 120)),
                'quick_auto_assign_rider' => (bool) ($data['quick_auto_assign_rider'] ?? true),
                'accepts_pickup' => (bool) ($data['accepts_pickup'] ?? true),
                'category_ids' => $data['category_ids'] ?? null,
            ]);

            // Physical location
            StoreLocation::create([
                'store_id' => $store->id,
                'address' => $data['address'] ?? 'Store Premises',
                'city' => $data['city'] ?? 'Bengaluru',
                'state' => $data['state'] ?? 'Karnataka',
                'pincode' => $data['pincode'] ?? '560001',
                'lat' => (float) ($data['lat'] ?? 12.9716),
                'lng' => (float) ($data['lng'] ?? 77.5946),
            ]);

            // Auto-create dedicated Inventory Location for this store
            $invLocation = InventoryLocation::create([
                'seller_id' => $seller->id,
                'store_id' => $store->id,
                'name' => "{$store->name} Shelf & Storage",
                'code' => 'LOC-' . strtoupper(Str::random(6)),
                'type' => InventoryLocationType::STORE,
                'is_active' => true,
            ]);

            // Operating Hours (0 = Sunday to 6 = Saturday)
            for ($day = 0; $day <= 6; $day++) {
                StoreTiming::create([
                    'store_id' => $store->id,
                    'day_of_week' => $day,
                    'opens_at' => $data['opens_at'] ?? '09:00:00',
                    'closes_at' => $data['closes_at'] ?? '22:00:00',
                    'is_closed' => false,
                ]);
            }

            // Radial Delivery Zone
            StoreDeliveryZone::create([
                'store_id' => $store->id,
                'name' => 'Standard Radial Coverage',
                'type' => DeliveryZoneType::RADIUS,
                'radius_km' => $store->delivery_radius_km,
                'delivery_fee' => (float) setting('default_delivery_fee', 30.00),
                'min_order_amount' => (float) setting('min_order_value', 99.00),
                'estimated_delivery_minutes' => 20,
                'is_quick' => true,
            ]);

            // If opening inventory is provided
            if (!empty($data['opening_inventory']) && is_array($data['opening_inventory'])) {
                foreach ($data['opening_inventory'] as $item) {
                    if (!empty($item['variant_id'])) {
                        Inventory::create([
                            'variant_id' => (int) $item['variant_id'],
                            'location_id' => $invLocation->id,
                            'quantity_on_hand' => (int) ($item['quantity'] ?? 0),
                            'quantity_reserved' => 0,
                            'reorder_threshold' => (int) ($item['reorder_threshold'] ?? 5),
                        ]);
                    }
                }
            }

            // Assign seller owner as store owner staff
            if ($seller->user_id) {
                StoreStaff::firstOrCreate(
                    ['store_id' => $store->id, 'user_id' => $seller->user_id],
                    [
                        'role' => StoreStaffRole::OWNER,
                        'status' => 'active',
                        'permissions' => StoreStaffRole::OWNER->defaultPermissions(),
                    ]
                );
            }

            $store->load(['location', 'timings', 'deliveryZones', 'inventoryLocations']);

            return $store;
        });
    }

    /**
     * Store-level performance reports and analytics.
     */
    public function getStoreReports(Seller $seller, ?int $storeId = null, string $range = '30d'): array
    {
        $startDate = match ($range) {
            '7d' => now()->subDays(7),
            '90d' => now()->subDays(90),
            'year' => now()->subYear(),
            default => now()->subDays(30),
        };

        $foQuery = FulfilmentOrder::query()
            ->where('seller_id', $seller->id)
            ->where('created_at', '>=', $startDate);

        if ($storeId) {
            $foQuery->where('store_id', $storeId);
        }

        $allOrders = (clone $foQuery)->get();
        $deliveredOrders = $allOrders->where('status', 'delivered');
        $cancelledOrders = $allOrders->where('status', 'cancelled');
        $quickOrders = $deliveredOrders->where('fulfilment_type', \App\Enums\FulfilmentType::QUICK);
        $standardOrders = $deliveredOrders->where('fulfilment_type', \App\Enums\FulfilmentType::STANDARD);

        $grossSales = (float) $deliveredOrders->sum('subtotal');
        $totalOrdersCount = $allOrders->count();
        $deliveredCount = $deliveredOrders->count();
        $cancelledCount = $cancelledOrders->count();
        $aov = $deliveredCount > 0 ? round($grossSales / $deliveredCount, 2) : 0.00;

        // Top products sold for this store
        $topItems = OrderItem::query()
            ->select('product_id', 'title', DB::raw('SUM(qty) as total_qty'), DB::raw('SUM(total) as total_sales'))
            ->whereIn('fulfilment_order_id', $deliveredOrders->pluck('id'))
            ->groupBy('product_id', 'title')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->get();

        // Daily breakdown
        $dailySales = $deliveredOrders
            ->groupBy(fn ($fo) => $fo->created_at->format('Y-m-d'))
            ->map(fn ($group) => [
                'count' => $group->count(),
                'sales' => (float) $group->sum('subtotal'),
            ]);

        return [
            'range' => $range,
            'store_id' => $storeId,
            'gross_sales' => $grossSales,
            'total_orders' => $totalOrdersCount,
            'delivered_orders' => $deliveredCount,
            'cancelled_orders' => $cancelledCount,
            'quick_orders_count' => $quickOrders->count(),
            'quick_orders_sales' => (float) $quickOrders->sum('subtotal'),
            'standard_orders_count' => $standardOrders->count(),
            'standard_orders_sales' => (float) $standardOrders->sum('subtotal'),
            'aov' => $aov,
            'top_items' => $topItems,
            'daily_sales' => $dailySales,
        ];
    }
}
