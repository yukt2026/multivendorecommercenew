<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\CartService;
use App\Services\Storefront\LocationService;
use App\Services\Storefront\StorefrontService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(
        protected StorefrontService $storefrontService,
        protected LocationService $locationService,
        protected CartService $cartService
    ) {}

    /**
     * Render OneStall omnichannel storefront homepage.
     */
    public function index(Request $request): View
    {
        $location = $this->locationService->getCurrentLocation($request);
        $heroBanners = $this->storefrontService->getHeroBanners();
        $promoBanners = $this->storefrontService->getPromoBanners();
        $quickCommerce = $this->storefrontService->getQuickCommerceStatus($location['lat'], $location['lng']);
        $categories = $this->storefrontService->getCategories();
        $nearbyStores = $this->storefrontService->getNearbyStores($location['lat'], $location['lng']);
        $dealsProducts = $this->storefrontService->getDealsProducts(8);
        $bestSellers = $this->storefrontService->getBestSellers(8);
        $brands = $this->storefrontService->getBrands(12);
        $onestallStores = $this->storefrontService->getOnestallStores(6);
        $recommendedProducts = $this->storefrontService->getRecommendedProducts(12);

        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getActiveCart($request->user(), $sessionId);
        $cartSummary = $this->cartService->getSummary($cart);

        return view('storefront.home', [
            'appName' => setting('name', 'OneStall'),
            'location' => $location,
            'heroBanners' => $heroBanners,
            'promoBanners' => $promoBanners,
            'quickCommerce' => $quickCommerce,
            'categories' => $categories,
            'nearbyStores' => $nearbyStores,
            'dealsProducts' => $dealsProducts,
            'bestSellers' => $bestSellers,
            'brands' => $brands,
            'onestallStores' => $onestallStores,
            'recommendedProducts' => $recommendedProducts,
            'cartSummary' => $cartSummary,
            'deliveryRadius' => setting('default_delivery_radius_km', 8.00),
            'currencySymbol' => setting('currency_symbol', '₹'),
            'freeDeliveryThreshold' => setting('free_delivery_threshold', 499.00),
        ]);
    }
}
