<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Enums\FulfilmentType;
use App\Enums\StockMovementType;
use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\FulfilmentOrder;
use App\Models\Inventory;
use App\Models\OrderStatusHistory;
use App\Models\StockMovement;
use App\Models\Store;
use App\Services\Seller\SellerDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected SellerDashboardService $dashboardService
    ) {}

    /**
     * Display isolated seller dashboard.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $seller = $user?->seller;

        abort_if(!$seller, 403, 'User account is not linked to an active merchant profile.');

        // Multi-store resolution
        $stores = $seller->stores()->with('location')->get();
        $activeStoreId = session('seller_active_store_id');

        // Check if query parameter sets or resets the active store
        if ($request->has('store_id')) {
            $param = $request->query('store_id');
            if ($param === 'all' || empty($param)) {
                session()->forget('seller_active_store_id');
                $activeStoreId = null;
            } else {
                $foundStore = $stores->firstWhere('id', (int) $param);
                if ($foundStore) {
                    session(['seller_active_store_id' => $foundStore->id]);
                    $activeStoreId = $foundStore->id;
                }
            }
        }

        // Verify activeStore exists among seller's stores
        $activeStore = $activeStoreId ? $stores->firstWhere('id', $activeStoreId) : null;
        if ($activeStoreId && !$activeStore) {
            session()->forget('seller_active_store_id');
            $activeStoreId = null;
        }

        $range = (string) $request->query('range', '30d');
        $data = $this->dashboardService->getDashboardData($seller, $activeStore?->id, $range);

        return view('seller.dashboard', array_merge($data, [
            'seller' => $seller,
            'stores' => $stores,
            'activeStore' => $activeStore,
            'range' => $range,
        ]));
    }

    /**
     * Return JSON charts payload for dynamic AJAX filtering.
     */
    public function chartData(Request $request): JsonResponse
    {
        $user = Auth::user();
        $seller = $user?->seller;
        abort_if(!$seller, 403);

        $storeId = session('seller_active_store_id');
        $range = (string) $request->query('range', '30d');

        $charts = $this->dashboardService->getChartsData($seller, $storeId ? (int) $storeId : null, $range);

        return response()->json($charts);
    }

    /**
     * Switch active store for multi-store merchants (scopes entire panel).
     */
    public function switchStore(Request $request): RedirectResponse
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller, 403);

        $storeId = $request->input('store_id');

        if (empty($storeId) || $storeId === 'all') {
            session()->forget('seller_active_store_id');
            return back()->with('success', 'Switched context to All Outlets (Aggregated View).');
        }

        $store = $seller->stores()->findOrFail($storeId);
        session(['seller_active_store_id' => $store->id]);

        return back()->with('success', "Scoped dashboard context to {$store->name}.");
    }

    /**
     * Toggle active store status (Open for orders vs Closed).
     */
    public function toggleStoreStatus(Request $request): JsonResponse|RedirectResponse
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller, 403);

        $storeId = $request->input('store_id');
        $store = $storeId ? $seller->stores()->findOrFail($storeId) : $seller->stores()->first();

        abort_if(!$store, 404, 'Store outlet not found.');

        $newStatus = $store->status === StoreStatus::ACTIVE ? StoreStatus::INACTIVE : StoreStatus::ACTIVE;
        $store->update(['status' => $newStatus]);

        $this->dashboardService->clearCache($seller->id);

        $statusLabel = $newStatus === StoreStatus::ACTIVE ? 'Open & Live' : 'Temporarily Closed';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_open' => $newStatus === StoreStatus::ACTIVE,
                'status_label' => $statusLabel,
                'message' => "Store outlet is now {$statusLabel}.",
            ]);
        }

        return back()->with('success', "Store status updated: {$statusLabel}.");
    }

    /**
     * Inline Action: Accept incoming order for fulfillment.
     */
    public function acceptOrder(Request $request, FulfilmentOrder $fulfilmentOrder): JsonResponse|RedirectResponse
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller || $fulfilmentOrder->seller_id !== $seller->id, 403);

        $oldStatus = $fulfilmentOrder->status;
        $newStatus = $fulfilmentOrder->fulfilment_type === FulfilmentType::QUICK ? 'store_accepted' : 'confirmed';

        $fulfilmentOrder->update(['status' => $newStatus]);

        OrderStatusHistory::create([
            'orderable_type' => FulfilmentOrder::class,
            'orderable_id' => $fulfilmentOrder->id,
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'note' => 'Order accepted by store manager for fulfillment.',
            'changed_by' => Auth::id(),
        ]);

        $this->dashboardService->clearCache($seller->id);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "Order #{$fulfilmentOrder->fulfilment_order_number} accepted."]);
        }

        return back()->with('success', "Order #{$fulfilmentOrder->fulfilment_order_number} marked Accepted.");
    }

    /**
     * Inline Action: Mark order packing in progress.
     */
    public function packOrder(Request $request, FulfilmentOrder $fulfilmentOrder): JsonResponse|RedirectResponse
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller || $fulfilmentOrder->seller_id !== $seller->id, 403);

        $oldStatus = $fulfilmentOrder->status;
        $newStatus = 'packing';

        $fulfilmentOrder->update(['status' => $newStatus]);

        OrderStatusHistory::create([
            'orderable_type' => FulfilmentOrder::class,
            'orderable_id' => $fulfilmentOrder->id,
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'note' => 'Items gathered and packing underway.',
            'changed_by' => Auth::id(),
        ]);

        $this->dashboardService->clearCache($seller->id);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "Order #{$fulfilmentOrder->fulfilment_order_number} is now packing."]);
        }

        return back()->with('success', "Order #{$fulfilmentOrder->fulfilment_order_number} marked Packing.");
    }

    /**
     * Inline Action: Mark order ready for courier pickup / rider handover.
     */
    public function readyOrder(Request $request, FulfilmentOrder $fulfilmentOrder): JsonResponse|RedirectResponse
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller || $fulfilmentOrder->seller_id !== $seller->id, 403);

        $oldStatus = $fulfilmentOrder->status;
        $newStatus = $fulfilmentOrder->fulfilment_type === FulfilmentType::QUICK ? 'ready' : 'ready_to_ship';

        $fulfilmentOrder->update(['status' => $newStatus]);

        OrderStatusHistory::create([
            'orderable_type' => FulfilmentOrder::class,
            'orderable_id' => $fulfilmentOrder->id,
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'note' => 'Order packed and placed at dispatch counter ready for handover.',
            'changed_by' => Auth::id(),
        ]);

        $this->dashboardService->clearCache($seller->id);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "Order #{$fulfilmentOrder->fulfilment_order_number} ready for handover."]);
        }

        return back()->with('success', "Order #{$fulfilmentOrder->fulfilment_order_number} marked Ready For Dispatch.");
    }

    /**
     * Quick Restock Modal Submission: replenish available inventory.
     */
    public function quickRestock(Request $request, Inventory $inventory): JsonResponse|RedirectResponse
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller, 403);

        // Security check: ensure inventory location belongs to this seller
        $inventory->loadMissing('location');
        abort_if(!$inventory->location || $inventory->location->seller_id !== $seller->id, 403, 'Unauthorized inventory location.');

        $request->validate([
            'added_quantity' => ['required', 'integer', 'min:1', 'max:50000'],
        ]);

        $addedQty = (int) $request->input('added_quantity');

        DB::transaction(function () use ($inventory, $addedQty) {
            $inventory->increment('quantity_on_hand', $addedQty);

            StockMovement::create([
                'inventory_id' => $inventory->id,
                'type' => StockMovementType::PURCHASE,
                'quantity' => $addedQty,
                'note' => 'Quick restock adjustment via merchant dashboard.',
                'created_by' => Auth::id(),
            ]);
        });

        $this->dashboardService->clearCache($seller->id);

        $variantName = $inventory->variant?->name ?? 'Product SKU';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'new_quantity' => $inventory->fresh()->quantity_on_hand,
                'available_quantity' => $inventory->fresh()->available_quantity,
                'message' => "Restocked +{$addedQty} units for {$variantName}.",
            ]);
        }

        return back()->with('success', "Added +{$addedQty} units to {$variantName}. Stock replenished!");
    }
}
