@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Seller Settlements & Payouts'])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        title="Disbursements & Payout Batches" 
        subtitle="Weekly bank settlements, reconciliation statements, GST credit records, and wire payment references."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Financials' => '#', 'Settlements' => '#']"
    >
        <x-slot name="badge">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                {{ $metrics['total_settlements'] }} Settlement Batches
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center gap-2">
                <x-button variant="outline" size="sm" href="{{ route('seller.settlements.export', request()->query()) }}">
                    <svg class="w-3.5 h-3.5 mr-1.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export All CSV
                </x-button>

                <x-button variant="secondary" size="sm" href="{{ route('seller.payments.index') }}">
                    <span>View Earnings Ledger</span>
                    <span class="ml-1">→</span>
                </x-button>
            </div>
        </x-slot>
    </x-page-header>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <!-- Paid -->
        <a href="{{ route('seller.settlements.index', ['status' => 'paid']) }}" class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition hover:border-emerald-500/50 hover:shadow-md block">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs text-emerald-600 dark:text-emerald-400 font-bold uppercase tracking-wider">Total Paid</span>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">
                    {{ $metrics['paid_count'] ?? 0 }} Batches
                </span>
            </div>
            <div class="text-xl sm:text-2xl font-black font-mono text-slate-900 dark:text-white">{{ format_currency($metrics['paid_amount']) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Direct wire disbursements</div>
        </a>

        <!-- Processing -->
        <a href="{{ route('seller.settlements.index', ['status' => 'processing']) }}" class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition hover:border-indigo-500/50 hover:shadow-md block">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs text-indigo-600 dark:text-indigo-400 font-bold uppercase tracking-wider">Processing</span>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300">
                    {{ $metrics['processing_count'] ?? 0 }} Batches
                </span>
            </div>
            <div class="text-xl sm:text-2xl font-black font-mono text-slate-900 dark:text-white">{{ format_currency($metrics['processing_amount']) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Approved for bank transfer</div>
        </a>

        <!-- Pending Approval -->
        <a href="{{ route('seller.settlements.index', ['status' => 'pending']) }}" class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition hover:border-amber-500/50 hover:shadow-md block">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs text-amber-600 dark:text-amber-400 font-bold uppercase tracking-wider">Pending Audit</span>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300">
                    {{ $metrics['pending_count'] ?? 0 }} Batches
                </span>
            </div>
            <div class="text-xl sm:text-2xl font-black font-mono text-slate-900 dark:text-white">{{ format_currency($metrics['pending_amount']) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Current batch pending verification</div>
        </a>

        <!-- On Hold -->
        <a href="{{ route('seller.settlements.index', ['status' => 'on_hold']) }}" class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition hover:border-rose-500/50 hover:shadow-md block">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs text-rose-600 dark:text-rose-400 font-bold uppercase tracking-wider">On Hold</span>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300">
                    {{ $metrics['on_hold_count'] ?? 0 }} Batches
                </span>
            </div>
            <div class="text-xl sm:text-2xl font-black font-mono text-slate-900 dark:text-white">{{ format_currency($metrics['on_hold_amount']) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Subject to compliance review</div>
        </a>
    </div>

    <!-- Active Disbursement Bank Banner -->
    @if(isset($primaryBank) && $primaryBank)
        <div class="p-3.5 sm:p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-sm shrink-0">
                    🏦
                </div>
                <div>
                    <span class="font-bold text-slate-900 dark:text-white">Designated Payout Account:</span>
                    <span class="text-slate-600 dark:text-slate-300 ml-1.5">{{ $primaryBank->bank_name ?: 'Bank' }} (••••{{ substr($primaryBank->account_no, -4) }})</span>
                    <span class="text-slate-400 font-mono ml-1">&bull; IFSC: {{ $primaryBank->ifsc }}</span>
                </div>
            </div>
            <span class="inline-flex items-center text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold shrink-0">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                Verified for NEFT/RTGS Wire Transfer
            </span>
        </div>
    @endif

    <!-- Settlements Table Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        
        <!-- Filter Bar & Search Toolbar -->
        <div class="p-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
            
            <!-- Status Tabs -->
            @php $currentStatus = request('status', 'all'); @endphp
            <div class="flex flex-wrap items-center gap-1.5 overflow-x-auto pb-1 md:pb-0">
                <a href="{{ route('seller.settlements.index', ['status' => 'all', 'search' => request('search')]) }}" class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $currentStatus === 'all' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300' }}">
                    All Batches
                </a>
                <a href="{{ route('seller.settlements.index', ['status' => 'paid', 'search' => request('search')]) }}" class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $currentStatus === 'paid' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300' }}">
                    Paid ({{ $metrics['paid_count'] ?? 0 }})
                </a>
                <a href="{{ route('seller.settlements.index', ['status' => 'processing', 'search' => request('search')]) }}" class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $currentStatus === 'processing' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300' }}">
                    Processing ({{ $metrics['processing_count'] ?? 0 }})
                </a>
                <a href="{{ route('seller.settlements.index', ['status' => 'pending', 'search' => request('search')]) }}" class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $currentStatus === 'pending' ? 'bg-amber-500 text-slate-950 font-black shadow-xs' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300' }}">
                    Pending ({{ $metrics['pending_count'] ?? 0 }})
                </a>
                <a href="{{ route('seller.settlements.index', ['status' => 'on_hold', 'search' => request('search')]) }}" class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $currentStatus === 'on_hold' ? 'bg-rose-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300' }}">
                    On Hold ({{ $metrics['on_hold_count'] ?? 0 }})
                </a>
            </div>

            <!-- Search Bar -->
            <form method="GET" action="{{ route('seller.settlements.index') }}" class="flex items-center gap-2">
                @if(request('status') && request('status') !== 'all')
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="relative w-full sm:w-56">
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ request('search') }}" 
                        placeholder="Search settlement # or UTR..." 
                        class="w-full text-xs pl-8 pr-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500"
                    >
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <x-button variant="secondary" size="sm" type="submit">Search</x-button>
                @if(request()->hasAny(['search', 'status']))
                    <a href="{{ route('seller.settlements.index') }}" class="text-xs text-rose-600 dark:text-rose-400 hover:underline px-1 font-medium">Reset</a>
                @endif
            </form>
        </div>

        <!-- Settlements Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-5 py-3.5">Settlement #</th>
                        <th class="px-5 py-3.5">Cycle Period</th>
                        <th class="px-5 py-3.5 text-right">Gross Sales</th>
                        <th class="px-5 py-3.5 text-right">Marketplace Fee</th>
                        <th class="px-5 py-3.5 text-right">GST (18%)</th>
                        <th class="px-5 py-3.5 text-right">COD Deducted</th>
                        <th class="px-5 py-3.5 text-right">Net Payout</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($settlements as $s)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-5 py-3.5 font-mono">
                            <a href="{{ route('seller.settlements.show', $s) }}" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">
                                {{ $s->settlement_number }}
                            </a>
                            @if($s->reference)
                                <div class="text-[10px] text-emerald-600 dark:text-emerald-400 font-mono mt-0.5">
                                    UTR: {{ $s->reference }}
                                </div>
                            @endif
                        </td>

                        <td class="px-5 py-3.5 text-slate-500 dark:text-slate-400 font-mono text-[11px] whitespace-nowrap">
                            {{ $s->period_start->format('d M') }} → {{ $s->period_end->format('d M Y') }}
                        </td>

                        <td class="px-5 py-3.5 text-right font-mono font-bold text-slate-900 dark:text-white whitespace-nowrap">
                            {{ format_currency((float)$s->gross) }}
                        </td>

                        <td class="px-5 py-3.5 text-right font-mono text-rose-600 dark:text-rose-400 whitespace-nowrap">
                            - {{ format_currency((float)$s->commission) }}
                        </td>

                        <td class="px-5 py-3.5 text-right font-mono text-rose-600 dark:text-rose-400 whitespace-nowrap">
                            - {{ format_currency((float)$s->tax_on_commission) }}
                        </td>

                        <td class="px-5 py-3.5 text-right font-mono text-amber-600 dark:text-amber-400 whitespace-nowrap">
                            @if((float)$s->cod_collected > 0)
                                - {{ format_currency((float)$s->cod_collected) }}
                            @else
                                ₹0.00
                            @endif
                        </td>

                        <td class="px-5 py-3.5 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm whitespace-nowrap">
                            {{ format_currency((float)$s->net) }}
                        </td>

                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                                @if($s->status->value === 'paid' || $s->status->value === 'settled') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800
                                @elseif($s->status->value === 'processing') bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800
                                @elseif($s->status->value === 'on_hold') bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800
                                @else bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800
                                @endif
                            ">
                                {{ $s->status->label() }}
                            </span>
                        </td>

                        <td class="px-5 py-3.5 text-right space-x-1.5 whitespace-nowrap">
                            <x-button variant="outline" size="sm" href="{{ route('seller.settlements.show', $s) }}" class="text-[11px] py-1 px-2.5">
                                View Statement
                            </x-button>

                            <a href="{{ route('seller.settlements.pdf', $s) }}" class="p-1.5 text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition inline-block rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" title="Download PDF Statement">
                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </a>

                            <a href="{{ route('seller.settlements.csv', $s) }}" class="p-1.5 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition inline-block rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" title="Download CSV Items">
                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="p-8">
                            <x-empty-state 
                                title="No Settlement Statements Found" 
                                description="Settlements are generated automatically on a weekly batch cycle for orders past the customer return window."
                                icon="cube"
                            />
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($settlements->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
                {{ $settlements->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
