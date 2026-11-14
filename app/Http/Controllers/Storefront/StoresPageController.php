<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Services\Search\SearchServiceInterface;
use App\Services\Storefront\LocationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoresPageController extends Controller
{
    public function __construct(
        protected SearchServiceInterface $searchService,
        protected LocationService $locationService
    ) {}

    /**
     * Nearby Stores & Map Directory (/stores).
     */
    public function index(Request $request): View
    {
        $query = (string) $request->input('q', '');
        $city = (string) $request->input('city', '');
        $categoryId = $request->input('category_id') ? (int) $request->input('category_id') : null;
        $sortBy = (string) $request->input('sort', 'distance');
        $allStoresRequested = $request->boolean('all_stores') || $request->input('radius_km') === 'all';

        $location = $this->locationService->getCurrentLocation($request);

        $filters = [
            'quick_only' => $request->boolean('quick_only'),
            'pickup_only' => $request->boolean('pickup_only'),
            'open_only' => $request->boolean('open_only'),
            'radius_km' => $request->input('radius_km', 25.0),
            'city' => $city,
            'category_id' => $categoryId,
            'sort_by' => $sortBy,
            'all_stores' => $allStoresRequested,
        ];

        $stores = $this->searchService->searchStores($query, $filters, $location, 18);

        // Fetch distinct cities with active store counts
        $cityCounts = StoreLocation::select('city', DB::raw('count(distinct store_id) as store_count'))
            ->whereHas('store', fn ($q) => $q->active())
            ->whereNotNull('city')
            ->groupBy('city')
            ->orderBy('city')
            ->pluck('store_count', 'city');

        // Top categories for filter bar
        $categories = Category::active()->whereNull('parent_id')->orderBy('sort_order')->take(8)->get();

        // If location radius yielded 0 stores, provide top network stores as fallback
        $fallbackStores = null;
        if ($stores->total() === 0 && empty($query) && empty($city)) {
            $fallbackStores = Store::active()
                ->with(['location', 'timings', 'seller'])
                ->orderByDesc('rating_avg')
                ->take(6)
                ->get();
        }

        // Serialize store pins for interactive map
        $pinSource = $stores->total() > 0 ? $stores->getCollection() : ($fallbackStores ?: collect());
        $mapPins = $pinSource->map(function (Store $store) {
            $loc = $store->location;
            return [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'lat' => $loc ? (float) $loc->lat : null,
                'lng' => $loc ? (float) $loc->lng : null,
                'address' => $loc ? "{$loc->address}, {$loc->city} {$loc->pincode}" : 'Local Store',
                'city' => $loc?->city ?? 'Bengaluru',
                'rating' => $store->rating_avg ? (float) $store->rating_avg : 4.8,
                'rating_count' => $store->rating_count ?? 0,
                'is_open' => $store->isOpenNow(),
                'logo_url' => $store->logo_url,
                'banner_url' => $store->banner_url,
                'distance_km' => isset($store->distance_km) ? (float) $store->distance_km : null,
                'is_quick' => (bool) $store->accepts_quick_commerce,
                'is_pickup' => (bool) $store->accepts_pickup,
                'products_count' => $store->products()->count(),
                'url' => route('store.show', $store->slug),
            ];
        })->filter(fn ($pin) => $pin['lat'] !== null && $pin['lng'] !== null)->values();

        return view('storefront.stores', [
            'stores' => $stores,
            'fallbackStores' => $fallbackStores,
            'mapPins' => $mapPins,
            'query' => $query,
            'city' => $city,
            'categoryId' => $categoryId,
            'sortBy' => $sortBy,
            'filters' => $filters,
            'location' => $location,
            'cityCounts' => $cityCounts,
            'categories' => $categories,
            'totalActiveStores' => Store::active()->count(),
        ]);
    }
}
