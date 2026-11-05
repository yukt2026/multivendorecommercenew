<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\QCStatus;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderReturn;
use App\Models\Seller;
use App\Services\Order\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReturnController extends Controller
{
    public function __construct(
        protected ReturnService $returnService
    ) {}

    /**
     * Display the global returns and RMA oversight console.
     */
    public function index(Request $request): View
    {
        $query = OrderReturn::query()
            ->with([
                'orderItem.variant',
                'orderItem.fulfilmentOrder.seller',
                'orderItem.fulfilmentOrder.order.user',
            ])
            ->latest('id');

        // Filter: Status
        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        // Filter: QC Status
        if ($qcStatus = $request->query('qc_status')) {
            if ($qcStatus !== 'all') {
                $query->where('qc_status', $qcStatus);
            }
        }

        // Filter: Seller
        if ($sellerId = $request->query('seller_id')) {
            $query->whereHas('orderItem.fulfilmentOrder', fn($foq) => $foq->where('seller_id', $sellerId));
        }

        // Search: RMA, Order Number, Waybill, Product Title
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                  ->orWhere('pickup_waybill', 'like', "%{$search}%")
                  ->orWhereHas('orderItem', fn($iq) => $iq->where('title', 'like', "%{$search}%"))
                  ->orWhereHas('orderItem.fulfilmentOrder.order', fn($oq) => $oq->where('order_number', 'like', "%{$search}%"))
                  ->orWhereHas('orderItem.fulfilmentOrder.order.user', fn($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $returns = $query->paginate(20)->withQueryString();

        $metrics = [
            'total_returns' => OrderReturn::count(),
            'pending_review' => OrderReturn::where('status', ReturnStatus::REQUESTED)->count(),
            'in_transit' => OrderReturn::whereIn('status', [ReturnStatus::REVERSE_PICKUP_SCHEDULED, ReturnStatus::PICKED_UP])->count(),
            'disputed_qc' => OrderReturn::where('qc_status', QCStatus::FAILED)->count(),
            'total_refunded_value' => (float) OrderReturn::where('status', ReturnStatus::REFUNDED)->sum('refund_amount'),
        ];

        $sellers = Seller::orderBy('business_name')->get(['id', 'business_name']);

        return view('admin.returns.index', compact('returns', 'metrics', 'sellers'));
    }

    /**
     * Show return dispute & resolution console.
     */
    public function show(OrderReturn $return): View
    {
        $return->load([
            'orderItem.variant.product',
            'orderItem.fulfilmentOrder.order.user',
            'orderItem.fulfilmentOrder.seller',
            'pickupShipment.tracking',
            'refund',
        ]);

        return view('admin.returns.show', compact('return'));
    }

    /**
     * Admin Dispute Override (Approve / Reject / Change decision).
     */
    public function override(Request $request, OrderReturn $return): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->returnService->adminOverride(
                $return,
                $validated['action'],
                ['notes' => $validated['notes']],
                Auth::user()
            );

            return back()->with('success', "Dispute overridden: Return #{$return->return_number} has been marked as {$validated['action']}d.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Admin Force-Refund Override (Bypasses seller delays or resolves dispute in customer's favor).
     */
    public function forceRefund(Request $request, OrderReturn $return): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0'],
            'is_resellable' => ['nullable', 'boolean'],
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->returnService->adminOverride(
                $return,
                'force_refund',
                [
                    'amount' => !empty($validated['amount']) ? (float) $validated['amount'] : null,
                    'is_resellable' => $request->boolean('is_resellable', false),
                    'notes' => $validated['notes'],
                ],
                Auth::user()
            );

            return back()->with('success', "Administrative force-refund executed for Return #{$return->return_number}.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Export Returns CSV report.
     */
    public function export(Request $request): StreamedResponse
    {
        $filename = 'returns-report-' . now()->format('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Return Number',
                'Order Number',
                'Customer Name',
                'Customer Email',
                'Seller',
                'Item Title',
                'SKU',
                'Quantity',
                'Refund Amount',
                'Refund Mode',
                'Reason',
                'Status',
                'QC Status',
                'Resellable',
                'Waybill',
                'Created At',
                'Refunded At',
            ]);

            OrderReturn::with([
                'orderItem.fulfilmentOrder.seller',
                'orderItem.fulfilmentOrder.order.user',
            ])->chunk(150, function ($returns) use ($handle) {
                foreach ($returns as $r) {
                    $item = $r->orderItem;
                    $order = $item?->fulfilmentOrder?->order;
                    $user = $order?->user;
                    $seller = $item?->fulfilmentOrder?->seller;

                    fputcsv($handle, [
                        $r->return_number,
                        $order?->order_number ?? 'N/A',
                        $user?->name ?? 'N/A',
                        $user?->email ?? 'N/A',
                        $seller?->company_name ?? 'N/A',
                        $item?->title ?? 'N/A',
                        $item?->sku ?? 'N/A',
                        $r->qty ?? $item?->qty ?? 1,
                        $r->refund_amount ?? $item?->total ?? 0,
                        $r->refund_mode ?? 'original',
                        $r->reason,
                        $r->status->value,
                        $r->qc_status->value,
                        $r->is_resellable ? 'Yes' : 'No',
                        $r->pickup_waybill ?? 'N/A',
                        $r->created_at->toDateTimeString(),
                        $r->refunded_at?->toDateTimeString() ?? 'N/A',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
