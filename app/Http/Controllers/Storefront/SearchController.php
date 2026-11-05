<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\AI\Contracts\SemanticSearchParserInterface;
use App\Services\Search\SearchServiceInterface;
use App\Services\Storefront\LocationService;
use App\Services\Storefront\StorefrontService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        protected SearchServiceInterface $searchService,
        protected StorefrontService $storefrontService,
        protected LocationService $locationService,
        protected SemanticSearchParserInterface $semanticParser
    ) {}

    /**
     * Discovery Search Page (/search?q=).
     */
    public function index(Request $request): View
    {
        $query = (string) $request->input('q', '');
        $tab = (string) $request->input('tab', 'products');
        $sort = (string) $request->input('sort', 'relevance');

        $location = $this->locationService->getCurrentLocation($request);

        $filters = [
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'brands' => $request->input('brands', []),
            'sellers' => $request->input('sellers', []),
            'in_stock' => $request->boolean('in_stock'),
            'delivery_type' => $request->input('delivery_type'),
            'delivery_time' => $request->input('delivery_time'),
            'category' => $request->input('category'),
            'quick_only' => $request->boolean('quick_only') || $request->input('delivery_type') === 'quick',
            'pickup_only' => $request->boolean('pickup_only') || $request->input('delivery_type') === 'pickup',
            'open_only' => $request->boolean('open_only'),
            'radius_km' => $request->input('radius_km'),
        ];

        // Format array filters if comma-separated string passed
        if (is_string($filters['brands']) && !empty($filters['brands'])) {
            $filters['brands'] = explode(',', $filters['brands']);
        }
        if (is_string($filters['sellers']) && !empty($filters['sellers'])) {
            $filters['sellers'] = explode(',', $filters['sellers']);
        }

        // Natural-Language Semantic Search Parsing
        $parsedSemantic = null;
        if ($tab === 'products' && !empty($query)) {
            $parsedSemantic = $this->semanticParser->parse($query, $location);
            if ($filters['price_max'] === null && $parsedSemantic->maxPrice !== null) {
                $filters['price_max'] = $parsedSemantic->maxPrice;
            }
            if ($filters['price_min'] === null && $parsedSemantic->minPrice !== null) {
                $filters['price_min'] = $parsedSemantic->minPrice;
            }
            if (!$filters['quick_only'] && ($parsedSemantic->isQuickCommerce || $parsedSemantic->isNearMe)) {
                $filters['quick_only'] = true;
            }
            if (!empty($parsedSemantic->cleanedKeyword)) {
                $query = $parsedSemantic->cleanedKeyword;
            }
        }

        // Execute Tab-based Search
        if ($tab === 'stores') {
            $results = $this->searchService->searchStores($query, $filters, $location, 12);
        } elseif ($tab === 'brands') {
            $results = $this->searchService->searchBrands($query, 18);
        } else {
            $tab = 'products';
            $results = $this->searchService->searchProducts($query, $filters, $sort, $location, 16);
        }

        // Typo correction suggestions
        $corrections = $this->searchService->suggestCorrections($query);

        // Sidebar Facets
        $facets = $this->searchService->getFilterFacets($query, $filters);

        // Applied Filter Chips
        $appliedChips = $this->buildAppliedChips($request, $filters);

        return view('storefront.search', [
            'query' => $query,
            'tab' => $tab,
            'sort' => $sort,
            'seoOverrides' => [
                'title' => $query ? "Search results for \"{$query}\" | OneStall" : 'Search Catalog & Stores | OneStall',
                'noindex' => true,
            ],
            'filters' => $filters,
            'results' => $results,
            'corrections' => $corrections,
            'facets' => $facets,
            'appliedChips' => $appliedChips,
            'location' => $location,
        ]);
    }

    /**
     * Live search autocomplete endpoint.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = (string) $request->input('q', '');
        $type = (string) $request->input('type', 'all');

        $suggestions = $this->storefrontService->searchSuggestions($query, $type);

        return response()->json([
            'query' => $query,
            'type' => $type,
            'results' => $suggestions,
        ]);
    }

    /**
     * Build interactive chips for currently applied filters.
     */
    protected function buildAppliedChips(Request $request, array $filters): array
    {
        $chips = [];
        $currentUrl = $request->fullUrl();

        if (!empty($filters['price_min']) || !empty($filters['price_max'])) {
            $label = 'Price: ' . ($filters['price_min'] ? '₹' . $filters['price_min'] : '₹0') . ' - ' . ($filters['price_max'] ? '₹' . $filters['price_max'] : 'Any');
            $chips[] = [
                'label' => $label,
                'remove_url' => $this->urlWithoutParams($request, ['price_min', 'price_max']),
            ];
        }

        if (!empty($filters['delivery_type'])) {
            $typeLabels = ['quick' => '⚡ 15m Quick Delivery', 'standard' => '📦 Standard Delivery', 'pickup' => '🛍️ Store Pickup'];
            $chips[] = [
                'label' => $typeLabels[$filters['delivery_type']] ?? ucfirst($filters['delivery_type']),
                'remove_url' => $this->urlWithoutParams($request, ['delivery_type']),
            ];
        }

        if (!empty($filters['in_stock'])) {
            $chips[] = [
                'label' => 'In Stock Only',
                'remove_url' => $this->urlWithoutParams($request, ['in_stock']),
            ];
        }

        if (!empty($filters['delivery_time']) && $filters['delivery_time'] === 'under_30_mins') {
            $chips[] = [
                'label' => 'Delivery under 30 mins',
                'remove_url' => $this->urlWithoutParams($request, ['delivery_time']),
            ];
        }

        if (!empty($filters['category'])) {
            $chips[] = [
                'label' => 'Category: ' . $filters['category'],
                'remove_url' => $this->urlWithoutParams($request, ['category']),
            ];
        }

        if (!empty($filters['brands'])) {
            foreach ($filters['brands'] as $b) {
                $chips[] = [
                    'label' => 'Brand: ' . $b,
                    'remove_url' => $this->urlWithoutArrayValue($request, 'brands', (string) $b),
                ];
            }
        }

        return $chips;
    }

    /**
     * Generate URL without specific parameters.
     */
    protected function urlWithoutParams(Request $request, array $keys): string
    {
        $query = $request->query();
        foreach ($keys as $key) {
            unset($query[$key]);
        }
        return $request->url() . ($query ? '?' . http_build_query($query) : '');
    }

    /**
     * Remove single value from an array query parameter.
     */
    protected function urlWithoutArrayValue(Request $request, string $key, string $value): string
    {
        $query = $request->query();
        if (isset($query[$key])) {
            $arr = is_array($query[$key]) ? $query[$key] : explode(',', $query[$key]);
            $arr = array_values(array_diff($arr, [$value]));
            if (empty($arr)) {
                unset($query[$key]);
            } else {
                $query[$key] = $arr;
            }
        }
        return $request->url() . ($query ? '?' . http_build_query($query) : '');
    }

    /**
     * API: Parse natural language search query into structured facets (/api/v1/search/parse?q=).
     */
    public function parseQueryApi(Request $request): JsonResponse
    {
        $q = (string) $request->input('q', '');
        $location = $this->locationService->getCurrentLocation($request);
        $parsed = $this->semanticParser->parse($q, $location);

        return response()->json([
            'success' => true,
            'data' => $parsed->toArray(),
        ]);
    }
}
