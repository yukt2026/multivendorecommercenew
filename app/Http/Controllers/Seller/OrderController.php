<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Enums\DeliveryAssignmentStatus;
use App\Enums\FulfilmentType;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Models\FulfilmentOrder;
use App\Models\Store;
use App\Models\StoreStaff;
use App\Services\Order\OrderPdfService;
use App\Services\Order\OrderStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        protected OrderStateMachine $stateMachine,
        protected OrderPdfService $pdfService
    ) {}

    /**
     * Display seller orders list with tabs: All / Quick / Online / Pickup.
     */
    public function index(Request $request): View
    {
        $seller = Auth::user()->seller;
        if (!$seller) {
            abort(403, 'Unauthorized seller account.');
        }

        $tab = $request->query('tab', 'all');
        $status = $request->query('status');
        $storeId = $request->query('store_id');
        $search = $request->query('search');

        // Base query scoped strictly to logged-in seller
        $baseQuery = FulfilmentOrder::query()
            ->where('seller_id', $seller->id)
            ->with(['order.customer.user', 'items.variant', 'store', 'inventoryLocation']);

        // Tab counts
        $counts = [
            'all' => (clone $baseQuery)->count(),
            'quick' => (clone $baseQuery)->where('fulfilment_type', FulfilmentType::QUICK)->count(),
            'online' => (clone $baseQuery)->where('fulfilment_type', FulfilmentType::STANDARD)->count(),
            'pickup' => (clone $baseQuery)->where('fulfilment_type', FulfilmentType::PICKUP)->count(),
            'action_needed' => (clone $baseQuery)->whereIn('status', ['placed', 'store_accepted'])->count(),
        ];

        // Apply Tab Filter
        if ($tab === 'quick') {
            $baseQuery->where('fulfilment_type', FulfilmentType::QUICK);
        } elseif ($tab === 'online') {
            $baseQuery->where('fulfilment_type', FulfilmentType::STANDARD);
        } elseif ($tab === 'pickup') {
            $baseQuery->where('fulfilment_type', FulfilmentType::PICKUP);
        }

        // Apply Status Filter
        if ($status && $status !== 'all') {
            $baseQuery->where('status', $status);
        }

        // Apply Store Filter
        if ($storeId) {
            $baseQuery->where('store_id', (int) $storeId);
        }

        // Apply Search Filter
        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('fulfilment_order_number', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($oq) use ($search) {
                        $oq->where('order_number', 'like', "%{$search}%")
                            ->orWhere('address_snapshot', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items', function ($iq) use ($search) {
                        $iq->where('title', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $baseQuery->latest()->paginate(15)->withQueryString();
        $stores = Store::where('seller_id', $seller->id)->get();

        return view('seller.orders.index', [
            'orders' => $orders,
            'tab' => $tab,
            'status' => $status ?? 'all',
            'storeId' => $storeId,
            'search' => $search ?? '',
            'counts' => $counts,
            'stores' => $stores,
        ]);
    }

    /**
     * Show detailed fulfilment order management console.
     */
    public function show(FulfilmentOrder $fulfilmentOrder): View
    {
        $seller = Auth::user()->seller;
        if (!$seller || $fulfilmentOrder->seller_id !== $seller->id) {
            abort(403, 'Unauthorized access to this order.');
        }

        $fulfilmentOrder->loadMissing([
            'order.customer.user',
            'items.variant',
            'store',
            'inventoryLocation',
            'shipment.tracking',
            'deliveryAssignment.deliveryPartner',
            'statusHistory.changedByUser',
        ]);

        $allowedNext = $this->stateMachine->getAllowedNextFulfilmentStatuses($fulfilmentOrder);

        // Fetch eligible staff / riders for assignment
        $storeStaff = $fulfilmentOrder->store_id 
            ? StoreStaff::where('store_id', $fulfilmentOrder->store_id)->with('user')->get() 
            : collect();
        $deliveryPartners = DeliveryPartner::where('is_active', true)->get();

        return view('seller.orders.show', [
            'fo' => $fulfilmentOrder,
            'allowedNext' => $allowedNext,
            'storeStaff' => $storeStaff,
            'deliveryPartners' => $deliveryPartners,
        ]);
    }

    /**
     * Perform state machine transition (Accept, Pack, Ready, Ship, etc.).
     */
    public function transition(Request $request, FulfilmentOrder $fulfilmentOrder): RedirectResponse
    {
        $seller = Auth::user()->seller;
        if (!$seller || $fulfilmentOrder->seller_id !== $seller->id) {
            abort(403, 'Unauthorized access.');
        }

        $request->validate([
            'target_status' => 'required|string',
            'note' => 'nullable|string|max:255',
        ]);

        $target = $request->input('target_status');
        $note = $request->input('note');

        try {
            $this->stateMachine->transitionFulfilmentOrder(
                $fulfilmentOrder,
                $target,
                $note,
                Auth::id()
            );

            return back()->with('success', "Order #{$fulfilmentOrder->fulfilment_order_number} transitioned to " . ucwords(str_replace('_', ' ', $target)) . " successfully!");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Assign delivery staff / rider to a Quick Commerce package.
     */
    public function assignStaff(Request $request, FulfilmentOrder $fulfilmentOrder): RedirectResponse
    {
        $seller = Auth::user()->seller;
        if (!$seller || $fulfilmentOrder->seller_id !== $seller->id) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'delivery_partner_id' => 'nullable|exists:delivery_partners,id',
            'staff_user_id' => 'nullable|exists:users,id',
        ]);

        $partnerId = $request->input('delivery_partner_id');

        // Create or update DeliveryAssignment
        DeliveryAssignment::updateOrCreate(
            ['fulfilment_order_id' => $fulfilmentOrder->id],
            [
                'delivery_partner_id' => $partnerId,
                'status' => DeliveryAssignmentStatus::ASSIGNED,
                'assigned_at' => now(),
            ]
        );

        // Transition status if allowed
        if ($this->stateMachine->canTransitionFulfilment($fulfilmentOrder, 'rider_assigned')) {
            $this->stateMachine->transitionFulfilmentOrder(
                $fulfilmentOrder,
                'rider_assigned',
                "Assigned to delivery staff / partner ID #{$partnerId}",
                Auth::id()
            );
        }

        return back()->with('success', 'Rider / staff assigned to order successfully!');
    }

    /**
     * Download Packing Slip PDF.
     */
    public function packingSlip(FulfilmentOrder $fulfilmentOrder): Response
    {
        $seller = Auth::user()->seller;
        if (!$seller || $fulfilmentOrder->seller_id !== $seller->id) {
            abort(403);
        }

        return $this->pdfService->generatePackingSlipPdf($fulfilmentOrder);
    }

    /**
     * Download Tax Invoice PDF.
     */
    public function invoice(FulfilmentOrder $fulfilmentOrder): Response
    {
        $seller = Auth::user()->seller;
        if (!$seller || $fulfilmentOrder->seller_id !== $seller->id) {
            abort(403);
        }

        if (!$fulfilmentOrder->order) {
            abort(404, 'Parent order not found.');
        }

        return $this->pdfService->generateInvoicePdf($fulfilmentOrder->order);
    }

    /**
     * Download Shipping Label PDF.
     */
    public function shippingLabel(FulfilmentOrder $fulfilmentOrder): Response
    {
        $seller = Auth::user()->seller;
        if (!$seller || $fulfilmentOrder->seller_id !== $seller->id) {
            abort(403);
        }

        return $this->pdfService->generateShippingLabelPdf($fulfilmentOrder);
    }

    /**
     * API for Topbar audio alert & pending orders live badge polling.
     */
    public function pendingCount(Request $request): JsonResponse
    {
        $seller = Auth::user()?->seller;
        if (!$seller) {
            return response()->json(['pending_count' => 0, 'new_orders' => 0]);
        }

        $pendingCount = FulfilmentOrder::where('seller_id', $seller->id)
            ->whereIn('status', ['placed', 'store_accepted'])
            ->count();

        // Orders placed in the last 60 seconds
        $freshPlaced = FulfilmentOrder::where('seller_id', $seller->id)
            ->where('status', 'placed')
            ->where('created_at', '>=', now()->subSeconds(45))
            ->count();

        return response()->json([
            'pending_count' => $pendingCount,
            'has_fresh' => $freshPlaced > 0,
            'timestamp' => now()->timestamp,
        ]);
    }
}
