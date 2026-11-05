<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Seller;
use App\Services\Order\OrderPdfService;
use App\Services\Order\OrderStateMachine;
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
     * Global Orders Registry.
     */
    public function index(Request $request): View
    {
        $query = Order::query()
            ->with(['customer.user', 'fulfilmentOrders.store', 'fulfilmentOrders.seller', 'payments'])
            ->latest();

        // Metrics Summary
        $metrics = [
            'total_orders' => Order::count(),
            'total_revenue' => (float) Order::where('payment_status', PaymentStatus::PAID)->sum('total'),
            'processing' => Order::whereIn('status', [OrderStatus::PLACED, OrderStatus::PROCESSING])->count(),
            'delivered' => Order::where('status', OrderStatus::DELIVERED)->count(),
            'cancelled' => Order::where('status', OrderStatus::CANCELLED)->count(),
        ];

        // Status Filter
        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        // Payment Status Filter
        if ($paymentStatus = $request->query('payment_status')) {
            if ($paymentStatus !== 'all') {
                $query->where('payment_status', $paymentStatus);
            }
        }

        // Search Filter
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('address_snapshot', 'like', "%{$search}%")
                    ->orWhereHas('customer.user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('fulfilmentOrders', function ($foq) use ($search) {
                        $foq->where('fulfilment_order_number', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $query->paginate(20)->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'metrics' => $metrics,
            'status' => $status ?? 'all',
            'paymentStatus' => $paymentStatus ?? 'all',
            'search' => $search ?? '',
        ]);
    }

    /**
     * Master Order Detail Console.
     */
    public function show(Order $order): View
    {
        $order->loadMissing([
            'customer.user',
            'customerAddress',
            'payments',
            'fulfilmentOrders.items.variant',
            'fulfilmentOrders.store',
            'fulfilmentOrders.seller',
            'fulfilmentOrders.shipment.tracking',
            'fulfilmentOrders.statusHistory.changedByUser',
            'statusHistory.changedByUser',
        ]);

        return view('admin.orders.show', [
            'order' => $order,
        ]);
    }

    /**
     * Admin Force Override Order Status with mandatory reason.
     */
    public function overrideStatus(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'status' => 'required|string',
            'reason' => 'required|string|max:500',
        ]);

        $newStatus = OrderStatus::from($request->input('status'));
        $reason = $request->input('reason');

        $this->stateMachine->adminOverride($order, $newStatus, $reason, Auth::id());

        return back()->with('success', "Order #{$order->order_number} status overridden to {$newStatus->label()} successfully.");
    }

    /**
     * Add administrative internal note.
     */
    public function addNote(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        $timestamp = now()->format('d M Y, h:i A');
        $adminName = Auth::user()->name;
        $newNote = "[{$timestamp} by {$adminName}]: " . trim($request->input('note'));

        $existingNotes = $order->notes ? $order->notes . "\n\n" : '';
        $order->notes = $existingNotes . $newNote;
        $order->save();

        // Also record in status history as internal note
        OrderStatusHistory::create([
            'orderable_type' => Order::class,
            'orderable_id' => $order->id,
            'from_status' => $order->status->value,
            'to_status' => $order->status->value,
            'note' => "[INTERNAL NOTE]: " . trim($request->input('note')),
            'changed_by' => Auth::id(),
        ]);

        return back()->with('success', 'Internal note added to order.');
    }

    /**
     * Download Tax Invoice PDF from Admin.
     */
    public function invoice(Order $order): Response
    {
        return $this->pdfService->generateInvoicePdf($order);
    }
}
