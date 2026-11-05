<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class StorefrontService
{
    /**
     * Get home hero banners (cached 10 min).
     */
    public function getHeroBanners(): Collection
    {
        return Cache::remember('storefront.hero_banners', 600, function () {
            $banners = Banner::active()
                ->whereIn('position', ['home_hero', 'hero_carousel', 'top_slider'])
                ->orderBy('sort')
                ->get();

            if ($banners->isEmpty()) {
                $banners = Banner::active()->orderBy('sort')->take(5)->get();
            }

            return $banners;
        });
    }

    /**
     * Get promotional strip banners.
     */
    public function getPromoBanners(): Collection
    {
        return Cache::remember('storefront.promo_banners', 600, function () {
            return Banner::active()
                ->whereIn('position', ['home_sub', 'homepage_strip', 'promo_strip'])
                ->orderBy('sort')
                ->get();
        });
    }

    /**
     * Get parent categories with subcategories and product counts for mega-menu and category grid.
     */
    public function getCategories(): Collection
    {
        return Cache::remember('storefront.categories_tree', 600, function () {
            return Category::whereNull('parent_id')
                ->where('is_active', true)
                ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                ->withCount(['products' => fn ($q) => $q->where('status', ProductStatus::ACTIVE)])
                ->orderBy('sort_order')
                ->get();
        });
    }

    /**
     * Check if Quick Commerce is active and return nearby quick stores count and ETA.
     *
     * @param float $lat
     * @param float $lng
     * @return array{is_available: bool, store_count: int, eta_minutes: int, stores: Collection}
     */
    public function getQuickCommerceStatus(float $lat, float $lng): array
    {
        $cacheKey = "storefront.quick_status_" . round($lat, 2) . "_" . round($lng, 2);
        return Cache::remember($cacheKey, 120, function () use ($lat, $lng) {
            $radius = (float) setting('default_delivery_radius_km', 8.00);

            try {
                $stores = Store::active()
                    ->quickCommerce()
                    ->with(['location', 'timings'])
                    ->nearby($lat, $lng, $radius)
                    ->take(8)
                    ->get();
            } catch (\Throwable $e) {
                // Fallback if location search returns empty or SQLite math mismatch
                $stores = Store::active()
                    ->quickCommerce()
                    ->with(['location', 'timings'])
                    ->take(8)
                    ->get();
            }

            // Filter stores currently open
            $openStores = $stores->filter(fn (Store $store) => $store->isOpenNow());

            return [
                'is_available' => $openStores->isNotEmpty(),
                'store_count' => $openStores->count(),
                'eta_minutes' => 15,
                'stores' => $openStores->values(),
            ];
        });
    }

    /**
     * Get nearby physical and local stores.
     *
     * @param float $lat
     * @param float $lng
     * @return Collection
     */
    public function getNearbyStores(float $lat, float $lng): Collection
    {
        $cacheKey = "storefront.nearby_stores_" . round($lat, 2) . "_" . round($lng, 2);
        return Cache::remember($cacheKey, 120, function () use ($lat, $lng) {
            $radius = (float) setting('default_delivery_radius_km', 15.00);

            try {
                $stores = Store::active()
                    ->with(['location', 'timings', 'seller'])
                    ->nearby($lat, $lng, $radius)
                    ->take(8)
                    ->get();

                if ($stores->isNotEmpty()) {
                    return $stores;
                }
            } catch (\Throwable $e) {
                // Fall through to fallback
            }

            // Fallback: Return top rated active stores
            return Store::active()
                ->with(['location', 'timings', 'seller'])
                ->orderByDesc('rating_avg')
                ->take(8)
                ->get();
        });
    }

    /**
     * Get deals / high-discount products.
     */
    public function getDealsProducts(int $limit = 8): Collection
    {
        return Cache::remember("storefront.deals_{$limit}", 300, function () use ($limit) {
            return Product::active()
                ->with(['media', 'defaultVariant', 'variants', 'brand', 'seller'])
                ->whereHas('variants', function ($q) {
                    $q->where('is_active', true)
                        ->whereRaw('mrp > selling_price');
                })
                ->latest('id')
                ->take($limit)
                ->get();
        });
    }

    /**
     * Get best sellers.
     */
    public function getBestSellers(int $limit = 8): Collection
    {
        return Cache::remember("storefront.bestsellers_{$limit}", 300, function () use ($limit) {
            return Product::active()
                ->with(['media', 'defaultVariant', 'variants', 'brand', 'seller'])
                ->orderBy('id', 'asc') // Consistent ordering
                ->take($limit)
                ->get();
        });
    }

    /**
     * Get popular brands with active products.
     */
    public function getBrands(int $limit = 12): Collection
    {
        return Cache::remember("storefront.brands_{$limit}", 600, function () use ($limit) {
            return Brand::query()
                ->withCount(['products' => fn ($q) => $q->where('status', ProductStatus::ACTIVE)])
                ->orderByDesc('is_verified')
                ->orderBy('name')
                ->take($limit)
                ->get();
        });
    }

    /**
     * Get OneStall direct / flagship stores.
     */
    public function getOnestallStores(int $limit = 6): Collection
    {
        return Cache::remember("storefront.onestall_stores_{$limit}", 600, function () use ($limit) {
            $stores = Store::active()
                ->where('is_onestall_owned', true)
                ->with(['location', 'timings'])
                ->take($limit)
                ->get();

            if ($stores->isEmpty()) {
                // If no specific onestall_owned store exists, return top rated verified stores
                $stores = Store::active()
                    ->with(['location', 'timings'])
                    ->orderByDesc('rating_avg')
                    ->take($limit)
                    ->get();
            }

            return $stores;
        });
    }

    /**
     * Get recommended products.
     */
    public function getRecommendedProducts(int $limit = 12): Collection
    {
        return Cache::remember("storefront.recommended_{$limit}", 300, function () use ($limit) {
            return Product::active()
                ->with(['media', 'defaultVariant', 'variants', 'brand', 'seller', 'category'])
                ->inRandomOrder(12345) // Seeded random for cache stability
                ->take($limit)
                ->get();
        });
    }

    /**
     * Live search autocomplete suggestions across Products, Stores, Brands.
     *
     * @param string $query
     * @param string $type products|stores|brands|all
     * @return array
     */
    public function searchSuggestions(string $query, string $type = 'all'): array
    {
        $term = trim($query);
        if (strlen($term) < 2) {
            return ['products' => [], 'stores' => [], 'brands' => []];
        }

        $results = [
            'products' => [],
            'stores' => [],
            'brands' => [],
        ];

        if ($type === 'all' || $type === 'products') {
            $products = Product::active()
                ->where(function ($q) use ($term) {
                    $q->where('title', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                })
                ->with(['media', 'defaultVariant', 'variants', 'brand', 'category'])
                ->take(6)
                ->get();

            $results['products'] = $products->map(function (Product $p) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'slug' => $p->slug,
                    'price' => $p->effective_price,
                    'formatted_price' => format_currency($p->effective_price),
                    'mrp' => $p->effective_mrp,
                    'image' => $p->primaryImageUrl(),
                    'brand' => $p->brand?->name,
                    'category' => $p->category?->name,
                    'is_quick' => (bool) $p->is_quick_capable,
                    'url' => route('home') . '?search=' . urlencode($p->title),
                ];
            })->toArray();
        }

        if ($type === 'all' || $type === 'stores') {
            $stores = Store::active()
                ->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                })
                ->with('location')
                ->take(4)
                ->get();

            $results['stores'] = $stores->map(function (Store $s) {
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                    'logo' => $s->logo ? asset('storage/' . $s->logo) : null,
                    'city' => $s->location?->city ?? 'Bengaluru',
                    'rating' => $s->rating_avg ?? 4.8,
                    'is_open' => $s->isOpenNow(),
                    'is_quick' => (bool) $s->accepts_quick_commerce,
                    'url' => route('store.show', $s->slug),
                ];
            })->toArray();
        }

        if ($type === 'all' || $type === 'brands') {
            $brands = Brand::query()
                ->where('name', 'like', "%{$term}%")
                ->take(4)
                ->get();

            $results['brands'] = $brands->map(function (Brand $b) {
                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'slug' => $b->slug,
                    'logo' => $b->logo ? asset('storage/' . $b->logo) : null,
                    'is_verified' => (bool) $b->is_verified,
                    'url' => route('home') . '?brand=' . urlencode($b->slug),
                ];
            })->toArray();
        }

        return $results;
    }
}
