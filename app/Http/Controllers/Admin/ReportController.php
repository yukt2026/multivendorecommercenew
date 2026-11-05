<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CommissionEntry;
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
        $tab = $request->query('tab', 'sales');
        $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date'))->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date'))->endOfDay() : now()->endOfDay();

        $data = match ($tab) {
            'orders' => $this->getOrdersReport($startDate, $endDate),
            'sellers' => $this->getSellersReport($startDate, $endDate),
            'categories' => $this->getCategoriesReport($startDate, $endDate),
            'tax' => $this->getTaxReport($startDate, $endDate),
            'commission' => $this->getCommissionReport($startDate, $endDate),
            default => $this->getSalesReport($startDate, $endDate),
        };

        return view('admin.reports.index', compact('tab', 'startDate', 'endDate', 'data'));
    }

    /**
     * Export selected report type as CSV.
     */
    public function export(Request $request, string $type): StreamedResponse
    {
        $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date'))->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date'))->endOfDay() : now()->endOfDay();

        $filename = "report-{$type}-" . now()->format('Ymd') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($type, $startDate, $endDate) {
            $handle = fopen('php://output', 'w');

            if ($type === 'orders') {
                fputcsv($handle, ['Status', 'Order Count', 'Total Value']);
                $report = $this->getOrdersReport($startDate, $endDate);
                foreach ($report['by_status'] as $row) {
                    fputcsv($handle, [$row->status, $row->count, $row->total]);
                }
            } elseif ($type === 'sellers') {
                fputcsv($handle, ['Seller ID', 'Business Name', 'Orders', 'Gross GMV', 'Commission Deducted']);
                $report = $this->getSellersReport($startDate, $endDate);
                foreach ($report['sellers'] as $row) {
                    fputcsv($handle, [$row['id'], $row['name'], $row['orders_count'], $row['gross_gmv'], $row['commission']]);
                }
            } elseif ($type === 'categories') {
                fputcsv($handle, ['Category', 'Units Sold', 'Revenue']);
                $report = $this->getCategoriesReport($startDate, $endDate);
                foreach ($report['categories'] as $row) {
                    fputcsv($handle, [$row['name'], $row['units'], $row['revenue']]);
                }
            } elseif ($type === 'tax') {
                fputcsv($handle, ['Metric', 'Amount (INR)']);
                $report = $this->getTaxReport($startDate, $endDate);
                fputcsv($handle, ['Gross Revenue', $report['gross_revenue']]);
                fputcsv($handle, ['Product Tax (GST) Collected', $report['product_tax']]);
                fputcsv($handle, ['Platform Commission GST (18%)', $report['commission_tax']]);
            } elseif ($type === 'commission') {
                fputcsv($handle, ['Date', 'Base Sales', 'Commission Pre-Tax', 'GST on Commission', 'Net Seller Payable']);
                $report = $this->getCommissionReport($startDate, $endDate);
                foreach ($report['entries'] as $entry) {
                    fputcsv($handle, [$entry->created_at->toDateString(), $entry->base_amount, $entry->commission, $entry->tax_on_commission, $entry->net_payable]);
                }
            } else {
                fputcsv($handle, ['Date', 'Orders', 'Gross Total', 'Delivery Fees', 'Net Revenue']);
                $report = $this->getSalesReport($startDate, $endDate);
                foreach ($report['daily'] as $row) {
                    fputcsv($handle, [$row->date, $row->order_count, $row->gross, $row->delivery_fee, $row->net]);
                }
            }

            fclose($handle);
        }, 200, $headers);
    }

    protected function getSalesReport(Carbon $start, Carbon $end): array
    {
        $orders = Order::whereBetween('created_at', [$start, $end])->where('payment_status', 'paid');

        $totalRevenue = (float) (clone $orders)->sum('total');
        $totalOrders = (clone $orders)->count();
        $totalDeliveryFees = (float) (clone $orders)->sum('delivery_fee');

        $daily = Order::query()
            ->selectRaw('DATE(created_at) as date, count(*) as order_count, sum(total) as gross, sum(delivery_fee) as delivery_fee, sum(subtotal) as net')
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'paid')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at) desc')
            ->get();

        return compact('totalRevenue', 'totalOrders', 'totalDeliveryFees', 'daily');
    }

    protected function getOrdersReport(Carbon $start, Carbon $end): array
    {
        $byStatus = Order::query()
            ->selectRaw('status, count(*) as count, sum(total) as total')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('status')
            ->get();

        $byMethod = Order::query()
            ->selectRaw('payment_method, count(*) as count, sum(total) as total')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('payment_method')
            ->get();

        return compact('byStatus', 'byMethod');
    }

    protected function getSellersReport(Carbon $start, Carbon $end): array
    {
        $sellers = Seller::withCount(['fulfilmentOrders as period_orders_count' => fn($q) => $q->whereBetween('created_at', [$start, $end])])
            ->withSum(['fulfilmentOrders as period_gmv' => fn($q) => $q->whereBetween('created_at', [$start, $end])], 'subtotal')
            ->withSum(['fulfilmentOrders as period_commission' => fn($q) => $q->whereBetween('created_at', [$start, $end])], 'commission_amount')
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->business_name,
                'orders_count' => $s->period_orders_count ?? 0,
                'gross_gmv' => (float) ($s->period_gmv ?? 0),
                'commission' => (float) ($s->period_commission ?? 0),
            ])
            ->sortByDesc('gross_gmv')
            ->values()
            ->all();

        return compact('sellers');
    }

    protected function getCategoriesReport(Carbon $start, Carbon $end): array
    {
        $categories = Category::query()
            ->with(['products.variants.orderItems' => fn($q) => $q->whereBetween('created_at', [$start, $end])])
            ->get()
            ->map(function ($cat) {
                $units = 0;
                $revenue = 0.00;
                foreach ($cat->products as $p) {
                    foreach ($p->variants as $v) {
                        foreach ($v->orderItems as $item) {
                            $units += (int) $item->quantity;
                            $revenue += (float) ($item->total ?? ($item->price * $item->quantity));
                        }
                    }
                }
                return [
                    'name' => $cat->name,
                    'units' => $units,
                    'revenue' => $revenue,
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->all();

        return compact('categories');
    }

    protected function getTaxReport(Carbon $start, Carbon $end): array
    {
        $orders = Order::whereBetween('created_at', [$start, $end])->where('payment_status', 'paid');
        $grossRevenue = (float) (clone $orders)->sum('total');
        $productTax = (float) (clone $orders)->sum('tax');

        $commissionTax = (float) CommissionEntry::whereBetween('created_at', [$start, $end])->sum('tax_on_commission');

        return compact('grossRevenue', 'productTax', 'commissionTax');
    }

    protected function getCommissionReport(Carbon $start, Carbon $end): array
    {
        $entries = CommissionEntry::whereBetween('created_at', [$start, $end])->latest('id')->paginate(20);
        $totalCommission = (float) CommissionEntry::whereBetween('created_at', [$start, $end])->sum('commission');
        $totalTax = (float) CommissionEntry::whereBetween('created_at', [$start, $end])->sum('tax_on_commission');
        $totalSettled = (float) SellerSettlement::whereBetween('created_at', [$start, $end])->whereIn('status', ['paid', 'settled'])->sum('net');

        return compact('entries', 'totalCommission', 'totalTax', 'totalSettled');
    }
}
