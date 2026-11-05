@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Store Performance Analytics'])

@section('content')
<div class="space-y-6">
    <!-- Header with Filters -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">Store Analytics &amp; Revenue Reports</h1>
            <p class="text-xs text-slate-500">Real-time outlet level sales, AOV, quick commerce volume, and unit velocity.</p>
        </div>

        <form method="GET" action="{{ route('seller.stores.reports') }}" class="flex items-center space-x-2">
            <!-- Outlet Filter -->
            <select name="store_id" onchange="this.form.submit()" class="text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white font-semibold">
                <option value="all">All Outlets (Aggregated)</option>
                @foreach($stores as $s)
                    <option value="{{ $s->id }}" {{ $selectedStore && $selectedStore->id === $s->id ? 'selected' : '' }}>
                        {{ $s->name }}
                    </option>
                @endforeach
            </select>

            <!-- Range Filter -->
            <select name="range" onchange="this.form.submit()" class="text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white font-semibold">
                <option value="7d" {{ $range === '7d' ? 'selected' : '' }}>Last 7 Days</option>
                <option value="30d" {{ $range === '30d' ? 'selected' : '' }}>Last 30 Days</option>
                <option value="90d" {{ $range === '90d' ? 'selected' : '' }}>Last 90 Days</option>
                <option value="year" {{ $range === 'year' ? 'selected' : '' }}>Past 12 Months</option>
            </select>
        </form>
    </div>

    <!-- KPI Strip -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card 
            label="Delivered Revenue" 
            :value="'₹' . number_format($report['gross_sales'], 2)" 
            icon="currency-rupee" 
            color="emerald"
            helpText="{{ $report['delivered_orders'] }} successful orders"
        />
        <x-stat-card 
            label="Average Order Value" 
            :value="'₹' . number_format($report['aov'], 2)" 
            icon="tag" 
            color="indigo"
            helpText="Per delivered package"
        />
        <x-stat-card 
            label="Quick Commerce Revenue" 
            :value="'₹' . number_format($report['quick_orders_sales'], 2)" 
            icon="bolt" 
            color="amber"
            helpText="{{ $report['quick_orders_count'] }} instant 10-30m orders"
        />
        <x-stat-card 
            label="Standard Orders" 
            :value="'₹' . number_format($report['standard_orders_sales'], 2)" 
            icon="truck" 
            color="sky"
            helpText="{{ $report['standard_orders_count'] }} cargo consignments"
        />
    </div>

    <!-- Fulfillment Distribution Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Products Table -->
        <x-card title="Top 5 Products by Sales Velocity" :padded="false">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            <th class="py-2.5 px-4">Product Title</th>
                            <th class="py-2.5 px-4 text-center">Units Sold</th>
                            <th class="py-2.5 px-4 text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        @forelse($report['top_items'] as $item)
                            <tr>
                                <td class="py-2.5 px-4 font-bold text-slate-900 dark:text-white truncate max-w-xs">{{ $item->title }}</td>
                                <td class="py-2.5 px-4 text-center font-mono font-bold">{{ $item->total_qty }}</td>
                                <td class="py-2.5 px-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    ₹{{ number_format((float) $item->total_sales, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-6 text-center text-slate-400">No sales recorded during this date window.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <!-- Channel Performance Card -->
        <x-card title="Fulfillment Mode Share">
            <div class="space-y-4 pt-2">
                <div>
                    <div class="flex items-center justify-between text-xs font-bold mb-1">
                        <span class="text-amber-600 dark:text-amber-400">⚡ Quick Commerce (10-30 min)</span>
                        <span class="font-mono">{{ $report['quick_orders_count'] }} orders (₹{{ number_format($report['quick_orders_sales'], 2) }})</span>
                    </div>
                    @php
                        $totalSales = max(0.01, $report['gross_sales']);
                        $quickPct = round(($report['quick_orders_sales'] / $totalSales) * 100);
                        $stdPct = round(($report['standard_orders_sales'] / $totalSales) * 100);
                    @endphp
                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-amber-500 h-2.5 rounded-full" style="width: {{ $quickPct }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between text-xs font-bold mb-1">
                        <span class="text-sky-600 dark:text-sky-400">🚚 Standard Cargo Logistics</span>
                        <span class="font-mono">{{ $report['standard_orders_count'] }} orders (₹{{ number_format($report['standard_orders_sales'], 2) }})</span>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-sky-500 h-2.5 rounded-full" style="width: {{ $stdPct }}%"></div>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700/60 text-xs text-slate-500">
                    <span class="font-bold text-slate-700 dark:text-slate-300">Cancellation Rate:</span> 
                    {{ $report['total_orders'] > 0 ? round(($report['cancelled_orders'] / $report['total_orders']) * 100, 1) : 0 }}%
                    ({{ $report['cancelled_orders'] }} of {{ $report['total_orders'] }} orders)
                </div>
            </div>
        </x-card>
    </div>
</div>
@endsection
