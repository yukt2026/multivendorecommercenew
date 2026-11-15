@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Analytics & Financial Reports'])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        title="Business Intelligence & Reports" 
        subtitle="Analyze platform sales velocity, seller GMV, tax liabilities, commissions, and category turnover."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Reports' => '#']"
    >
        <x-slot name="actions">
            <a 
                href="{{ route('admin.reports.export', ['type' => $tab, 'start_date' => $startDate->toDateString(), 'end_date' => $endDate->toDateString()]) }}" 
                class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-100 dark:hover:bg-white dark:text-slate-900 rounded-xl text-xs font-semibold flex items-center space-x-1.5 shadow-xs transition"
            >
                <svg class="w-4 h-4 text-emerald-400 dark:text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Export {{ ucfirst($tab) }} CSV</span>
            </a>
        </x-slot>
    </x-page-header>

    <!-- Controls Filter Strip -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-xs space-y-4">
        <!-- Tab Selector (Omnichannel Reporting Pillars) -->
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 dark:border-slate-800/80 pb-4">
            @foreach([
                'sales' => ['label' => 'Sales Performance', 'icon' => '📈'],
                'orders' => ['label' => 'Orders & Channels', 'icon' => '📦'],
                'sellers' => ['label' => 'Seller Leaderboard', 'icon' => '🏪'],
                'categories' => ['label' => 'Category Breakdown', 'icon' => '🗂️'],
                'tax' => ['label' => 'GST & Tax Accounting', 'icon' => '⚖️'],
                'commission' => ['label' => 'Platform Commission', 'icon' => '💰']
            ] as $tKey => $tConfig)
                <a 
                    href="{{ route('admin.reports.index', ['tab' => $tKey, 'start_date' => $startDate->toDateString(), 'end_date' => $endDate->toDateString()]) }}" 
                    class="px-3.5 py-2 rounded-xl text-xs font-semibold transition flex items-center space-x-1.5 {{ $tab === $tKey ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}"
                >
                    <span>{{ $tConfig['icon'] }}</span>
                    <span>{{ $tConfig['label'] }}</span>
                </a>
            @endforeach
        </div>

        <!-- Presets and Custom Date Range Form -->
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 pt-1">
            <!-- Quick Date Presets -->
            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                <span class="text-slate-400 font-semibold text-[11px] mr-1">Presets:</span>
                @foreach([
                    'today' => 'Today',
                    '7d' => 'Last 7 Days',
                    '30d' => 'Last 30 Days',
                    'this_month' => 'This Month',
                    'last_month' => 'Last Month'
                ] as $pKey => $pLabel)
                    <a 
                        href="{{ route('admin.reports.index', ['tab' => $tab, 'preset' => $pKey]) }}" 
                        class="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-indigo-400 dark:hover:border-indigo-600 text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 font-medium transition"
                    >
                        {{ $pLabel }}
                    </a>
                @endforeach
            </div>

            <!-- Custom Date Range Form -->
            <form method="GET" action="{{ route('admin.reports.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="flex items-center space-x-2 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-1.5 text-xs shadow-2xs">
                    <span class="text-[10px] uppercase font-bold text-slate-400">From</span>
                    <input type="date" name="start_date" value="{{ $startDate->toDateString() }}" class="text-xs bg-transparent border-0 p-0 text-slate-700 dark:text-slate-300 focus:ring-0">
                    <span class="text-[10px] uppercase font-bold text-slate-400">To</span>
                    <input type="date" name="end_date" value="{{ $endDate->toDateString() }}" class="text-xs bg-transparent border-0 p-0 text-slate-700 dark:text-slate-300 focus:ring-0">
                </div>
                <button type="submit" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold shadow-xs transition">
                    Apply Filter
                </button>
            </form>
        </div>
    </div>

    <!-- Tab 1: Sales Performance -->
    @if($tab === 'sales')
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-slate-500">Gross Paid Revenue</div>
                <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ format_currency($data['totalRevenue']) }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Total customer checkout value</div>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">Total Settled Orders</div>
                <div class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">{{ number_format($data['totalOrders']) }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Orders processed across platform</div>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-slate-500">Shipping Fees Collected</div>
                <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ format_currency($data['totalDeliveryFees']) }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Cargo & hyperlocal delivery rakes</div>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Average Order Value (AOV)</div>
                <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{{ format_currency($data['avgOrderValue']) }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Mean cart checkout basket size</div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 font-bold text-xs uppercase tracking-wider text-slate-500 flex items-center justify-between">
                <span>Daily Sales Aggregates</span>
                <span class="text-[11px] text-slate-400 font-normal">Aggregated by order placement date</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50/70 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                        <tr>
                            <th class="px-6 py-3.5">Date</th>
                            <th class="px-6 py-3.5">Orders Count</th>
                            <th class="px-6 py-3.5">Gross Revenue</th>
                            <th class="px-6 py-3.5">Delivery Fees</th>
                            <th class="px-6 py-3.5 text-right">Net Cart Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @forelse($data['daily'] as $row)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-850/50 transition-colors">
                            <td class="px-6 py-3.5 font-medium text-slate-900 dark:text-white">{{ $row->date }}</td>
                            <td class="px-6 py-3.5 font-mono">{{ $row->order_count }}</td>
                            <td class="px-6 py-3.5 font-bold text-slate-900 dark:text-white">{{ format_currency((float)$row->gross) }}</td>
                            <td class="px-6 py-3.5 text-slate-600 dark:text-slate-400">{{ format_currency((float)$row->delivery_fee) }}</td>
                            <td class="px-6 py-3.5 text-right font-bold text-emerald-600 dark:text-emerald-400">{{ format_currency((float)$row->net) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">No order data for selected period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Tab 2: Orders & Channels -->
    @if($tab === 'orders')
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-slate-500">Total Orders in Window</div>
                <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ number_format($data['totalOrderCount']) }}</div>
                <div class="text-[11px] text-slate-400 mt-1">All consumer checkouts in selected range</div>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">Total Order Gross Value</div>
                <div class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">{{ format_currency($data['totalOrderValue']) }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Cumulative basket size before discounts</div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- 1. Orders by Status -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-100 dark:border-slate-800 pb-2">
                    Orders by Lifecycle Status
                </h3>
                <table class="w-full text-left text-xs">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @forelse($data['byStatus'] as $st)
                        <tr>
                            <td class="py-2.5 font-semibold text-slate-800 dark:text-slate-200">{{ $st->label }}</td>
                            <td class="py-2.5 text-center font-mono">{{ $st->count }}</td>
                            <td class="py-2.5 text-right font-bold text-slate-900 dark:text-white">{{ format_currency((float)$st->total) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="py-4 text-center text-slate-400">No status data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- 2. Orders by Payment Method -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-100 dark:border-slate-800 pb-2">
                    Orders by Payment Gateway
                </h3>
                <table class="w-full text-left text-xs">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @forelse($data['byMethod'] as $pm)
                        <tr>
                            <td class="py-2.5 font-semibold text-slate-800 dark:text-slate-200">{{ $pm->label }}</td>
                            <td class="py-2.5 text-center font-mono">{{ $pm->count }}</td>
                            <td class="py-2.5 text-right font-bold text-slate-900 dark:text-white">{{ format_currency((float)$pm->total) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="py-4 text-center text-slate-400">No payment data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- 3. Orders by Fulfilment Type -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-100 dark:border-slate-800 pb-2">
                    Orders by Channel Type
                </h3>
                <table class="w-full text-left text-xs">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @forelse($data['byFulfilment'] as $fo)
                        <tr>
                            <td class="py-2.5 font-semibold text-slate-800 dark:text-slate-200">
                                {{ $fo->label }}
                            </td>
                            <td class="py-2.5 text-center font-mono">{{ $fo->count }}</td>
                            <td class="py-2.5 text-right font-bold text-slate-900 dark:text-white">{{ format_currency((float)$fo->total) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="py-4 text-center text-slate-400">No channel data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Tab 3: Seller Leaderboard -->
    @if($tab === 'sellers')
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-slate-500">Active Sellers in Window</div>
                <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ number_format($data['totalSellersCount']) }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Merchants with active product offerings</div>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">Total Merchant GMV</div>
                <div class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">{{ format_currency($data['totalSellersGmv']) }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Cumulative sales generated by merchants</div>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Platform Commission Accrued</div>
                <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{{ format_currency($data['totalSellersCommission']) }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Platform rake on merchant volume</div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 font-bold text-xs uppercase tracking-wider text-slate-500">
                Top Performing Merchant Accounts
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50/70 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                        <tr>
                            <th class="px-6 py-3.5">Seller Business Name</th>
                            <th class="px-6 py-3.5 text-center">Delivered / Fulfilment Orders</th>
                            <th class="px-6 py-3.5 text-right">Gross Merchandising Value (GMV)</th>
                            <th class="px-6 py-3.5 text-right">Commission Accrued</th>
                            <th class="px-6 py-3.5 text-right">Net Seller Earnings</th>
                            <th class="px-6 py-3.5 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @forelse($data['sellers'] as $s)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-850/50 transition-colors">
                            <td class="px-6 py-3.5">
                                <a href="{{ route('admin.sellers.show', $s['id']) }}" class="font-bold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                                    {{ $s['name'] }}
                                </a>
                                <span class="text-[10px] text-slate-400 block font-normal">Merchant ID: #{{ $s['id'] }}</span>
                            </td>
                            <td class="px-6 py-3.5 text-center font-mono font-medium">{{ $s['orders_count'] }}</td>
                            <td class="px-6 py-3.5 text-right font-bold text-slate-900 dark:text-white">{{ format_currency($s['gross_gmv']) }}</td>
                            <td class="px-6 py-3.5 text-right font-bold text-indigo-600 dark:text-indigo-400">{{ format_currency($s['commission']) }}</td>
                            <td class="px-6 py-3.5 text-right font-bold text-emerald-600 dark:text-emerald-400">{{ format_currency($s['net_payout']) }}</td>
                            <td class="px-6 py-3.5 text-center">
                                <a href="{{ route('admin.sellers.show', $s['id']) }}" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900 transition inline-flex items-center space-x-1">
                                    <span>Profile</span>
                                    <span>→</span>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-slate-500">No seller transactions in this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Tab 4: Categories Breakdown -->
    @if($tab === 'categories')
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-slate-500">Total Product Categories</div>
                <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ number_format($data['totalCategoriesCount']) }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Active verticals across store catalog</div>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">Total Units Sold</div>
                <div class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">{{ number_format($data['totalUnits']) }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Dispatched quantities in selected period</div>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Category Revenue (GMV)</div>
                <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{{ format_currency($data['totalCategoryRevenue']) }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Aggregated department turnover</div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 font-bold text-xs uppercase tracking-wider text-slate-500">
                Departmental Sales &amp; Unit Turnover
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50/70 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                        <tr>
                            <th class="px-6 py-3.5">Category Name</th>
                            <th class="px-6 py-3.5 text-center">Units Dispatched</th>
                            <th class="px-6 py-3.5 text-right">Gross GMV</th>
                            <th class="px-6 py-3.5 text-right">Revenue Share</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @forelse($data['categories'] as $c)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-850/50 transition-colors">
                            <td class="px-6 py-3.5 font-bold text-slate-900 dark:text-white">{{ $c['name'] }}</td>
                            <td class="px-6 py-3.5 text-center font-mono">{{ number_format($c['units']) }} units</td>
                            <td class="px-6 py-3.5 text-right font-bold text-emerald-600 dark:text-emerald-400">{{ format_currency($c['revenue']) }}</td>
                            <td class="px-6 py-3.5 text-right font-mono text-slate-500">
                                {{ $data['totalCategoryRevenue'] > 0 ? number_format(($c['revenue'] / $data['totalCategoryRevenue']) * 100, 1) . '%' : '0.0%' }}
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-6 py-12 text-center text-slate-500">No category transactions in this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Tab 5: GST & Tax Accounting -->
    @if($tab === 'tax')
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-slate-500">Gross Paid Revenue</div>
                <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ format_currency($data['grossRevenue']) }}</div>
                <p class="text-[10px] text-slate-400 mt-1">Total revenue including taxes & delivery</p>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Merchant GST Collected</div>
                <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{{ format_currency($data['productTax']) }}</div>
                <p class="text-[10px] text-slate-400 mt-1">Remitted to merchants for GSTR-1 filing</p>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">Platform Commission GST (18%)</div>
                <div class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">{{ format_currency($data['commissionTax']) }}</div>
                <p class="text-[10px] text-slate-400 mt-1">Platform output GST liability on take-rate</p>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-purple-600 dark:text-purple-400">Total Tax Collected</div>
                <div class="text-2xl font-extrabold text-purple-600 dark:text-purple-400 mt-1">{{ format_currency($data['totalTaxCollected']) }}</div>
                <p class="text-[10px] text-slate-400 mt-1">Combined GST turnover in reporting period</p>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 font-bold text-xs uppercase tracking-wider text-slate-500">
                Recent Taxable Orders Audit Trail
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50/70 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                        <tr>
                            <th class="px-6 py-3.5">Order #</th>
                            <th class="px-6 py-3.5">Date</th>
                            <th class="px-6 py-3.5">Customer</th>
                            <th class="px-6 py-3.5 text-right">Taxable Subtotal</th>
                            <th class="px-6 py-3.5 text-right">GST Collected</th>
                            <th class="px-6 py-3.5 text-right">Total Paid</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @forelse($data['recentOrdersWithTax'] as $order)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-850/50 transition-colors">
                            <td class="px-6 py-3.5 font-mono font-bold">
                                <a href="{{ route('admin.orders.show', $order) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {{ $order->order_number }}
                                </a>
                            </td>
                            <td class="px-6 py-3.5 text-slate-500">{{ $order->created_at->format('d M Y, h:i A') }}</td>
                            <td class="px-6 py-3.5 text-slate-800 dark:text-slate-200">{{ $order->customer?->user?->name ?? 'Guest User' }}</td>
                            <td class="px-6 py-3.5 text-right font-mono">{{ format_currency((float)$order->subtotal) }}</td>
                            <td class="px-6 py-3.5 text-right font-bold text-emerald-600 dark:text-emerald-400">{{ format_currency((float)$order->tax) }}</td>
                            <td class="px-6 py-3.5 text-right font-bold text-slate-900 dark:text-white">{{ format_currency((float)$order->total) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-slate-500">No taxable transactions in this window.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Tab 6: Commission & Settled Payouts -->
    @if($tab === 'commission')
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-slate-500">Net Platform Commission</div>
                <div class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">{{ format_currency($data['totalCommission']) }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Platform rake on order fulfillments</div>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-slate-500">GST on Commission (18%)</div>
                <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ format_currency($data['totalTax']) }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Output tax on platform take-rate</div>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Settled to Sellers</div>
                <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{{ format_currency($data['totalSettled']) }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Disbursed to merchant bank accounts</div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 font-bold text-xs uppercase tracking-wider text-slate-500 flex items-center justify-between">
                <span>Commission &amp; Disbursements Ledger</span>
                <span class="text-[11px] text-slate-400 font-normal">Real-time ledger entries from fulfilled orders</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50/70 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                        <tr>
                            <th class="px-6 py-3.5">Date &amp; Time</th>
                            <th class="px-6 py-3.5">Order / Package #</th>
                            <th class="px-6 py-3.5">Merchant</th>
                            <th class="px-6 py-3.5 text-right">Base Order GMV</th>
                            <th class="px-6 py-3.5 text-right">Commission</th>
                            <th class="px-6 py-3.5 text-right">GST (18%)</th>
                            <th class="px-6 py-3.5 text-right">Seller Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @forelse($data['entries'] as $ce)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-850/50 transition-colors">
                            <td class="px-6 py-3.5 text-slate-500 whitespace-nowrap">{{ $ce->created_at->format('d M Y, h:i A') }}</td>
                            <td class="px-6 py-3.5 font-mono whitespace-nowrap">
                                @if($ce->fulfilmentOrder?->order)
                                    <a href="{{ route('admin.orders.show', $ce->fulfilmentOrder->order) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold">
                                        {{ $ce->fulfilmentOrder->order->order_number }}
                                    </a>
                                @elseif($ce->order)
                                    <a href="{{ route('admin.orders.show', $ce->order) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold">
                                        {{ $ce->order->order_number }}
                                    </a>
                                @else
                                    <span class="text-slate-400">N/A</span>
                                @endif
                            </td>
                            <td class="px-6 py-3.5 whitespace-nowrap">
                                @if($ce->fulfilmentOrder?->seller)
                                    <a href="{{ route('admin.sellers.show', $ce->fulfilmentOrder->seller) }}" class="font-semibold text-slate-800 dark:text-slate-200 hover:text-indigo-600 dark:hover:text-indigo-400">
                                        {{ $ce->fulfilmentOrder->seller->business_name }}
                                    </a>
                                @else
                                    <span class="text-slate-400">Merchant</span>
                                @endif
                            </td>
                            <td class="px-6 py-3.5 text-right font-semibold text-slate-900 dark:text-white whitespace-nowrap">{{ format_currency((float)$ce->base_amount) }}</td>
                            <td class="px-6 py-3.5 text-right font-bold text-indigo-600 dark:text-indigo-400 whitespace-nowrap">{{ format_currency((float)$ce->commission) }}</td>
                            <td class="px-6 py-3.5 text-right text-slate-500 whitespace-nowrap">{{ format_currency((float)$ce->tax_on_commission) }}</td>
                            <td class="px-6 py-3.5 text-right font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">{{ format_currency((float)$ce->net_payable) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="px-6 py-12 text-center text-slate-500">No commission entries recorded in this window.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($data['entries'], 'links'))
                <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                    {{ $data['entries']->links() }}
                </div>
            @endif
        </div>
    @endif

</div>
@endsection
