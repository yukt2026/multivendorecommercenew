@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Seller Settlements & Payouts'])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        title="Disbursements &amp; Payout Batches" 
        subtitle="Weekly bank settlements, reconciliation statements, GST credit records, and payment references."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Financials' => '#', 'Settlements' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                {{ $metrics['total_settlements'] }} Batches
            </span>
        </x-slot>

        <x-slot name="actions">
            <a href="{{ route('seller.payments.index') }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1 transition">
                <span>View Earnings Ledger</span>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </x-slot>
    </x-page-header>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold mb-2">Total Paid to Bank</div>
            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ format_currency($metrics['paid_amount']) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Direct wire disbursements</div>
        </div>

        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold mb-2">Processing Payout</div>
            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ format_currency($metrics['processing_amount']) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Approved for bank transfer</div>
        </div>

        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-amber-600 dark:text-amber-400 font-semibold mb-2">Pending Approval</div>
            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ format_currency($metrics['pending_amount']) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Current batch pending audit</div>
        </div>

        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-rose-600 dark:text-rose-400 font-semibold mb-2">On Hold</div>
            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ format_currency($metrics['on_hold_amount']) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Subject to verification</div>
        </div>
    </div>

    <!-- Settlements Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Payout Statements</h3>

            <div class="flex items-center space-x-2">
                @php $status = request('status', 'all'); @endphp
                <a href="{{ route('seller.settlements.index', ['status' => 'all']) }}" class="px-2.5 py-1 rounded text-xs {{ $status === 'all' ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">All</a>
                <a href="{{ route('seller.settlements.index', ['status' => 'paid']) }}" class="px-2.5 py-1 rounded text-xs {{ $status === 'paid' ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">Paid</a>
                <a href="{{ route('seller.settlements.index', ['status' => 'processing']) }}" class="px-2.5 py-1 rounded text-xs {{ $status === 'processing' ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">Processing</a>
                <a href="{{ route('seller.settlements.index', ['status' => 'pending']) }}" class="px-2.5 py-1 rounded text-xs {{ $status === 'pending' ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">Pending</a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Settlement #</th>
                        <th class="px-6 py-3.5">Cycle Period</th>
                        <th class="px-6 py-3.5 text-right">Gross Sales</th>
                        <th class="px-6 py-3.5 text-right">Marketplace Fee</th>
                        <th class="px-6 py-3.5 text-right">GST (18%)</th>
                        <th class="px-6 py-3.5 text-right">COD Deducted</th>
                        <th class="px-6 py-3.5 text-right">Net Payout</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($settlements as $s)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4 font-mono font-bold text-slate-900 dark:text-white">
                            <a href="{{ route('seller.settlements.show', $s) }}" class="text-emerald-600 dark:text-emerald-400 hover:underline">
                                {{ $s->settlement_number }}
                            </a>
                            @if($s->reference)
                                <div class="text-[10px] text-slate-400 font-mono">UTR: {{ $s->reference }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400 font-mono text-[11px]">
                            {{ $s->period_start->format('d M') }} &rarr; {{ $s->period_end->format('d M Y') }}
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-bold text-slate-900 dark:text-white">
                            {{ format_currency((float)$s->gross) }}
                        </td>
                        <td class="px-6 py-4 text-right font-mono text-rose-600 dark:text-rose-400">
                            - {{ format_currency((float)$s->commission) }}
                        </td>
                        <td class="px-6 py-4 text-right font-mono text-rose-600 dark:text-rose-400">
                            - {{ format_currency((float)$s->tax_on_commission) }}
                        </td>
                        <td class="px-6 py-4 text-right font-mono text-amber-600 dark:text-amber-400">
                            @if((float)$s->cod_collected > 0)
                                - {{ format_currency((float)$s->cod_collected) }}
                            @else
                                ₹0.00
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm">
                            {{ format_currency((float)$s->net) }}
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase
                                @if($s->status->value === 'paid' || $s->status->value === 'settled') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400
                                @elseif($s->status->value === 'processing') bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400
                                @elseif($s->status->value === 'on_hold') bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400
                                @else bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400
                                @endif
                            ">
                                {{ $s->status->label() }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                            <a href="{{ route('seller.settlements.show', $s) }}" class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded text-[11px] font-semibold transition">
                                View
                            </a>
                            <a href="{{ route('seller.settlements.pdf', $s) }}" class="p-1 text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition inline-block" title="Download PDF Statement">
                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </a>
                            <a href="{{ route('seller.settlements.csv', $s) }}" class="p-1 text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition inline-block" title="Download CSV Items">
                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            No settlement statements found yet. Settlements run on a weekly cycle for delivered orders.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
            {{ $settlements->links() }}
        </div>
    </div>

</div>
@endsection
