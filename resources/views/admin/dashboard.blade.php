@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Admin Central Command'])

@section('content')
<div class="space-y-8" x-data="adminDashboard()" x-init="initDashboard()">
    <!-- Page Header & Period Range Controls -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-1">
        <div>
            <div class="flex items-center space-x-3 mb-1.5">
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    Central Operations & Command Hub
                </h1>
                <x-badge variant="success" dot pulse size="sm">
                    Live Telemetry
                </x-badge>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Omnichannel sales velocity, quick commerce fulfilment, merchant activity, and logistics health.
            </p>
        </div>

        <!-- Range Filter Buttons, Refresh, and CSV Export -->
        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Period Selector Pills -->
            <div class="inline-flex items-center p-1 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                @foreach(['today' => 'Today', '7d' => '7 Days', '30d' => '30 Days', '90d' => '90 Days'] as $key => $title)
                    <button 
                        type="button"
                        @click="setRange('{{ $key }}')"
                        :class="activeRange === '{{ $key }}' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 font-bold shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-medium'"
                        class="px-3 py-1.5 text-xs rounded-lg transition-all duration-150 focus:outline-hidden"
                    >
                        {{ $title }}
                    </button>
                @endforeach
            </div>

            <!-- Refresh Button -->
            <button 
                type="button" 
                @click="refresh()" 
                title="Refresh metrics"
                class="p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-750 shadow-xs transition-colors"
            >
                <svg :class="isLoading ? 'animate-spin' : ''" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>

            <!-- Export Summary CSV -->
            <a 
                :href="exportUrl"
                class="inline-flex items-center px-3.5 py-2 text-xs font-semibold rounded-xl text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200/90 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-750 shadow-xs transition-all hover:border-slate-300 dark:hover:border-slate-600"
            >
                <svg class="w-3.5 h-3.5 mr-1.5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Export Summary (CSV)
            </a>
        </div>
    </div>

    <!-- 8 Top-Row Stat Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
        <!-- 1. Total GMV -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total GMV</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800/60">
                        Gross Volume
                    </span>
                </div>
                <div class="flex items-baseline justify-between gap-2">
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white" x-text="stats.total_gmv?.formatted ?? '{{ $stats['total_gmv']['formatted'] }}'">
                        {{ $stats['total_gmv']['formatted'] }}
                    </div>
                    <div class="flex items-center text-xs font-bold" :class="getTrendColor(stats.total_gmv?.trend?.type)">
                        <svg x-show="stats.total_gmv?.trend?.type === 'up'" class="w-3.5 h-3.5 mr-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        <svg x-show="stats.total_gmv?.trend?.type === 'down'" class="w-3.5 h-3.5 mr-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                        <span x-text="stats.total_gmv?.trend?.text ?? '{{ $stats['total_gmv']['trend']['text'] }}'">{{ $stats['total_gmv']['trend']['text'] }}</span>
                    </div>
                </div>
            </div>
            <div class="mt-3 pt-2 text-[11px] text-slate-400 dark:text-slate-500">Gross sales before cancellations</div>
        </div>

        <!-- 2. Total Orders -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Orders</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60">
                        Checkouts
                    </span>
                </div>
                <div class="flex items-baseline justify-between gap-2">
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white" x-text="stats.total_orders?.formatted ?? '{{ $stats['total_orders']['formatted'] }}'">
                        {{ $stats['total_orders']['formatted'] }}
                    </div>
                    <div class="flex items-center text-xs font-bold" :class="getTrendColor(stats.total_orders?.trend?.type)">
                        <svg x-show="stats.total_orders?.trend?.type === 'up'" class="w-3.5 h-3.5 mr-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        <svg x-show="stats.total_orders?.trend?.type === 'down'" class="w-3.5 h-3.5 mr-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                        <span x-text="stats.total_orders?.trend?.text ?? '{{ $stats['total_orders']['trend']['text'] }}'">{{ $stats['total_orders']['trend']['text'] }}</span>
                    </div>
                </div>
            </div>
            <div class="mt-3 pt-2 text-[11px] text-slate-400 dark:text-slate-500">Parent checkout order volume</div>
        </div>

        <!-- 3. Active Sellers -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Active Sellers</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800/60">
                        Approved
                    </span>
                </div>
                <div class="flex items-baseline justify-between gap-2">
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white" x-text="stats.active_sellers?.formatted ?? '{{ $stats['active_sellers']['formatted'] }}'">
                        {{ $stats['active_sellers']['formatted'] }}
                    </div>
                    <div class="flex items-center text-xs font-bold text-emerald-600 dark:text-emerald-400">
                        <span x-text="stats.active_sellers?.trend?.text ?? '{{ $stats['active_sellers']['trend']['text'] }}'">{{ $stats['active_sellers']['trend']['text'] }}</span>
                    </div>
                </div>
            </div>
            <div class="mt-3 pt-2 text-[11px] text-slate-400 dark:text-slate-500">Stores selling actively on OneStall</div>
        </div>

        <!-- 4. Active Customers -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Active Customers</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/60">
                        User Base
                    </span>
                </div>
                <div class="flex items-baseline justify-between gap-2">
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white" x-text="stats.active_customers?.formatted ?? '{{ $stats['active_customers']['formatted'] }}'">
                        {{ $stats['active_customers']['formatted'] }}
                    </div>
                    <div class="flex items-center text-xs font-bold text-emerald-600 dark:text-emerald-400">
                        <span x-text="stats.active_customers?.trend?.text ?? '{{ $stats['active_customers']['trend']['text'] }}'">{{ $stats['active_customers']['trend']['text'] }}</span>
                    </div>
                </div>
            </div>
            <div class="mt-3 pt-2 text-[11px] text-slate-400 dark:text-slate-500">Total registered buyer accounts</div>
        </div>

        <!-- 5. Pending Seller Approvals -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Pending KYC</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border" :class="(stats.pending_seller_approvals?.value ?? {{ $stats['pending_seller_approvals']['value'] }}) > 0 ? 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-800/60' : 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'">
                        Audit Queue
                    </span>
                </div>
                <div class="flex items-baseline justify-between gap-2">
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white" x-text="stats.pending_seller_approvals?.formatted ?? '{{ $stats['pending_seller_approvals']['formatted'] }}'">
                        {{ $stats['pending_seller_approvals']['formatted'] }}
                    </div>
                    <div class="flex items-center text-xs font-bold" :class="(stats.pending_seller_approvals?.value ?? {{ $stats['pending_seller_approvals']['value'] }}) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500 dark:text-slate-400'">
                        <span x-text="stats.pending_seller_approvals?.trend?.text ?? '{{ $stats['pending_seller_approvals']['trend']['text'] }}'">{{ $stats['pending_seller_approvals']['trend']['text'] }}</span>
                    </div>
                </div>
            </div>
            <div class="mt-3 pt-2 text-[11px] text-slate-400 dark:text-slate-500">Merchants awaiting document verification</div>
        </div>

        <!-- 6. Pending Product Reviews -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Pending Products</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/60">
                        Catalogue
                    </span>
                </div>
                <div class="flex items-baseline justify-between gap-2">
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white" x-text="stats.pending_product_reviews?.formatted ?? '{{ $stats['pending_product_reviews']['formatted'] }}'">
                        {{ $stats['pending_product_reviews']['formatted'] }}
                    </div>
                    <div class="flex items-center text-xs font-bold" :class="(stats.pending_product_reviews?.value ?? {{ $stats['pending_product_reviews']['value'] }}) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400'">
                        <span x-text="stats.pending_product_reviews?.trend?.text ?? '{{ $stats['pending_product_reviews']['trend']['text'] }}'">{{ $stats['pending_product_reviews']['trend']['text'] }}</span>
                    </div>
                </div>
            </div>
            <div class="mt-3 pt-2 text-[11px] text-slate-400 dark:text-slate-500">Catalogue listings awaiting moderation</div>
        </div>

        <!-- 7. Quick Commerce Orders -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Quick Orders</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60">
                        10-30 Mins
                    </span>
                </div>
                <div class="flex items-baseline justify-between gap-2">
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white" x-text="stats.quick_orders?.formatted ?? '{{ $stats['quick_orders']['formatted'] }}'">
                        {{ $stats['quick_orders']['formatted'] }}
                    </div>
                    <div class="flex items-center text-xs font-bold" :class="getTrendColor(stats.quick_orders?.trend?.type)">
                        <svg x-show="stats.quick_orders?.trend?.type === 'up'" class="w-3.5 h-3.5 mr-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        <svg x-show="stats.quick_orders?.trend?.type === 'down'" class="w-3.5 h-3.5 mr-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                        <span x-text="stats.quick_orders?.trend?.text ?? '{{ $stats['quick_orders']['trend']['text'] }}'">{{ $stats['quick_orders']['trend']['text'] }}</span>
                    </div>
                </div>
            </div>
            <div class="mt-3 pt-2 text-[11px] text-slate-400 dark:text-slate-500">Hyperlocal rapid fulfilment dispatches</div>
        </div>

        <!-- 8. Platform Commission Earned -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Commission Earned</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800/60">
                        Net Revenue
                    </span>
                </div>
                <div class="flex items-baseline justify-between gap-2">
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white" x-text="stats.platform_commission?.formatted ?? '{{ $stats['platform_commission']['formatted'] }}'">
                        {{ $stats['platform_commission']['formatted'] }}
                    </div>
                    <div class="flex items-center text-xs font-bold" :class="getTrendColor(stats.platform_commission?.trend?.type)">
                        <svg x-show="stats.platform_commission?.trend?.type === 'up'" class="w-3.5 h-3.5 mr-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        <svg x-show="stats.platform_commission?.trend?.type === 'down'" class="w-3.5 h-3.5 mr-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                        <span x-text="stats.platform_commission?.trend?.text ?? '{{ $stats['platform_commission']['trend']['text'] }}'">{{ $stats['platform_commission']['trend']['text'] }}</span>
                    </div>
                </div>
            </div>
            <div class="mt-3 pt-2 text-[11px] text-slate-400 dark:text-slate-500">Platform rake from order fulfilments</div>
        </div>
    </div>

    <!-- Charts Row 1: Dual-Axis Sales Trend + Orders by Type Donut -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Sales & Orders Dual-Axis Trend Line (2/3) -->
        <div class="lg:col-span-2">
            <x-card 
                title="Sales & Orders Trend" 
                subtitle="Gross Merchandise Value (₹) on primary axis, Order Volume on secondary axis"
            >
                <div class="relative w-full h-72 sm:h-80">
                    <div x-show="isLoading" class="absolute inset-0 z-10 bg-white/70 dark:bg-slate-900/70 backdrop-blur-xs flex items-center justify-center rounded-xl">
                        <div class="flex items-center space-x-2 text-indigo-600 dark:text-indigo-400 font-semibold text-xs">
                            <svg class="animate-spin w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>Updating trend analysis...</span>
                        </div>
                    </div>
                    <canvas id="adminSalesOrdersChart" class="w-full h-full"></canvas>
                </div>
            </x-card>
        </div>

        <!-- Orders by Fulfilment Type Donut (1/3) -->
        <div>
            <x-card 
                title="Orders by Fulfilment" 
                subtitle="Standard Marketplace vs Quick vs Pickup"
            >
                <div class="relative w-full h-72 sm:h-80 flex items-center justify-center">
                    <div x-show="isLoading" class="absolute inset-0 z-10 bg-white/70 dark:bg-slate-900/70 backdrop-blur-xs flex items-center justify-center rounded-xl">
                        <svg class="animate-spin w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </div>
                    <canvas id="adminOrderTypesChart" class="w-full h-full"></canvas>
                </div>
            </x-card>
        </div>
    </div>

    <!-- Charts Row 2: Category Revenue Horizontal Bar + Order Status Funnel -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Category Revenue Bar -->
        <x-card 
            title="Revenue by Category" 
            subtitle="Top performing product verticals by Gross Value (₹)"
        >
            <div class="relative w-full h-72">
                <div x-show="isLoading" class="absolute inset-0 z-10 bg-white/70 dark:bg-slate-900/70 backdrop-blur-xs flex items-center justify-center rounded-xl">
                    <svg class="animate-spin w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </div>
                <canvas id="adminCategoryRevenueChart" class="w-full h-full"></canvas>
            </div>
        </x-card>

        <!-- Order Status Funnel -->
        <x-card 
            title="Order Pipeline & Funnel" 
            subtitle="Drop-off and fulfilment progression through pipeline"
        >
            <div class="relative w-full h-72">
                <div x-show="isLoading" class="absolute inset-0 z-10 bg-white/70 dark:bg-slate-900/70 backdrop-blur-xs flex items-center justify-center rounded-xl">
                    <svg class="animate-spin w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </div>
                <canvas id="adminStatusFunnelChart" class="w-full h-full"></canvas>
            </div>
        </x-card>
    </div>

    <!-- Top 10 Sellers Table with Sparklines -->
    <x-card 
        title="Top 10 Merchants by Sales Volume" 
        subtitle="Ranked performance with 7-day activity sparkline"
    >
        <div class="overflow-x-auto -mx-6">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-500 font-semibold uppercase tracking-wider">
                        <th class="py-3 px-6">Rank & Merchant</th>
                        <th class="py-3 px-4">Store Model</th>
                        <th class="py-3 px-4 text-right">Orders</th>
                        <th class="py-3 px-4 text-right">GMV (₹)</th>
                        <th class="py-3 px-6 text-center">7-Day Sparkline</th>
                        <th class="py-3 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    <template x-for="(seller, index) in topSellers" :key="seller.id">
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-850/50 transition-colors">
                            <td class="py-3.5 px-6">
                                <div class="flex items-center space-x-3">
                                    <span 
                                        class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-[11px]"
                                        :class="index === 0 ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 font-extrabold' : (index === 1 ? 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300' : 'text-slate-500 dark:text-slate-400')"
                                        x-text="'#' + (index + 1)"
                                    ></span>
                                    <div>
                                        <div class="font-bold text-slate-900 dark:text-white" x-text="seller.business_name"></div>
                                        <div class="text-[11px] text-slate-400 dark:text-slate-500" x-text="seller.owner_name"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded-md font-medium text-[11px] bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/50" x-text="seller.seller_type"></span>
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-medium text-slate-700 dark:text-slate-300" x-text="seller.order_count"></td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-900 dark:text-white" x-text="seller.formatted_sales"></td>
                            <td class="py-3.5 px-6 text-center">
                                <div class="inline-flex items-center justify-center">
                                    <svg class="w-24 h-6 text-indigo-500" viewBox="0 0 80 24" fill="none">
                                        <polyline 
                                            :points="renderSparklinePoints(seller.sparkline)" 
                                            fill="none" 
                                            stroke="currentColor" 
                                            stroke-width="2" 
                                            stroke-linecap="round" 
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <x-badge variant="success" size="sm">Verified</x-badge>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="!topSellers || topSellers.length === 0">
                        <td colspan="6" class="py-8 text-center text-slate-500 dark:text-slate-400">
                            No seller activity recorded for this period.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </x-card>

    <!-- Operational Widgets Section (2 Columns Grid) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Widget 1: Recent Checkouts Table -->
        <x-card 
            title="Recent Orders" 
            subtitle="Latest incoming consumer orders across all channels"
        >
            @if(isset($widgets['recent_orders']) && count($widgets['recent_orders']) > 0)
                <div class="overflow-x-auto -mx-6">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 font-semibold uppercase tracking-wider">
                                <th class="py-2.5 px-6">Order #</th>
                                <th class="py-2.5 px-4">Customer</th>
                                <th class="py-2.5 px-4 text-right">Amount</th>
                                <th class="py-2.5 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                            @foreach($widgets['recent_orders'] as $order)
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-850/50 transition-colors">
                                    <td class="py-3 px-6 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ $order->order_number }}
                                        <div class="text-[10px] font-normal text-slate-400">{{ $order->created_at->diffForHumans() }}</div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-slate-900 dark:text-white">{{ $order->customer?->user?->name ?? 'Guest User' }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $order->fulfilmentOrders->count() }} split package(s)</div>
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 dark:text-white">
                                        ₹{{ number_format((float) $order->total, 2) }}
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $order->status->badgeClass() }}">
                                            {{ $order->status->label() }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-empty-state 
                    title="No orders yet" 
                    description="When customers place orders, they will appear in this real-time stream." 
                />
            @endif
        </x-card>

        <!-- Widget 2: Pending KYC Queue -->
        <x-card 
            title="Pending Merchant KYC Queue" 
            subtitle="Sellers awaiting verification before store go-live"
        >
            @if(isset($widgets['pending_kyc']) && count($widgets['pending_kyc']) > 0)
                <div class="space-y-3">
                    @foreach($widgets['pending_kyc'] as $seller)
                        <div class="p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-950/40 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-bold text-xs text-slate-900 dark:text-white truncate">
                                    {{ $seller->business_name }}
                                </div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                    Owner: {{ $seller->user?->name ?? 'N/A' }} &bull; {{ $seller->user?->phone ?? $seller->user?->email }}
                                </div>
                                <div class="text-[10px] text-amber-600 dark:text-amber-400 font-medium mt-0.5">
                                    Status: {{ $seller->status->label() }} &bull; Applied {{ $seller->created_at->diffForHumans() }}
                                </div>
                            </div>
                            <div class="shrink-0 flex items-center space-x-2">
                                <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 cursor-pointer hover:bg-indigo-100">
                                    Audit KYC &rarr;
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <x-empty-state 
                    title="KYC Queue Clear" 
                    description="All merchant applications have been reviewed and approved." 
                />
            @endif
        </x-card>

        <!-- Widget 3: Open Support Tickets -->
        <x-card 
            title="Open Support Inquiries" 
            subtitle="Customer and merchant escalations needing resolution"
        >
            @if(isset($widgets['open_tickets']) && count($widgets['open_tickets']) > 0)
                <div class="space-y-2.5">
                    @foreach($widgets['open_tickets'] as $ticket)
                        <div class="p-3 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center space-x-2">
                                    <span class="font-mono text-[11px] font-bold text-indigo-600 dark:text-indigo-400">
                                        #{{ $ticket->ticket_number }}
                                    </span>
                                    <span class="px-1.5 py-0.5 rounded-sm text-[10px] font-bold uppercase {{ $ticket->priority->value === 'urgent' ? 'bg-rose-100 text-rose-800' : ($ticket->priority->value === 'high' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">
                                        {{ $ticket->priority->label() }}
                                    </span>
                                </div>
                                <div class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate mt-0.5">
                                    {{ $ticket->subject }}
                                </div>
                                <div class="text-[10px] text-slate-400">
                                    From: {{ $ticket->user?->name ?? 'User' }} &bull; {{ $ticket->created_at->diffForHumans() }}
                                </div>
                            </div>
                            <span class="shrink-0 px-2 py-0.5 text-[10px] font-bold rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                {{ $ticket->status->label() }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <x-empty-state 
                    title="No open tickets" 
                    description="Zero active customer or merchant complaints at this time." 
                />
            @endif
        </x-card>

        <!-- Widget 4: Settlements Due & Platform Health -->
        <div class="space-y-6">
            <!-- Settlements Due Box -->
            <x-card 
                title="Settlement Due This Week" 
                subtitle="Upcoming payout disbursements to merchant bank accounts"
            >
                <div class="p-4 rounded-xl bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/60 flex items-center justify-between mb-4">
                    <div>
                        <div class="text-xs font-bold text-indigo-900 dark:text-indigo-200">Total Pending Payout</div>
                        <div class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400">
                            ₹{{ number_format((float) ($widgets['total_pending_payout'] ?? 0), 2) }}
                        </div>
                    </div>
                    <span class="px-3 py-1 rounded-lg text-xs font-bold bg-indigo-600 text-white shadow-xs cursor-pointer hover:bg-indigo-700">
                        Execute Payouts
                    </span>
                </div>

                @if(isset($widgets['due_settlements']) && count($widgets['due_settlements']) > 0)
                    <div class="space-y-2">
                        @foreach($widgets['due_settlements'] as $settlement)
                            <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-100 dark:border-slate-800/80">
                                <div>
                                    <div class="font-bold text-slate-800 dark:text-slate-200">{{ $settlement->seller?->business_name ?? 'Merchant' }}</div>
                                    <div class="text-[10px] text-slate-400">#{{ $settlement->settlement_number }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-mono font-bold text-slate-900 dark:text-white">₹{{ number_format((float) $settlement->net, 2) }}</div>
                                    <span class="text-[10px] text-amber-600 font-semibold">Pending</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-xs text-slate-400 text-center py-2">All due merchant settlements have been settled.</div>
                @endif
            </x-card>

            <!-- System Health & Background Jobs -->
            <x-card 
                title="System Health & Infrastructure" 
                subtitle="Queue backlog, failed jobs, cache engine, and service telemetry"
            >
                <div class="grid grid-cols-2 gap-3">
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200/80 dark:border-slate-800">
                        <div class="text-[11px] font-semibold text-slate-500">Queue Backlog</div>
                        <div class="text-xl font-extrabold text-slate-900 dark:text-white mt-1">
                            {{ $widgets['queue_backlog'] ?? 0 }}
                        </div>
                        <div class="text-[10px] text-emerald-600 font-semibold mt-0.5">Redis / MySQL Workers Active</div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200/80 dark:border-slate-800">
                        <div class="text-[11px] font-semibold text-slate-500">Failed Jobs</div>
                        <div class="text-xl font-extrabold {{ ($widgets['failed_jobs'] ?? 0) > 0 ? 'text-rose-600' : 'text-slate-900 dark:text-white' }} mt-1">
                            {{ $widgets['failed_jobs'] ?? 0 }}
                        </div>
                        <div class="text-[10px] {{ ($widgets['failed_jobs'] ?? 0) > 0 ? 'text-rose-500 font-bold' : 'text-emerald-600 font-semibold' }} mt-0.5">
                            {{ ($widgets['failed_jobs'] ?? 0) > 0 ? 'Action Required' : 'Zero Exceptions' }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs text-slate-500">
                    <div class="flex items-center space-x-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="font-medium text-slate-700 dark:text-slate-300">All Systems Nominal</span>
                    </div>
                    <span class="font-mono text-[11px] text-slate-400">Laravel v{{ app()->version() }} &bull; PHP 8.3</span>
                </div>
            </x-card>
        </div>
    </div>
</div>

<script>
    function adminDashboard() {
        return {
            activeRange: '{{ $activeRange }}',
            isLoading: false,
            stats: @js($stats),
            topSellers: @js($topSellers),
            salesOrdersChart: null,
            orderTypesChart: null,
            categoryRevenueChart: null,
            statusFunnelChart: null,

            get exportUrl() {
                return '{{ route("admin.dashboard.export") }}?range=' + this.activeRange;
            },

            initDashboard() {
                this.$nextTick(() => {
                    this.initCharts();
                    this.fetchData(this.activeRange, false);
                });
            },

            setRange(range) {
                if (this.activeRange === range && !this.isLoading) return;
                this.activeRange = range;
                this.fetchData(range, true);
            },

            refresh() {
                this.fetchData(this.activeRange, true);
            },

            getTrendColor(type) {
                if (type === 'up') return 'text-emerald-600 dark:text-emerald-400';
                if (type === 'down') return 'text-rose-600 dark:text-rose-400';
                return 'text-slate-500 dark:text-slate-400';
            },

            renderSparklinePoints(data) {
                if (!data || !Array.isArray(data) || data.length === 0) return '';
                const min = Math.min(...data);
                const max = Math.max(...data) || 1;
                const range = (max - min) === 0 ? 1 : (max - min);
                const width = 80;
                const height = 24;
                const step = width / (data.length - 1 || 1);

                return data.map((val, idx) => {
                    const x = (idx * step).toFixed(1);
                    const y = (height - ((val - min) / range) * (height - 6) - 3).toFixed(1);
                    return `${x},${y}`;
                }).join(' ');
            },

            getChartTheme() {
                const isDark = document.documentElement.classList.contains('dark');
                return {
                    textColor: isDark ? '#94a3b8' : '#64748b',
                    gridColor: isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(0, 0, 0, 0.06)',
                };
            },

            initCharts() {
                if (!window.Chart) return;
                const theme = this.getChartTheme();

                // 1. Dual Axis Sales & Orders Line Chart
                const ctxSales = document.getElementById('adminSalesOrdersChart');
                if (ctxSales) {
                    this.salesOrdersChart = new window.Chart(ctxSales, {
                        type: 'line',
                        data: { labels: [], datasets: [] },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: {
                                    labels: { color: theme.textColor, font: { family: 'Inter', size: 11, weight: '600' } }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            if (context.dataset.yAxisID === 'y') {
                                                return ' GMV: ₹' + Number(context.parsed.y).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                                            }
                                            return ' Orders: ' + context.parsed.y;
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { color: theme.gridColor },
                                    ticks: { color: theme.textColor, font: { family: 'Inter', size: 10 } }
                                },
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    grid: { color: theme.gridColor },
                                    ticks: {
                                        color: theme.textColor,
                                        font: { family: 'Inter', size: 10 },
                                        callback: (val) => '₹' + Number(val).toLocaleString('en-IN')
                                    }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    grid: { drawOnChartArea: false },
                                    ticks: {
                                        color: theme.textColor,
                                        font: { family: 'Inter', size: 10 },
                                        precision: 0
                                    }
                                }
                            }
                        }
                    });
                }

                // 2. Orders by Fulfilment Type Donut Chart
                const ctxTypes = document.getElementById('adminOrderTypesChart');
                if (ctxTypes) {
                    this.orderTypesChart = new window.Chart(ctxTypes, {
                        type: 'doughnut',
                        data: { labels: [], datasets: [] },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '70%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { color: theme.textColor, boxWidth: 12, padding: 16, font: { family: 'Inter', size: 11 } }
                                }
                            }
                        }
                    });
                }

                // 3. Category Revenue Horizontal Bar Chart
                const ctxCat = document.getElementById('adminCategoryRevenueChart');
                if (ctxCat) {
                    this.categoryRevenueChart = new window.Chart(ctxCat, {
                        type: 'bar',
                        data: { labels: [], datasets: [] },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => ' Revenue: ₹' + Number(ctx.parsed.x).toLocaleString('en-IN', { minimumFractionDigits: 2 })
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { color: theme.gridColor },
                                    ticks: {
                                        color: theme.textColor,
                                        font: { family: 'Inter', size: 10 },
                                        callback: (val) => '₹' + Number(val).toLocaleString('en-IN')
                                    }
                                },
                                y: {
                                    grid: { display: false },
                                    ticks: { color: theme.textColor, font: { family: 'Inter', size: 10, weight: '500' } }
                                }
                            }
                        }
                    });
                }

                // 4. Order Status Funnel Chart
                const ctxFunnel = document.getElementById('adminStatusFunnelChart');
                if (ctxFunnel) {
                    this.statusFunnelChart = new window.Chart(ctxFunnel, {
                        type: 'bar',
                        data: { labels: [], datasets: [] },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: { color: theme.textColor, font: { family: 'Inter', size: 10, weight: '600' } }
                                },
                                y: {
                                    grid: { color: theme.gridColor },
                                    ticks: { color: theme.textColor, font: { family: 'Inter', size: 10 }, precision: 0 }
                                }
                            }
                        }
                    });
                }
            },

            async fetchData(range, updateStats = true) {
                this.isLoading = true;
                try {
                    const response = await fetch(`{{ route('admin.dashboard.chart_data') }}?range=${range}&chart=all`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) throw new Error('Failed to load telemetry');
                    const result = await response.json();
                    const data = result.data;

                    if (updateStats && data.stats) {
                        this.stats = data.stats;
                    }

                    if (data.top_sellers) {
                        this.topSellers = data.top_sellers;
                    }

                    // Update Sales & Orders Trend
                    if (this.salesOrdersChart && data.sales_orders) {
                        this.salesOrdersChart.data.labels = data.sales_orders.labels;
                        this.salesOrdersChart.data.datasets = data.sales_orders.datasets;
                        this.salesOrdersChart.update();
                    }

                    // Update Orders by Fulfilment Type
                    if (this.orderTypesChart && data.order_types) {
                        this.orderTypesChart.data.labels = data.order_types.labels;
                        this.orderTypesChart.data.datasets = data.order_types.datasets;
                        this.orderTypesChart.update();
                    }

                    // Update Revenue by Category
                    if (this.categoryRevenueChart && data.category_revenue) {
                        this.categoryRevenueChart.data.labels = data.category_revenue.labels;
                        this.categoryRevenueChart.data.datasets = data.category_revenue.datasets;
                        this.categoryRevenueChart.update();
                    }

                    // Update Status Funnel
                    if (this.statusFunnelChart && data.status_funnel) {
                        this.statusFunnelChart.data.labels = data.status_funnel.labels;
                        this.statusFunnelChart.data.datasets = data.status_funnel.datasets;
                        this.statusFunnelChart.update();
                    }
                } catch (error) {
                    console.error('Error fetching dashboard telemetry:', error);
                } finally {
                    this.isLoading = false;
                }
            }
        };
    }
</script>
@endsection
