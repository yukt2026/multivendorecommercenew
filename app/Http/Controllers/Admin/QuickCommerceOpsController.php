<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\FulfilmentType;
use App\Http\Controllers\Controller;
use App\Models\FulfilmentOrder;
use App\Services\Fulfilment\FulfilmentRerouteService;
use App\Services\QuickCommerce\QuickCommerceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuickCommerceOpsController extends Controller
{
    public function __construct(
        protected QuickCommerceService $quickService
    ) {}

    /**
     * Realtime Operations & Mission Control Dashboard (/admin/quick-commerce).
     */
    public function index(Request $request): View
    {
        $opsData = $this->quickService->getOpsDashboardData();

        return view('admin.quick-commerce.index', [
            'ops' => $opsData,
        ]);
    }

    /**
     * Polling JSON Feed for live Ops Control Room wall screen.
     */
    public function apiFeed(Request $request): JsonResponse
    {
        $opsData = $this->quickService->getOpsDashboardData();

        return response()->json([
            'status' => 'success',
            'data' => $opsData,
        ]);
    }

    /**
     * Trigger background timeout scan and auto-reroute for pending quick orders.
     */
    public function scanTimeouts(Request $request, FulfilmentRerouteService $rerouteService): RedirectResponse|JsonResponse
    {
        $pendingQuickOrders = FulfilmentOrder::query()
            ->with(['store'])
            ->where('fulfilment_type', FulfilmentType::QUICK)
            ->where('status', 'placed')
            ->get();

        $reroutedCount = 0;
        $rejectedCount = 0;

        foreach ($pendingQuickOrders as $order) {
            $timeoutSeconds = $order->store ? $order->store->effectiveQuickTimeout() : (int) setting('quick_order_timeout_seconds', 120);
            $isExpired = $order->created_at ? $order->created_at->copy()->addSeconds($timeoutSeconds)->isPast() : false;

            if ($isExpired) {
                $result = $rerouteService->attemptAutoReroute(
                    $order,
                    "Admin manual scan: Store acceptance timed out after {$timeoutSeconds}s"
                );

                if ($result['rerouted']) {
                    $reroutedCount++;
                } else {
                    $rejectedCount++;
                }
            }
        }

        $message = "Timeout scan complete: {$reroutedCount} order(s) rerouted, {$rejectedCount} unable to fallback.";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'rerouted' => $reroutedCount,
                'rejected' => $rejectedCount,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Manually trigger auto-reroute for a specific breached or timed-out fulfilment order.
     */
    public function reroute(Request $request, FulfilmentOrder $fulfilmentOrder, FulfilmentRerouteService $rerouteService): RedirectResponse|JsonResponse
    {
        $result = $rerouteService->attemptAutoReroute($fulfilmentOrder, 'Admin manual dispatch intervention from Mission Control');

        $status = $result['rerouted'] ? 'success' : 'error';

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => $status,
                'message' => $result['message'],
                'data' => $result,
            ]);
        }

        return back()->with($status, $result['message']);
    }
}
