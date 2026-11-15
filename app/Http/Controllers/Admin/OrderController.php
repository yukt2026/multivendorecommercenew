<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryPartner;
use App\Models\FulfilmentOrder;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function __construct(
        protected OrderStateMachine $stateMachine,
        protected OrderPdfService $pdfService
    ) {}

    /**
     * Shared orders query builder supporting searching, filtering, and export.
     */
    protected function buildOrdersQuery(Request $request)
    {
        $query = Order::query()
            ->with(['customer.user', 'fulfilmentOrders.store', 'fulfilmentOrders.seller', 'payments'])
            ->latest();

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

        // Fulfilment Type Filter (Quick Commerce vs Standard)
        if ($fulfilmentType = $request->query('fulfilment_type')) {
            if ($fulfilmentType !== 'all') {
                $query->whereHas('fulfilmentOrders', function ($foq) use ($fulfilmentType) {
                    $foq->where('fulfilment_type', $fulfilmentType);
                });
            }
        }

        // Date Range Filter
        if ($dateRange = $request->query('date_range')) {
            match ($dateRange) {
                'today' => $query->whereDate('created_at', today()),
                'yesterday' => $query->whereDate('created_at', today()->subDay()),
                'week' => $query->where('created_at', '>=', now()->subDays(7)),
                'month' => $query->where('created_at', '>=', now()->startOfMonth()),
                default => null,
            };
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

        return $query;
    }

    /**
     * Global Orders Registry.
     */
    public function index(Request $request): View
    {
        $query = $this->buildOrdersQuery($request);

        // Metrics Summary
        $metrics = [
            'total_orders' => Order::count(),
            'total_revenue' => (float) Order::where('payment_status', PaymentStatus::PAID)->sum('total'),
            'processing' => Order::whereIn('status', [OrderStatus::PLACED, OrderStatus::PROCESSING])->count(),
            'delivered' => Order::where('status', OrderStatus::DELIVERED)->count(),
            'cancelled' => Order::where('status', OrderStatus::CANCELLED)->count(),
            'quick_orders' => FulfilmentOrder::where('fulfilment_type', FulfilmentType::QUICK)->count(),
        ];

        $orders = $query->paginate(20)->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'metrics' => $metrics,
            'status' => $request->query('status', 'all'),
            'paymentStatus' => $request->query('payment_status', 'all'),
            'fulfilmentType' => $request->query('fulfilment_type', 'all'),
            'dateRange' => $request->query('date_range', 'all'),
            'search' => $request->query('search', ''),
        ]);
    }

    /**
     * Stream CSV export of orders matching active filters.
     */
    public function export(Request $request): StreamedResponse
    {
        $orders = $this->buildOrdersQuery($request)->get();
        $fileName = 'orders_export_' . now()->format('Y_m_d_His') . '.csv';

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Order Number',
                'Date Placed',
                'Customer Name',
                'Customer Email',
                'Customer Phone',
                'Delivery City',
                'Pincode',
                'Fulfilment Types',
                'Payment Method',
                'Payment Status',
                'Order Status',
                'Packages Count',
                'Subtotal',
                'Discount',
                'Delivery Fee',
                'Tax',
                'Total',
            ]);

            foreach ($orders as $order) {
                $addr = $order->address_snapshot ?? [];
                $types = $order->fulfilmentOrders->pluck('fulfilment_type')->unique()->map(fn ($t) => is_object($t) ? $t->value : $t)->implode(', ');
                fputcsv($handle, [
                    $order->order_number,
                    $order->created_at->format('Y-m-d H:i:s'),
                    $addr['name'] ?? $order->customer?->user?->name ?? 'Guest',
                    $order->customer?->user?->email ?? 'N/A',
                    $addr['phone'] ?? $order->customer?->user?->phone ?? 'N/A',
                    $addr['city'] ?? 'N/A',
                    $addr['pincode'] ?? 'N/A',
                    $types ?: 'standard',
                    $order->payment_method?->value ?? (string) $order->payment_method,
                    $order->payment_status?->value ?? (string) $order->payment_status,
                    $order->status?->value ?? (string) $order->status,
                    $order->fulfilmentOrders->count(),
                    $order->subtotal,
                    $order->discount,
                    $order->delivery_fee,
                    $order->tax,
                    $order->total,
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
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
            'fulfilmentOrders.items.variant.product',
            'fulfilmentOrders.store',
            'fulfilmentOrders.seller',
            'fulfilmentOrders.shipment.tracking',
            'fulfilmentOrders.deliveryAssignment.deliveryPartner',
            'fulfilmentOrders.statusHistory.changedByUser',
            'statusHistory.changedByUser',
        ]);

        $availableRiders = DeliveryPartner::where('is_active', true)
            ->where('approval_status', 'approved')
            ->orderBy('name')
            ->get();

        return view('admin.orders.show', [
            'order' => $order,
            'availableRiders' => $availableRiders,
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
