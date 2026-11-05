<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SettlementStatus;
use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\SellerSettlement;
use App\Services\Settlement\SettlementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettlementController extends Controller
{
    public function __construct(
        protected SettlementService $settlementService
    ) {}

    /**
     * Display list of settlements with metrics and filters.
     */
    public function index(Request $request): View
    {
        $query = SellerSettlement::query()
            ->with(['seller', 'items'])
            ->latest('id');

        // Metrics Summary
        $metrics = [
            'total_settlements' => SellerSettlement::count(),
            'pending_count' => SellerSettlement::where('status', SettlementStatus::PENDING)->count(),
            'pending_amount' => (float) SellerSettlement::where('status', SettlementStatus::PENDING)->sum('net'),
            'processing_count' => SellerSettlement::where('status', SettlementStatus::PROCESSING)->count(),
            'processing_amount' => (float) SellerSettlement::where('status', SettlementStatus::PROCESSING)->sum('net'),
            'paid_amount' => (float) SellerSettlement::whereIn('status', [SettlementStatus::PAID, SettlementStatus::SETTLED])->sum('net'),
            'on_hold_count' => SellerSettlement::where('status', SettlementStatus::ON_HOLD)->count(),
        ];

        // Filters
        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($sellerId = $request->query('seller_id')) {
            $query->where('seller_id', $sellerId);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('settlement_number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhereHas('seller', function ($sq) use ($search) {
                      $sq->where('store_name', 'like', "%{$search}%");
                  });
            });
        }

        $settlements = $query->paginate(15)->withQueryString();
        $sellers = Seller::whereIn('status', [\App\Enums\SellerStatus::APPROVED, 'approved', 'active'])->orderBy('business_name')->get();

        return view('admin.settlements.index', compact('settlements', 'metrics', 'sellers'));
    }

    /**
     * Display settlement detail console with items breakdown.
     */
    public function show(SellerSettlement $settlement): View
    {
        $settlement->load(['seller', 'items.fulfilmentOrder.order', 'items.commissionEntry']);

        return view('admin.settlements.show', compact('settlement'));
    }

    /**
     * Trigger manual batch settlement generation.
     */
    public function generate(Request $request): RedirectResponse
    {
        $sellerId = $request->input('seller_id') ? (int) $request->input('seller_id') : null;
        $periodEnd = $request->input('period_end') ? Carbon::parse($request->input('period_end')) : null;

        $generated = $this->settlementService->generateWeeklyBatch($periodEnd, $sellerId);

        if ($generated->isEmpty()) {
            return redirect()->route('admin.settlements.index')
                ->with('info', 'No new delivered orders past the return window were eligible for settlement.');
        }

        return redirect()->route('admin.settlements.index')
            ->with('success', "Successfully generated {$generated->count()} settlement batch(es).");
    }

    /**
     * Approve settlement for processing.
     */
    public function approve(SellerSettlement $settlement): RedirectResponse
    {
        try {
            $this->settlementService->approveSettlement($settlement, Auth::id());
            return back()->with('success', "Settlement #{$settlement->settlement_number} approved for processing.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Mark settlement paid with bank UTR.
     */
    public function markPaid(Request $request, SellerSettlement $settlement): RedirectResponse
    {
        $request->validate([
            'reference' => ['required', 'string', 'max:100'],
        ]);

        try {
            $this->settlementService->markPaid($settlement, $request->input('reference'), Auth::id());
            return back()->with('success', "Settlement #{$settlement->settlement_number} marked PAID with UTR {$request->input('reference')}.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Hold settlement with reason.
     */
    public function hold(Request $request, SellerSettlement $settlement): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->settlementService->holdSettlement($settlement, $request->input('reason'), Auth::id());
            return back()->with('success', "Settlement #{$settlement->settlement_number} placed on hold.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Release hold on settlement.
     */
    public function release(Request $request, SellerSettlement $settlement): RedirectResponse
    {
        $note = $request->input('note');

        try {
            $this->settlementService->releaseHold($settlement, $note);
            return back()->with('success', "Settlement #{$settlement->settlement_number} hold released back to pending.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Add manual adjustment to settlement.
     */
    public function addAdjustment(Request $request, SellerSettlement $settlement): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->settlementService->addAdjustment($settlement, (float) $validated['amount'], $validated['reason']);
            return back()->with('success', "Adjustment of " . format_currency((float) $validated['amount']) . " applied.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Export Settlement as PDF Statement.
     */
    public function exportPdf(SellerSettlement $settlement): Response
    {
        $settlement->load(['seller', 'items.fulfilmentOrder.order', 'items.commissionEntry']);

        $pdf = Pdf::loadView('pdf.settlement-statement', compact('settlement'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("Settlement-{$settlement->settlement_number}.pdf");
    }

    /**
     * Export Settlement Items as CSV.
     */
    public function exportCsv(SellerSettlement $settlement): StreamedResponse
    {
        $settlement->load(['seller', 'items.fulfilmentOrder', 'items.commissionEntry']);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"Settlement-{$settlement->settlement_number}-items.csv\"",
        ];

        return response()->stream(function () use ($settlement) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Item ID',
                'Type',
                'Description',
                'Fulfilment Order #',
                'Base Gross Amount',
                'Commission Rate %',
                'Commission Fee',
                'Tax on Commission (GST)',
                'Net Amount',
                'Created At',
            ]);

            foreach ($settlement->items as $item) {
                fputcsv($handle, [
                    $item->id,
                    $item->type,
                    $item->description,
                    $item->fulfilmentOrder?->fulfilment_order_number ?? 'N/A',
                    $item->commissionEntry?->base_amount ?? 'N/A',
                    $item->commissionEntry?->rule?->percent ?? 'N/A',
                    $item->commissionEntry?->commission ?? 'N/A',
                    $item->commissionEntry?->tax_on_commission ?? 'N/A',
                    $item->amount,
                    $item->created_at->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
