<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Store;
use App\Services\Seller\StoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StoreOutletController extends Controller
{
    public function __construct(
        protected StoreService $storeService
    ) {}

    /**
     * List all physical store outlets owned by the seller.
     */
    public function index(Request $request): View
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403, 'No seller profile associated with this account.');

        $stores = $seller->stores()
            ->with(['location', 'timings', 'staff.user', 'inventoryLocations'])
            ->latest()
            ->get();

        $activeStoreId = session('seller_active_store_id');
        $activeStore = $activeStoreId ? $stores->firstWhere('id', $activeStoreId) : null;

        return view('seller.stores.index', [
            'stores' => $stores,
            'activeStore' => $activeStore,
            'seller' => $seller,
            'totalStores' => $stores->count(),
            'activeCount' => $stores->where('status', StoreStatus::ACTIVE)->count(),
            'quickCount' => $stores->where('accepts_quick_commerce', true)->count(),
        ]);
    }

    /**
     * Show form to create a new physical store outlet.
     */
    public function create(): View
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);

        return view('seller.stores.create', [
            'seller' => $seller,
            'categories' => $categories,
            'defaultDeliveryRadius' => (float) setting('default_delivery_radius_km', 8.00),
        ]);
    }

    /**
     * Store newly created store outlet.
     */
    public function store(Request $request): RedirectResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:stores,slug',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pincode' => 'required|string|max:20',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'delivery_radius_km' => 'nullable|numeric|min:0.5|max:100',
            'accepts_quick_commerce' => 'nullable|boolean',
            'quick_max_orders' => 'nullable|integer|min:1|max:200',
            'quick_prep_time_minutes' => 'nullable|integer|min:1|max:120',
            'quick_order_timeout_seconds' => 'nullable|integer|min:30|max:600',
            'accepts_pickup' => 'nullable|boolean',
            'opens_at' => 'nullable|date_format:H:i',
            'closes_at' => 'nullable|date_format:H:i',
            'category_ids' => 'nullable|array',
        ]);

        $store = $this->storeService->createStore($seller, $validated);

        // Auto switch active store to newly created outlet
        session(['seller_active_store_id' => $store->id]);

        return redirect()->route('seller.stores.index')
            ->with('success', "Store outlet '{$store->name}' created successfully with dedicated inventory shelf!");
    }

    /**
     * Display store-level performance reports and analytics.
     */
    public function reports(Request $request): View
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $stores = $seller->stores()->get();
        $storeIdParam = $request->query('store_id', session('seller_active_store_id'));
        $storeId = (!empty($storeIdParam) && $storeIdParam !== 'all') ? (int) $storeIdParam : null;
        $range = (string) $request->query('range', '30d');
        $from = $request->query('from');
        $to = $request->query('to');

        $selectedStore = $storeId ? $stores->firstWhere('id', $storeId) : null;
        $reportData = $this->storeService->getStoreReports($seller, $storeId, $range, $from, $to);

        return view('seller.stores.reports', [
            'seller' => $seller,
            'stores' => $stores,
            'selectedStore' => $selectedStore,
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'report' => $reportData,
        ]);
    }

    /**
     * Export store performance report as CSV.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $stores = $seller->stores()->get();
        $storeIdParam = $request->query('store_id', session('seller_active_store_id'));
        $storeId = (!empty($storeIdParam) && $storeIdParam !== 'all') ? (int) $storeIdParam : null;
        $range = (string) $request->query('range', '30d');
        $from = $request->query('from');
        $to = $request->query('to');

        $report = $this->storeService->getStoreReports($seller, $storeId, $range, $from, $to);
        $storeName = $storeId ? ($stores->firstWhere('id', $storeId)?->name ?? 'Outlet') : 'All Outlets';
        $fileName = 'store_report_' . \Illuminate\Support\Str::slug($storeName) . '_' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($report, $storeName, $range) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['OneStall Store Performance Report']);
            fputcsv($handle, ['Store / Scope', $storeName]);
            fputcsv($handle, ['Window Range', "{$report['from']} to {$report['to']} ({$range})"]);
            fputcsv($handle, []);

            fputcsv($handle, ['KPI Metric', 'Value']);
            fputcsv($handle, ['Gross Delivered Sales (INR)', $report['gross_sales']]);
            fputcsv($handle, ['Average Order Value (INR)', $report['aov']]);
            fputcsv($handle, ['Total Checkouts', $report['total_orders']]);
            fputcsv($handle, ['Delivered Orders', $report['delivered_orders']]);
            fputcsv($handle, ['Cancelled Orders', $report['cancelled_orders']]);
            fputcsv($handle, ['Quick Commerce Sales (INR)', $report['quick_orders_sales']]);
            fputcsv($handle, ['Quick Commerce Orders', $report['quick_orders_count']]);
            fputcsv($handle, ['Standard Delivery Sales (INR)', $report['standard_orders_sales']]);
            fputcsv($handle, ['Standard Delivery Orders', $report['standard_orders_count']]);
            fputcsv($handle, ['Fulfillment Health Rate (%)', $report['fulfillment_rate']]);
            fputcsv($handle, ['Cancellation Rate (%)', $report['cancellation_rate']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Timeline Breakdown']);
            fputcsv($handle, ['Date', 'Delivered Sales (INR)', 'Orders Count']);
            foreach ($report['chart']['labels'] as $idx => $label) {
                fputcsv($handle, [$label, $report['chart']['sales'][$idx] ?? 0, $report['chart']['orders'][$idx] ?? 0]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Top Products Velocity']);
            fputcsv($handle, ['Rank', 'Product Title', 'SKU', 'Units Sold', 'Total Revenue (INR)']);
            foreach ($report['top_items'] as $idx => $item) {
                fputcsv($handle, [$idx + 1, $item->title, $item->sku ?: 'DEFAULT', $item->total_qty, $item->total_sales]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Outlets Performance Breakdown']);
            fputcsv($handle, ['Outlet Name', 'City', 'Status', 'Quick Commerce', 'Orders', 'Gross Sales (INR)', 'AOV (INR)', 'Cancel Rate (%)']);
            foreach ($report['outlet_breakdown'] as $ob) {
                fputcsv($handle, [
                    $ob['name'],
                    $ob['city'],
                    $ob['is_open'] ? 'Open' : 'Closed',
                    $ob['accepts_quick_commerce'] ? 'Yes' : 'No',
                    $ob['total_orders'],
                    $ob['gross_sales'],
                    $ob['aov'],
                    $ob['cancellation_rate'],
                ]);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
