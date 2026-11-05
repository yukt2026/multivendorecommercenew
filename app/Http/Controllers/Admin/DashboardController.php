<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected AdminDashboardService $dashboardService
    ) {}

    /**
     * Display the Admin Central Command SaaS Dashboard.
     */
    public function index(Request $request): View
    {
        $range = (string) $request->query('range', '30d');
        if (!in_array($range, ['today', '7d', '30d', '90d'], true)) {
            $range = '30d';
        }

        $stats = $this->dashboardService->getTopStats($range);
        $widgets = $this->dashboardService->getWidgetsData();
        $topSellers = $this->dashboardService->getTopSellers($range);

        return view('admin.dashboard', [
            'stats' => $stats,
            'widgets' => $widgets,
            'topSellers' => $topSellers,
            'activeRange' => $range,
        ]);
    }

    /**
     * AJAX endpoint returning JSON data for Chart.js and dynamic widgets.
     */
    public function chartData(Request $request): JsonResponse
    {
        $range = (string) $request->query('range', '30d');
        if (!in_array($range, ['today', '7d', '30d', '90d'], true)) {
            $range = '30d';
        }

        $chartType = (string) $request->query('chart', 'all');

        $data = match ($chartType) {
            'sales_orders' => $this->dashboardService->getSalesAndOrdersTrend($range),
            'order_types' => $this->dashboardService->getOrdersByType($range),
            'category_revenue' => $this->dashboardService->getRevenueByCategory($range),
            'top_sellers' => $this->dashboardService->getTopSellers($range),
            'status_funnel' => $this->dashboardService->getOrderStatusFunnel($range),
            'stats' => $this->dashboardService->getTopStats($range),
            default => [
                'stats' => $this->dashboardService->getTopStats($range),
                'sales_orders' => $this->dashboardService->getSalesAndOrdersTrend($range),
                'order_types' => $this->dashboardService->getOrdersByType($range),
                'category_revenue' => $this->dashboardService->getRevenueByCategory($range),
                'top_sellers' => $this->dashboardService->getTopSellers($range),
                'status_funnel' => $this->dashboardService->getOrderStatusFunnel($range),
            ],
        };

        return response()->json([
            'success' => true,
            'range' => $range,
            'chart' => $chartType,
            'data' => $data,
        ]);
    }

    /**
     * Stream CSV export of executive dashboard metrics.
     */
    public function exportSummary(Request $request): StreamedResponse
    {
        $range = (string) $request->query('range', '30d');
        if (!in_array($range, ['today', '7d', '30d', '90d'], true)) {
            $range = '30d';
        }

        return $this->dashboardService->exportSummaryCsv($range);
    }
}
