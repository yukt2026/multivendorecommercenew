<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Enums\QCStatus;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderReturn;
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
     * Display seller returns management console.
     */
    public function index(Request $request): View
    {
        $seller = Auth::user()->seller;
        if (!$seller) {
            abort(403, 'Unauthorized. Merchant seller profile required.');
        }

        $query = OrderReturn::query()
            ->forSeller($seller->id)
            ->with(['orderItem.variant', 'orderItem.fulfilmentOrder.order.user'])
            ->latest('id');

        // Status filter
        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        // Search
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                  ->orWhere('pickup_waybill', 'like', "%{$search}%")
                  ->orWhereHas('orderItem', fn($iq) => $iq->where('title', 'like', "%{$search}%"))
                  ->orWhereHas('orderItem.fulfilmentOrder.order', fn($oq) => $oq->where('order_number', 'like', "%{$search}%"));
            });
        }

        $returns = $query->paginate(15)->withQueryString();

        $metrics = [
            'total_returns' => OrderReturn::forSeller($seller->id)->count(),
            'pending_approval' => OrderReturn::forSeller($seller->id)->where('status', ReturnStatus::REQUESTED)->count(),
            'in_transit' => OrderReturn::forSeller($seller->id)->whereIn('status', [ReturnStatus::REVERSE_PICKUP_SCHEDULED, ReturnStatus::PICKED_UP])->count(),
            'qc_pending' => OrderReturn::forSeller($seller->id)->where('status', ReturnStatus::RECEIVED)->count(),
            'refunded' => OrderReturn::forSeller($seller->id)->where('status', ReturnStatus::REFUNDED)->count(),
        ];

        return view('seller.returns.index', compact('returns', 'metrics'));
    }

    /**
     * Display return request detail screen.
     */
    public function show(OrderReturn $return): View
    {
        $this->authorizeSeller($return);

        $return->load(['orderItem.variant.product', 'orderItem.fulfilmentOrder.order.user', 'pickupShipment.tracking', 'refund']);

        return view('seller.returns.show', compact('return'));
    }

    /**
     * Merchant approves the return request.
     */
    public function approve(Request $request, OrderReturn $return): RedirectResponse
    {
        $this->authorizeSeller($return);

        $validated = $request->validate([
            'seller_notes' => ['nullable', 'string', 'max:500'],
            'auto_schedule_pickup' => ['nullable', 'boolean'],
        ]);

        try {
            $this->returnService->approve(
                $return,
                $validated['seller_notes'] ?? null,
                Auth::user(),
                $request->boolean('auto_schedule_pickup', true)
            );

            return back()->with('success', "Return #{$return->return_number} has been approved.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Merchant rejects the return request with mandatory remarks.
     */
    public function reject(Request $request, OrderReturn $return): RedirectResponse
    {
        $this->authorizeSeller($return);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->returnService->reject($return, $validated['reason'], Auth::user());

            return back()->with('success', "Return #{$return->return_number} has been rejected.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Schedule reverse pickup manually if not auto-scheduled.
     */
    public function schedulePickup(Request $request, OrderReturn $return): RedirectResponse
    {
        $this->authorizeSeller($return);

        try {
            $this->returnService->scheduleReversePickup($return, Auth::user());

            return back()->with('success', "Reverse pickup scheduled. Waybill: {$return->pickup_waybill}");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Mark returned package received at facility.
     */
    public function receive(Request $request, OrderReturn $return): RedirectResponse
    {
        $this->authorizeSeller($return);

        try {
            $this->returnService->markReceived($return, Auth::user());

            return back()->with('success', "Return package marked as received at inspection hub.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Record Quality Control (QC) inspection outcome.
     */
    public function recordQc(Request $request, OrderReturn $return): RedirectResponse
    {
        $this->authorizeSeller($return);

        $validated = $request->validate([
            'outcome' => ['required', 'in:passed,failed'],
            'qc_notes' => ['nullable', 'string', 'max:1000'],
            'is_resellable' => ['nullable', 'boolean'],
        ]);

        try {
            $this->returnService->recordQcOutcome(
                $return,
                $validated['outcome'],
                $validated['qc_notes'] ?? null,
                $request->boolean('is_resellable', true),
                Auth::user()
            );

            return back()->with('success', "Quality inspection outcome recorded successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Execute final financial refund upon QC pass.
     */
    public function refund(Request $request, OrderReturn $return): RedirectResponse
    {
        $this->authorizeSeller($return);

        try {
            $this->returnService->processRefund($return, null, Auth::user());

            return back()->with('success', "Refund of ₹{$return->refund_amount} processed successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Export returns list as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $seller = Auth::user()->seller;
        if (!$seller) {
            abort(403);
        }

        $filename = "returns-export-seller-{$seller->id}-" . now()->format('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($seller) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Return Number', 'Order Number', 'Item', 'Qty', 'Amount', 'Reason', 'Status', 'QC Status', 'Waybill', 'Date']);

            OrderReturn::forSeller($seller->id)
                ->with(['orderItem.fulfilmentOrder.order'])
                ->chunk(100, function ($returns) use ($handle) {
                    foreach ($returns as $r) {
                        fputcsv($handle, [
                            $r->return_number,
                            $r->orderItem?->fulfilmentOrder?->order?->order_number ?? 'N/A',
                            $r->orderItem?->title ?? 'N/A',
                            $r->orderItem?->qty ?? 1,
                            $r->refund_amount ?? $r->orderItem?->total ?? 0,
                            $r->reason,
                            $r->status->value,
                            $r->qc_status->value,
                            $r->pickup_waybill ?? 'N/A',
                            $r->created_at->toDateTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Enforce multi-tenant seller ownership.
     */
    protected function authorizeSeller(OrderReturn $return): void
    {
        $seller = Auth::user()->seller;
        if (!$seller || $return->orderItem?->fulfilmentOrder?->seller_id !== $seller->id) {
            abort(403, 'Unauthorized. This return does not belong to your merchant account.');
        }
    }
}
