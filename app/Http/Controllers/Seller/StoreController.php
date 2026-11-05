<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\UpdateStoreRequest;
use App\Models\Category;
use App\Models\Store;
use App\Services\Seller\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function __construct(
        protected StoreService $storeService
    ) {}

    /**
     * Display the My Store configuration screen.
     */
    public function edit(Request $request): View
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403, 'No seller profile associated with this account.');

        $activeStoreId = session('seller_active_store_id');
        $store = $this->storeService->getSellerStore($seller, $activeStoreId ? (int) $activeStoreId : null);

        // Fetch parent categories for store category tagging
        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);

        $publicStoreUrl = url("/store/{$store->slug}");

        return view('seller.store.edit', [
            'store' => $store,
            'seller' => $seller,
            'categories' => $categories,
            'publicStoreUrl' => $publicStoreUrl,
            'defaultDeliveryRadius' => (float) setting('default_delivery_radius_km', 8.00),
            'defaultDeliveryFee' => (float) setting('default_delivery_fee', 30.00),
            'defaultMinOrder' => (float) setting('min_order_value', 99.00),
        ]);
    }

    /**
     * Update store parameters, media branding, location, timings, and delivery zones.
     */
    public function update(UpdateStoreRequest $request): RedirectResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $activeStoreId = session('seller_active_store_id');
        $store = $this->storeService->getSellerStore($seller, $activeStoreId ? (int) $activeStoreId : null);

        $this->storeService->updateStore(
            $store,
            $request->validated(),
            $request->file('logo'),
            $request->file('banner')
        );

        return redirect()->route('seller.store.edit')
            ->with('success', 'Storefront details, operating timings, and delivery zones saved successfully!');
    }

    /**
     * Live AJAX check for slug availability and reserved word filtering.
     */
    public function checkSlug(Request $request): JsonResponse
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller, 403);

        $activeStoreId = session('seller_active_store_id') ?: $seller->stores()->first()?->id;
        $slug = (string) $request->input('slug', '');

        $result = $this->storeService->checkSlugAvailability($slug, $activeStoreId ? (int) $activeStoreId : null);

        return response()->json($result);
    }

    /**
     * Generate / deliver QR code image for the public store URL.
     */
    public function qrCode(Request $request): Response
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller, 403);

        $activeStoreId = session('seller_active_store_id');
        $store = $this->storeService->getSellerStore($seller, $activeStoreId ? (int) $activeStoreId : null);

        $storeUrl = url("/store/{$store->slug}");
        $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=" . urlencode($storeUrl);

        try {
            $imageContent = file_get_contents($qrApiUrl);
            if ($imageContent !== false) {
                return response($imageContent, 200, [
                    'Content-Type' => 'image/png',
                    'Content-Disposition' => 'inline; filename="qr-' . $store->slug . '.png"',
                ]);
            }
        } catch (\Throwable) {
            // Fallback placeholder
        }

        return redirect($qrApiUrl);
    }
}
