@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Refunds Audit Ledger'])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        title="Refunds Audit & Reconciliations" 
        subtitle="Gateway reversed transactions, customer refund history, and ledger reversals."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Payments' => route('admin.payments.index'), 'Refunds' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                {{ format_currency($metrics['total_refunded']) }} Refunded
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.payments.index') }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                    <span>&larr; Back to Payments</span>
                </a>
                <a href="{{ route('admin.payments.refunds.export', request()->query()) }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Export CSV</span>
                </a>
            </div>
        </x-slot>
    </x-page-header>

    <!-- KPI Metrics -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-rose-600 dark:text-rose-400 mb-1">Total Refunded Volume</div>
            <div class="text-2xl font-black font-mono text-rose-600 dark:text-rose-400">{{ format_currency($metrics['total_refunded']) }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-slate-500 mb-1">Refunds Recorded</div>
            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ number_format($metrics['total_refunds_count']) }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 mb-1">Settled / Processed</div>
            <div class="text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">{{ number_format($metrics['processed_count']) }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-amber-500 mb-1">Pending Gateways</div>
            <div class="text-2xl font-black font-mono text-amber-500">{{ number_format($metrics['pending_refunds_count']) }}</div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <form method="GET" action="{{ route('admin.payments.refunds') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <!-- Search -->
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Search</label>
                <div class="relative">
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ request('search') }}" 
                        placeholder="Search order #, refund ID, reason, customer..." 
                        class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                    >
                    @if(request('search'))
                        <a href="{{ route('admin.payments.refunds', array_merge(request()->except('search'), ['page' => 1])) }}" class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 text-xs">
                            &times;
                        </a>
                    @endif
                </div>
            </div>

            <!-- Status Dropdown -->
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Status</label>
                <select name="status" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                    <option value="all">All Statuses</option>
                    @foreach(\App\Enums\RefundStatus::cases() as $st)
                        <option value="{{ $st->value }}" {{ request('status') === $st->value ? 'selected' : '' }}>
                            {{ $st->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- From Date -->
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">From Date</label>
                <input 
                    type="date" 
                    name="from_date" 
                    value="{{ request('from_date') }}" 
                    class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                >
            </div>

            <!-- To Date + Action Buttons -->
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">To Date</label>
                <div class="flex items-center space-x-2">
                    <input 
                        type="date" 
                        name="to_date" 
                        value="{{ request('to_date') }}" 
                        class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                    >
                    <button type="submit" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                        Filter
                    </button>
                    @if(request()->hasAny(['search', 'status', 'from_date', 'to_date']))
                        <a href="{{ route('admin.payments.refunds') }}" class="px-2.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-xs font-medium transition" title="Reset Filters">
                            Reset
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <!-- Refunds Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Refund ID</th>
                        <th class="px-6 py-3.5">Gateway Reference</th>
                        <th class="px-6 py-3.5">Parent Order #</th>
                        <th class="px-6 py-3.5">Customer</th>
                        <th class="px-6 py-3.5">Reason</th>
                        <th class="px-6 py-3.5 text-right">Amount</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5">Processed At</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($refunds as $r)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4 font-mono font-bold text-slate-900 dark:text-white">
                            #{{ $r->id }}
                            @if($r->payment_id)
                                <div class="text-[10px] text-slate-400 font-normal">
                                    Payment: <a href="{{ route('admin.payments.show', $r->payment_id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">#{{ $r->payment_id }}</a>
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 font-mono text-slate-500 select-all">{{ $r->gateway_refund_id ?? 'N/A' }}</td>
                        <td class="px-6 py-4 font-mono">
                            @if($r->payment?->order)
                                <a href="{{ route('admin.orders.show', $r->payment->order) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold">
                                    {{ $r->payment->order->order_number }}
                                </a>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-800 dark:text-slate-200">{{ $r->payment?->order?->customer?->user?->name ?? 'N/A' }}</div>
                        </td>
                        <td class="px-6 py-4 text-slate-700 dark:text-slate-300 max-w-xs truncate" title="{{ $r->reason }}">
                            {{ $r->reason }}
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-black text-rose-600 dark:text-rose-400 text-sm">
                            - {{ format_currency((float)$r->amount) }}
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase
                                @if($r->status->value === 'processed') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400
                                @elseif($r->status->value === 'pending') bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400
                                @else bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400
                                @endif
                            ">
                                {{ $r->status->label() }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-500 font-mono text-[11px] whitespace-nowrap">
                            {{ $r->created_at->format('d M Y, h:i A') }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if($r->payment)
                                <a href="{{ route('admin.payments.show', $r->payment) }}" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold transition">
                                    View Payment &rarr;
                                </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                            <div class="flex flex-col items-center justify-center space-y-2">
                                <svg class="w-8 h-8 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span>No refund records found matching the selected criteria.</span>
                                @if(request()->hasAny(['search', 'status', 'from_date', 'to_date']))
                                    <a href="{{ route('admin.payments.refunds') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Clear all filters</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 dark:border-slate-800">
            <x-pagination :paginator="$refunds" />
        </div>
    </div>

</div>
@endsection
