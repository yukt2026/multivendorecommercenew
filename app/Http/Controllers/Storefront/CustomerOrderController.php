<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Models\FulfilmentOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\ShipmentTracking;
use App\Services\Order\OrderPdfService;
use App\Services\Order\OrderStateMachine;
use App\Services\Order\ReturnService;
use App\Services\Storefront\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerOrderController extends Controller
{
    public function __construct(
        protected OrderStateMachine $stateMachine,
        protected OrderPdfService $pdfService,
        protected CartService $cartService,
        protected ReturnService $returnService
    ) {}

    /**
     * Display customer's orders list.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $customer = $user->customer;

        $query = Order::query()
            ->where('customer_id', $customer?->id ?? 0)
            ->with(['fulfilmentOrders.items.variant', 'fulfilmentOrders.store', 'fulfilmentOrders.seller', 'payments'])
            ->latest();

        // Status Filter
        $status = $request->query('status');
        if ($status && $status !== 'all') {
            if ($status === 'processing') {
                $query->whereIn('status', [OrderStatus::PLACED, OrderStatus::CONFIRMED, OrderStatus::PROCESSING, OrderStatus::READY_FOR_PICKUP, OrderStatus::OUT_FOR_DELIVERY]);
            } elseif ($status === 'delivered') {
                $query->where('status', OrderStatus::DELIVERED);
            } elseif ($status === 'cancelled') {
                $query->where('status', OrderStatus::CANCELLED);
            }
        }

        // Search
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('fulfilmentOrders.items', function ($iq) use ($search) {
                        $iq->where('title', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $query->paginate(10)->withQueryString();

        return view('storefront.orders.index', [
            'orders' => $orders,
            'currentStatus' => $status ?? 'all',
            'search' => $search ?? '',
        ]);
    }

    /**
     * Show order details with per-fulfilment-order timeline.
     */
    public function show(Request $request, string $orderNumber): View
    {
        $user = Auth::user();
        $customer = $user->customer;

        $order = Order::where('order_number', $orderNumber)
            ->where('customer_id', $customer?->id ?? 0)
            ->with([
                'fulfilmentOrders.items.variant',
                'fulfilmentOrders.store',
                'fulfilmentOrders.seller',
                'fulfilmentOrders.shipment.tracking',
                'payments',
                'statusHistory.changedByUser',
            ])
            ->firstOrFail();

        // Check if customer can cancel this order
        $canCancel = in_array($order->status, [OrderStatus::PLACED, OrderStatus::PENDING, OrderStatus::PROCESSING], true);
        if ($canCancel) {
            foreach ($order->fulfilmentOrders as $fo) {
                if (in_array(strtolower($fo->status), ['picked_up', 'in_transit', 'out_for_delivery', 'delivered'], true)) {
                    $canCancel = false;
                    break;
                }
            }
        }

        return view('storefront.orders.show', [
            'order' => $order,
            'canCancel' => $canCancel,
        ]);
    }

    /**
     * Download Tax Invoice PDF.
     */
    public function invoice(Request $request, string $orderNumber): Response
    {
        $user = Auth::user();
        $customer = $user->customer;

        $order = Order::where('order_number', $orderNumber)
            ->where('customer_id', $customer?->id ?? 0)
            ->firstOrFail();

        return $this->pdfService->generateInvoicePdf($order);
    }

    /**
     * Customer cancels order (while allowed).
     */
    public function cancel(Request $request, string $orderNumber): RedirectResponse
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        $customer = $user->customer;

        $order = Order::where('order_number', $orderNumber)
            ->where('customer_id', $customer?->id ?? 0)
            ->firstOrFail();

        try {
            $this->stateMachine->cancelOrder(
                $order,
                $request->input('reason'),
                $user->id
            );

            return redirect()->route('orders.show', $order->order_number)
                ->with('success', 'Order has been cancelled successfully. Any active stock holds have been released.');
        } catch (\Throwable $e) {
            return redirect()->route('orders.show', $order->order_number)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Reorder items from this order into customer cart.
     */
    public function reorder(Request $request, string $orderNumber): RedirectResponse
    {
        $user = Auth::user();
        $customer = $user->customer;

        $order = Order::where('order_number', $orderNumber)
            ->where('customer_id', $customer?->id ?? 0)
            ->with(['fulfilmentOrders.items.variant.product'])
            ->firstOrFail();

        $cart = $this->cartService->getActiveCart($user, session()->getId());
        $addedCount = 0;

        foreach ($order->fulfilmentOrders as $fo) {
            foreach ($fo->items as $item) {
                if ($item->variant && $item->variant->is_active && ($item->variant->product?->status === \App\Enums\ProductStatus::ACTIVE)) {
                    try {
                        $this->cartService->addItem($cart, $item->variant->id, $item->qty);
                        $addedCount++;
                    } catch (\Throwable) {
                        // Skip if out of stock
                    }
                }
            }
        }

        if ($addedCount > 0) {
            return redirect()->route('cart.index')
                ->with('success', "{$addedCount} item(s) from Order #{$order->order_number} added to your cart!");
        }

        return redirect()->route('orders.show', $order->order_number)
            ->with('error', 'None of the items from this order could be re-added (currently inactive or out of stock).');
    }

    /**
     * Track shipment / live milestone view for a fulfilment package.
     */
    public function track(Request $request, string $orderNumber, string $foNumber): View
    {
        $user = Auth::user();
        $customer = $user->customer;

        $order = Order::where('order_number', $orderNumber)
            ->where('customer_id', $customer?->id ?? 0)
            ->firstOrFail();

        $fo = FulfilmentOrder::where('order_id', $order->id)
            ->where('fulfilment_order_number', $foNumber)
            ->with(['items.variant', 'store', 'seller', 'shipment.tracking', 'order.customerAddress'])
            ->firstOrFail();

        return view('storefront.orders.track', [
            'order' => $order,
            'fo' => $fo,
        ]);
    }

    /**
     * Display Return Request form for an eligible order item.
     */
    public function returnForm(string $orderNumber, int $itemId): View|RedirectResponse
    {
        $user = Auth::user();
        $customer = $user->customer;

        $order = Order::where('order_number', $orderNumber)
            ->where(function ($q) use ($customer, $user) {
                if ($customer) {
                    $q->where('customer_id', $customer->id);
                } else {
                    $q->where('user_id', $user->id);
                }
            })
            ->firstOrFail();

        $item = OrderItem::where('id', $itemId)
            ->whereHas('fulfilmentOrder', fn($q) => $q->where('order_id', $order->id))
            ->with(['product.brand', 'variant', 'fulfilmentOrder.seller'])
            ->firstOrFail();

        $eligibility = $this->returnService->canRequestReturn($item);
        if (!$eligibility['allowed']) {
            return redirect()->route('orders.show', $order->order_number)
                ->with('error', $eligibility['reason'] ?? 'This item is not eligible for return.');
        }

        return view('storefront.orders.return-form', compact('order', 'item', 'eligibility'));
    }

    /**
     * Submit Return Request per item with reason, customer notes, photos, and refund mode.
     */
    public function submitItemReturn(Request $request, string $orderNumber, int $itemId): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['nullable', 'image', 'max:5120'], // max 5MB per photo
            'refund_mode' => ['required', 'in:gateway,wallet'],
        ]);

        $user = Auth::user();
        $customer = $user->customer;

        $order = Order::where('order_number', $orderNumber)
            ->where(function ($q) use ($customer, $user) {
                if ($customer) {
                    $q->where('customer_id', $customer->id);
                } else {
                    $q->where('user_id', $user->id);
                }
            })
            ->firstOrFail();

        $item = OrderItem::where('id', $itemId)
            ->whereHas('fulfilmentOrder', fn($q) => $q->where('order_id', $order->id))
            ->firstOrFail();

        try {
            $return = $this->returnService->requestReturn($item, $validated, $user);

            return redirect()->route('orders.return.track', [$order->order_number, $return->return_number])
                ->with('success', "Return request #{$return->return_number} submitted successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Customer cancels a specific item before dispatch.
     */
    public function cancelItem(Request $request, string $orderNumber, int $itemId): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $user = Auth::user();
        $customer = $user->customer;

        $order = Order::where('order_number', $orderNumber)
            ->where(function ($q) use ($customer, $user) {
                if ($customer) {
                    $q->where('customer_id', $customer->id);
                } else {
                    $q->where('user_id', $user->id);
                }
            })
            ->firstOrFail();

        $item = OrderItem::where('id', $itemId)
            ->whereHas('fulfilmentOrder', fn($q) => $q->where('order_id', $order->id))
            ->firstOrFail();

        $canCancel = $this->returnService->canCancelItem($item);
        if (!$canCancel['allowed']) {
            return back()->with('error', $canCancel['reason']);
        }

        $returnNumber = 'CAN-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        $refundAmount = (float) ($item->total ?? ($item->price * $item->qty));

        $cancellation = OrderReturn::create([
            'return_number' => $returnNumber,
            'type' => 'cancellation',
            'order_item_id' => $item->id,
            'reason' => $validated['reason'],
            'status' => ReturnStatus::CANCELLED,
            'qc_status' => \App\Enums\QCStatus::WAIVED,
            'refund_mode' => 'gateway',
            'refund_amount' => $refundAmount,
            'rejection_reason' => 'Item cancelled by customer before dispatch',
        ]);

        // Process refund for the cancelled item
        $this->returnService->processRefund($cancellation, $refundAmount, $user);

        return redirect()->route('orders.show', $order->order_number)
            ->with('success', "Item '{$item->title}' has been cancelled and refund processed.");
    }

    /**
     * View return tracking timeline and status details for customer.
     */
    public function trackReturn(string $orderNumber, string $returnNumber): View
    {
        $user = Auth::user();
        $customer = $user->customer;

        $order = Order::where('order_number', $orderNumber)
            ->where(function ($q) use ($customer, $user) {
                if ($customer) {
                    $q->where('customer_id', $customer->id);
                } else {
                    $q->where('user_id', $user->id);
                }
            })
            ->firstOrFail();

        $return = OrderReturn::where('return_number', $returnNumber)
            ->whereHas('orderItem.fulfilmentOrder', fn($q) => $q->where('order_id', $order->id))
            ->with(['orderItem.variant', 'orderItem.fulfilmentOrder.seller', 'pickupShipment.tracking'])
            ->firstOrFail();

        return view('storefront.orders.return-track', compact('order', 'return'));
    }

    /**
     * Customer self-service resolution for failed delivery / NDR.
     */
    public function resolveNdr(Request $request, string $orderNumber, string $foNumber): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:reattempt,change_address,rto'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'new_phone' => ['nullable', 'string', 'max:20'],
            'new_address' => ['nullable', 'string', 'max:255'],
        ]);

        $user = Auth::user();
        $customer = $user->customer;

        $order = Order::where('order_number', $orderNumber)
            ->where(function ($q) use ($customer, $user) {
                if ($customer) {
                    $q->where('customer_id', $customer->id);
                } else {
                    $q->where('user_id', $user->id);
                }
            })
            ->firstOrFail();

        $fo = FulfilmentOrder::where('order_id', $order->id)
            ->where('fulfilment_order_number', $foNumber)
            ->firstOrFail();

        $shipment = $fo->shipment;
        if (!$shipment) {
            return back()->with('error', 'Shipment not found.');
        }

        $ndr = $shipment->ndrCases()->where('status', '!=', 'closed')->latest()->first();
        if (!$ndr) {
            $ndr = \App\Models\NdrCase::create([
                'shipment_id' => $shipment->id,
                'reason' => 'Customer self-service resolution instruction',
                'attempts' => 1,
                'status' => 'pending',
            ]);
        }

        try {
            app(\App\Services\Cargo\Contracts\LogisticsProvider::class)->submitNdrAction($ndr, $validated['action'], $validated);

            return back()->with('success', "Your delivery instruction has been transmitted to the courier: {$validated['action']}.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Submit Return Request for an eligible delivered package (legacy route compatibility).
     */
    public function submitReturn(Request $request, string $orderNumber, string $foNumber): RedirectResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'order_item_id' => 'nullable|exists:order_items,id',
        ]);

        $user = Auth::user();
        $customer = $user->customer;

        $order = Order::where('order_number', $orderNumber)
            ->where('customer_id', $customer?->id ?? 0)
            ->firstOrFail();

        $fo = FulfilmentOrder::where('order_id', $order->id)
            ->where('fulfilment_order_number', $foNumber)
            ->firstOrFail();

        $item = $request->input('order_item_id') ? OrderItem::find($request->input('order_item_id')) : $fo->items->first();
        if (!$item) {
            return back()->with('error', 'No item found to return.');
        }

        try {
            $return = $this->returnService->requestReturn($item, [
                'reason' => $request->input('reason'),
                'refund_mode' => 'gateway',
            ], $user);

            return redirect()->route('orders.show', $order->order_number)
                ->with('success', 'Return request has been submitted successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
