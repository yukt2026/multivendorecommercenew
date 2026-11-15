<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CommissionEntry;
use App\Models\FulfilmentOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
use App\Models\SellerSettlement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Display reports dashboard with tab selector and date filtering.
     */
    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'sales');
        if (!in_array($tab, ['sales', 'orders', 'sellers', 'categories', 'tax', 'commission'], true)) {
            $tab = 'sales';
        }

        [$startDate, $endDate] = $this->resolveDates($request);

        $data = match ($tab) {
            'orders' => $this->getOrdersReport($startDate, $endDate),
            'sellers' => $this->getSellersReport($startDate, $endDate),
            'categories' => $this->getCategoriesReport($startDate, $endDate),
            'tax' => $this->getTaxReport($startDate, $endDate),
            'commission' => $this->getCommissionReport($startDate, $endDate, true),
            default => $this->getSalesReport($startDate, $endDate),
        };

        return view('admin.reports.index', compact('tab', 'startDate', 'endDate', 'data'));
    }

    /**
     * Export selected report type as CSV.
     */
    public function export(Request $request, string $type): StreamedResponse
    {
        [$startDate, $endDate] = $this->resolveDates($request);

        $filename = "report-{$type}-" . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($type, $startDate, $endDate) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($type === 'orders') {
                fputcsv($handle, ['OneStall Orders & Channels Report']);
                fputcsv($handle, ['Reporting Period', $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d')]);
                fputcsv($handle, []);
                fputcsv($handle, ['--- BY LIFECYCLE STATUS ---']);
                fputcsv($handle, ['Status', 'Order Count', 'Total Gross Value (INR)']);
                $report = $this->getOrdersReport($startDate, $endDate);
                foreach ($report['byStatus'] as $row) {
                    fputcsv($handle, [$row->status, $row->count, number_format((float) $row->total, 2, '.', '')]);
                }
                fputcsv($handle, []);
                fputcsv($handle, ['--- BY PAYMENT METHOD ---']);
                fputcsv($handle, ['Payment Method', 'Order Count', 'Total Gross Value (INR)']);
                foreach ($report['byMethod'] as $row) {
                    fputcsv($handle, [$row->payment_method ?: 'N/A', $row->count, number_format((float) $row->total, 2, '.', '')]);
                }
                fputcsv($handle, []);
                fputcsv($handle, ['--- BY FULFILMENT CHANNEL ---']);
                fputcsv($handle, ['Channel Type', 'Packages Count', 'Subtotal (INR)']);
                foreach ($report['byFulfilment'] as $row) {
                    fputcsv($handle, [ucfirst((string) $row->fulfilment_type), $row->count, number_format((float) $row->total, 2, '.', '')]);
                }
            } elseif ($type === 'sellers') {
                fputcsv($handle, ['OneStall Seller Performance Leaderboard']);
                fputcsv($handle, ['Reporting Period', $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d')]);
                fputcsv($handle, []);
                fputcsv($handle, ['Seller ID', 'Business Name', 'Orders Fulfilled', 'Gross GMV (INR)', 'Platform Commission (INR)', 'Net Seller Earnings (INR)']);
                $report = $this->getSellersReport($startDate, $endDate);
                foreach ($report['sellers'] as $row) {
                    fputcsv($handle, [
                        $row['id'],
                        $row['name'],
                        $row['orders_count'],
                        number_format((float) $row['gross_gmv'], 2, '.', ''),
                        number_format((float) $row['commission'], 2, '.', ''),
                        number_format((float) ($row['net_payout'] ?? ($row['gross_gmv'] - $row['commission'])), 2, '.', ''),
                    ]);
                }
            } elseif ($type === 'categories') {
                fputcsv($handle, ['OneStall Category Turnover & Demand Report']);
                fputcsv($handle, ['Reporting Period', $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d')]);
                fputcsv($handle, []);
                fputcsv($handle, ['Category ID', 'Category Name', 'Units Dispatched', 'Gross Revenue (INR)']);
                $report = $this->getCategoriesReport($startDate, $endDate);
                foreach ($report['categories'] as $row) {
                    fputcsv($handle, [
                        $row['id'] ?? 'N/A',
                        $row['name'],
                        $row['units'],
                        number_format((float) $row['revenue'], 2, '.', ''),
                    ]);
                }
            } elseif ($type === 'tax') {
                fputcsv($handle, ['OneStall GST & Tax Accounting Reconciliation']);
                fputcsv($handle, ['Reporting Period', $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d')]);
                fputcsv($handle, []);
                fputcsv($handle, ['Tax Metric', 'Amount (INR)']);
                $report = $this->getTaxReport($startDate, $endDate);
                fputcsv($handle, ['Gross Paid Platform Revenue', number_format((float) $report['grossRevenue'], 2, '.', '')]);
                fputcsv($handle, ['Merchant GST Collected (Product Tax)', number_format((float) $report['productTax'], 2, '.', '')]);
                fputcsv($handle, ['Platform Commission GST (18%)', number_format((float) $report['commissionTax'], 2, '.', '')]);
                fputcsv($handle, ['Total Tax Liability / Collected', number_format((float) $report['totalTaxCollected'], 2, '.', '')]);
            } elseif ($type === 'commission') {
                fputcsv($handle, ['OneStall Platform Commission & Disbursements Ledger']);
                fputcsv($handle, ['Reporting Period', $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d')]);
                fputcsv($handle, []);
                fputcsv($handle, ['Entry ID', 'Created At', 'Order #', 'Merchant Name', 'Base Order GMV (INR)', 'Commission Rate', 'Commission Pre-Tax (INR)', 'GST on Commission (INR)', 'Seller Net Payable (INR)']);
                $report = $this->getCommissionReport($startDate, $endDate, false);
                foreach ($report['entries'] as $entry) {
                    fputcsv($handle, [
                        $entry->id,
                        $entry->created_at->format('Y-m-d H:i:s'),
                        $entry->order?->order_number ?? 'N/A',
                        $entry->fulfilmentOrder?->seller?->business_name ?? 'N/A',
                        number_format((float) $entry->base_amount, 2, '.', ''),
                        ($entry->rate ?? 0) . '%',
                        number_format((float) $entry->commission, 2, '.', ''),
                        number_format((float) $entry->tax_on_commission, 2, '.', ''),
                        number_format((float) $entry->net_payable, 2, '.', ''),
                    ]);
                }
            } else {
                fputcsv($handle, ['OneStall Daily Sales Performance Aggregates']);
                fputcsv($handle, ['Reporting Period', $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d')]);
                fputcsv($handle, []);
                fputcsv($handle, ['Date', 'Orders Count', 'Gross Revenue (INR)', 'Delivery Fees (INR)', 'Net Cart Subtotal (INR)']);
                $report = $this->getSalesReport($startDate, $endDate);
                foreach ($report['daily'] as $row) {
                    fputcsv($handle, [
                        $row->date,
                        $row->order_count,
                        number_format((float) $row->gross, 2, '.', ''),
                        number_format((float) $row->delivery_fee, 2, '.', ''),
                        number_format((float) $row->net, 2, '.', ''),
                    ]);
                }
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Resolve start and end dates from preset or explicit inputs.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveDates(Request $request): array
    {
        $preset = $request->query('preset');

        if ($preset === 'today') {
            return [now()->startOfDay(), now()->endOfDay()];
        }
        if ($preset === '7d') {
            return [now()->subDays(6)->startOfDay(), now()->endOfDay()];
        }
        if ($preset === '30d') {
            return [now()->subDays(29)->startOfDay(), now()->endOfDay()];
        }
        if ($preset === 'this_month') {
            return [now()->startOfMonth()->startOfDay(), now()->endOfDay()];
        }
        if ($preset === 'last_month') {
            return [now()->subMonth()->startOfMonth()->startOfDay(), now()->subMonth()->endOfMonth()->endOfDay()];
        }

        $start = $request->query('start_date')
            ? Carbon::parse((string) $request->query('start_date'))->startOfDay()
            : now()->subDays(30)->startOfDay();

        $end = $request->query('end_date')
            ? Carbon::parse((string) $request->query('end_date'))->endOfDay()
            : now()->endOfDay();

        if ($start->gt($end)) {
            $start = (clone $end)->subDays(30)->startOfDay();
        }

        return [$start, $end];
    }

    /**
     * Tab 1: Sales Performance aggregates.
     *
     * @return array<string, mixed>
     */
    protected function getSalesReport(Carbon $start, Carbon $end): array
    {
        $orders = Order::whereBetween('created_at', [$start, $end])
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                  ->orWhere('status', '!=', OrderStatus::CANCELLED);
            });

        $totalRevenue = (float) (clone $orders)->sum('total');
        $totalOrders = (clone $orders)->count();
        $totalDeliveryFees = (float) (clone $orders)->sum('delivery_fee');
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0.00;

        $daily = Order::query()
            ->selectRaw('DATE(created_at) as date, count(*) as order_count, sum(total) as gross, sum(delivery_fee) as delivery_fee, sum(subtotal) as net')
            ->whereBetween('created_at', [$start, $end])
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                  ->orWhere('status', '!=', OrderStatus::CANCELLED);
            })
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at) desc')
            ->get();

        return compact('totalRevenue', 'totalOrders', 'totalDeliveryFees', 'avgOrderValue', 'daily');
    }

    /**
     * Tab 2: Orders by status, payment method, and fulfilment channel.
     *
     * @return array<string, mixed>
     */
    protected function getOrdersReport(Carbon $start, Carbon $end): array
    {
        $byStatus = Order::query()
            ->selectRaw('status, count(*) as count, sum(total) as total')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('status')
            ->get()
            ->map(function ($row) {
                $statusVal = $row->status instanceof \BackedEnum ? $row->status->value : (string) ($row->status ?? 'unknown');
                $label = $row->status instanceof OrderStatus ? $row->status->label() : ucfirst(str_replace('_', ' ', $statusVal));
                return (object) [
                    'status' => $statusVal,
                    'label' => $label,
                    'count' => (int) $row->count,
                    'total' => (float) $row->total,
                ];
            });

        $byMethod = Order::query()
            ->selectRaw('payment_method, count(*) as count, sum(total) as total')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('payment_method')
            ->get()
            ->map(function ($row) {
                $methodVal = $row->payment_method instanceof \BackedEnum ? $row->payment_method->value : (string) ($row->payment_method ?? 'N/A');
                $label = $row->payment_method instanceof \App\Enums\PaymentMethod ? $row->payment_method->label() : strtoupper($methodVal);
                return (object) [
                    'payment_method' => $methodVal,
                    'label' => $label,
                    'count' => (int) $row->count,
                    'total' => (float) $row->total,
                ];
            });

        $byFulfilment = FulfilmentOrder::query()
            ->selectRaw('fulfilment_type, count(*) as count, sum(subtotal) as total')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('fulfilment_type')
            ->get()
            ->map(function ($row) {
                $typeVal = $row->fulfilment_type instanceof \BackedEnum ? $row->fulfilment_type->value : (string) ($row->fulfilment_type ?? 'standard');
                $label = match ($typeVal) {
                    'quick' => '⚡ Quick Commerce',
                    'pickup' => '🏬 Store Pickup',
                    default => '🚚 Standard Cargo',
                };
                return (object) [
                    'fulfilment_type' => $typeVal,
                    'label' => $label,
                    'count' => (int) $row->count,
                    'total' => (float) $row->total,
                ];
            });

        $totalOrderCount = Order::whereBetween('created_at', [$start, $end])->count();
        $totalOrderValue = (float) Order::whereBetween('created_at', [$start, $end])->sum('total');

        return compact('byStatus', 'byMethod', 'byFulfilment', 'totalOrderCount', 'totalOrderValue');
    }

    /**
     * Tab 3: Top merchant performance leaderboard.
     *
     * @return array<string, mixed>
     */
    protected function getSellersReport(Carbon $start, Carbon $end): array
    {
        $sellers = Seller::withCount(['fulfilmentOrders as period_orders_count' => fn($q) => $q->whereBetween('created_at', [$start, $end])])
            ->withSum(['fulfilmentOrders as period_gmv' => fn($q) => $q->whereBetween('created_at', [$start, $end])], 'subtotal')
            ->withSum(['fulfilmentOrders as period_commission' => fn($q) => $q->whereBetween('created_at', [$start, $end])], 'commission_amount')
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->business_name,
                'orders_count' => (int) ($s->period_orders_count ?? 0),
                'gross_gmv' => (float) ($s->period_gmv ?? 0),
                'commission' => (float) ($s->period_commission ?? 0),
                'net_payout' => (float) (($s->period_gmv ?? 0) - ($s->period_commission ?? 0)),
            ])
            ->sortByDesc('gross_gmv')
            ->values()
            ->all();

        $totalSellersCount = count($sellers);
        $totalSellersGmv = array_sum(array_column($sellers, 'gross_gmv'));
        $totalSellersCommission = array_sum(array_column($sellers, 'commission'));

        return compact('sellers', 'totalSellersCount', 'totalSellersGmv', 'totalSellersCommission');
    }

    /**
     * Tab 4: Category revenue and turnover aggregates.
     *
     * @return array<string, mixed>
     */
    protected function getCategoriesReport(Carbon $start, Carbon $end): array
    {
        $aggregates = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->selectRaw('products.category_id, SUM(order_items.qty) as units, SUM(order_items.total) as revenue')
            ->whereBetween('order_items.created_at', [$start, $end])
            ->groupBy('products.category_id')
            ->get()
            ->keyBy('category_id');

        $categories = Category::all()->map(function ($cat) use ($aggregates) {
            $agg = $aggregates->get($cat->id);
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'units' => $agg ? (int) $agg->units : 0,
                'revenue' => $agg ? (float) $agg->revenue : 0.00,
            ];
        })
        ->sortByDesc('revenue')
        ->values()
        ->all();

        $totalCategoriesCount = count($categories);
        $totalUnits = array_sum(array_column($categories, 'units'));
        $totalCategoryRevenue = array_sum(array_column($categories, 'revenue'));

        return compact('categories', 'totalCategoriesCount', 'totalUnits', 'totalCategoryRevenue');
    }

    /**
     * Tab 5: Tax, GST, and government remittals report.
     *
     * @return array<string, mixed>
     */
    protected function getTaxReport(Carbon $start, Carbon $end): array
    {
        $orders = Order::whereBetween('created_at', [$start, $end])
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                  ->orWhere('status', '!=', OrderStatus::CANCELLED);
            });

        $grossRevenue = (float) (clone $orders)->sum('total');
        $productTax = (float) (clone $orders)->sum('tax');

        $commissionTax = (float) CommissionEntry::whereBetween('created_at', [$start, $end])->sum('tax_on_commission');
        $totalTaxCollected = $productTax + $commissionTax;

        // Recent orders with tax audit trail
        $recentOrdersWithTax = Order::with('customer.user')
            ->whereBetween('created_at', [$start, $end])
            ->where('tax', '>', 0)
            ->latest('id')
            ->limit(8)
            ->get();

        return compact('grossRevenue', 'productTax', 'commissionTax', 'totalTaxCollected', 'recentOrdersWithTax');
    }

    /**
     * Tab 6: Commission & Settled Disbursements Ledger.
     *
     * @return array<string, mixed>
     */
    protected function getCommissionReport(Carbon $start, Carbon $end, bool $paginate = true): array
    {
        $query = CommissionEntry::with(['fulfilmentOrder.order', 'fulfilmentOrder.seller', 'order'])
            ->whereBetween('created_at', [$start, $end])
            ->latest('id');

        $entries = $paginate ? $query->paginate(20)->withQueryString() : $query->get();
        $totalCommission = (float) CommissionEntry::whereBetween('created_at', [$start, $end])->sum('commission');
        $totalTax = (float) CommissionEntry::whereBetween('created_at', [$start, $end])->sum('tax_on_commission');
        $totalSettled = (float) SellerSettlement::whereBetween('created_at', [$start, $end])->whereIn('status', ['paid', 'settled'])->sum('net');

        return compact('entries', 'totalCommission', 'totalTax', 'totalSettled');
    }
}
