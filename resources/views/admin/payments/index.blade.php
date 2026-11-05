@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Payments & Transactions'])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        title="Payments &amp; Transactions Ledger" 
        subtitle="Gateway transaction records, captured orders, doorstep COD remittances, and refund tracking."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Payments' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                {{ format_currency($metrics['total_volume']) }} Volume
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.payments.refunds') }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                    <span>Refunds Audit</span>
                </a>
                <a href="{{ route('admin.payments.export') }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Export CSV</span>
                </a>
            </div>
        </x-slot>
    </x-page-header>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-slate-500 mb-1">Captured Volume</div>
            <div class="text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">{{ format_currency($metrics['total_volume']) }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-slate-500 mb-1">Successful Payments</div>
            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ number_format($metrics['paid_count']) }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-rose-600 dark:text-rose-400 mb-1 font-semibold">Total Refunded</div>
            <div class="text-2xl font-black font-mono text-rose-600 dark:text-rose-400">{{ format_currency($metrics['refunded_amount']) }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-amber-500 mb-1 font-semibold">Cash On Delivery (COD)</div>
            <div class="text-2xl font-black font-mono text-amber-500">{{ number_format($metrics['cod_count']) }} Orders</div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="flex items-center space-x-2">
            @php $gateway = request('gateway', 'all'); @endphp
            <a href="{{ route('admin.payments.index', ['gateway' => 'all']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $gateway === 'all' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">All Gateways</a>
            <a href="{{ route('admin.payments.index', ['gateway' => 'razorpay']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $gateway === 'razorpay' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">Razorpay</a>
            <a href="{{ route('admin.payments.index', ['gateway' => 'cashfree']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $gateway === 'cashfree' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">Cashfree</a>
            <a href="{{ route('admin.payments.index', ['gateway' => 'cod']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $gateway === 'cod' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">COD</a>
        </div>

        <form method="GET" action="{{ route('admin.payments.index') }}" class="flex items-center space-x-2">
            <input 
                type="text" 
                name="search" 
                value="{{ request('search') }}" 
                placeholder="Search order #, pay ID..." 
                class="text-xs px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500 w-60"
            >
            <button type="submit" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-medium">Search</button>
        </form>
    </div>

    <!-- Payments Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Payment ID</th>
                        <th class="px-6 py-3.5">Parent Order #</th>
                        <th class="px-6 py-3.5">Customer</th>
                        <th class="px-6 py-3.5">Gateway &amp; Method</th>
                        <th class="px-6 py-3.5 text-right">Amount</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5">Date</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($payments as $p)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4 font-mono font-bold text-slate-900 dark:text-white">
                            <a href="{{ route('admin.payments.show', $p) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                #{{ $p->id }}
                            </a>
                            @if($p->gateway_payment_id)
                                <div class="text-[10px] text-slate-400 font-mono">{{ $p->gateway_payment_id }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 font-mono">
                            @if($p->order)
                                <a href="{{ route('admin.orders.show', $p->order) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {{ $p->order->order_number }}
                                </a>
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-800 dark:text-slate-200">{{ $p->order?->customer?->user?->name ?? 'Guest' }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-semibold uppercase text-slate-900 dark:text-white">{{ $p->gateway }}</div>
                            <div class="text-[10px] text-slate-400 uppercase font-mono">{{ $p->method?->value ?? (string) $p->method }}</div>
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-bold text-slate-900 dark:text-white">
                            {{ format_currency((float)$p->amount) }}
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase
                                @if($p->status->value === 'paid') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400
                                @elseif($p->status->value === 'pending') bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400
                                @elseif($p->status->value === 'refunded') bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-400
                                @else bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400
                                @endif
                            ">
                                {{ $p->status->label() }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-500 font-mono text-[11px]">
                            {{ $p->created_at->format('d M Y, h:i A') }}
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <a href="{{ route('admin.payments.show', $p) }}" class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded text-[11px] font-semibold transition">
                                Details
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-slate-400">No payment transactions found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
            {{ $payments->links() }}
        </div>
    </div>

</div>
@endsection
