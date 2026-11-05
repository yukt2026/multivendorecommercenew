<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\Cargo\CreateShipmentJob;
use App\Models\FulfilmentOrder;
use App\Models\NdrCase;
use App\Models\Shipment;
use App\Services\Cargo\Contracts\LogisticsProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShipmentController extends Controller
{
    public function __construct(
        protected LogisticsProvider $logisticsProvider
    ) {}

    /**
     * Display seller shipments dashboard and logistics overview.
     */
    public function index(Request $request): View
    {
        $seller = Auth::user()->seller;
        if (!$seller) {
            abort(403, 'Merchant profile required.');
        }

        $query = Shipment::query()
            ->whereHas('fulfilmentOrder', fn($q) => $q->where('seller_id', $seller->id))
            ->with(['fulfilmentOrder.order.user', 'ndrCases' => fn($q) => $q->latest()])
            ->latest('id');

        // Status filter
        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        // Courier filter
        if ($courier = $request->query('courier')) {
            if ($courier !== 'all') {
                $query->where('courier', 'like', "%{$courier}%");
            }
        }

        // Search
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('shipment_number', 'like', "%{$search}%")
                  ->orWhere('awb', 'like', "%{$search}%")
                  ->orWhereHas('fulfilmentOrder', fn($foq) => $foq->where('fulfilment_order_number', 'like', "%{$search}%"))
                  ->orWhereHas('fulfilmentOrder.order', fn($oq) => $oq->where('order_number', 'like', "%{$search}%"));
            });
        }

        $shipments = $query->paginate(15)->withQueryString();

        $metrics = [
            'total_shipments' => Shipment::whereHas('fulfilmentOrder', fn($q) => $q->where('seller_id', $seller->id))->count(),
            'in_transit' => Shipment::whereHas('fulfilmentOrder', fn($q) => $q->where('seller_id', $seller->id))
                ->whereIn('status', [ShipmentStatus::IN_TRANSIT, ShipmentStatus::OUT_FOR_DELIVERY, ShipmentStatus::PICKED_UP])
                ->count(),
            'ndr_action_required' => Shipment::whereHas('fulfilmentOrder', fn($q) => $q->where('seller_id', $seller->id))
                ->where('status', ShipmentStatus::NDR_RAISED)
                ->count(),
            'delivered' => Shipment::whereHas('fulfilmentOrder', fn($q) => $q->where('seller_id', $seller->id))
                ->where('status', ShipmentStatus::DELIVERED)
                ->count(),
            'rto' => Shipment::whereHas('fulfilmentOrder', fn($q) => $q->where('seller_id', $seller->id))
                ->whereIn('status', [ShipmentStatus::RTO_INITIATED, ShipmentStatus::RTO_DELIVERED])
                ->count(),
        ];

        return view('seller.shipments.index', compact('shipments', 'metrics'));
    }

    /**
     * Display shipment tracking details, AWB info, and NDR resolution console.
     */
    public function show(Shipment $shipment): View
    {
        $this->authorizeSeller($shipment);

        $shipment->load([
            'fulfilmentOrder.order.customerAddress',
            'fulfilmentOrder.order.user',
            'fulfilmentOrder.items.variant',
            'tracking',
            'ndrCases' => fn($q) => $q->latest(),
            'rtoCases',
            'codRemittance',
        ]);

        return view('seller.shipments.show', compact('shipment'));
    }

    /**
     * Direct shipping label print / download.
     */
    public function downloadLabel(Shipment $shipment): Response|RedirectResponse
    {
        $this->authorizeSeller($shipment);

        if (!$shipment->awb) {
            $this->logisticsProvider->generateAwb($shipment);
        }

        if ($shipment->label_url && str_starts_with($shipment->label_url, 'http')) {
            return redirect()->away($shipment->label_url);
        }

        // Fallback printable thermal HTML label
        return response()->view('seller.shipments.printable-label', compact('shipment'));
    }

    /**
     * Request carrier physical pickup from warehouse.
     */
    public function requestPickup(Request $request, Shipment $shipment): RedirectResponse
    {
        $this->authorizeSeller($shipment);

        $validated = $request->validate([
            'pickup_date' => ['nullable', 'date', 'after_or_equal:today'],
            'time_slot' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $this->logisticsProvider->requestPickup($shipment, $validated);

            return back()->with('success', "Carrier pickup scheduled successfully for AWB: {$shipment->awb}");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Sync tracking events manually from carrier API.
     */
    public function syncTracking(Shipment $shipment): RedirectResponse
    {
        $this->authorizeSeller($shipment);

        if (!$shipment->awb) {
            return back()->with('error', 'Cannot track a shipment without an assigned AWB.');
        }

        try {
            $this->logisticsProvider->track($shipment->awb);

            return back()->with('success', "Tracking milestones synchronized for AWB: {$shipment->awb}");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Resolve Non-Delivery Report (NDR) with carrier action.
     */
    public function resolveNdr(Request $request, Shipment $shipment): RedirectResponse
    {
        $this->authorizeSeller($shipment);

        $validated = $request->validate([
            'action' => ['required', 'in:reattempt,change_address,rto'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'new_phone' => ['nullable', 'string', 'max:20'],
            'new_address' => ['nullable', 'string', 'max:255'],
        ]);

        $ndrCase = $shipment->ndrCases()->where('status', '!=', 'closed')->latest()->first();
        if (!$ndrCase) {
            $ndrCase = NdrCase::create([
                'shipment_id' => $shipment->id,
                'reason' => 'Customer communication received',
                'attempts' => 1,
                'status' => 'pending',
            ]);
        }

        try {
            $this->logisticsProvider->submitNdrAction($ndrCase, $validated['action'], $validated);

            return back()->with('success', "NDR resolution instruction transmitted to carrier: {$validated['action']}");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Bulk print shipping labels for selected shipments.
     */
    public function bulkLabels(Request $request): Response|RedirectResponse
    {
        $seller = Auth::user()->seller;
        $ids = $request->input('shipment_ids', []);

        if (empty($ids) || !is_array($ids)) {
            return back()->with('error', 'Please select at least one shipment to print labels.');
        }

        $shipments = Shipment::whereIn('id', $ids)
            ->whereHas('fulfilmentOrder', fn($q) => $q->where('seller_id', $seller->id))
            ->with(['fulfilmentOrder.order.customerAddress'])
            ->get();

        foreach ($shipments as $s) {
            if (!$s->awb) {
                $this->logisticsProvider->generateAwb($s);
            }
        }

        return response()->view('seller.shipments.bulk-labels', compact('shipments'));
    }

    /**
     * Bulk create shipments for ready-to-ship orders.
     */
    public function bulkCreate(Request $request): RedirectResponse
    {
        $seller = Auth::user()->seller;
        $orderIds = $request->input('fulfilment_order_ids', []);

        if (empty($orderIds) || !is_array($orderIds)) {
            return back()->with('error', 'Please select at least one package to dispatch.');
        }

        $orders = FulfilmentOrder::whereIn('id', $orderIds)
            ->where('seller_id', $seller->id)
            ->get();

        $dispatchedCount = 0;
        foreach ($orders as $fo) {
            CreateShipmentJob::dispatch($fo);
            $dispatchedCount++;
        }

        return back()->with('success', "{$dispatchedCount} shipment(s) queued for generation & carrier dispatch.");
    }

    /**
     * Export shipments to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $seller = Auth::user()->seller;
        $filename = "shipments-export-{$seller->id}-" . now()->format('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($seller) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Shipment #', 'AWB', 'Order #', 'Courier', 'Service', 'Status', 'Weight (kg)', 'COD Amount', 'Created At']);

            Shipment::whereHas('fulfilmentOrder', fn($q) => $q->where('seller_id', $seller->id))
                ->with(['fulfilmentOrder.order'])
                ->chunk(150, function ($shipments) use ($handle) {
                    foreach ($shipments as $s) {
                        fputcsv($handle, [
                            $s->shipment_number,
                            $s->awb ?? 'Pending',
                            $s->fulfilmentOrder?->order?->order_number ?? 'N/A',
                            $s->courier,
                            $s->service,
                            $s->status->value,
                            $s->weight,
                            $s->cod_amount,
                            $s->created_at->toDateTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Enforce tenant boundary.
     */
    protected function authorizeSeller(Shipment $shipment): void
    {
        $seller = Auth::user()->seller;
        if (!$seller || $shipment->fulfilmentOrder?->seller_id !== $seller->id) {
            abort(403, 'Unauthorized access to this consignment.');
        }
    }
}
