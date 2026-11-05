<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Search\SearchServiceInterface;
use App\Services\Storefront\LocationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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
        $location = $this->locationService->getCurrentLocation($request);

        $filters = [
            'quick_only' => $request->boolean('quick_only'),
            'pickup_only' => $request->boolean('pickup_only'),
            'open_only' => $request->boolean('open_only'),
            'radius_km' => $request->input('radius_km', 25.0),
        ];

        $stores = $this->searchService->searchStores($query, $filters, $location, 20);

        // Serialize store pins for interactive map
        $mapPins = $stores->getCollection()->map(function (Store $store) {
            $loc = $store->location;
            return [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'lat' => $loc ? (float) $loc->lat : null,
                'lng' => $loc ? (float) $loc->lng : null,
                'address' => $loc ? "{$loc->address}, {$loc->city} {$loc->pincode}" : 'Local Store',
                'city' => $loc?->city ?? 'Bengaluru',
                'rating' => $store->rating_avg ?? 4.8,
                'is_open' => $store->isOpenNow(),
                'distance_km' => isset($store->distance_km) ? (float) $store->distance_km : null,
                'is_quick' => (bool) $store->accepts_quick_commerce,
                'is_pickup' => (bool) $store->accepts_pickup,
                'url' => route('store.show', $store->slug),
            ];
        })->filter(fn ($pin) => $pin['lat'] !== null && $pin['lng'] !== null)->values();

        return view('storefront.stores', [
            'stores' => $stores,
            'mapPins' => $mapPins,
            'query' => $query,
            'filters' => $filters,
            'location' => $location,
        ]);
    }
}
