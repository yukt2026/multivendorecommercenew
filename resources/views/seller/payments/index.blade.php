@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Earnings & Financial Ledger'])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        title="Earnings & Financial Ledger" 
        subtitle="Transparent accounting of sales revenues, platform commission deductions, GST accounting, and upcoming payout balances."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Financials' => '#', 'Ledger' => '#']"
    >
        <x-slot name="badge">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                {{ number_format($entries->total()) }} Ledger Entries
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center gap-2">
                <x-button variant="outline" size="sm" href="{{ route('seller.payments.export', request()->query()) }}">
                    <svg class="w-3.5 h-3.5 mr-1.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export CSV
                </x-button>

                <x-button variant="primary" size="sm" href="{{ route('seller.settlements.index') }}">
                    <span>View Payout Batches</span>
                    <span class="ml-1">→</span>
                </x-button>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Upcoming Payout Widget & Merchant Schedule Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Upcoming Payout Estimation Card (Span 2) -->
        <div class="lg:col-span-2 bg-gradient-to-br from-indigo-900 to-slate-900 text-white rounded-2xl p-6 shadow-md border border-indigo-800/40 relative overflow-hidden flex flex-col justify-between">
            <div class="absolute -right-10 -bottom-10 w-44 h-44 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>

            <div>
                <div class="flex items-center justify-between pb-4 border-b border-indigo-800/60">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-300 flex items-center justify-center font-bold text-lg">
                            💳
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-white tracking-tight">Upcoming Weekly Payout Projection</h2>
                            <p class="text-xs text-indigo-200">Scheduled batch disbursement for verified delivered orders</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-mono font-bold bg-indigo-500/30 text-indigo-200 border border-indigo-400/20">
                        {{ $returnWindowDays }}-Day Return Safe
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-5">
                    <div class="p-4 rounded-xl bg-indigo-950/60 border border-indigo-800/40">
                        <div class="text-xs text-emerald-400 font-semibold flex items-center space-x-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            <span>Matured & Ready for Next Batch</span>
                        </div>
                        <div class="text-2xl font-black font-mono text-white mt-1">
                            {{ format_currency($upcomingMaturedAmount) }}
                        </div>
                        <div class="text-[11px] text-indigo-300 mt-1">
                            {{ $upcomingMatured->count() }} delivered order(s) past policy window.
                        </div>
                    </div>

                    <div class="p-4 rounded-xl bg-indigo-950/60 border border-indigo-800/40">
                        <div class="text-xs text-amber-300 font-semibold flex items-center space-x-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                            <span>Maturing in Return Window</span>
                        </div>
                        <div class="text-2xl font-black font-mono text-white mt-1">
                            {{ format_currency($upcomingMaturingAmount) }}
                        </div>
                        <div class="text-[11px] text-indigo-300 mt-1">
                            {{ $upcomingMaturing->count() }} delivered order(s) completing return window.
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-indigo-800/60 flex flex-col sm:flex-row sm:items-center justify-between text-xs text-indigo-300 gap-2">
                <span>Platform payouts are batch processed automatically on a weekly cycle.</span>
                <a href="{{ route('seller.settlements.index') }}" class="text-white hover:underline font-semibold flex items-center space-x-1">
                    <span>View Settlement Batches</span>
                    <span>→</span>
                </a>
            </div>
        </div>

        <!-- Commission Rates & Bank Account Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm p-6 flex flex-col justify-between">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-2">Merchant Rate Schedule</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Standard rates applicable to your authorized store catalog.</p>

                <div class="space-y-2.5">
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 text-xs">
                        <span class="text-slate-600 dark:text-slate-400">Store Commission</span>
                        <span class="font-bold font-mono text-slate-900 dark:text-white">
                            {{ $seller->commission_override !== null ? $seller->commission_override . '%' : 'Category / Global Tier' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 text-xs">
                        <span class="text-slate-600 dark:text-slate-400">GST on Commission</span>
                        <span class="font-bold font-mono text-slate-900 dark:text-white">{{ setting('commission_gst_pct', 18) }}%</span>
                    </div>

                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 text-xs">
                        <span class="text-slate-600 dark:text-slate-400">Customer Return Window</span>
                        <span class="font-bold font-mono text-slate-900 dark:text-white">{{ $returnWindowDays }} Days</span>
                    </div>
                </div>
            </div>

            <!-- Active Disbursement Bank Account -->
            <div class="mt-4 pt-3.5 border-t border-slate-100 dark:border-slate-800">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5 flex items-center justify-between">
                    <span>Disbursement Bank Account</span>
                    @if($primaryBank)
                        <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-50 dark:bg-emerald-950/60 px-1.5 py-0.5 rounded">Active</span>
                    @endif
                </div>

                @if($primaryBank)
                    <div class="text-xs text-slate-800 dark:text-slate-200">
                        <div class="font-bold text-slate-900 dark:text-white">{{ $primaryBank->bank_name ?: 'Registered Bank' }}</div>
                        <div class="text-slate-500 dark:text-slate-400 text-[11px] mt-0.5">
                            A/C: <span class="font-mono font-bold">{{ $primaryBank->account_no ? '••••' . substr($primaryBank->account_no, -4) : '••••' }}</span>
                            &bull; IFSC: <span class="font-mono">{{ $primaryBank->ifsc }}</span>
                        </div>
                    </div>
                @else
                    <div class="text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                        <span>⚠️ Bank details not configured. Payouts will remain on hold.</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Gross Sales</div>
            <div class="text-xl sm:text-2xl font-black font-mono text-slate-900 dark:text-white mt-1">{{ format_currency($totalSales) }}</div>
            <div class="text-[10px] text-slate-400 mt-1">Delivered orders gross</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-bold text-rose-500 uppercase tracking-wider">Platform Fees</div>
            <div class="text-xl sm:text-2xl font-black font-mono text-rose-600 dark:text-rose-400 mt-1">- {{ format_currency($totalCommission) }}</div>
            <div class="text-[10px] text-slate-400 mt-1">Commission deductions</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-bold text-rose-500 uppercase tracking-wider">GST on Fees (18%)</div>
            <div class="text-xl sm:text-2xl font-black font-mono text-rose-600 dark:text-rose-400 mt-1">- {{ format_currency($totalTaxOnCommission) }}</div>
            <div class="text-[10px] text-slate-400 mt-1">Input tax credit eligible</div>
        </div>

        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 shadow-sm">
            <div class="text-[11px] font-bold text-emerald-700 dark:text-emerald-300 uppercase tracking-wider">Net Seller Payable</div>
            <div class="text-xl sm:text-2xl font-black font-mono text-emerald-700 dark:text-emerald-300 mt-1">{{ format_currency($totalNetPayable) }}</div>
            <div class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-1">Total accumulated earnings</div>
        </div>
    </div>

    <!-- Ledger Filter Toolbar & Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        
        <!-- Search & Filter Controls -->
        <div class="p-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
            <form method="GET" action="{{ route('seller.payments.index') }}" class="flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
                
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Search Input -->
                    <div class="relative w-full sm:w-56">
                        <input 
                            type="text" 
                            name="search" 
                            value="{{ request('search') }}" 
                            placeholder="Search package # or order #..." 
                            class="w-full text-xs pl-8 pr-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500"
                        >
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>

                    <!-- Store Outlet Filter (if multi-store) -->
                    @if(isset($stores) && $stores->count() > 1)
                        <select name="store_id" class="text-xs py-2 px-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 focus:ring-indigo-500">
                            <option value="">All Outlets ({{ $stores->count() }})</option>
                            @foreach($stores as $st)
                                <option value="{{ $st->id }}" {{ request('store_id') == $st->id ? 'selected' : '' }}>
                                    {{ $st->name }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    <!-- Entry Type Filter -->
                    <select name="entry_type" class="text-xs py-2 px-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 focus:ring-indigo-500">
                        <option value="">All Transaction Types</option>
                        <option value="standard" {{ request('entry_type') === 'standard' ? 'selected' : '' }}>Earnings (Standard)</option>
                        <option value="reversal" {{ request('entry_type') === 'reversal' ? 'selected' : '' }}>Reversals (Returns)</option>
                    </select>

                    <!-- Settlement Status Filter -->
                    <select name="settlement_status" class="text-xs py-2 px-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 focus:ring-indigo-500">
                        <option value="">All Settlement Statuses</option>
                        <option value="pending" {{ request('settlement_status') === 'pending' ? 'selected' : '' }}>Pending Batch</option>
                        <option value="settled" {{ request('settlement_status') === 'settled' ? 'selected' : '' }}>Settled / Paid</option>
                    </select>

                    <x-button variant="secondary" size="sm" type="submit">Filter</x-button>

                    @if(request()->hasAny(['search', 'entry_type', 'settlement_status', 'store_id']))
                        <a href="{{ route('seller.payments.index') }}" class="text-xs text-rose-600 dark:text-rose-400 hover:underline px-2 font-medium">
                            Clear Filters
                        </a>
                    @endif
                </div>

                <div class="text-xs text-slate-500 self-end md:self-center">
                    Showing {{ $entries->firstItem() ?? 0 }}-{{ $entries->lastItem() ?? 0 }} of {{ $entries->total() }} entries
                </div>
            </form>
        </div>

        <!-- Transactions Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-5 py-3.5">Date & Time</th>
                        <th class="px-5 py-3.5">Type</th>
                        <th class="px-5 py-3.5">Package & Order #</th>
                        <th class="px-5 py-3.5">Store / Outlet</th>
                        <th class="px-5 py-3.5 text-right">Order Gross</th>
                        <th class="px-5 py-3.5 text-right">Platform Fee</th>
                        <th class="px-5 py-3.5 text-right">GST (18%)</th>
                        <th class="px-5 py-3.5 text-right">Net Payable</th>
                        <th class="px-5 py-3.5">Settlement Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($entries as $entry)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-5 py-3.5 text-slate-500 dark:text-slate-400 font-mono text-[11px] whitespace-nowrap">
                            {{ $entry->created_at->format('d M Y, h:i A') }}
                        </td>

                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $entry->entry_type === 'reversal' ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' }}">
                                {{ $entry->entry_type }}
                            </span>
                        </td>

                        <td class="px-5 py-3.5 font-mono">
                            @if($entry->fulfilmentOrder)
                                <a href="{{ route('seller.orders.show', $entry->fulfilmentOrder) }}" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">
                                    #{{ $entry->fulfilmentOrder->fulfilment_order_number }}
                                </a>
                                @if($entry->fulfilmentOrder->order)
                                    <div class="text-[10px] text-slate-400 font-normal">
                                        Order: {{ $entry->fulfilmentOrder->order->order_number }}
                                    </div>
                                @endif
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>

                        <td class="px-5 py-3.5 text-slate-700 dark:text-slate-300">
                            {{ $entry->fulfilmentOrder?->store?->name ?? 'Default Store' }}
                        </td>

                        <td class="px-5 py-3.5 text-right font-mono font-bold text-slate-900 dark:text-white whitespace-nowrap">
                            {{ format_currency((float)$entry->base_amount) }}
                        </td>

                        <td class="px-5 py-3.5 text-right font-mono text-rose-600 dark:text-rose-400 whitespace-nowrap">
                            - {{ format_currency((float)$entry->commission) }}
                        </td>

                        <td class="px-5 py-3.5 text-right font-mono text-rose-600 dark:text-rose-400 whitespace-nowrap">
                            - {{ format_currency((float)$entry->tax_on_commission) }}
                        </td>

                        <td class="px-5 py-3.5 text-right font-mono font-bold whitespace-nowrap {{ (float)$entry->net_payable < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }} text-sm">
                            {{ format_currency((float)$entry->net_payable) }}
                        </td>

                        <td class="px-5 py-3.5 whitespace-nowrap">
                            @if($entry->settlementItem && ($entry->settlementItem->seller_settlement_id || $entry->settlementItem->settlement))
                                <a href="{{ route('seller.settlements.show', $entry->settlementItem->seller_settlement_id ?? $entry->settlementItem->settlement) }}" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:underline">
                                    Batch #{{ $entry->settlementItem->settlement?->settlement_number ?? $entry->settlementItem->seller_settlement_id }}
                                </a>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                    Pending Payout
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="p-8">
                            <x-empty-state 
                                title="No Transaction Entries Found" 
                                description="Delivered orders past fulfillment and return adjustments will appear in this ledger automatically with full financial breakdown."
                                icon="cube"
                            />
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($entries->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
                {{ $entries->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
