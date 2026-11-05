@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Settlement #' . $settlement->settlement_number])

@section('content')
<div class="space-y-6">

    <!-- Page Header & Action Bar -->
    <x-page-header 
        :title="'Settlement Statement #' . $settlement->settlement_number" 
        :subtitle="'Cycle period ' . $settlement->period_start->format('d M Y') . ' to ' . $settlement->period_end->format('d M Y')"
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Settlements' => route('seller.settlements.index'), $settlement->settlement_number => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase
                @if($settlement->status->value === 'paid' || $settlement->status->value === 'settled') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400
                @elseif($settlement->status->value === 'processing') bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400
                @elseif($settlement->status->value === 'on_hold') bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400
                @else bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400
                @endif
            ">
                {{ $settlement->status->label() }}
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <a href="{{ route('seller.settlements.pdf', $settlement) }}" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-semibold flex items-center space-x-1.5 shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Download PDF Statement</span>
                </a>
                <a href="{{ route('seller.settlements.csv', $settlement) }}" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Export CSV</span>
                </a>
            </div>
        </x-slot>
    </x-page-header>

    @if($settlement->status->value === 'on_hold')
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs flex items-center space-x-2">
            <svg class="w-5 h-5 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div><strong>Notice:</strong> This settlement batch is currently undergoing compliance or dispute review: {{ $settlement->hold_reason }}</div>
        </div>
    @endif

    <!-- Accounting Ledger Breakdown Cards -->
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase">Gross Sales</div>
            <div class="text-xl font-bold font-mono text-slate-900 dark:text-white mt-1">{{ format_currency((float)$settlement->gross) }}</div>
            <div class="text-[10px] text-slate-400 mt-1">Delivered orders</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-rose-500 uppercase">Marketplace Fee</div>
            <div class="text-xl font-bold font-mono text-rose-600 dark:text-rose-400 mt-1">- {{ format_currency((float)$settlement->commission) }}</div>
            <div class="text-[10px] text-slate-400 mt-1">Platform service fee</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-rose-500 uppercase">GST on Fee</div>
            <div class="text-xl font-bold font-mono text-rose-600 dark:text-rose-400 mt-1">- {{ format_currency((float)$settlement->tax_on_commission) }}</div>
            <div class="text-[10px] text-slate-400 mt-1">18% GST (Eligible for ITC)</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-amber-500 uppercase">COD Deducted</div>
            <div class="text-xl font-bold font-mono text-amber-600 dark:text-amber-400 mt-1">
                @if((float)$settlement->cod_collected > 0)
                    - {{ format_currency((float)$settlement->cod_collected) }}
                @else
                    ₹0.00
                @endif
            </div>
            <div class="text-[10px] text-slate-400 mt-1">Doorstep cash collected</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-indigo-500 uppercase">Adjustments</div>
            <div class="text-xl font-bold font-mono text-indigo-600 dark:text-indigo-400 mt-1">
                {{ (float)$settlement->adjustments >= 0 ? '+ ' : '' }}{{ format_currency((float)$settlement->adjustments) }}
            </div>
            <div class="text-[10px] text-slate-400 mt-1">Manual / policy</div>
        </div>

        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 shadow-sm">
            <div class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-300 uppercase">Net Payout</div>
            <div class="text-xl font-bold font-mono text-emerald-700 dark:text-emerald-300 mt-1">{{ format_currency((float)$settlement->net) }}</div>
            <div class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-1">
                @if($settlement->reference)
                    UTR: {{ $settlement->reference }}
                @else
                    Payable amount
                @endif
            </div>
        </div>
    </div>

    <!-- Payout Information & Timestamps -->
    <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-3">Bank Disbursement Status</h3>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 text-xs">
            <div>
                <span class="text-slate-400 block">Cycle Period:</span>
                <strong class="text-slate-900 dark:text-white">{{ $settlement->period_start->format('d M') }} &rarr; {{ $settlement->period_end->format('d M Y') }}</strong>
            </div>
            <div>
                <span class="text-slate-400 block">Payout Status:</span>
                <strong class="text-slate-900 dark:text-white">{{ $settlement->status->label() }}</strong>
            </div>
            <div>
                <span class="text-slate-400 block">Bank UTR Reference:</span>
                <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                    {{ $settlement->reference ?: 'Pending Disbursement' }}
                </span>
            </div>
            <div>
                <span class="text-slate-400 block">Disbursed At:</span>
                <span class="text-slate-700 dark:text-slate-300">
                    {{ $settlement->paid_at ? $settlement->paid_at->format('d M Y, h:i A') : 'Pending wire transfer' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Settlement Items Ledger -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Itemized Transactions Ledger ({{ $settlement->items->count() }})</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">#</th>
                        <th class="px-6 py-3.5">Transaction Type</th>
                        <th class="px-6 py-3.5">Description</th>
                        <th class="px-6 py-3.5">Package Number</th>
                        <th class="px-6 py-3.5 text-right">Order Gross</th>
                        <th class="px-6 py-3.5 text-right">Marketplace Fee</th>
                        <th class="px-6 py-3.5 text-right">GST (18%)</th>
                        <th class="px-6 py-3.5 text-right">Net Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($settlement->items as $idx => $item)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-3.5 text-slate-400 font-mono">{{ $idx + 1 }}</td>
                        <td class="px-6 py-3.5">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                @if($item->type === 'order_payout') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400
                                @elseif($item->type === 'cod_deduction') bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400
                                @elseif($item->type === 'adjustment') bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400
                                @else bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300
                                @endif
                            ">
                                {{ str_replace('_', ' ', $item->type) }}
                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-slate-900 dark:text-white font-medium">
                            {{ $item->description }}
                        </td>
                        <td class="px-6 py-3.5 font-mono text-slate-600 dark:text-slate-400">
                            @if($item->fulfilmentOrder)
                                <a href="{{ route('seller.orders.show', $item->fulfilmentOrder) }}" class="text-emerald-600 dark:text-emerald-400 hover:underline">
                                    #{{ $item->fulfilmentOrder->fulfilment_order_number }}
                                </a>
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-6 py-3.5 text-right font-mono text-slate-900 dark:text-white">
                            {{ $item->commissionEntry ? format_currency((float)$item->commissionEntry->base_amount) : '-' }}
                        </td>
                        <td class="px-6 py-3.5 text-right font-mono text-rose-600 dark:text-rose-400">
                            {{ $item->commissionEntry ? '- ' . format_currency((float)$item->commissionEntry->commission) : '-' }}
                        </td>
                        <td class="px-6 py-3.5 text-right font-mono text-rose-600 dark:text-rose-400">
                            {{ $item->commissionEntry ? '- ' . format_currency((float)$item->commissionEntry->tax_on_commission) : '-' }}
                        </td>
                        <td class="px-6 py-3.5 text-right font-mono font-bold {{ (float)$item->amount < 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            {{ format_currency((float)$item->amount) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-slate-500">No items found in this settlement.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
