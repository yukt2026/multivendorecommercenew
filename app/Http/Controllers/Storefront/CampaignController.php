<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    /**
     * List all active promotional & festival marketing campaigns.
     */
    public function index(Request $request): View
    {
        $campaigns = Campaign::active()
            ->orderBy('starts_at', 'desc')
            ->paginate(12);

        $coupons = Coupon::active()
            ->whereNull('seller_id')
            ->orderBy('min_order')
            ->take(6)
            ->get();

        return view('storefront.campaigns.index', compact('campaigns', 'coupons'));
    }

    /**
     * Display a specific festival or promotional campaign landing page.
     */
    public function show(string $slug): View
    {
        $campaign = Campaign::where('slug', $slug)->firstOrFail();

        // Featured products for this campaign: discounted / on-sale products
        $products = Product::active()
            ->with(['media', 'defaultVariant', 'variants', 'brand', 'seller'])
            ->whereHas('variants', function ($q) {
                $q->where('is_active', true)
                    ->whereRaw('mrp > selling_price');
            })
            ->latest('id')
            ->paginate(16);

        if ($products->isEmpty()) {
            $products = Product::active()
                ->with(['media', 'defaultVariant', 'variants', 'brand', 'seller'])
                ->latest('id')
                ->paginate(16);
        }

        // Platform coupons valid during this campaign
        $coupons = Coupon::active()
            ->whereNull('seller_id')
            ->orderBy('min_order')
            ->take(4)
            ->get();

        return view('storefront.campaigns.show', compact('campaign', 'products', 'coupons'));
    }
}
