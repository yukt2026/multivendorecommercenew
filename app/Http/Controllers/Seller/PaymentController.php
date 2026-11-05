<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Enums\SettlementStatus;
use App\Http\Controllers\Controller;
use App\Models\CommissionEntry;
use App\Models\FulfilmentOrder;
use App\Models\SellerSettlement;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    /**
     * Earnings Ledger & Upcoming Payout Widget.
     */
    public function payments(Request $request): View
    {
        $seller = Auth::user()->seller;
        if (!$seller) {
            abort(403, 'Unauthorized seller account.');
        }

        // Commission entries for seller's fulfilment orders
        $query = CommissionEntry::query()
            ->whereHas('fulfilmentOrder', function ($q) use ($seller) {
                $q->where('seller_id', $seller->id);
            })
            ->with(['fulfilmentOrder.order', 'rule'])
            ->latest('id');

        if ($search = $request->query('search')) {
            $query->whereHas('fulfilmentOrder', function ($q) use ($search) {
                $q->where('fulfilment_order_number', 'like', "%{$search}%");
            });
        }

        $entries = $query->paginate(20)->withQueryString();

        // Metrics Summary
        $baseEntries = CommissionEntry::query()
            ->whereHas('fulfilmentOrder', function ($q) use ($seller) {
                $q->where('seller_id', $seller->id);
            });

        $totalSales = (float) (clone $baseEntries)->where('entry_type', 'standard')->sum('base_amount');
        $totalCommission = (float) (clone $baseEntries)->where('entry_type', 'standard')->sum('commission');
        $totalTaxOnCommission = (float) (clone $baseEntries)->where('entry_type', 'standard')->sum('tax_on_commission');
        $totalNetPayable = (float) (clone $baseEntries)->sum('net_payable');

        // Upcoming Payout calculation: delivered orders past return window not yet in a settlement
        $returnWindowDays = (int) Setting::get('return_window_days', 7);
        $eligibleCutoff = Carbon::now()->subDays($returnWindowDays);

        $unsettledOrders = FulfilmentOrder::query()
            ->where('seller_id', $seller->id)
            ->where('status', 'delivered')
            ->whereDoesntHave('settlementItem')
            ->with('commissionEntry')
            ->get();

        $upcomingMatured = $unsettledOrders->filter(fn ($fo) => $fo->delivered_at && $fo->delivered_at->lte($eligibleCutoff));
        $upcomingMaturing = $unsettledOrders->filter(fn ($fo) => $fo->delivered_at && $fo->delivered_at->gt($eligibleCutoff));

        $upcomingMaturedAmount = $upcomingMatured->sum(fn ($fo) => (float) ($fo->commissionEntry?->net_payable ?? $fo->seller_payable));
        $upcomingMaturingAmount = $upcomingMaturing->sum(fn ($fo) => (float) ($fo->commissionEntry?->net_payable ?? $fo->seller_payable));

        return view('seller.payments.index', compact(
            'entries',
            'seller',
            'totalSales',
            'totalCommission',
            'totalTaxOnCommission',
            'totalNetPayable',
            'upcomingMaturedAmount',
            'upcomingMaturingAmount',
            'upcomingMatured',
            'upcomingMaturing',
            'returnWindowDays'
        ));
    }

    /**
     * Seller Settlements History & Statements.
     */
    public function settlements(Request $request): View
    {
        $seller = Auth::user()->seller;
        if (!$seller) {
            abort(403, 'Unauthorized seller account.');
        }

        $query = SellerSettlement::query()
            ->where('seller_id', $seller->id)
            ->with(['items'])
            ->latest('id');

        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $settlements = $query->paginate(15)->withQueryString();

        $metrics = [
            'total_settlements' => SellerSettlement::where('seller_id', $seller->id)->count(),
            'paid_amount' => (float) SellerSettlement::where('seller_id', $seller->id)->whereIn('status', [SettlementStatus::PAID, SettlementStatus::SETTLED])->sum('net'),
            'pending_amount' => (float) SellerSettlement::where('seller_id', $seller->id)->where('status', SettlementStatus::PENDING)->sum('net'),
            'processing_amount' => (float) SellerSettlement::where('seller_id', $seller->id)->where('status', SettlementStatus::PROCESSING)->sum('net'),
            'on_hold_amount' => (float) SellerSettlement::where('seller_id', $seller->id)->where('status', SettlementStatus::ON_HOLD)->sum('net'),
        ];

        return view('seller.settlements.index', compact('settlements', 'metrics', 'seller'));
    }

    /**
     * View individual settlement statement.
     */
    public function showSettlement(SellerSettlement $settlement): View
    {
        $seller = Auth::user()->seller;
        if (!$seller || $settlement->seller_id !== $seller->id) {
            abort(403, 'Unauthorized settlement access.');
        }

        $settlement->load(['items.fulfilmentOrder.order', 'items.commissionEntry']);

        return view('seller.settlements.show', compact('settlement', 'seller'));
    }

    /**
     * Download Settlement PDF Statement.
     */
    public function downloadPdf(SellerSettlement $settlement): Response
    {
        $seller = Auth::user()->seller;
        if (!$seller || $settlement->seller_id !== $seller->id) {
            abort(403, 'Unauthorized settlement statement access.');
        }

        $settlement->load(['seller', 'items.fulfilmentOrder.order', 'items.commissionEntry']);

        $pdf = Pdf::loadView('pdf.settlement-statement', compact('settlement'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("Settlement-Statement-{$settlement->settlement_number}.pdf");
    }

    /**
     * Download Settlement CSV.
     */
    public function downloadCsv(SellerSettlement $settlement): StreamedResponse
    {
        $seller = Auth::user()->seller;
        if (!$seller || $settlement->seller_id !== $seller->id) {
            abort(403, 'Unauthorized settlement export access.');
        }

        $settlement->load(['items.fulfilmentOrder', 'items.commissionEntry']);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"Settlement-{$settlement->settlement_number}.csv\"",
        ];

        return response()->stream(function () use ($settlement) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Item ID',
                'Type',
                'Description',
                'Package #',
                'Gross Amount',
                'Net Amount',
                'Date',
            ]);

            foreach ($settlement->items as $item) {
                fputcsv($handle, [
                    $item->id,
                    $item->type,
                    $item->description,
                    $item->fulfilmentOrder?->fulfilment_order_number ?? 'N/A',
                    $item->commissionEntry?->base_amount ?? 'N/A',
                    $item->amount,
                    $item->created_at->toDateString(),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
