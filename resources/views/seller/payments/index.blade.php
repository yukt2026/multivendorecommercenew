@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Earnings & Commission Ledger'])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        title="Earnings &amp; Financial Ledger" 
        subtitle="Transparent accounting of sales revenues, platform commission deductions, GST accounting, and upcoming payout balances."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Financials' => '#', 'Ledger' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                {{ $entries->total() }} Entries
            </span>
        </x-slot>

        <x-slot name="actions">
            <a href="{{ route('seller.settlements.index') }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1 transition">
                <span>View Payout Batches</span>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </x-slot>
    </x-page-header>

    <!-- Upcoming Payout Widget & Policy Notice -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Upcoming Payout Estimation Card (Span 2) -->
        <div class="lg:col-span-2 bg-gradient-to-br from-indigo-900 to-slate-900 text-white rounded-2xl p-6 shadow-md border border-indigo-800/40 relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 w-44 h-44 bg-indigo-500/10 rounded-full blur-2xl"></div>

            <div class="flex items-center justify-between pb-4 border-b border-indigo-800/60">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-300 flex items-center justify-center font-bold">
                        💳
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-white">Upcoming Weekly Payout Projection</h2>
                        <p class="text-xs text-indigo-200">Scheduled batch disbursement for delivered orders</p>
                    </div>
                </div>
                <span class="px-2.5 py-1 rounded-full text-[10px] font-mono font-bold bg-indigo-500/30 text-indigo-200">
                    {{ $returnWindowDays }}-Day Return Safe
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-5">
                <div class="p-4 rounded-xl bg-indigo-950/60 border border-indigo-800/40">
                    <div class="text-xs text-emerald-400 font-semibold flex items-center space-x-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        <span>Matured &amp; Ready for Next Batch</span>
                    </div>
                    <div class="text-2xl font-black font-mono text-white mt-1">
                        {{ format_currency($upcomingMaturedAmount) }}
                    </div>
                    <div class="text-[11px] text-indigo-300 mt-1">
                        {{ $upcomingMatured->count() }} delivered order(s) past the return window.
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
                        {{ $upcomingMaturing->count() }} recently delivered order(s) completing return window.
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-indigo-800/60 flex items-center justify-between text-xs text-indigo-300">
                <span>Platform settlements run automatically every Monday at 02:00 AM UTC.</span>
                <a href="{{ route('seller.settlements.index') }}" class="text-white hover:underline font-semibold flex items-center space-x-1">
                    <span>View Settlements</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <!-- Commission Rates & Information -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm p-6 flex flex-col justify-between">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-2">Merchant Rate Schedule</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Standard rates applicable to your authorized store catalog.</p>

                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                        <span class="text-xs text-slate-600 dark:text-slate-400">Store Override Rate</span>
                        <span class="text-xs font-bold font-mono text-slate-900 dark:text-white">
                            {{ $seller->commission_override_pct !== null ? $seller->commission_override_pct . '%' : 'Category / Global Tier' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                        <span class="text-xs text-slate-600 dark:text-slate-400">GST on Commission</span>
                        <span class="text-xs font-bold font-mono text-slate-900 dark:text-white">{{ setting('commission_gst_pct', 18) }}%</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                        <span class="text-xs text-slate-600 dark:text-slate-400">Return Window</span>
                        <span class="text-xs font-bold font-mono text-slate-900 dark:text-white">{{ $returnWindowDays }} Days</span>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-400">
                GST input tax credit (ITC) invoice invoices are generated upon payout settlement.
            </div>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase">Gross Sales</div>
            <div class="text-xl font-bold font-mono text-slate-900 dark:text-white mt-1">{{ format_currency($totalSales) }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-rose-500 uppercase">Marketplace Fees</div>
            <div class="text-xl font-bold font-mono text-rose-600 dark:text-rose-400 mt-1">- {{ format_currency($totalCommission) }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-rose-500 uppercase">GST on Fees (18%)</div>
            <div class="text-xl font-bold font-mono text-rose-600 dark:text-rose-400 mt-1">- {{ format_currency($totalTaxOnCommission) }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 shadow-sm">
            <div class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-300 uppercase">Net Seller Payable</div>
            <div class="text-xl font-bold font-mono text-emerald-700 dark:text-emerald-300 mt-1">{{ format_currency($totalNetPayable) }}</div>
        </div>
    </div>

    <!-- Ledger Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Transaction Ledger</h3>

            <form method="GET" action="{{ route('seller.payments.index') }}" class="flex items-center space-x-2">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ request('search') }}" 
                    placeholder="Search package #..." 
                    class="text-xs px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-emerald-500 w-44"
                >
                <button type="submit" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-medium">Search</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Date</th>
                        <th class="px-6 py-3.5">Type</th>
                        <th class="px-6 py-3.5">Package Number</th>
                        <th class="px-6 py-3.5 text-right">Order Gross</th>
                        <th class="px-6 py-3.5 text-right">Commission Fee</th>
                        <th class="px-6 py-3.5 text-right">GST (18%)</th>
                        <th class="px-6 py-3.5 text-right">Net Payable</th>
                        <th class="px-6 py-3.5">Settlement Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($entries as $entry)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400 font-mono text-[11px]">
                            {{ $entry->created_at->format('d M Y, h:i A') }}
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $entry->entry_type === 'reversal' ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' }}">
                                {{ $entry->entry_type }}
                            </span>
                        </td>
                        <td class="px-6 py-4 font-mono font-bold text-slate-900 dark:text-white">
                            @if($entry->fulfilmentOrder)
                                <a href="{{ route('seller.orders.show', $entry->fulfilmentOrder) }}" class="text-emerald-600 dark:text-emerald-400 hover:underline">
                                    #{{ $entry->fulfilmentOrder->fulfilment_order_number }}
                                </a>
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-bold text-slate-900 dark:text-white">
                            {{ format_currency((float)$entry->base_amount) }}
                        </td>
                        <td class="px-6 py-4 text-right font-mono text-rose-600 dark:text-rose-400">
                            - {{ format_currency((float)$entry->commission) }}
                        </td>
                        <td class="px-6 py-4 text-right font-mono text-rose-600 dark:text-rose-400">
                            - {{ format_currency((float)$entry->tax_on_commission) }}
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm">
                            {{ format_currency((float)$entry->net_payable) }}
                        </td>
                        <td class="px-6 py-4">
                            @if($entry->settlementItem)
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400">
                                    Settled ({{ $entry->settlementItem->settlement?->settlement_number }})
                                </span>
                            @else
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400">
                                    Pending Batch
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            No ledger entries found. Delivered orders will appear here automatically.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
            {{ $entries->links() }}
        </div>
    </div>

</div>
@endsection
