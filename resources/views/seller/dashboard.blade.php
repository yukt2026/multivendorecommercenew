@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Seller Studio'])

@section('content')
<div class="space-y-8" x-data="sellerDashboard({{ Js::from([
    'charts' => $charts,
    'chartDataUrl' => route('seller.chart_data'),
    'activeStoreId' => $activeStore?->id,
    'range' => $range,
]) }})">
    <!-- Page Header & Top Operations Bar -->
    <x-page-header 
        title="Merchant Operations Hub" 
        subtitle="Real-time multi-store orders, 10-30m instant dispatch SLA, and catalog inventory."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), ($activeStore ? $activeStore->name : 'All Outlets') => '#']"
    >
        <x-slot name="badge">
            <div class="flex items-center space-x-2">
                @if($storeStatus['is_open'])
                    <x-badge variant="success" dot pulse size="sm">
                        Store Open &amp; Accepting Orders
                    </x-badge>
                @else
                    <x-badge variant="danger" dot size="sm">
                        Store Offline / Closed
                    </x-badge>
                @endif
                <span class="text-[11px] text-slate-500 font-medium hidden sm:inline">&bull; {{ $storeStatus['today_timings'] }}</span>
            </div>
        </x-slot>

        <x-slot name="actions">
            <!-- Store Open/Close Toggle Form -->
            <form method="POST" action="{{ route('seller.store.toggle_status') }}" class="inline">
                @csrf
                <input type="hidden" name="store_id" value="{{ $activeStore?->id }}">
                <button 
                    type="submit" 
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 border {{ $storeStatus['is_open'] ? 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100 dark:bg-rose-950/40 dark:border-rose-800' : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:border-emerald-800' }}"
                >
                    <span class="w-2 h-2 rounded-full {{ $storeStatus['is_open'] ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
                    <span>{{ $storeStatus['is_open'] ? 'Close Store Temporarily' : 'Open Store Now' }}</span>
                </button>
            </form>

            <x-button variant="outline" size="sm" href="{{ route('home') }}" target="_blank">
                Live Storefront &rarr;
            </x-button>
        </x-slot>
    </x-page-header>

    <!-- 8 KPI Cards Grid (Blueprint Section 15) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        @foreach($kpi as $metricKey => $stat)
            <a href="{{ $stat['url'] }}" class="block group transition transform hover:-translate-y-0.5">
                <x-stat-card 
                    :label="$stat['label']" 
                    :value="$stat['formatted_value'] ?? $stat['value']" 
                    :change="$stat['change']" 
                    :changeType="$stat['change_type']"
                    :badge="$stat['badge']"
                    :color="$stat['color']" 
                />
            </a>
        @endforeach
    </div>

    <!-- Charts Section: Sales 30d, Channels, Top Products & Store Comparison -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Sales & Orders 30-Day Trend (Dual Line Chart) -->
        <div class="lg:col-span-2">
            <x-card title="Sales & Order Trajectory" subtitle="Revenue (₹) and daily order counts across the last 30 days">
                <x-slot name="action">
                    <div class="flex items-center space-x-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl text-xs font-bold">
                        <button 
                            type="button" 
                            @click="changeRange('7d')" 
                            :class="activeRange === '7d' ? 'bg-white dark:bg-slate-900 shadow-xs text-indigo-600' : 'text-slate-500 hover:text-slate-900'"
                            class="px-2.5 py-1 rounded-lg transition"
                        >7d</button>
                        <button 
                            type="button" 
                            @click="changeRange('30d')" 
                            :class="activeRange === '30d' ? 'bg-white dark:bg-slate-900 shadow-xs text-indigo-600' : 'text-slate-500 hover:text-slate-900'"
                            class="px-2.5 py-1 rounded-lg transition"
                        >30d</button>
                        <button 
                            type="button" 
                            @click="changeRange('90d')" 
                            :class="activeRange === '90d' ? 'bg-white dark:bg-slate-900 shadow-xs text-indigo-600' : 'text-slate-500 hover:text-slate-900'"
                            class="px-2.5 py-1 rounded-lg transition"
                        >90d</button>
                    </div>
                </x-slot>

                <div class="relative h-72">
                    <canvas id="sellerSalesChart"></canvas>
                </div>
            </x-card>
        </div>

        <!-- Orders by Channel (Donut Chart) -->
        <div>
            <x-card title="Fulfillment Channels" subtitle="10-30m Quick Commerce vs National Courier">
                <div class="relative h-60 flex items-center justify-center">
                    <canvas id="sellerChannelChart"></canvas>
                </div>
                <div class="grid grid-cols-3 gap-2 pt-3 border-t border-slate-100 dark:border-slate-800 text-center text-xs">
                    <div>
                        <div class="font-extrabold text-amber-500">{{ $charts['channel']['data'][0] ?? 0 }}</div>
                        <div class="text-[10px] text-slate-400 font-semibold">⚡ Quick 15m</div>
                    </div>
                    <div>
                        <div class="font-extrabold text-indigo-500">{{ $charts['channel']['data'][1] ?? 0 }}</div>
                        <div class="text-[10px] text-slate-400 font-semibold">📦 Standard</div>
                    </div>
                    <div>
                        <div class="font-extrabold text-emerald-500">{{ $charts['channel']['data'][2] ?? 0 }}</div>
                        <div class="text-[10px] text-slate-400 font-semibold">🏬 Pickup</div>
                    </div>
                </div>
            </x-card>
        </div>
    </div>

    <!-- Secondary Charts & Multi-Store Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top 5 Products by Revenue -->
        <x-card title="Top 5 Products by Revenue" subtitle="Best-performing catalog items this month">
            <div class="relative h-56">
                <canvas id="sellerTopProductsChart"></canvas>
            </div>
        </x-card>

        <!-- Revenue by Store Outlet (If Multi-store) or Category Distribution -->
        @if($charts['is_multi_store'] && !empty($charts['revenue_by_store']['labels']))
            <x-card title="Revenue by Physical Store Outlet" subtitle="Comparison of earnings across physical branch locations">
                <div class="relative h-56">
                    <canvas id="sellerStoreComparisonChart"></canvas>
                </div>
            </x-card>
        @else
            <!-- Store Dispatch & Timings Profile -->
            <x-card title="Physical Store Performance" subtitle="Hyper-local dispatch metrics & profile">
                <div class="space-y-3 text-xs">
                    <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500">Active Outlet:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $activeStore ? $activeStore->name : ($stores->first()?->name ?? 'Default Store') }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500">Store City & Pincode:</span>
                        <span class="font-mono text-slate-800 dark:text-slate-200">{{ $activeStore?->location?->city ?? 'Bengaluru' }} - {{ $activeStore?->location?->pincode ?? '560038' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500">Instant Delivery Radius:</span>
                        <span class="font-bold text-amber-500">{{ setting('default_delivery_radius_km', 8.00) }} km (Blinkit Mode)</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-slate-500">POS Integration Status:</span>
                        <span class="text-emerald-500 font-bold flex items-center space-x-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>Live Bi-directional Sync</span>
                        </span>
                    </div>
                </div>
            </x-card>
        @endif
    </div>

    <!-- Widgets Section 1: Orders Needing Action & Low Stock Items -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Orders Needing Action (Urgent Live Queue) -->
        <div class="lg:col-span-2">
            <x-card 
                title="Orders Needing Immediate Action" 
                subtitle="Accept, pack, and hand over orders to meet your 10-30 min delivery SLA"
            >
                <x-slot name="action">
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 flex items-center space-x-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                        <span>{{ $actionOrders->count() }} Live Orders</span>
                    </span>
                </x-slot>

                <div class="space-y-3">
                    @forelse($actionOrders as $order)
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex items-center space-x-2">
                                    <span class="text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400">#{{ $order->fulfilment_order_number }}</span>
                                    <x-badge 
                                        :variant="$order->fulfilment_type === \App\Enums\FulfilmentType::QUICK ? 'warning' : 'info'" 
                                        size="sm"
                                    >
                                        {{ $order->fulfilment_type === \App\Enums\FulfilmentType::QUICK ? '⚡ Quick 15m' : '📦 Courier' }}
                                    </x-badge>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        {{ str_replace('_', ' ', $order->status) }}
                                    </span>
                                </div>

                                <div class="text-xs font-medium text-slate-800 dark:text-slate-200">
                                    {{ $order->items->count() }} items &bull; {{ format_currency($order->seller_payable) }} payable
                                </div>

                                <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                    Placed {{ $order->created_at->diffForHumans() }} &bull; Store: {{ $order->store?->name ?? 'Default' }}
                                </div>
                            </div>

                            <!-- Inline Action Buttons based on status -->
                            <div class="flex items-center space-x-2">
                                @if(in_array($order->status, ['placed', 'confirmed']))
                                    <form method="POST" action="{{ route('seller.orders.accept', $order) }}">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-xs transition">
                                            Accept Order
                                        </button>
                                    </form>
                                @endif

                                @if(in_array($order->status, ['store_accepted', 'confirmed']))
                                    <form method="POST" action="{{ route('seller.orders.pack', $order) }}">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs shadow-xs transition">
                                            Start Packing
                                        </button>
                                    </form>
                                @endif

                                @if(in_array($order->status, ['packing']))
                                    <form method="POST" action="{{ route('seller.orders.ready', $order) }}">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition">
                                            Ready For Handover
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <x-empty-state 
                            title="No urgent orders pending" 
                            description="All incoming store orders have been accepted and dispatched!" 
                        />
                    @endforelse
                </div>
            </x-card>
        </div>

        <!-- Low Stock Items List with Quick Restock Modal -->
        <div>
            <x-card title="Low Stock Warning" subtitle="Items near stock-out threshold">
                <div class="space-y-3">
                    @forelse($lowStock as $item)
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 flex items-center justify-between text-xs">
                            <div class="min-w-0 flex-1 pr-2">
                                <div class="font-bold text-slate-900 dark:text-white truncate">
                                    {{ $item->variant?->product?->title ?? 'Product SKU' }}
                                </div>
                                <div class="text-[11px] text-slate-400 font-mono">
                                    {{ $item->variant?->sku }} &bull; Available: <strong class="text-rose-500 font-bold">{{ $item->available_quantity }}</strong> (Min: {{ $item->low_stock_threshold }})
                                </div>
                            </div>

                            <button 
                                type="button" 
                                @click="openRestockModal(@js($item))"
                                class="px-2.5 py-1 text-xs font-bold rounded-lg bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 hover:bg-rose-100 transition shrink-0"
                            >
                                Restock +
                            </button>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-slate-400">
                            🎉 All inventory levels are healthy!
                        </div>
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>

    <!-- Widgets Section 2: Settlements, Reviews, Checklist, Announcements -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- 1. Settlements & Payouts Card -->
        <x-card title="Settlements & Payouts" subtitle="Weekly automated clearing">
            <div class="space-y-3 text-xs">
                <div>
                    <div class="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Unsettled Accrued Balance</div>
                    <div class="text-xl font-black text-emerald-600 dark:text-emerald-400 mt-0.5">
                        {{ $settlement['formatted_accrued'] }}
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                    <div class="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Next Scheduled Payout</div>
                    <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">
                        {{ $settlement['next_payout_date'] }}
                    </div>
                </div>

                @if($settlement['primary_bank'])
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                        <div class="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Payout Bank Account</div>
                        <div class="font-mono text-slate-800 dark:text-slate-200 mt-0.5 truncate">
                            {{ $settlement['primary_bank']->bank_name }} (&bull;&bull;&bull;&bull; {{ substr($settlement['primary_bank']->account_no, -4) }})
                        </div>
                    </div>
                @endif
            </div>
        </x-card>

        <!-- 2. Recent Customer Reviews Card -->
        <x-card title="Customer Feedback" subtitle="Recent ratings & reviews">
            <div class="space-y-3">
                @forelse($reviews as $rev)
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/60 dark:border-slate-800 text-xs space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ $rev->user?->name ?? 'Verified Shopper' }}</span>
                            <div class="flex items-center text-amber-400 text-xs">
                                @for($i = 0; $i < $rev->rating; $i++)
                                    ★
                                @endfor
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-600 dark:text-slate-400 line-clamp-2">"{{ $rev->body }}"</p>
                        <div class="text-[10px] text-slate-400 truncate">&bull; {{ $rev->product?->title }}</div>
                    </div>
                @empty
                    <div class="py-6 text-center text-xs text-slate-400">
                        No customer reviews yet.
                    </div>
                @endforelse
            </div>
        </x-card>

        <!-- 3. Onboarding Launchpad Checklist -->
        <x-card title="Merchant Readiness" subtitle="Checklist to scale sales">
            <div class="space-y-3">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ $checklist['progress'] }}% Ready</span>
                    <span class="text-slate-400">{{ $checklist['completed_count'] }}/{{ $checklist['total_count'] }} Steps</span>
                </div>
                <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                    <div class="bg-indigo-600 h-2 rounded-full transition-all" style="width: {{ $checklist['progress'] }}%"></div>
                </div>

                <div class="space-y-2 pt-1 text-xs">
                    @foreach($checklist['items'] as $item)
                        <div class="flex items-center space-x-2">
                            <span class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-bold {{ $item['done'] ? 'bg-emerald-500 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-400' }}">
                                {{ $item['done'] ? '✓' : '•' }}
                            </span>
                            <span class="{{ $item['done'] ? 'text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-400' }}">
                                {{ $item['title'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-card>

        <!-- 4. Platform Announcements from Admin -->
        <x-card title="Platform Notices" subtitle="Updates from OneStall Command">
            <div class="space-y-3">
                @foreach($announcements as $anc)
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/60 dark:border-slate-800 text-xs space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">{{ $anc['badge'] }}</span>
                            <span class="text-[10px] text-slate-400">{{ $anc['date'] }}</span>
                        </div>
                        <h4 class="font-bold text-slate-900 dark:text-white">{{ $anc['title'] }}</h4>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $anc['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>

    <!-- Quick Restock Modal -->
    <x-modal name="quick-restock-modal" size="sm">
        <x-slot:header>
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Quick Inventory Replenishment</h3>
        </x-slot:header>

        <form :action="restockAction" method="POST" class="space-y-4">
            @csrf

            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Target Variant</span>
                <div class="font-bold text-sm text-slate-900 dark:text-white" x-text="restockItem?.variant?.product?.title || 'Product SKU'"></div>
                <div class="text-xs text-slate-500 font-mono" x-text="'SKU: ' + (restockItem?.variant?.sku || 'N/A')"></div>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
                <span class="text-slate-500">Current On Hand:</span>
                <span class="font-bold text-slate-900 dark:text-white" x-text="restockItem?.quantity_on_hand || 0"></span>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Units to Add to Stock
                </label>
                <div class="flex items-center space-x-2 mb-2">
                    <button type="button" @click="restockQty = 10" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200">+10</button>
                    <button type="button" @click="restockQty = 25" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200">+25</button>
                    <button type="button" @click="restockQty = 50" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200">+50</button>
                    <button type="button" @click="restockQty = 100" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200">+100</button>
                </div>
                <input 
                    type="number" 
                    name="added_quantity" 
                    x-model.number="restockQty"
                    min="1" 
                    max="10000" 
                    required 
                    class="w-full p-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-bold"
                />
            </div>

            <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" @click="$dispatch('close-modal', 'quick-restock-modal')" class="px-3 py-1.5 text-xs text-slate-500">Cancel</button>
                <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs">Replenish Stock</button>
            </div>
        </form>
    </x-modal>
</div>

<!-- Chart.js Client Controller Logic -->
<script>
function sellerDashboard(config) {
    return {
        activeRange: config.range || '30d',
        salesChart: null,
        channelChart: null,
        topProductsChart: null,
        storeComparisonChart: null,
        restockItem: null,
        restockQty: 25,
        restockAction: '',

        init() {
            this.$nextTick(() => {
                this.renderSalesChart(config.charts.sales_trend);
                this.renderChannelChart(config.charts.channel);
                this.renderTopProductsChart(config.charts.top_products);
                if (config.charts.is_multi_store && config.charts.revenue_by_store) {
                    this.renderStoreComparisonChart(config.charts.revenue_by_store);
                }
            });
        },

        openRestockModal(item) {
            this.restockItem = item;
            this.restockQty = 25;
            this.restockAction = '/seller/inventory/' + item.id + '/quick-restock';
            this.$dispatch('open-modal', 'quick-restock-modal');
        },

        async changeRange(newRange) {
            this.activeRange = newRange;
            try {
                const res = await fetch(`${config.chartDataUrl}?range=${newRange}`);
                const data = await res.json();
                if (data.sales_trend && this.salesChart) {
                    this.salesChart.data.labels = data.sales_trend.labels;
                    this.salesChart.data.datasets[0].data = data.sales_trend.sales;
                    this.salesChart.data.datasets[1].data = data.sales_trend.orders;
                    this.salesChart.update();
                }
            } catch (e) {
                console.error('Failed to update chart range', e);
            }
        },

        renderSalesChart(data) {
            const ctx = document.getElementById('sellerSalesChart');
            if (!ctx) return;

            this.salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Revenue (₹)',
                            data: data.sales,
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Orders Count',
                            data: data.orders,
                            borderColor: '#f59e0b',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            tension: 0.3,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            grid: { color: 'rgba(148, 163, 184, 0.1)' }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: { drawOnChartArea: false }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { maxTicksLimit: 10, font: { size: 10 } }
                        }
                    }
                }
            });
        },

        renderChannelChart(data) {
            const ctx = document.getElementById('sellerChannelChart');
            if (!ctx) return;

            this.channelChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.data,
                        backgroundColor: data.colors,
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } }
                    },
                    cutout: '70%'
                }
            });
        },

        renderTopProductsChart(data) {
            const ctx = document.getElementById('sellerTopProductsChart');
            if (!ctx) return;

            this.topProductsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels.length > 0 ? data.labels : ['No orders yet'],
                    datasets: [{
                        label: 'Sales (₹)',
                        data: data.data.length > 0 ? data.data : [0],
                        backgroundColor: '#6366f1',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { grid: { color: 'rgba(148, 163, 184, 0.1)' } },
                        x: { grid: { display: false }, ticks: { font: { size: 9 } } }
                    }
                }
            });
        },

        renderStoreComparisonChart(data) {
            const ctx = document.getElementById('sellerStoreComparisonChart');
            if (!ctx) return;

            this.storeComparisonChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Outlet Revenue (₹)',
                        data: data.data,
                        backgroundColor: ['#10b981', '#6366f1', '#f59e0b', '#06b6d4'],
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { grid: { color: 'rgba(148, 163, 184, 0.1)' } },
                        x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                    }
                }
            });
        }
    };
}
</script>
@endsection
