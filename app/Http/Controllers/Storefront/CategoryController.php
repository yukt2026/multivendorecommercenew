<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Search\SearchServiceInterface;
use App\Services\Storefront\LocationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        protected SearchServiceInterface $searchService,
        protected LocationService $locationService
    ) {}

    /**
     * Category Discovery Page (/c/{slug}).
     */
    public function show(Request $request, string $slug): View
    {
        $category = Category::where('slug', $slug)
            ->where('is_active', true)
            ->with(['parent', 'children' => fn ($q) => $q->where('is_active', true)])
            ->firstOrFail();

        $sort = (string) $request->input('sort', 'relevance');
        $location = $this->locationService->getCurrentLocation($request);

        $filters = [
            'category' => $category->slug,
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'brands' => $request->input('brands', []),
            'sellers' => $request->input('sellers', []),
            'in_stock' => $request->boolean('in_stock'),
            'delivery_type' => $request->input('delivery_type'),
            'delivery_time' => $request->input('delivery_time'),
        ];

        if (is_string($filters['brands']) && !empty($filters['brands'])) {
            $filters['brands'] = explode(',', $filters['brands']);
        }
        if (is_string($filters['sellers']) && !empty($filters['sellers'])) {
            $filters['sellers'] = explode(',', $filters['sellers']);
        }

        $products = $this->searchService->searchProducts('', $filters, $sort, $location, 16);
        $facets = $this->searchService->getFilterFacets('', $filters);

        // Breadcrumbs
        $breadcrumbs = [
            ['name' => 'Home', 'url' => route('home')],
        ];
        if ($category->parent) {
            $breadcrumbs[] = [
                'name' => $category->parent->name,
                'url' => route('category.show', $category->parent->slug),
            ];
        }
        $breadcrumbs[] = [
            'name' => $category->name,
            'url' => route('category.show', $category->slug),
        ];

        return view('storefront.category', [
            'category' => $category,
            'products' => $products,
            'seoType' => 'category',
            'seoModel' => $category,
            'seoPaginator' => $products,
            'filters' => $filters,
            'sort' => $sort,
            'facets' => $facets,
            'breadcrumbs' => $breadcrumbs,
            'location' => $location,
        ]);
    }
}
