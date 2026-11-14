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
            ->with(['fulfilmentOrder.order', 'fulfilmentOrder.store', 'rule', 'settlementItem.settlement'])
            ->latest('id');

        if ($search = trim((string) $request->query('search'))) {
            $query->whereHas('fulfilmentOrder', function ($q) use ($search) {
                $q->where('fulfilment_order_number', 'like', "%{$search}%")
                  ->orWhereHas('order', function ($oq) use ($search) {
                      $oq->where('order_number', 'like', "%{$search}%");
                  });
            });
        }

        if ($entryType = $request->query('entry_type')) {
            if (in_array($entryType, ['standard', 'reversal'], true)) {
                $query->where('entry_type', $entryType);
            }
        }

        if ($settlementStatus = $request->query('settlement_status')) {
            if ($settlementStatus === 'settled') {
                $query->whereHas('settlementItem');
            } elseif ($settlementStatus === 'pending') {
                $query->whereDoesntHave('settlementItem');
            }
        }

        if ($storeId = $request->query('store_id')) {
            $query->whereHas('fulfilmentOrder', function ($q) use ($storeId) {
                $q->where('store_id', (int) $storeId);
            });
        }

        $entries = $query->paginate(20)->withQueryString();

        // Metrics Summary
        $baseEntries = CommissionEntry::query()
            ->whereHas('fulfilmentOrder', function ($q) use ($seller, $request) {
                $q->where('seller_id', $seller->id);
                if ($storeId = $request->query('store_id')) {
                    $q->where('store_id', (int) $storeId);
                }
            });

        $totalSales = (float) (clone $baseEntries)->where('entry_type', 'standard')->sum('base_amount');
        $totalCommission = (float) (clone $baseEntries)->where('entry_type', 'standard')->sum('commission');
        $totalTaxOnCommission = (float) (clone $baseEntries)->where('entry_type', 'standard')->sum('tax_on_commission');
        $totalNetPayable = (float) (clone $baseEntries)->sum('net_payable');

        // Upcoming Payout calculation: delivered orders past return window not yet in a settlement
        $returnWindowDays = (int) Setting::get('return_window_days', 7);
        $eligibleCutoff = Carbon::now()->subDays($returnWindowDays);

        $unsettledOrdersQuery = FulfilmentOrder::query()
            ->where('seller_id', $seller->id)
            ->where('status', 'delivered')
            ->whereDoesntHave('settlementItem')
            ->with(['commissionEntry', 'store']);

        if ($storeId = $request->query('store_id')) {
            $unsettledOrdersQuery->where('store_id', (int) $storeId);
        }

        $unsettledOrders = $unsettledOrdersQuery->get();

        $upcomingMatured = $unsettledOrders->filter(fn ($fo) => $fo->delivered_at && $fo->delivered_at->lte($eligibleCutoff));
        $upcomingMaturing = $unsettledOrders->filter(fn ($fo) => $fo->delivered_at && $fo->delivered_at->gt($eligibleCutoff));

        $upcomingMaturedAmount = $upcomingMatured->sum(fn ($fo) => (float) ($fo->commissionEntry?->net_payable ?? $fo->seller_payable));
        $upcomingMaturingAmount = $upcomingMaturing->sum(fn ($fo) => (float) ($fo->commissionEntry?->net_payable ?? $fo->seller_payable));

        $stores = $seller->stores()->orderBy('name')->get();
        $primaryBank = $seller->primaryBankAccount ?: $seller->bankAccounts()->first();

        return view('seller.payments.index', compact(
            'entries',
            'seller',
            'stores',
            'primaryBank',
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
     * Export Payments Ledger to CSV.
     */
    public function exportPayments(Request $request): StreamedResponse
    {
        $seller = Auth::user()->seller;
        if (!$seller) {
            abort(403, 'Unauthorized seller account.');
        }

        $query = CommissionEntry::query()
            ->whereHas('fulfilmentOrder', function ($q) use ($seller) {
                $q->where('seller_id', $seller->id);
            })
            ->with(['fulfilmentOrder.order', 'fulfilmentOrder.store', 'settlementItem.settlement'])
            ->latest('id');

        if ($search = trim((string) $request->query('search'))) {
            $query->whereHas('fulfilmentOrder', function ($q) use ($search) {
                $q->where('fulfilment_order_number', 'like', "%{$search}%")
                  ->orWhereHas('order', function ($oq) use ($search) {
                      $oq->where('order_number', 'like', "%{$search}%");
                  });
            });
        }

        if ($entryType = $request->query('entry_type')) {
            if (in_array($entryType, ['standard', 'reversal'], true)) {
                $query->where('entry_type', $entryType);
            }
        }

        if ($settlementStatus = $request->query('settlement_status')) {
            if ($settlementStatus === 'settled') {
                $query->whereHas('settlementItem');
            } elseif ($settlementStatus === 'pending') {
                $query->whereDoesntHave('settlementItem');
            }
        }

        if ($storeId = $request->query('store_id')) {
            $query->whereHas('fulfilmentOrder', function ($q) use ($storeId) {
                $q->where('store_id', (int) $storeId);
            });
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="Payments-Ledger-' . now()->format('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Entry ID',
                'Date & Time',
                'Type',
                'Package #',
                'Order #',
                'Store / Outlet',
                'Gross Sales (INR)',
                'Commission Fee (INR)',
                'GST (18%) (INR)',
                'Net Payable (INR)',
                'Settlement Status',
                'Settlement # / Batch',
            ]);

            $query->chunk(100, function ($entries) use ($handle) {
                foreach ($entries as $entry) {
                    fputcsv($handle, [
                        $entry->id,
                        $entry->created_at->toDateTimeString(),
                        strtoupper($entry->entry_type),
                        $entry->fulfilmentOrder?->fulfilment_order_number ?? 'N/A',
                        $entry->fulfilmentOrder?->order?->order_number ?? 'N/A',
                        $entry->fulfilmentOrder?->store?->name ?? 'Default Outlet',
                        number_format((float) $entry->base_amount, 2, '.', ''),
                        number_format((float) $entry->commission, 2, '.', ''),
                        number_format((float) $entry->tax_on_commission, 2, '.', ''),
                        number_format((float) $entry->net_payable, 2, '.', ''),
                        $entry->settlementItem ? 'Settled' : 'Pending Batch',
                        $entry->settlementItem?->settlement?->settlement_number ?? 'Unsettled',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
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

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('settlement_number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        $settlements = $query->paginate(15)->withQueryString();

        $metrics = [
            'total_settlements' => SellerSettlement::where('seller_id', $seller->id)->count(),
            'paid_amount' => (float) SellerSettlement::where('seller_id', $seller->id)->whereIn('status', [SettlementStatus::PAID, SettlementStatus::SETTLED])->sum('net'),
            'paid_count' => SellerSettlement::where('seller_id', $seller->id)->whereIn('status', [SettlementStatus::PAID, SettlementStatus::SETTLED])->count(),
            'processing_amount' => (float) SellerSettlement::where('seller_id', $seller->id)->where('status', SettlementStatus::PROCESSING)->sum('net'),
            'processing_count' => SellerSettlement::where('seller_id', $seller->id)->where('status', SettlementStatus::PROCESSING)->count(),
            'pending_amount' => (float) SellerSettlement::where('seller_id', $seller->id)->where('status', SettlementStatus::PENDING)->sum('net'),
            'pending_count' => SellerSettlement::where('seller_id', $seller->id)->where('status', SettlementStatus::PENDING)->count(),
            'on_hold_amount' => (float) SellerSettlement::where('seller_id', $seller->id)->where('status', SettlementStatus::ON_HOLD)->sum('net'),
            'on_hold_count' => SellerSettlement::where('seller_id', $seller->id)->where('status', SettlementStatus::ON_HOLD)->count(),
        ];

        $primaryBank = $seller->primaryBankAccount ?: $seller->bankAccounts()->first();

        return view('seller.settlements.index', compact('settlements', 'metrics', 'seller', 'primaryBank'));
    }

    /**
     * Export Settlements Summary to CSV.
     */
    public function exportSettlements(Request $request): StreamedResponse
    {
        $seller = Auth::user()->seller;
        if (!$seller) {
            abort(403, 'Unauthorized seller account.');
        }

        $query = SellerSettlement::query()
            ->where('seller_id', $seller->id)
            ->latest('id');

        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('settlement_number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="Settlements-Summary-' . now()->format('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Settlement #',
                'Period Start',
                'Period End',
                'Gross Sales (INR)',
                'Marketplace Fee (INR)',
                'GST on Fee (18%) (INR)',
                'COD Deducted (INR)',
                'Adjustments (INR)',
                'Net Payout (INR)',
                'Status',
                'Bank Reference (UTR)',
                'Disbursed At',
            ]);

            $query->chunk(100, function ($settlements) use ($handle) {
                foreach ($settlements as $s) {
                    fputcsv($handle, [
                        $s->settlement_number,
                        $s->period_start->toDateString(),
                        $s->period_end->toDateString(),
                        number_format((float) $s->gross, 2, '.', ''),
                        number_format((float) $s->commission, 2, '.', ''),
                        number_format((float) $s->tax_on_commission, 2, '.', ''),
                        number_format((float) $s->cod_collected, 2, '.', ''),
                        number_format((float) $s->adjustments, 2, '.', ''),
                        number_format((float) $s->net, 2, '.', ''),
                        $s->status->label(),
                        $s->reference ?: 'Pending',
                        $s->paid_at ? $s->paid_at->toDateTimeString() : 'Unpaid',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
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

        $settlement->load(['items.fulfilmentOrder.order', 'items.fulfilmentOrder.store', 'items.commissionEntry']);
        $primaryBank = $seller->primaryBankAccount ?: $seller->bankAccounts()->first();

        return view('seller.settlements.show', compact('settlement', 'seller', 'primaryBank'));
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
