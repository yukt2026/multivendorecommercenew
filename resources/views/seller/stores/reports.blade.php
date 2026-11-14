@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Store Performance Analytics'])

@section('content')
<div class="space-y-6" x-data="storeReportsPage({
    report: @js($report),
    storeName: '{{ $selectedStore ? addslashes($selectedStore->name) : 'All Outlets' }}',
    rangeLabel: '{{ $range }}'
})">
    <!-- Header with Filters & Export -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-2 border-b border-slate-200 dark:border-slate-800">
        <div>
            <div class="flex items-center space-x-2">
                <h1 class="text-xl font-extrabold text-slate-900 dark:text-white tracking-tight">Store Analytics &amp; Revenue Reports</h1>
                @if($selectedStore)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 dark:bg-indigo-950/70 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                        {{ $selectedStore->name }}
                    </span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        All Outlets Aggregated
                    </span>
                @endif
            </div>
            <p class="text-xs text-slate-500 mt-1">Real-time outlet level sales velocity, average basket size, quick commerce volume, and unit dispatch health.</p>
        </div>

        <!-- Filter Controls & Actions -->
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('seller.stores.reports') }}" id="report-filter-form" class="flex flex-wrap items-center gap-2">
                <!-- Outlet Filter -->
                <select 
                    name="store_id" 
                    onchange="this.form.submit()" 
                    class="text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white font-semibold shadow-2xs focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="all" {{ empty($selectedStore) ? 'selected' : '' }}>🏢 All Outlets (Aggregated)</option>
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" {{ $selectedStore && $selectedStore->id === $s->id ? 'selected' : '' }}>
                            📍 {{ $s->name }} ({{ $s->location?->city ?? 'Main' }})
                        </option>
                    @endforeach
                </select>

                <!-- Range Filter -->
                <select 
                    name="range" 
                    x-model="selectedRange"
                    @change="onRangeChange($event)"
                    class="text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white font-semibold shadow-2xs focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="7d">Last 7 Days</option>
                    <option value="30d">Last 30 Days</option>
                    <option value="90d">Last 90 Days</option>
                    <option value="year">Past 12 Months</option>
                    <option value="custom">Custom Date Range</option>
                </select>

                <!-- Custom Date Range Picker (shown when custom is selected) -->
                <div x-show="selectedRange === 'custom'" class="flex items-center space-x-1.5 bg-white dark:bg-slate-900 p-1 rounded-xl border border-slate-300 dark:border-slate-700 shadow-2xs">
                    <input 
                        type="date" 
                        name="from" 
                        value="{{ $from ?? $report['from'] }}" 
                        class="p-1 text-xs rounded-lg border-0 bg-transparent text-slate-900 dark:text-white focus:ring-0" 
                    />
                    <span class="text-xs text-slate-400">to</span>
                    <input 
                        type="date" 
                        name="to" 
                        value="{{ $to ?? $report['to'] }}" 
                        class="p-1 text-xs rounded-lg border-0 bg-transparent text-slate-900 dark:text-white focus:ring-0" 
                    />
                    <button 
                        type="submit" 
                        class="px-2.5 py-1 text-xs font-bold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition"
                    >
                        Apply
                    </button>
                </div>
            </form>

            <!-- Export CSV Button (Direct Streamed Server Download with Client Fallback) -->
            <a 
                href="{{ route('seller.stores.reports.export', request()->query()) }}" 
                class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-900 dark:bg-white text-white dark:text-slate-900 hover:bg-slate-800 dark:hover:bg-slate-100 transition shadow-2xs flex items-center space-x-1.5 shrink-0"
                title="Download full detailed performance spreadsheet"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Export CSV</span>
            </a>
        </div>
    </div>

    <!-- Active Window Indicator -->
    <div class="flex items-center justify-between text-xs text-slate-500 bg-slate-50 dark:bg-slate-900/60 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800">
        <div class="flex items-center space-x-2">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>Reporting Window: <strong class="text-slate-800 dark:text-slate-200">{{ \Carbon\Carbon::parse($report['from'])->format('d M Y') }}</strong> to <strong class="text-slate-800 dark:text-slate-200">{{ \Carbon\Carbon::parse($report['to'])->format('d M Y') }}</strong></span>
        </div>
        <span class="font-mono text-[11px] text-slate-400">Timezone: Asia/Kolkata (IST)</span>
    </div>

    <!-- 6-Metric KPI Grid -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- Metric 1: Gross Sales -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xs">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Delivered Revenue</span>
            <div class="text-xl font-extrabold text-slate-900 dark:text-white mt-1 font-mono">
                ₹{{ number_format($report['gross_sales'], 2) }}
            </div>
            <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold mt-1">
                {{ $report['delivered_orders'] }} successful orders
            </p>
        </div>

        <!-- Metric 2: Average Order Value -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xs">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Avg Order Value (AOV)</span>
            <div class="text-xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1 font-mono">
                ₹{{ number_format($report['aov'], 2) }}
            </div>
            <p class="text-[10px] text-slate-400 mt-1">Gross sales / delivered</p>
        </div>

        <!-- Metric 3: Total Orders -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xs">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Total Checkouts</span>
            <div class="text-xl font-extrabold text-slate-900 dark:text-white mt-1 font-mono">
                {{ $report['total_orders'] }}
            </div>
            <p class="text-[10px] text-slate-400 mt-1">
                {{ $report['in_flight_orders'] }} in-flight / processing
            </p>
        </div>

        <!-- Metric 4: Quick Commerce -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xs">
            <span class="text-[11px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider block flex items-center space-x-1">
                <span>⚡ Instant Quick</span>
            </span>
            <div class="text-xl font-extrabold text-amber-500 mt-1 font-mono">
                ₹{{ number_format($report['quick_orders_sales'], 2) }}
            </div>
            <p class="text-[10px] text-slate-400 mt-1">
                {{ $report['quick_orders_count'] }} orders (10-30m)
            </p>
        </div>

        <!-- Metric 5: Standard Cargo -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xs">
            <span class="text-[11px] font-bold text-sky-600 dark:text-sky-400 uppercase tracking-wider block flex items-center space-x-1">
                <span>🚚 Standard Cargo</span>
            </span>
            <div class="text-xl font-extrabold text-sky-500 mt-1 font-mono">
                ₹{{ number_format($report['standard_orders_sales'], 2) }}
            </div>
            <p class="text-[10px] text-slate-400 mt-1">
                {{ $report['standard_orders_count'] }} orders
            </p>
        </div>

        <!-- Metric 6: Fulfillment Health -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xs">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Fulfillment Health</span>
            <div class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1 font-mono">
                {{ $report['fulfillment_rate'] }}%
            </div>
            <p class="text-[10px] text-rose-500 mt-1 font-semibold">
                {{ $report['cancellation_rate'] }}% cancellation rate
            </p>
        </div>
    </div>

    <!-- Chart & Channel Performance Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Sales & Orders Trajectory Line Chart (2 Columns) -->
        <div class="lg:col-span-2 p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                        <span>Revenue &amp; Order Volume Velocity</span>
                        <span class="text-[10px] px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-mono">Dual-Axis</span>
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">Consecutive timeline tracking delivered revenue against checkout volume.</p>
                </div>
            </div>

            <!-- Canvas Container -->
            <div class="relative h-72 w-full">
                <canvas id="storeReportsChart"></canvas>
            </div>
        </div>

        <!-- Fulfillment Channels & Order Health (1 Column) -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xs space-y-5">
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Fulfillment Mode Split</h2>
                <p class="text-xs text-slate-400 mt-0.5">Order distribution across fulfillment channels.</p>
            </div>

            @php
                $totSales = max(0.01, (float) $report['gross_sales']);
                $quickPct = round(($report['quick_orders_sales'] / $totSales) * 100);
                $stdPct = round(($report['standard_orders_sales'] / $totSales) * 100);
                $pickupPct = round(($report['pickup_orders_sales'] / $totSales) * 100);
            @endphp

            <div class="space-y-4">
                <!-- Quick Commerce -->
                <div>
                    <div class="flex items-center justify-between text-xs font-bold mb-1">
                        <span class="text-amber-600 dark:text-amber-400 flex items-center space-x-1">
                            <span>⚡ Quick Commerce</span>
                        </span>
                        <span class="font-mono text-slate-800 dark:text-slate-200">{{ $quickPct }}% (₹{{ number_format($report['quick_orders_sales'], 2) }})</span>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden">
                        <div class="bg-amber-500 h-2 rounded-full transition-all duration-500" style="width: {{ $quickPct }}%"></div>
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">{{ $report['quick_orders_count'] }} orders delivered via 10-30m fleet</div>
                </div>

                <!-- Standard Cargo -->
                <div>
                    <div class="flex items-center justify-between text-xs font-bold mb-1">
                        <span class="text-sky-600 dark:text-sky-400 flex items-center space-x-1">
                            <span>🚚 Standard Cargo Delivery</span>
                        </span>
                        <span class="font-mono text-slate-800 dark:text-slate-200">{{ $stdPct }}% (₹{{ number_format($report['standard_orders_sales'], 2) }})</span>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden">
                        <div class="bg-sky-500 h-2 rounded-full transition-all duration-500" style="width: {{ $stdPct }}%"></div>
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">{{ $report['standard_orders_count'] }} orders via linehaul &amp; hub network</div>
                </div>

                <!-- Store Pickup -->
                @if($report['pickup_orders_count'] > 0)
                <div>
                    <div class="flex items-center justify-between text-xs font-bold mb-1">
                        <span class="text-purple-600 dark:text-purple-400 flex items-center space-x-1">
                            <span>🛍️ Click &amp; Collect Pickup</span>
                        </span>
                        <span class="font-mono text-slate-800 dark:text-slate-200">{{ $pickupPct }}% (₹{{ number_format($report['pickup_orders_sales'], 2) }})</span>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden">
                        <div class="bg-purple-500 h-2 rounded-full transition-all duration-500" style="width: {{ $pickupPct }}%"></div>
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">{{ $report['pickup_orders_count'] }} in-store collections</div>
                </div>
                @endif
            </div>

            <!-- Health & Safety Summary Box -->
            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-slate-500 font-medium">Delivered Success:</span>
                    <span class="font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ $report['delivered_orders'] }} of {{ $report['total_orders'] }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500 font-medium">Cancellations:</span>
                    <span class="font-bold text-rose-500 font-mono">{{ $report['cancelled_orders'] }} ({{ $report['cancellation_rate'] }}%)</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500 font-medium">In-Flight / Pending:</span>
                    <span class="font-bold text-indigo-500 font-mono">{{ $report['in_flight_orders'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Outlet Performance Comparison (Table) -->
    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Store Outlets Performance Breakdown</h2>
                <p class="text-xs text-slate-400 mt-0.5">Individual unit sales, quick commerce readiness, and operational status across all outlets.</p>
            </div>
            <a href="{{ route('seller.stores.create') }}" class="px-3 py-1.5 text-xs font-bold rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 transition inline-flex items-center space-x-1 shrink-0">
                <span>+ Add Outlet</span>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="py-3 px-4">Outlet Name &amp; Location</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Quick Commerce</th>
                        <th class="py-3 px-4 text-right">Orders</th>
                        <th class="py-3 px-4 text-right">Gross Sales (₹)</th>
                        <th class="py-3 px-4 text-right">AOV (₹)</th>
                        <th class="py-3 px-4 text-center">Cancel Rate</th>
                        <th class="py-3 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse($report['outlet_breakdown'] as $ob)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $ob['name'] }}</div>
                                <div class="text-[11px] text-slate-400 truncate max-w-xs">{{ $ob['city'] }} &bull; {{ $ob['address'] }}</div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($ob['is_open'])
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300">
                                        ● Open Now
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                        Closed
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($ob['accepts_quick_commerce'])
                                    <span class="inline-flex items-center text-amber-500 font-bold text-[11px]">
                                        ⚡ Active
                                    </span>
                                @else
                                    <span class="text-slate-400 text-[11px]">-</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-800 dark:text-slate-200">
                                {{ $ob['total_orders'] }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                ₹{{ number_format($ob['gross_sales'], 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-slate-600 dark:text-slate-300">
                                ₹{{ number_format($ob['aov'], 2) }}
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-slate-500">
                                {{ $ob['cancellation_rate'] }}%
                            </td>
                            <td class="py-3 px-4 text-right space-x-1">
                                <a 
                                    href="{{ route('seller.stores.reports', ['store_id' => $ob['id'], 'range' => $range]) }}" 
                                    class="px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/70 dark:text-indigo-400 hover:bg-indigo-100 transition inline-block"
                                >
                                    Filter
                                </a>
                                <a 
                                    href="{{ route('seller.store.edit', ['store_id' => $ob['id']]) }}" 
                                    class="px-2.5 py-1 text-[11px] font-semibold rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 transition inline-block"
                                >
                                    Manage
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400">No store outlets registered.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Products by Sales Velocity -->
    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xs space-y-4">
        <div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Top 10 Products by Sales Velocity</h2>
            <p class="text-xs text-slate-400 mt-0.5">Highest revenue-generating catalog items during this period.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="py-3 px-4 w-12 text-center">#</th>
                        <th class="py-3 px-4">Product Title</th>
                        <th class="py-3 px-4 text-center">SKU</th>
                        <th class="py-3 px-4 text-center">Units Sold</th>
                        <th class="py-3 px-4 text-right">Delivered Revenue</th>
                        <th class="py-3 px-4 text-right">Contribution</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse($report['top_items'] as $index => $item)
                        @php
                            $tot = max(0.01, (float) $report['gross_sales']);
                            $contrib = round(((float) $item->total_sales / $tot) * 100, 1);
                        @endphp
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                            <td class="py-3 px-4 text-center font-mono font-bold text-slate-400">
                                {{ $index + 1 }}
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-900 dark:text-white truncate max-w-sm">
                                {{ $item->title }}
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-[11px] text-slate-500">
                                {{ $item->sku ?: 'DEFAULT' }}
                            </td>
                            <td class="py-3 px-4 text-center font-mono font-bold text-slate-800 dark:text-slate-200">
                                {{ $item->total_qty }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                ₹{{ number_format((float) $item->total_sales, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="inline-flex items-center space-x-2">
                                    <span class="font-mono text-slate-500 text-[11px]">{{ $contrib }}%</span>
                                    <div class="w-16 bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                        <div class="bg-indigo-600 h-1.5 rounded-full" style="width: {{ min(100, $contrib) }}%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                No product sales recorded during this date window.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function storeReportsPage(config) {
    return {
        selectedRange: '{{ $range }}',
        chartInstance: null,

        init() {
            this.ensureChartLoaded().then(() => {
                this.renderChart(config.report.chart);
            });
        },

        ensureChartLoaded() {
            return new Promise((resolve) => {
                if (typeof window.Chart !== 'undefined') {
                    resolve();
                    return;
                }
                const interval = setInterval(() => {
                    if (typeof window.Chart !== 'undefined') {
                        clearInterval(interval);
                        resolve();
                    }
                }, 100);
                setTimeout(() => {
                    clearInterval(interval);
                    resolve();
                }, 4000);
            });
        },

        onRangeChange(e) {
            const val = e.target.value;
            if (val !== 'custom') {
                document.getElementById('report-filter-form').submit();
            }
        },

        renderChart(chartData) {
            const ctx = document.getElementById('storeReportsChart');
            if (!ctx || typeof window.Chart === 'undefined') return;

            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#94a3b8' : '#64748b';
            const gridColor = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)';

            this.chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels || [],
                    datasets: [
                        {
                            label: 'Delivered Revenue (₹)',
                            data: chartData.sales || [],
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.12)',
                            fill: true,
                            tension: 0.35,
                            borderWidth: 2.5,
                            pointRadius: (chartData.labels && chartData.labels.length > 25) ? 1.5 : 3.5,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#10b981',
                            yAxisID: 'y'
                        },
                        {
                            label: 'Checkout Orders',
                            data: chartData.orders || [],
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99, 102, 241, 0.05)',
                            fill: false,
                            tension: 0.35,
                            borderWidth: 2,
                            borderDash: [4, 4],
                            pointRadius: (chartData.labels && chartData.labels.length > 25) ? 1.5 : 3.5,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#6366f1',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: textColor,
                                font: { family: 'Inter', size: 11, weight: 'bold' },
                                boxWidth: 12,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: isDark ? '#0f172a' : '#1e293b',
                            titleColor: '#ffffff',
                            bodyColor: '#cbd5e1',
                            borderColor: isDark ? '#334155' : '#475569',
                            borderWidth: 1,
                            padding: 10,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) label += ': ';
                                    if (context.dataset.yAxisID === 'y') {
                                        label += '₹' + Number(context.parsed.y).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                                    } else {
                                        label += context.parsed.y + ' orders';
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: gridColor },
                            ticks: { 
                                color: textColor, 
                                font: { family: 'Inter', size: 10 },
                                maxRotation: 45
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            grid: { color: gridColor },
                            ticks: {
                                color: textColor,
                                font: { family: 'Inter', size: 10 },
                                callback: function(val) {
                                    return '₹' + Number(val).toLocaleString('en-IN');
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: {
                                color: textColor,
                                font: { family: 'Inter', size: 10 },
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        },

        exportCsv() {
            const rep = config.report;
            let csv = [];
            csv.push(['OneStall Store Performance Report']);
            csv.push(['Store / Scope', config.storeName]);
            csv.push(['Window Range', `${rep.from} to ${rep.to} (${config.rangeLabel})`]);
            csv.push([]);
            csv.push(['KPI Summary', 'Value']);
            csv.push(['Gross Delivered Sales (INR)', rep.gross_sales]);
            csv.push(['Average Order Value (INR)', rep.aov]);
            csv.push(['Total Checkouts', rep.total_orders]);
            csv.push(['Delivered Orders', rep.delivered_orders]);
            csv.push(['Cancelled Orders', rep.cancelled_orders]);
            csv.push(['Quick Commerce Sales (INR)', rep.quick_orders_sales]);
            csv.push(['Quick Commerce Orders', rep.quick_orders_count]);
            csv.push(['Standard Delivery Sales (INR)', rep.standard_orders_sales]);
            csv.push(['Standard Delivery Orders', rep.standard_orders_count]);
            csv.push(['Fulfillment Health Rate (%)', rep.fulfillment_rate]);
            csv.push(['Cancellation Rate (%)', rep.cancellation_rate]);
            csv.push([]);

            // Timeline rows
            csv.push(['Timeline Breakdown']);
            csv.push(['Date', 'Delivered Sales (INR)', 'Orders Count']);
            const labels = rep.chart.labels || [];
            const sales = rep.chart.sales || [];
            const orders = rep.chart.orders || [];
            for (let i = 0; i < labels.length; i++) {
                csv.push([labels[i], sales[i] || 0, orders[i] || 0]);
            }
            csv.push([]);

            // Top items
            csv.push(['Top Products Velocity']);
            csv.push(['Rank', 'Product Title', 'SKU', 'Units Sold', 'Total Revenue (INR)']);
            (rep.top_items || []).forEach((item, idx) => {
                csv.push([idx + 1, `"${(item.title || '').replace(/"/g, '""')}"`, item.sku || 'DEFAULT', item.total_qty, item.total_sales]);
            });

            const csvString = "\uFEFF" + csv.map(row => row.map(cell => {
                const str = String(cell ?? '');
                return str.includes(',') || str.includes('"') || str.includes('\n') ? `"${str.replace(/"/g, '""')}"` : str;
            }).join(',')).join('\r\n');

            const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', `store_report_${config.rangeLabel}_${new Date().toISOString().slice(0, 10)}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);

            window.dispatchEvent(new CustomEvent('toast', {
                detail: { message: '📊 Performance report downloaded successfully as CSV!', type: 'success' }
            }));
        }
    };
}
</script>
@endsection
