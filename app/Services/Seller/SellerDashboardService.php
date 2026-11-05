<?php

declare(strict_types=1);

namespace App\Services\Seller;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\ReviewStatus;
use App\Enums\SettlementStatus;
use App\Enums\StoreStatus;
use App\Models\FulfilmentOrder;
use App\Models\Inventory;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Seller;
use App\Models\SellerSettlement;
use App\Models\StockMovement;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SellerDashboardService
{
    protected const CACHE_TTL = 300; // 5-minute cache per non-negotiable rule

    /**
     * Retrieve complete dashboard payload with seller & store isolation.
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(Seller $seller, ?int $storeId = null, string $range = '30d'): array
    {
        $storeKey = $storeId ? "store_{$storeId}" : 'all_stores';
        $cacheKey = "seller_dash_{$seller->id}_{$storeKey}_{$range}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($seller, $storeId, $range) {
            return [
                'kpi' => $this->getKpiStats($seller, $storeId),
                'charts' => $this->getChartsData($seller, $storeId, $range),
                'actionOrders' => $this->getOrdersNeedingAction($seller, $storeId),
                'lowStock' => $this->getLowStockItems($seller, $storeId),
                'settlement' => $this->getSettlementOverview($seller),
                'reviews' => $this->getRecentReviews($seller),
                'storeStatus' => $this->getStoreStatusInfo($seller, $storeId),
                'checklist' => $this->getOnboardingChecklist($seller),
                'announcements' => $this->getAnnouncements(),
            ];
        });
    }

    /**
     * Compute 8 core KPI cards (Blueprint Section 15) with trends & click-through metrics.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getKpiStats(Seller $seller, ?int $storeId = null): array
    {
        $baseQuery = FulfilmentOrder::query()->where('seller_id', $seller->id);
        if ($storeId) {
            $baseQuery->where('store_id', $storeId);
        }

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $startOf30d = Carbon::now()->subDays(30);
        $prev30d = Carbon::now()->subDays(60);

        // 1. Today's Sales
        $todaySales = (float) (clone $baseQuery)
            ->whereDate('created_at', $today)
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->sum('seller_payable');

        $yesterdaySales = (float) (clone $baseQuery)
            ->whereDate('created_at', $yesterday)
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->sum('seller_payable');

        $todaySalesChange = $yesterdaySales > 0
            ? round((($todaySales - $yesterdaySales) / $yesterdaySales) * 100, 1)
            : ($todaySales > 0 ? 100.0 : 0.0);

        // 2. Total Orders (Last 30 Days)
        $currentOrdersCount = (clone $baseQuery)
            ->whereBetween('created_at', [$startOf30d, Carbon::now()])
            ->count();

        $previousOrdersCount = (clone $baseQuery)
            ->whereBetween('created_at', [$prev30d, $startOf30d])
            ->count();

        $ordersChange = $previousOrdersCount > 0
            ? round((($currentOrdersCount - $previousOrdersCount) / $previousOrdersCount) * 100, 1)
            : 0.0;

        // 3. Quick Orders (10-30 min Blinkit SLA)
        $quickOrdersCount = (clone $baseQuery)
            ->where('fulfilment_type', FulfilmentType::QUICK)
            ->whereBetween('created_at', [$startOf30d, Carbon::now()])
            ->count();

        $quickShare = $currentOrdersCount > 0
            ? round(($quickOrdersCount / $currentOrdersCount) * 100, 1)
            : 0.0;

        // 4. Online Orders (Standard Courier / National Marketplace)
        $onlineOrdersCount = (clone $baseQuery)
            ->where('fulfilment_type', FulfilmentType::STANDARD)
            ->whereBetween('created_at', [$startOf30d, Carbon::now()])
            ->count();

        // 5. Pending Action Required
        $pendingCount = (clone $baseQuery)
            ->whereIn('status', ['placed', 'store_accepted', 'packing', 'confirmed'])
            ->count();

        // 6. Delivered Orders
        $deliveredCount = (clone $baseQuery)
            ->where('status', 'delivered')
            ->count();

        // 7. Returns & Cancellations
        $returnsCount = (clone $baseQuery)
            ->whereIn('status', ['returned', 'cancelled'])
            ->count();

        $returnRate = $currentOrdersCount > 0
            ? round(($returnsCount / $currentOrdersCount) * 100, 1)
            : 0.0;

        // 8. Low Stock Items count
        $lowStockQuery = Inventory::query()
            ->whereHas('location', function ($q) use ($seller, $storeId) {
                $q->where('seller_id', $seller->id);
                if ($storeId) {
                    $q->where('store_id', $storeId);
                }
            })
            ->lowStock();

        $lowStockCount = $lowStockQuery->count();

        return [
            'today_sales' => [
                'label' => "Today's Sales",
                'value' => $todaySales,
                'formatted_value' => format_currency($todaySales),
                'change' => ($todaySalesChange >= 0 ? "+{$todaySalesChange}%" : "{$todaySalesChange}%") . " vs yesterday",
                'change_type' => $todaySalesChange >= 0 ? 'up' : 'down',
                'badge' => 'Gross Earnings',
                'color' => 'emerald',
                'url' => route('seller.dashboard', ['filter' => 'today']),
            ],
            'total_orders' => [
                'label' => 'Total Orders',
                'value' => $currentOrdersCount,
                'change' => ($ordersChange >= 0 ? "+{$ordersChange}%" : "{$ordersChange}%") . " (30d)",
                'change_type' => $ordersChange >= 0 ? 'up' : 'down',
                'badge' => 'Volume',
                'color' => 'primary',
                'url' => route('seller.dashboard', ['filter' => 'all']),
            ],
            'quick_orders' => [
                'label' => 'Quick Orders',
                'value' => $quickOrdersCount,
                'change' => "{$quickShare}% of volume",
                'change_type' => 'neutral',
                'badge' => '⚡ 10-30m SLA',
                'color' => 'amber',
                'url' => route('seller.dashboard', ['filter' => 'quick']),
            ],
            'online_orders' => [
                'label' => 'Online Orders',
                'value' => $onlineOrdersCount,
                'change' => 'National Courier',
                'change_type' => 'neutral',
                'badge' => 'Marketplace',
                'color' => 'sky',
                'url' => route('seller.dashboard', ['filter' => 'online']),
            ],
            'pending_orders' => [
                'label' => 'Pending Orders',
                'value' => $pendingCount,
                'change' => $pendingCount > 0 ? 'Action required' : 'Queue clear',
                'change_type' => $pendingCount > 0 ? 'down' : 'up',
                'badge' => $pendingCount > 0 ? 'Urgent' : 'All Clear',
                'color' => $pendingCount > 0 ? 'rose' : 'primary',
                'url' => route('seller.dashboard', ['filter' => 'pending']),
            ],
            'delivered_orders' => [
                'label' => 'Delivered Orders',
                'value' => $deliveredCount,
                'change' => 'Fulfilled',
                'change_type' => 'up',
                'badge' => 'Completed',
                'color' => 'emerald',
                'url' => route('seller.dashboard', ['filter' => 'delivered']),
            ],
            'returned_orders' => [
                'label' => 'Returns & RTO',
                'value' => $returnsCount,
                'change' => "{$returnRate}% return rate",
                'change_type' => $returnRate > 5 ? 'down' : 'neutral',
                'badge' => 'Reverse Logistics',
                'color' => 'amber',
                'url' => route('seller.dashboard', ['filter' => 'returns']),
            ],
            'low_stock' => [
                'label' => 'Low Stock Items',
                'value' => $lowStockCount,
                'change' => $lowStockCount > 0 ? 'Restock needed' : 'Healthy inventory',
                'change_type' => $lowStockCount > 0 ? 'down' : 'up',
                'badge' => 'Catalog Alert',
                'color' => $lowStockCount > 0 ? 'rose' : 'emerald',
                'url' => route('seller.dashboard', ['filter' => 'low_stock']),
            ],
        ];
    }

    /**
     * Compute Chart.js data packages (Sales 30d, Channels, Top 5 Products, Multi-store Comparison).
     *
     * @return array<string, mixed>
     */
    public function getChartsData(Seller $seller, ?int $storeId = null, string $range = '30d'): array
    {
        $startDate = Carbon::now()->subDays(30)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $baseOrdersQuery = FulfilmentOrder::query()
            ->where('seller_id', $seller->id)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($storeId) {
            $baseOrdersQuery->where('store_id', $storeId);
        }

        // 1. Sales Last 30 Days (Daily time-series)
        $dailyRaw = (clone $baseOrdersQuery)
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->selectRaw('DATE(created_at) as order_date, SUM(seller_payable) as revenue, COUNT(*) as orders_count')
            ->groupBy('order_date')
            ->orderBy('order_date')
            ->get()
            ->keyBy('order_date');

        $salesLabels = [];
        $salesValues = [];
        $ordersValues = [];

        for ($d = 29; $d >= 0; $d--) {
            $dt = Carbon::now()->subDays($d);
            $key = $dt->format('Y-m-d');
            $salesLabels[] = $dt->format('d M');
            $salesValues[] = isset($dailyRaw[$key]) ? (float) $dailyRaw[$key]->revenue : 0.0;
            $ordersValues[] = isset($dailyRaw[$key]) ? (int) $dailyRaw[$key]->orders_count : 0;
        }

        // 2. Orders by Channel (Quick vs Online vs Pickup)
        $channelRaw = (clone $baseOrdersQuery)
            ->selectRaw('fulfilment_type, COUNT(*) as count, SUM(seller_payable) as revenue')
            ->groupBy('fulfilment_type')
            ->pluck('count', 'fulfilment_type')
            ->toArray();

        $channelData = [
            'labels' => ['Quick Commerce (10-30m)', 'Online Marketplace', 'Store Pickup'],
            'data' => [
                $channelRaw[FulfilmentType::QUICK->value] ?? ($channelRaw['quick'] ?? 0),
                $channelRaw[FulfilmentType::STANDARD->value] ?? ($channelRaw['standard'] ?? 0),
                $channelRaw[FulfilmentType::PICKUP->value] ?? ($channelRaw['pickup'] ?? 0),
            ],
            'colors' => ['#f59e0b', '#6366f1', '#10b981'],
        ];

        // 3. Top 5 Products by Revenue / Volume
        $topProducts = OrderItem::query()
            ->whereHas('fulfilmentOrder', function ($q) use ($seller, $storeId, $startDate, $endDate) {
                $q->where('seller_id', $seller->id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereNotIn('status', ['cancelled', 'returned']);
                if ($storeId) {
                    $q->where('store_id', $storeId);
                }
            })
            ->selectRaw('product_id, title, SUM(qty) as total_qty, SUM(total) as total_revenue')
            ->groupBy('product_id', 'title')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        $topProductLabels = [];
        $topProductRevenues = [];
        foreach ($topProducts as $p) {
            $topProductLabels[] = mb_substr($p->title ?? 'Item #' . $p->product_id, 0, 20) . '...';
            $topProductRevenues[] = (float) $p->total_revenue;
        }

        // 4. Revenue by Store (for multi-store sellers)
        $stores = $seller->stores()->get();
        $isMultiStore = $stores->count() > 1;
        $storeRevenueData = ['labels' => [], 'data' => []];

        if ($isMultiStore) {
            $storeRevenueRaw = FulfilmentOrder::query()
                ->where('seller_id', $seller->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotIn('status', ['cancelled', 'returned'])
                ->selectRaw('store_id, SUM(seller_payable) as revenue')
                ->groupBy('store_id')
                ->pluck('revenue', 'store_id')
                ->toArray();

            foreach ($stores as $s) {
                $storeRevenueData['labels'][] = $s->name;
                $storeRevenueData['data'][] = (float) ($storeRevenueRaw[$s->id] ?? 0.0);
            }
        }

        return [
            'sales_trend' => [
                'labels' => $salesLabels,
                'sales' => $salesValues,
                'orders' => $ordersValues,
            ],
            'channel' => $channelData,
            'top_products' => [
                'labels' => $topProductLabels,
                'data' => $topProductRevenues,
            ],
            'is_multi_store' => $isMultiStore,
            'revenue_by_store' => $storeRevenueData,
        ];
    }

    /**
     * Retrieve live orders requiring immediate store acceptance or packing action.
     */
    public function getOrdersNeedingAction(Seller $seller, ?int $storeId = null, int $limit = 6): Collection
    {
        $query = FulfilmentOrder::query()
            ->with(['order', 'items', 'store.location'])
            ->where('seller_id', $seller->id)
            ->whereIn('status', ['placed', 'store_accepted', 'packing', 'confirmed'])
            ->orderBy('created_at', 'asc')
            ->limit($limit);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->get();
    }

    /**
     * Retrieve list of low-stock inventory variants with available count and location.
     */
    public function getLowStockItems(Seller $seller, ?int $storeId = null, int $limit = 6): Collection
    {
        $query = Inventory::query()
            ->with(['variant.product', 'location'])
            ->whereHas('location', function ($q) use ($seller, $storeId) {
                $q->where('seller_id', $seller->id);
                if ($storeId) {
                    $q->where('store_id', $storeId);
                }
            })
            ->lowStock()
            ->orderByRaw('(quantity_on_hand - quantity_reserved) asc')
            ->limit($limit);

        return $query->get();
    }

    /**
     * Settlement overview: accrued unpaid balance, next payout schedule, and primary bank.
     *
     * @return array<string, mixed>
     */
    public function getSettlementOverview(Seller $seller): array
    {
        $primaryBank = $seller->primaryBankAccount ?: $seller->bankAccounts()->first();

        // Total accrued payable from delivered orders not yet attached to paid settlement
        $accruedBalance = (float) FulfilmentOrder::query()
            ->where('seller_id', $seller->id)
            ->where('status', 'delivered')
            ->sum('seller_payable');

        // Recent settlements
        $recentSettlements = SellerSettlement::query()
            ->where('seller_id', $seller->id)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        // Calculate next payout day: next Wednesday / Friday cycle
        $nextPayoutDate = Carbon::now()->next(Carbon::WEDNESDAY)->format('l, d M Y');

        return [
            'accrued_balance' => $accruedBalance,
            'formatted_accrued' => format_currency($accruedBalance),
            'next_payout_date' => $nextPayoutDate,
            'primary_bank' => $primaryBank,
            'recent_settlements' => $recentSettlements,
        ];
    }

    /**
     * Recent customer reviews on products owned by this seller.
     */
    public function getRecentReviews(Seller $seller, int $limit = 4): Collection
    {
        return Review::query()
            ->with(['product', 'user'])
            ->whereHas('product', fn ($q) => $q->where('seller_id', $seller->id))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Store status info and operational hours for the selected or default store.
     *
     * @return array<string, mixed>
     */
    public function getStoreStatusInfo(Seller $seller, ?int $storeId = null): array
    {
        $store = $storeId ? $seller->stores()->find($storeId) : $seller->stores()->first();

        if (!$store) {
            return [
                'store' => null,
                'is_open' => false,
                'today_timings' => 'No store initialized',
            ];
        }

        $store->loadMissing('timings');
        $todayDayOfWeek = Carbon::now()->dayOfWeek; // 0 (Sun) - 6 (Sat)
        $todayTiming = $store->timings->firstWhere('day_of_week', $todayDayOfWeek);

        $timingLabel = '07:00 AM - 11:00 PM (15m SLA)';
        if ($todayTiming) {
            if ($todayTiming->is_closed) {
                $timingLabel = 'Closed Today (Holiday / Off)';
            } elseif ($todayTiming->opens_at && $todayTiming->closes_at) {
                $timingLabel = Carbon::parse($todayTiming->opens_at)->format('h:i A') . ' - ' . Carbon::parse($todayTiming->closes_at)->format('h:i A');
            }
        }

        return [
            'store' => $store,
            'is_open' => $store->status === StoreStatus::ACTIVE,
            'today_timings' => $timingLabel,
        ];
    }

    /**
     * Store onboarding readiness checklist items.
     *
     * @return array{items: array<int, array<string, mixed>>, completed_count: int, total_count: int, progress: int}
     */
    public function getOnboardingChecklist(Seller $seller): array
    {
        $store = $seller->stores()->first();
        $hasProducts = $seller->products()->exists();
        $hasStock = Inventory::query()->whereHas('location', fn ($q) => $q->where('seller_id', $seller->id))->where('quantity_on_hand', '>', 0)->exists();
        $hasTimings = $store && $store->timings()->exists();
        $isLive = $store && $store->status === StoreStatus::ACTIVE;
        $hasBranding = $store && (!empty($store->logo) || !empty($store->banner));

        $items = [
            [
                'title' => 'Store Branding (Logo & Banner)',
                'description' => 'Personalize your digital storefront branding.',
                'done' => (bool) $hasBranding,
                'url' => route('seller.onboarding'),
            ],
            [
                'title' => 'Add First Product',
                'description' => 'Publish SKU with attributes, barcodes, and MRP.',
                'done' => $hasProducts,
                'url' => route('seller.onboarding'),
            ],
            [
                'title' => 'Set Stock & Inventory',
                'description' => 'Assign inventory on hand at your store location.',
                'done' => $hasStock,
                'url' => route('seller.dashboard', ['filter' => 'low_stock']),
            ],
            [
                'title' => 'Set Operating Timings',
                'description' => 'Configure daily opening and closing hours for 10-30m dispatch.',
                'done' => (bool) $hasTimings,
                'url' => route('seller.onboarding'),
            ],
            [
                'title' => 'Store Active & Live',
                'description' => 'Toggle store status to Open to accept customer orders.',
                'done' => (bool) $isLive,
                'url' => route('seller.dashboard'),
            ],
        ];

        $completed = count(array_filter($items, fn ($i) => $i['done']));
        $total = count($items);
        $progress = (int) round(($completed / $total) * 100);

        return [
            'items' => $items,
            'completed_count' => $completed,
            'total_count' => $total,
            'progress' => $progress,
        ];
    }

    /**
     * Announcements from admin to sellers.
     *
     * @return array<int, array<string, string>>
     */
    public function getAnnouncements(): array
    {
        return [
            [
                'id' => 'anc-1',
                'badge' => '⚡ Festival Surge',
                'badge_color' => 'amber',
                'title' => 'Diwali Quick Commerce Surge Bonus Active',
                'description' => 'Earn an extra ₹15 incentive per order for handovers completed within 8 minutes of rider arrival.',
                'date' => 'Active Now',
            ],
            [
                'id' => 'anc-2',
                'badge' => '📦 OneStall Cargo',
                'badge_color' => 'sky',
                'title' => 'NH-48 Inter-City Cargo Linehaul Guaranteed 48h SLA',
                'description' => 'Consignment dispatches from Bengaluru to Mumbai Central now operate on fixed 48-hour scheduled departures.',
                'date' => '2 days ago',
            ],
            [
                'id' => 'anc-3',
                'badge' => '🎉 0% Platform Fee',
                'badge_color' => 'emerald',
                'title' => 'New Store Launch Incentive',
                'description' => '0% commission deduction on your first 100 fulfilled quick commerce deliveries this calendar month.',
                'date' => '5 days ago',
            ],
        ];
    }

    /**
     * Invalidate cached dashboard metrics for this seller.
     */
    public function clearCache(int $sellerId): void
    {
        $ranges = ['today', '7d', '30d', '90d'];
        foreach ($ranges as $r) {
            Cache::forget("seller_dash_{$sellerId}_all_stores_{$r}");
        }
    }
}
