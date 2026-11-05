<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabaseSearchService implements SearchServiceInterface
{
    /**
     * Search products with comprehensive filters, location-aware ranking and pagination.
     */
    public function searchProducts(
        string $query = '',
        array $filters = [],
        string $sort = 'relevance',
        array $location = [],
        int $perPage = 16
    ): LengthAwarePaginator {
        $q = trim($query);

        $productQuery = Product::active()
            ->with([
                'media',
                'defaultVariant',
                'variants.inventories',
                'brand',
                'category',
                'seller.stores.location',
                'reviews',
            ]);

        // 1. Text Search with Fulltext & Trigram Token Fallback
        if ($q !== '') {
            $tokens = array_filter(explode(' ', $q), fn ($t) => strlen(trim($t)) > 1);

            $productQuery->where(function (Builder $builder) use ($q, $tokens) {
                // If MySQL, try FULLTEXT match; fallback to LIKE
                if (DB::connection()->getDriverName() === 'mysql') {
                    try {
                        $builder->whereRaw('MATCH(products.title, products.short_desc) AGAINST(? IN BOOLEAN MODE)', ["+{$q}*"]);
                    } catch (\Throwable $e) {
                        $builder->where('products.title', 'like', "%{$q}%")
                            ->orWhere('products.description', 'like', "%{$q}%");
                    }
                } else {
                    $builder->where('products.title', 'like', "%{$q}%")
                        ->orWhere('products.short_desc', 'like', "%{$q}%")
                        ->orWhere('products.description', 'like', "%{$q}%");
                }

                // Match in Brand Name
                $builder->orWhereHas('brand', function ($b) use ($q) {
                    $b->where('name', 'like', "%{$q}%");
                });

                // Match in Category Name
                $builder->orWhereHas('category', function ($c) use ($q) {
                    $c->where('name', 'like', "%{$q}%");
                });

                // Match individual tokens (trigram-like token matching)
                foreach ($tokens as $token) {
                    $builder->orWhere('products.title', 'like', "%{$token}%");
                }
            });
        }

        // 2. Category Filter
        if (!empty($filters['category'])) {
            $cat = $filters['category'];
            if (is_numeric($cat)) {
                $productQuery->where('products.category_id', (int) $cat);
            } else {
                $productQuery->whereHas('category', fn ($c) => $c->where('slug', $cat));
            }
        }

        // 3. Brand Filter (Array or CSV)
        if (!empty($filters['brands'])) {
            $brandIds = is_array($filters['brands']) ? $filters['brands'] : explode(',', (string) $filters['brands']);
            $productQuery->where(function ($bQuery) use ($brandIds) {
                $bQuery->whereIn('products.brand_id', array_filter($brandIds, 'is_numeric'))
                    ->orWhereHas('brand', fn ($b) => $b->whereIn('slug', $brandIds));
            });
        }

        // 4. Seller Filter
        if (!empty($filters['sellers'])) {
            $sellerIds = is_array($filters['sellers']) ? $filters['sellers'] : explode(',', (string) $filters['sellers']);
            $productQuery->whereIn('products.seller_id', $sellerIds);
        }

        // 5. Price Range Filter (Min & Max Selling Price)
        if (isset($filters['price_min']) && is_numeric($filters['price_min'])) {
            $min = (float) $filters['price_min'];
            $productQuery->whereHas('variants', fn ($v) => $v->where('selling_price', '>=', $min));
        }
        if (isset($filters['price_max']) && is_numeric($filters['price_max'])) {
            $max = (float) $filters['price_max'];
            $productQuery->whereHas('variants', fn ($v) => $v->where('selling_price', '<=', $max));
        }

        // 6. In Stock Availability Filter
        if (!empty($filters['in_stock'])) {
            $productQuery->whereHas('variants.inventories', function ($inv) {
                $inv->whereRaw('quantity_on_hand > quantity_reserved');
            });
        }

        // 7. Delivery Type Filter (quick / standard / pickup)
        if (!empty($filters['delivery_type'])) {
            $type = (string) $filters['delivery_type'];
            if ($type === 'quick') {
                $productQuery->where('products.is_quick_capable', true);
            } elseif ($type === 'pickup') {
                $productQuery->whereHas('seller.stores', fn ($s) => $s->where('accepts_pickup', true));
            }
        }

        // 8. Delivery Time Filter (under_30_mins / today)
        if (!empty($filters['delivery_time']) && $filters['delivery_time'] === 'under_30_mins') {
            $productQuery->where('products.is_quick_capable', true);
        }

        // 9. Location-Aware Ranking (Nearby Available First)
        $hasLocation = !empty($location['lat']) && !empty($location['lng']);
        if ($hasLocation) {
            $lat = (float) $location['lat'];
            $lng = (float) $location['lng'];
            $radius = (float) ($location['radius_km'] ?? setting('default_delivery_radius_km', 8.0));

            // Boost products whose seller has an active store nearby
            $productQuery->leftJoin('sellers', 'products.seller_id', '=', 'sellers.id')
                ->leftJoin('stores', 'sellers.id', '=', 'stores.seller_id')
                ->leftJoin('store_locations', 'stores.id', '=', 'store_locations.store_id');

            if (DB::connection()->getDriverName() === 'sqlite') {
                $latDelta = $radius / 111.0;
                $lngDelta = $radius / (111.0 * max(0.1, cos(deg2rad($lat))));
                $productQuery->select('products.*')
                    ->selectRaw("CASE WHEN store_locations.lat BETWEEN ? AND ? AND store_locations.lng BETWEEN ? AND ? THEN 1 ELSE 0 END as is_nearby", [
                        $lat - $latDelta, $lat + $latDelta, $lng - $lngDelta, $lng + $lngDelta
                    ]);
            } else {
                $haversine = "(6371 * acos(
                    cos(radians({$lat})) * cos(radians(store_locations.lat)) * 
                    cos(radians(store_locations.lng) - radians({$lng})) + 
                    sin(radians({$lat})) * sin(radians(store_locations.lat))
                ))";
                $productQuery->select('products.*')
                    ->selectRaw("CASE WHEN {$haversine} <= ? THEN 1 ELSE 0 END as is_nearby", [$radius]);
            }

            $productQuery->groupBy('products.id');
        } else {
            $productQuery->select('products.*');
        }

        // 10. Sorting
        if ($hasLocation && ($sort === 'relevance' || empty($sort))) {
            $productQuery->orderByDesc('is_nearby');
        }

        switch ($sort) {
            case 'price_asc':
                $productQuery->orderBy(
                    ProductVariant::select('selling_price')
                        ->whereColumn('product_variants.product_id', 'products.id')
                        ->orderBy('selling_price', 'asc')
                        ->limit(1),
                    'asc'
                );
                break;

            case 'price_desc':
                $productQuery->orderBy(
                    ProductVariant::select('selling_price')
                        ->whereColumn('product_variants.product_id', 'products.id')
                        ->orderBy('selling_price', 'desc')
                        ->limit(1),
                    'desc'
                );
                break;

            case 'newest':
                $productQuery->orderByDesc('products.created_at');
                break;

            case 'discount_desc':
                $productQuery->orderBy(
                    ProductVariant::selectRaw('((mrp - selling_price) / CASE WHEN mrp > 0 THEN mrp ELSE 1 END)')
                        ->whereColumn('product_variants.product_id', 'products.id')
                        ->orderByRaw('((mrp - selling_price) / CASE WHEN mrp > 0 THEN mrp ELSE 1 END) desc')
                        ->limit(1),
                    'desc'
                );
                break;

            case 'rating_desc':
            case 'relevance':
            default:
                $productQuery->orderByDesc('products.id');
                break;
        }

        return $productQuery->paginate($perPage)->withQueryString();
    }

    /**
     * Search stores with location and filters.
     */
    public function searchStores(
        string $query = '',
        array $filters = [],
        array $location = [],
        int $perPage = 12
    ): LengthAwarePaginator {
        $q = trim($query);

        $storeQuery = Store::active()
            ->with(['location', 'timings', 'seller']);

        if ($q !== '') {
            $storeQuery->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhereHas('location', function ($l) use ($q) {
                        $l->where('city', 'like', "%{$q}%")
                            ->orWhere('pincode', 'like', "%{$q}%")
                            ->orWhere('address', 'like', "%{$q}%");
                    });
            });
        }

        if (!empty($filters['quick_only'])) {
            $storeQuery->where('accepts_quick_commerce', true);
        }

        if (!empty($filters['pickup_only'])) {
            $storeQuery->where('accepts_pickup', true);
        }

        if (!empty($location['lat']) && !empty($location['lng'])) {
            $radius = (float) ($filters['radius_km'] ?? $location['radius_km'] ?? setting('default_delivery_radius_km', 15.0));
            try {
                $storeQuery->nearby((float) $location['lat'], (float) $location['lng'], $radius);
            } catch (\Throwable $e) {
                $storeQuery->orderByDesc('rating_avg');
            }
        } else {
            $storeQuery->orderByDesc('rating_avg');
        }

        $paginator = $storeQuery->paginate($perPage)->withQueryString();

        if (!empty($filters['open_only'])) {
            // Filter only open stores
            $openItems = $paginator->getCollection()->filter(fn (Store $s) => $s->isOpenNow());
            $paginator->setCollection($openItems->values());
        }

        return $paginator;
    }

    /**
     * Search brands with query.
     */
    public function searchBrands(
        string $query = '',
        int $perPage = 18
    ): LengthAwarePaginator {
        $q = trim($query);
        $brandQuery = Brand::query()
            ->withCount(['products' => fn ($p) => $p->active()]);

        if ($q !== '') {
            $brandQuery->where('name', 'like', "%{$q}%");
        }

        return $brandQuery->orderByDesc('is_verified')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Suggest typo corrections / "Did you mean?".
     */
    public function suggestCorrections(string $query): array
    {
        $term = strtolower(trim($query));
        if (strlen($term) < 3) {
            return ['suggested_query' => null, 'suggestions' => []];
        }

        // Pull popular product titles & brand names
        $words = Brand::pluck('name')
            ->merge(Category::where('is_active', true)->pluck('name'))
            ->merge(
                Product::active()->take(50)->pluck('title')->flatMap(function ($title) {
                    return explode(' ', $title);
                })
            )
            ->filter(fn ($w) => strlen(trim($w)) > 3)
            ->unique();

        $bestWord = null;
        $bestDistance = 999;
        $suggestions = [];

        foreach ($words as $word) {
            $cleanWord = strtolower(trim($word));
            $dist = levenshtein($term, $cleanWord);

            if ($dist > 0 && $dist <= 2 && $dist < $bestDistance) {
                $bestDistance = $dist;
                $bestWord = $word;
            }

            if ($dist <= 2 && !in_array($word, $suggestions, true)) {
                $suggestions[] = $word;
            }
        }

        return [
            'suggested_query' => $bestWord,
            'suggestions' => array_slice($suggestions, 0, 5),
        ];
    }

    /**
     * Get facet counts / aggregations for sidebar filters.
     */
    public function getFilterFacets(string $query = '', array $currentFilters = []): array
    {
        $baseQuery = Product::active();

        if (trim($query) !== '') {
            $q = trim($query);
            $baseQuery->where(function ($builder) use ($q) {
                $builder->where('products.title', 'like', "%{$q}%")
                    ->orWhere('products.description', 'like', "%{$q}%");
            });
        }

        // Categories with counts
        $categories = Category::where('is_active', true)
            ->whereHas('products', fn ($p) => $p->active())
            ->withCount(['products' => fn ($p) => $p->active()])
            ->orderByDesc('products_count')
            ->take(8)
            ->get();

        // Brands with counts
        $brands = Brand::whereHas('products', fn ($p) => $p->active())
            ->withCount(['products' => fn ($p) => $p->active()])
            ->orderByDesc('products_count')
            ->take(10)
            ->get();

        // Sellers
        $sellers = Seller::where('status', 'approved')
            ->whereHas('products', fn ($p) => $p->active())
            ->withCount(['products' => fn ($p) => $p->active()])
            ->take(6)
            ->get();

        // Price range bounds
        $minPrice = (float) (ProductVariant::where('is_active', true)->min('selling_price') ?? 0);
        $maxPrice = (float) (ProductVariant::where('is_active', true)->max('selling_price') ?? 2000);

        return [
            'categories' => $categories,
            'brands' => $brands,
            'sellers' => $sellers,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'total_products_count' => (clone $baseQuery)->count(),
        ];
    }
}
