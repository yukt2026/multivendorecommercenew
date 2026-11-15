@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Payments & Transactions'])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        title="Payments & Transactions Ledger" 
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
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Refunds Audit</span>
                </a>
                <a href="{{ route('admin.payments.export', request()->query()) }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Export CSV</span>
                </a>
            </div>
        </x-slot>
    </x-page-header>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-slate-500 mb-1">Captured Volume</div>
            <div class="text-xl sm:text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400 truncate">{{ format_currency($metrics['total_volume']) }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-slate-500 mb-1">Paid Count</div>
            <div class="text-xl sm:text-2xl font-black font-mono text-slate-900 dark:text-white">{{ number_format($metrics['paid_count']) }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-rose-600 dark:text-rose-400 mb-1">Total Refunded</div>
            <div class="text-xl sm:text-2xl font-black font-mono text-rose-600 dark:text-rose-400 truncate">{{ format_currency($metrics['refunded_amount']) }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-amber-500 mb-1">Cash On Delivery</div>
            <div class="text-xl sm:text-2xl font-black font-mono text-amber-500">{{ number_format($metrics['cod_count']) }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-amber-600 dark:text-amber-400 mb-1">Pending Capture</div>
            <div class="text-xl sm:text-2xl font-black font-mono text-amber-600 dark:text-amber-400">{{ number_format($metrics['pending_count']) }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-slate-400 mb-1">Failed Attempts</div>
            <div class="text-xl sm:text-2xl font-black font-mono text-slate-400">{{ number_format($metrics['failed_count']) }}</div>
        </div>
    </div>

    <!-- Filters & Search Form -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <!-- Gateway Pills Row -->
        <div class="flex items-center space-x-2 overflow-x-auto pb-1">
            @php $currentGateway = request('gateway', 'all'); @endphp
            @foreach(['all' => 'All Gateways', 'razorpay' => 'Razorpay', 'cashfree' => 'Cashfree', 'cod' => 'COD'] as $key => $label)
                <a 
                    href="{{ route('admin.payments.index', array_merge(request()->query(), ['gateway' => $key, 'page' => 1])) }}" 
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition {{ $currentGateway === $key ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <!-- Filter Fields -->
        <form method="GET" action="{{ route('admin.payments.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            @if(request('gateway') && request('gateway') !== 'all')
                <input type="hidden" name="gateway" value="{{ request('gateway') }}">
            @endif

            <!-- Search -->
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Search</label>
                <div class="relative">
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ request('search') }}" 
                        placeholder="Search order #, pay ID, customer..." 
                        class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    >
                    @if(request('search'))
                        <a href="{{ route('admin.payments.index', array_merge(request()->except('search'), ['page' => 1])) }}" class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs">
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
                    @foreach(\App\Enums\PaymentStatus::cases() as $st)
                        <option value="{{ $st->value }}" {{ request('status') === $st->value ? 'selected' : '' }}>
                            {{ $st->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Payment Method Dropdown -->
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Method</label>
                <select name="method" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                    <option value="all">All Methods</option>
                    @foreach(\App\Enums\PaymentMethod::cases() as $m)
                        <option value="{{ $m->value }}" {{ request('method') === $m->value ? 'selected' : '' }}>
                            {{ $m->label() }}
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
                    @if(request()->hasAny(['search', 'gateway', 'status', 'method', 'from_date', 'to_date']))
                        <a href="{{ route('admin.payments.index') }}" class="px-2.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-xs font-medium transition" title="Reset Filters">
                            Reset
                        </a>
                    @endif
                </div>
            </div>
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
                                <div class="text-[10px] text-slate-400 font-mono select-all">{{ $p->gateway_payment_id }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 font-mono">
                            @if($p->order)
                                <a href="{{ route('admin.orders.show', $p->order) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold">
                                    {{ $p->order->order_number }}
                                </a>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-800 dark:text-slate-200">{{ $p->order?->customer?->user?->name ?? 'Guest Customer' }}</div>
                            @if($p->order?->customer?->user?->email)
                                <div class="text-[10px] text-slate-400">{{ $p->order->customer->user->email }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-bold uppercase text-slate-900 dark:text-white tracking-wide">{{ $p->gateway }}</div>
                            <div class="text-[10px] text-slate-400 font-medium">
                                {{ $p->method instanceof \App\Enums\PaymentMethod ? $p->method->label() : strtoupper((string)$p->method) }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-black text-sm text-slate-900 dark:text-white">
                            {{ format_currency((float)$p->amount) }}
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase border {{ $p->status instanceof \App\Enums\PaymentStatus ? $p->status->badgeClass() : 'bg-slate-100 text-slate-800 border-slate-300' }}">
                                {{ $p->status instanceof \App\Enums\PaymentStatus ? $p->status->label() : (string)$p->status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-500 font-mono text-[11px] whitespace-nowrap">
                            {{ $p->created_at->format('d M Y, h:i A') }}
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <a href="{{ route('admin.payments.show', $p) }}" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold transition">
                                Details &rarr;
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-slate-400">
                            <div class="flex flex-col items-center justify-center space-y-2">
                                <svg class="w-8 h-8 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                <span>No payment transactions found matching the selected criteria.</span>
                                @if(request()->hasAny(['search', 'gateway', 'status', 'method', 'from_date', 'to_date']))
                                    <a href="{{ route('admin.payments.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Clear all filters</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 dark:border-slate-800">
            <x-pagination :paginator="$payments" />
        </div>
    </div>

</div>
@endsection
