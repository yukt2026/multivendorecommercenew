<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\Search\SearchServiceInterface;
use App\Services\Storefront\LocationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(
        protected SearchServiceInterface $searchService,
        protected LocationService $locationService
    ) {}

    /**
     * Brand Discovery Page (/b/{slug}).
     */
    public function show(Request $request, string $slug): View
    {
        $brand = Brand::where('slug', $slug)->firstOrFail();

        $sort = (string) $request->input('sort', 'relevance');
        $location = $this->locationService->getCurrentLocation($request);

        $filters = [
            'brands' => [$brand->slug],
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'category' => $request->input('category'),
            'sellers' => $request->input('sellers', []),
            'in_stock' => $request->boolean('in_stock'),
            'delivery_type' => $request->input('delivery_type'),
            'delivery_time' => $request->input('delivery_time'),
        ];

        if (is_string($filters['sellers']) && !empty($filters['sellers'])) {
            $filters['sellers'] = explode(',', $filters['sellers']);
        }

        $products = $this->searchService->searchProducts('', $filters, $sort, $location, 16);
        $facets = $this->searchService->getFilterFacets('', $filters);

        // Breadcrumbs
        $breadcrumbs = [
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Brands', 'url' => route('search') . '?tab=brands'],
            ['name' => $brand->name, 'url' => route('brand.show', $brand->slug)],
        ];

        return view('storefront.brand', [
            'brand' => $brand,
            'products' => $products,
            'seoType' => 'brand',
            'seoModel' => $brand,
            'seoPaginator' => $products,
            'filters' => $filters,
            'sort' => $sort,
            'facets' => $facets,
            'breadcrumbs' => $breadcrumbs,
            'location' => $location,
        ]);
    }
}
