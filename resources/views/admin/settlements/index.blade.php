@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Seller Settlements'])

@section('content')
<div class="space-y-6" x-data="{ openGenerateModal: false, openPayModal: false, selectedSettlement: null, utrRef: '' }">

    <!-- Page Header -->
    <x-page-header 
        title="Seller Settlements &amp; Disbursements" 
        subtitle="Weekly automated settlement batches, COD reconciliation, commission ledger auditing, and bank payout processing."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Settlements' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                {{ $metrics['total_settlements'] }} Batches
            </span>
        </x-slot>

        <x-slot name="actions">
            <button 
                @click="openGenerateModal = true" 
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center space-x-1.5"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                <span>Generate Settlement Batch</span>
            </button>
        </x-slot>
    </x-page-header>

    <!-- Financial KPI Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="flex items-center justify-between text-xs text-amber-600 dark:text-amber-400 font-semibold mb-2">
                <span>Pending Approval</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-amber-100 dark:bg-amber-950/60 font-mono">{{ $metrics['pending_count'] }}</span>
            </div>
            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ format_currency($metrics['pending_amount']) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Delivered past return window</div>
        </div>

        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="flex items-center justify-between text-xs text-indigo-600 dark:text-indigo-400 font-semibold mb-2">
                <span>Processing Payout</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-indigo-100 dark:bg-indigo-950/60 font-mono">{{ $metrics['processing_count'] }}</span>
            </div>
            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ format_currency($metrics['processing_amount']) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Approved for bank wire transfer</div>
        </div>

        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="flex items-center justify-between text-xs text-emerald-600 dark:text-emerald-400 font-semibold mb-2">
                <span>Disbursed &amp; Paid</span>
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            </div>
            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ format_currency($metrics['paid_amount']) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Total lifetime seller payouts</div>
        </div>

        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="flex items-center justify-between text-xs text-rose-600 dark:text-rose-400 font-semibold mb-2">
                <span>Settlements On Hold</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-rose-100 dark:bg-rose-950/60 font-mono">{{ $metrics['on_hold_count'] }}</span>
            </div>
            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ $metrics['on_hold_count'] }} Batches</div>
            <div class="text-[11px] text-slate-400 mt-1">Held for dispute or bank review</div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="flex items-center space-x-2">
            @php $currentStatus = request('status', 'all'); @endphp
            <a href="{{ route('admin.settlements.index', ['status' => 'all']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $currentStatus === 'all' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                All
            </a>
            <a href="{{ route('admin.settlements.index', ['status' => 'pending']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $currentStatus === 'pending' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                Pending ({{ $metrics['pending_count'] }})
            </a>
            <a href="{{ route('admin.settlements.index', ['status' => 'processing']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $currentStatus === 'processing' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                Processing ({{ $metrics['processing_count'] }})
            </a>
            <a href="{{ route('admin.settlements.index', ['status' => 'paid']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $currentStatus === 'paid' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                Paid
            </a>
            <a href="{{ route('admin.settlements.index', ['status' => 'on_hold']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $currentStatus === 'on_hold' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                On Hold ({{ $metrics['on_hold_count'] }})
            </a>
        </div>

        <form method="GET" action="{{ route('admin.settlements.index') }}" class="flex items-center space-x-2">
            <input type="hidden" name="status" value="{{ $currentStatus }}">
            <select name="seller_id" onchange="this.form.submit()" class="text-xs px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                <option value="">All Sellers</option>
                @foreach($sellers as $s)
                    <option value="{{ $s->id }}" {{ request('seller_id') == $s->id ? 'selected' : '' }}>{{ $s->store_name }}</option>
                @endforeach
            </select>
            <input 
                type="text" 
                name="search" 
                value="{{ request('search') }}" 
                placeholder="Settlement # or UTR..." 
                class="text-xs px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500 w-44"
            >
            <button type="submit" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-medium">Search</button>
        </form>
    </div>

    <!-- Settlements Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Settlement #</th>
                        <th class="px-6 py-3.5">Seller</th>
                        <th class="px-6 py-3.5">Cycle Period</th>
                        <th class="px-6 py-3.5 text-right">Gross Sales</th>
                        <th class="px-6 py-3.5 text-right">Commission</th>
                        <th class="px-6 py-3.5 text-right">GST (18%)</th>
                        <th class="px-6 py-3.5 text-right">COD Deducted</th>
                        <th class="px-6 py-3.5 text-right">Net Payable</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($settlements as $s)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4 font-mono font-bold text-slate-900 dark:text-white">
                            <a href="{{ route('admin.settlements.show', $s) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $s->settlement_number }}
                            </a>
                            @if($s->reference)
                                <div class="text-[10px] text-slate-400 font-mono">UTR: {{ $s->reference }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-900 dark:text-white">{{ $s->seller?->store_name ?? 'N/A' }}</div>
                            <div class="text-[10px] text-slate-400">Seller #{{ $s->seller_id }}</div>
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
                        <td class="px-6 py-4 text-right space-x-1.5 whitespace-nowrap">
                            <a href="{{ route('admin.settlements.show', $s) }}" class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded text-[11px] font-semibold transition">
                                Console
                            </a>

                            @if($s->status->value === 'pending')
                                <form method="POST" action="{{ route('admin.settlements.approve', $s) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-[11px] font-semibold shadow-sm transition">
                                        Approve
                                    </button>
                                </form>
                            @elseif($s->status->value === 'processing')
                                <button 
                                    @click="selectedSettlement = {{ json_encode($s) }}; openPayModal = true" 
                                    class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-[11px] font-semibold shadow-sm transition"
                                >
                                    Pay UTR
                                </button>
                            @endif

                            <a href="{{ route('admin.settlements.pdf', $s) }}" class="p-1 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition inline-block" title="Download PDF Statement">
                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            No seller settlements found matching your query.
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

    <!-- Manual Generate Settlement Modal -->
    <div x-show="openGenerateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div @click.away="openGenerateModal = false" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-2">Generate Settlement Batch</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                Delivered orders past the platform return window ({{ setting('return_window_days', 7) }} days) will be settled with commission deductions and COD cash reconciliation.
            </p>

            <form method="POST" action="{{ route('admin.settlements.generate') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Target Seller (Optional)</label>
                    <select name="seller_id" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                        <option value="">All Active Sellers</option>
                        @foreach($sellers as $s)
                            <option value="{{ $s->id }}">{{ $s->store_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Cycle Period End Date</label>
                    <input type="date" name="period_end" value="{{ now()->toDateString() }}" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="openGenerateModal = false" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm">
                        Generate Now
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mark Paid UTR Modal -->
    <div x-show="openPayModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div @click.away="openPayModal = false" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1">Confirm Bank Disbursement</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                Record bank NEFT/RTGS/IMPS transaction reference (UTR) for Settlement <strong class="text-indigo-600 dark:text-indigo-400" x-text="selectedSettlement?.settlement_number"></strong>.
            </p>

            <form :action="'/admin/settlements/' + selectedSettlement?.id + '/pay'" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Bank UTR Reference Number</label>
                    <input type="text" name="reference" required x-model="utrRef" placeholder="e.g. UTR829104810294" class="w-full text-xs font-mono rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="openPayModal = false" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-semibold shadow-sm">
                        Confirm Paid
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
