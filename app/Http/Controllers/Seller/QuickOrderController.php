<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Enums\DeliveryAssignmentStatus;
use App\Enums\FulfilmentType;
use App\Http\Controllers\Controller;
use App\Models\DeliveryPartner;
use App\Models\FulfilmentOrder;
use App\Models\Store;
use App\Services\Order\OrderStateMachine;
use App\Services\QuickCommerce\QuickCommerceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class QuickOrderController extends Controller
{
    public function __construct(
        protected OrderStateMachine $stateMachine,
        protected QuickCommerceService $quickService
    ) {}

    /**
     * Realtime Incoming Order Screen (/seller/orders/quick-live).
     */
    public function liveConsole(Request $request): View
    {
        $seller = Auth::user()->seller;
        if (!$seller) {
            abort(403, 'Seller profile required.');
        }

        $activeStoreId = session('active_store_id');
        $store = $activeStoreId ? Store::find($activeStoreId) : $seller->stores()->first();

        $activeQuickOrders = FulfilmentOrder::query()
            ->with(['order.customerAddress', 'items.product', 'deliveryAssignment.deliveryPartner'])
            ->where('seller_id', $seller->id)
            ->where('fulfilment_type', FulfilmentType::QUICK)
            ->when($store, fn ($q) => $q->where('store_id', $store->id))
            ->whereIn('status', ['placed', 'store_accepted', 'packing', 'ready', 'rider_assigned'])
            ->latest('id')
            ->get();

        $availableRiders = DeliveryPartner::query()
            ->where('is_active', true)
            ->get();

        return view('seller.orders.quick-live', [
            'store' => $store,
            'activeQuickOrders' => $activeQuickOrders,
            'availableRiders' => $availableRiders,
            'timeoutSeconds' => $store ? $store->effectiveQuickTimeout() : 120,
        ]);
    }

    /**
     * Polling fallback endpoint for live quick orders console.
     */
    public function pendingOrdersApi(Request $request): JsonResponse
    {
        $seller = Auth::user()->seller;
        if (!$seller) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $activeStoreId = session('active_store_id');

        $orders = FulfilmentOrder::query()
            ->with(['order.customerAddress', 'items', 'deliveryAssignment.deliveryPartner'])
            ->where('seller_id', $seller->id)
            ->where('fulfilment_type', FulfilmentType::QUICK)
            ->when($activeStoreId, fn ($q) => $q->where('store_id', $activeStoreId))
            ->whereIn('status', ['placed', 'store_accepted', 'packing', 'ready', 'rider_assigned'])
            ->latest('id')
            ->get()
            ->map(function ($fo) {
                $timeout = $fo->store ? $fo->store->effectiveQuickTimeout() : 120;
                $elapsed = now()->diffInSeconds($fo->created_at ?? now());
                $remaining = max(0, $timeout - $elapsed);

                return [
                    'id' => $fo->id,
                    'order_number' => $fo->fulfilment_order_number,
                    'status' => $fo->status,
                    'items_count' => $fo->items->sum('qty'),
                    'items' => $fo->items->map(fn ($it) => [
                        'title' => $it->title,
                        'qty' => $it->qty,
                        'variant_name' => $it->variant_name,
                    ]),
                    'subtotal' => $fo->subtotal,
                    'delivery_fee' => $fo->delivery_fee,
                    'total' => $fo->total_amount,
                    'elapsed_seconds' => $elapsed,
                    'remaining_seconds' => $remaining,
                    'customer_name' => $fo->order?->customerAddress?->name ?? 'Customer',
                    'customer_address' => $fo->order?->customerAddress?->line1 ?? '',
                    'rider' => $fo->deliveryAssignment?->deliveryPartner ? [
                        'name' => $fo->deliveryAssignment->deliveryPartner->name,
                        'phone' => $fo->deliveryAssignment->deliveryPartner->phone,
                    ] : null,
                ];
            });

        return response()->json([
            'count' => $orders->count(),
            'orders' => $orders,
        ]);
    }

    /**
     * Accept incoming quick commerce order.
     */
    public function accept(Request $request, FulfilmentOrder $fulfilmentOrder): JsonResponse|RedirectResponse
    {
        $this->authorizeOrder($fulfilmentOrder);

        try {
            $this->stateMachine->transitionFulfilmentOrder(
                $fulfilmentOrder,
                'store_accepted',
                'Order accepted by store staff',
                Auth::id()
            );

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Order accepted!']);
            }

            return back()->with('success', "Order #{$fulfilmentOrder->fulfilment_order_number} accepted!");
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reject incoming quick commerce order (auto triggers rerouting to next eligible store).
     */
    public function reject(Request $request, FulfilmentOrder $fulfilmentOrder): JsonResponse|RedirectResponse
    {
        $this->authorizeOrder($fulfilmentOrder);

        $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $reason = $request->input('reason', 'Store rejected order due to item unavailability / high load');

        try {
            $this->stateMachine->transitionFulfilmentOrder(
                $fulfilmentOrder,
                'rejected',
                $reason,
                Auth::id()
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Order rejected and auto-rerouted to fallback location.',
                ]);
            }

            return back()->with('notice', "Order #{$fulfilmentOrder->fulfilment_order_number} rejected and rerouted.");
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Mark package packing started.
     */
    public function pack(Request $request, FulfilmentOrder $fulfilmentOrder): JsonResponse|RedirectResponse
    {
        $this->authorizeOrder($fulfilmentOrder);

        try {
            $this->stateMachine->transitionFulfilmentOrder(
                $fulfilmentOrder,
                'packing',
                'Staff is packing items',
                Auth::id()
            );

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Order marked packing!']);
            }

            return back()->with('success', 'Order marked packing!');
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Mark package packed and ready for pickup (auto triggers rider assignment).
     */
    public function ready(Request $request, FulfilmentOrder $fulfilmentOrder): JsonResponse|RedirectResponse
    {
        $this->authorizeOrder($fulfilmentOrder);

        try {
            $this->stateMachine->transitionFulfilmentOrder(
                $fulfilmentOrder,
                'ready',
                'Order packed and ready for pickup',
                Auth::id()
            );

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Order marked ready for pickup!']);
            }

            return back()->with('success', 'Order marked ready for pickup!');
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Assign delivery rider manually or trigger nearest auto-assignment.
     */
    public function assignRider(Request $request, FulfilmentOrder $fulfilmentOrder): JsonResponse|RedirectResponse
    {
        $this->authorizeOrder($fulfilmentOrder);

        $partnerId = $request->input('delivery_partner_id');

        if ($partnerId) {
            $partner = DeliveryPartner::findOrFail($partnerId);
            $this->quickService->assignPartnerToOrder($fulfilmentOrder, $partner, 'Manually assigned by store manager');
        } else {
            $assigned = $this->quickService->autoAssignRider($fulfilmentOrder);
            if (!$assigned) {
                $msg = 'No nearby active delivery partners available right now.';
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $msg], 422);
                }
                return back()->with('error', $msg);
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Rider assigned successfully!']);
        }

        return back()->with('success', 'Rider assigned successfully!');
    }

    protected function authorizeOrder(FulfilmentOrder $fo): void
    {
        $seller = Auth::user()->seller;
        if (!$seller || $fo->seller_id !== $seller->id) {
            abort(403, 'Unauthorized access to this order.');
        }
    }
}
