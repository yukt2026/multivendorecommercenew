<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Store;
use App\Services\Seller\StoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StoreOutletController extends Controller
{
    public function __construct(
        protected StoreService $storeService
    ) {}

    /**
     * List all physical store outlets owned by the seller.
     */
    public function index(Request $request): View
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403, 'No seller profile associated with this account.');

        $stores = $seller->stores()
            ->with(['location', 'timings', 'staff.user', 'inventoryLocations'])
            ->latest()
            ->get();

        $activeStoreId = session('seller_active_store_id');
        $activeStore = $activeStoreId ? $stores->firstWhere('id', $activeStoreId) : null;

        return view('seller.stores.index', [
            'stores' => $stores,
            'activeStore' => $activeStore,
            'seller' => $seller,
            'totalStores' => $stores->count(),
            'activeCount' => $stores->where('status', StoreStatus::ACTIVE)->count(),
            'quickCount' => $stores->where('accepts_quick_commerce', true)->count(),
        ]);
    }

    /**
     * Show form to create a new physical store outlet.
     */
    public function create(): View
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);

        return view('seller.stores.create', [
            'seller' => $seller,
            'categories' => $categories,
            'defaultDeliveryRadius' => (float) setting('default_delivery_radius_km', 8.00),
        ]);
    }

    /**
     * Store newly created store outlet.
     */
    public function store(Request $request): RedirectResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:stores,slug',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pincode' => 'required|string|max:20',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'delivery_radius_km' => 'nullable|numeric|min:0.5|max:100',
            'accepts_quick_commerce' => 'nullable|boolean',
            'quick_max_orders' => 'nullable|integer|min:1|max:200',
            'quick_prep_time_minutes' => 'nullable|integer|min:1|max:120',
            'quick_order_timeout_seconds' => 'nullable|integer|min:30|max:600',
            'accepts_pickup' => 'nullable|boolean',
            'opens_at' => 'nullable|date_format:H:i',
            'closes_at' => 'nullable|date_format:H:i',
            'category_ids' => 'nullable|array',
        ]);

        $store = $this->storeService->createStore($seller, $validated);

        // Auto switch active store to newly created outlet
        session(['seller_active_store_id' => $store->id]);

        return redirect()->route('seller.stores.index')
            ->with('success', "Store outlet '{$store->name}' created successfully with dedicated inventory shelf!");
    }

    /**
     * Display store-level performance reports and analytics.
     */
    public function reports(Request $request): View
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $stores = $seller->stores()->get();
        $storeIdParam = $request->query('store_id', session('seller_active_store_id'));
        $storeId = (!empty($storeIdParam) && $storeIdParam !== 'all') ? (int) $storeIdParam : null;
        $range = (string) $request->query('range', '30d');

        $selectedStore = $storeId ? $stores->firstWhere('id', $storeId) : null;
        $reportData = $this->storeService->getStoreReports($seller, $storeId, $range);

        return view('seller.stores.reports', [
            'seller' => $seller,
            'stores' => $stores,
            'selectedStore' => $selectedStore,
            'range' => $range,
            'report' => $reportData,
        ]);
    }
}
