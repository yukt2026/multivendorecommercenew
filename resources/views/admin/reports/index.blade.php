@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Analytics & Financial Reports'])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        title="Business Intelligence &amp; Reports" 
        subtitle="Analyze platform sales velocity, seller GMV, tax liabilities, commissions, and category turnover."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Reports' => '#']"
    >
        <x-slot name="actions">
            <a 
                href="{{ route('admin.reports.export', ['type' => $tab, 'start_date' => $startDate->toDateString(), 'end_date' => $endDate->toDateString()]) }}" 
                class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-100 dark:hover:bg-white dark:text-slate-900 rounded-lg text-xs font-semibold flex items-center space-x-1.5 shadow-sm transition"
            >
                <svg class="w-4 h-4 text-emerald-400 dark:text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Export {{ ucfirst($tab) }} CSV</span>
            </a>
        </x-slot>
    </x-page-header>

    <!-- Controls Filter Strip -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row justify-between items-center gap-4">
        <!-- Tab Selector -->
        <div class="flex flex-wrap items-center gap-2">
            @foreach([
                'sales' => 'Sales Performance',
                'orders' => 'Orders & Channels',
                'sellers' => 'Seller Leaderboard',
                'categories' => 'Category Breakdown',
                'tax' => 'GST & Tax Accounting',
                'commission' => 'Platform Commission'
            ] as $tKey => $tLabel)
                <a 
                    href="{{ route('admin.reports.index', ['tab' => $tKey, 'start_date' => $startDate->toDateString(), 'end_date' => $endDate->toDateString()]) }}" 
                    class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition {{ $tab === $tKey ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}"
                >
                    {{ $tLabel }}
                </a>
            @endforeach
        </div>

        <!-- Date Range Form -->
        <form method="GET" action="{{ route('admin.reports.index') }}" class="flex items-center space-x-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="flex items-center space-x-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1">
                <span class="text-[10px] uppercase font-bold text-slate-400">From</span>
                <input type="date" name="start_date" value="{{ $startDate->toDateString() }}" class="text-xs bg-transparent border-0 p-0 text-slate-700 dark:text-slate-300 focus:ring-0">
                <span class="text-[10px] uppercase font-bold text-slate-400">To</span>
                <input type="date" name="end_date" value="{{ $endDate->toDateString() }}" class="text-xs bg-transparent border-0 p-0 text-slate-700 dark:text-slate-300 focus:ring-0">
            </div>
            <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                Apply
            </button>
        </form>
    </div>

    <!-- Tab 1: Sales Performance -->
    @if($tab === 'sales')
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="text-xs font-medium text-slate-500">Gross Paid Revenue</div>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ format_currency($data['totalRevenue']) }}</div>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Total Settled Orders</div>
                <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ number_format($data['totalOrders']) }}</div>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="text-xs font-medium text-slate-500">Shipping Fees Collected</div>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ format_currency($data['totalDeliveryFees']) }}</div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 font-bold text-xs uppercase tracking-wider text-slate-500">
                Daily Sales Aggregates
            </div>
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Date</th>
                        <th class="px-6 py-3.5">Orders Count</th>
                        <th class="px-6 py-3.5">Gross Revenue</th>
                        <th class="px-6 py-3.5">Delivery Fees</th>
                        <th class="px-6 py-3.5 text-right">Net Cart Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($data['daily'] as $row)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                        <td class="px-6 py-3.5 font-medium text-slate-900 dark:text-white">{{ $row->date }}</td>
                        <td class="px-6 py-3.5 font-mono">{{ $row->order_count }}</td>
                        <td class="px-6 py-3.5 font-bold text-slate-900 dark:text-white">{{ format_currency((float)$row->gross) }}</td>
                        <td class="px-6 py-3.5 text-slate-600 dark:text-slate-400">{{ format_currency((float)$row->delivery_fee) }}</td>
                        <td class="px-6 py-3.5 text-right font-bold text-emerald-600 dark:text-emerald-400">{{ format_currency((float)$row->net) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-6 py-10 text-center text-slate-500">No order data for selected period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <!-- Tab 2: Orders & Channels -->
    @if($tab === 'orders')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Orders by Lifecycle Status</h3>
                <table class="w-full text-left text-xs">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($data['byStatus'] as $st)
                        <tr>
                            <td class="py-2.5 font-semibold text-slate-800 dark:text-slate-200">{{ ucfirst(str_replace('_', ' ', $st->status)) }}</td>
                            <td class="py-2.5 text-center font-mono">{{ $st->count }} orders</td>
                            <td class="py-2.5 text-right font-bold text-slate-900 dark:text-white">{{ format_currency((float)$st->total) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Orders by Payment Gateway / Method</h3>
                <table class="w-full text-left text-xs">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($data['byMethod'] as $pm)
                        <tr>
                            <td class="py-2.5 font-semibold text-slate-800 dark:text-slate-200">{{ strtoupper($pm->payment_method ?: 'N/A') }}</td>
                            <td class="py-2.5 text-center font-mono">{{ $pm->count }} orders</td>
                            <td class="py-2.5 text-right font-bold text-slate-900 dark:text-white">{{ format_currency((float)$pm->total) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Tab 3: Seller Leaderboard -->
    @if($tab === 'sellers')
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 font-bold text-xs uppercase tracking-wider text-slate-500">
                Top Performing Merchant Accounts
            </div>
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Seller Business Name</th>
                        <th class="px-6 py-3.5">Delivered / Fulfilment Orders</th>
                        <th class="px-6 py-3.5">Gross Merchandising Value (GMV)</th>
                        <th class="px-6 py-3.5 text-right">Commission Accrued</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($data['sellers'] as $s)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                        <td class="px-6 py-3.5 font-bold text-slate-900 dark:text-white">
                            {{ $s['name'] }}
                            <span class="text-[10px] text-slate-400 block font-normal">#{{ $s['id'] }}</span>
                        </td>
                        <td class="px-6 py-3.5 font-mono">{{ $s['orders_count'] }}</td>
                        <td class="px-6 py-3.5 font-bold text-slate-900 dark:text-white">{{ format_currency($s['gross_gmv']) }}</td>
                        <td class="px-6 py-3.5 text-right font-bold text-indigo-600 dark:text-indigo-400">{{ format_currency($s['commission']) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-6 py-10 text-center text-slate-500">No seller transactions in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <!-- Tab 4: Categories Breakdown -->
    @if($tab === 'categories')
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 font-bold text-xs uppercase tracking-wider text-slate-500">
                Departmental Sales &amp; Unit Turnover
            </div>
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Category Name</th>
                        <th class="px-6 py-3.5">Units Dispatched</th>
                        <th class="px-6 py-3.5 text-right">Gross GMV</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($data['categories'] as $c)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                        <td class="px-6 py-3.5 font-bold text-slate-900 dark:text-white">{{ $c['name'] }}</td>
                        <td class="px-6 py-3.5 font-mono">{{ number_format($c['units']) }} units</td>
                        <td class="px-6 py-3.5 text-right font-bold text-emerald-600 dark:text-emerald-400">{{ format_currency($c['revenue']) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-6 py-10 text-center text-slate-500">No category transactions in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <!-- Tab 5: GST & Tax Accounting -->
    @if($tab === 'tax')
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm">
                <div class="text-xs font-semibold text-slate-500">Gross Paid Platform Revenue</div>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ format_currency($data['grossRevenue']) }}</div>
                <p class="text-[10px] text-slate-400 mt-2">Combined product price + tax + delivery charges</p>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm">
                <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Merchant GST Collected (B2C/B2B)</div>
                <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ format_currency($data['productTax']) }}</div>
                <p class="text-[10px] text-slate-400 mt-2">Remitted to merchants for GSTR-1 filing</p>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm">
                <div class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">Platform Commission GST (18%)</div>
                <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ format_currency($data['commissionTax']) }}</div>
                <p class="text-[10px] text-slate-400 mt-2">Platform output GST liability on take-rate</p>
            </div>
        </div>
    @endif

    <!-- Tab 6: Commission & Settled Payouts -->
    @if($tab === 'commission')
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="text-xs font-medium text-slate-500">Net Platform Commission</div>
                <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ format_currency($data['totalCommission']) }}</div>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="text-xs font-medium text-slate-500">GST on Commission (18%)</div>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ format_currency($data['totalTax']) }}</div>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="text-xs font-medium text-slate-500">Settled to Sellers</div>
                <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ format_currency($data['totalSettled']) }}</div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 font-bold text-xs uppercase tracking-wider text-slate-500">
                Recent Commission Ledger Entries
            </div>
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Date</th>
                        <th class="px-6 py-3.5">Base Order Amount</th>
                        <th class="px-6 py-3.5">Commission</th>
                        <th class="px-6 py-3.5">GST</th>
                        <th class="px-6 py-3.5 text-right">Seller Net</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($data['entries'] as $ce)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                        <td class="px-6 py-3.5 text-slate-500">{{ $ce->created_at->format('d M Y, h:i A') }}</td>
                        <td class="px-6 py-3.5 font-semibold text-slate-900 dark:text-white">{{ format_currency((float)$ce->base_amount) }}</td>
                        <td class="px-6 py-3.5 font-bold text-indigo-600 dark:text-indigo-400">{{ format_currency((float)$ce->commission) }}</td>
                        <td class="px-6 py-3.5 text-slate-500">{{ format_currency((float)$ce->tax_on_commission) }}</td>
                        <td class="px-6 py-3.5 text-right font-bold text-emerald-600 dark:text-emerald-400">{{ format_currency((float)$ce->net_payable) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-6 py-10 text-center text-slate-500">No commission entries recorded in this window.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if(method_exists($data['entries'], 'links'))
                <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                    {{ $data['entries']->links() }}
                </div>
            @endif
        </div>
    @endif

</div>
@endsection
