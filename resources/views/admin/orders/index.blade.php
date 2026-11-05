@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Global Orders Registry'])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        title="Orders &amp; Fulfilment Central" 
        subtitle="Global command registry monitoring parent orders, split packages, logistics SLAs, and payment states."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Orders' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                {{ number_format($metrics['total_orders']) }} Total Orders
            </span>
        </x-slot>
    </x-page-header>

    <!-- Metrics Cards Strip -->
    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Gross Merchandise Value</div>
            <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400">
                {{ format_currency($metrics['total_revenue']) }}
            </div>
            <div class="text-[11px] text-slate-400">Paid marketplace volume</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">In Fulfilment / Processing</div>
            <div class="text-2xl font-black text-amber-500">
                {{ number_format($metrics['processing']) }}
            </div>
            <div class="text-[11px] text-slate-400">Under store dispatch SLA</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Delivered Successfully</div>
            <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                {{ number_format($metrics['delivered']) }}
            </div>
            <div class="text-[11px] text-slate-400">Completed deliveries</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Cancelled / Refunded</div>
            <div class="text-2xl font-black text-rose-600 dark:text-rose-400">
                {{ number_format($metrics['cancelled']) }}
            </div>
            <div class="text-[11px] text-slate-400">Customer or store aborted</div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-xs">
        <form method="GET" action="{{ route('admin.orders.index') }}" class="flex flex-wrap items-center gap-3">
            <!-- Search -->
            <div class="relative flex-1 min-w-[220px]">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ $search }}" 
                    placeholder="Search order #, package #, customer name/email..." 
                    class="w-full text-xs font-medium pl-9 pr-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <!-- Status Filter -->
            <select name="status" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                <option value="all">All Order Statuses</option>
                <option value="placed" {{ $status === 'placed' ? 'selected' : '' }}>Placed</option>
                <option value="processing" {{ $status === 'processing' ? 'selected' : '' }}>Processing</option>
                <option value="ready_for_pickup" {{ $status === 'ready_for_pickup' ? 'selected' : '' }}>Ready for Pickup</option>
                <option value="out_for_delivery" {{ $status === 'out_for_delivery' ? 'selected' : '' }}>Out for Delivery</option>
                <option value="delivered" {{ $status === 'delivered' ? 'selected' : '' }}>Delivered</option>
                <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>

            <!-- Payment Status Filter -->
            <select name="payment_status" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                <option value="all">All Payments</option>
                <option value="paid" {{ $paymentStatus === 'paid' ? 'selected' : '' }}>Paid</option>
                <option value="pending" {{ $paymentStatus === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="cod_pending" {{ $paymentStatus === 'cod_pending' ? 'selected' : '' }}>COD Pending</option>
                <option value="refunded" {{ $paymentStatus === 'refunded' ? 'selected' : '' }}>Refunded</option>
                <option value="failed" {{ $paymentStatus === 'failed' ? 'selected' : '' }}>Failed</option>
            </select>

            <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shrink-0">
                Apply Filters
            </button>

            @if($search || $status !== 'all' || $paymentStatus !== 'all')
                <a href="{{ route('admin.orders.index') }}" class="text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 self-center">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Orders Table -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
        @if($orders->isEmpty())
            <div class="p-12 text-center space-y-3">
                <div class="w-16 h-16 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center text-3xl">
                    📦
                </div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">No orders found</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">Try clearing your search query or filters.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-[11px] font-bold uppercase text-slate-400 tracking-wider">
                        <tr>
                            <th class="p-4">Order # &amp; Date</th>
                            <th class="p-4">Customer</th>
                            <th class="p-4">Packages</th>
                            <th class="p-4">Total Amount</th>
                            <th class="p-4">Payment</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        @foreach($orders as $ord)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                                <td class="p-4">
                                    <div class="font-bold text-slate-900 dark:text-white">
                                        <a href="{{ route('admin.orders.show', $ord) }}" class="hover:text-indigo-600 transition">
                                            #{{ $ord->order_number }}
                                        </a>
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                        {{ $ord->created_at->format('d M Y, h:i A') }}
                                    </div>
                                </td>

                                <td class="p-4">
                                    <div class="font-bold text-slate-900 dark:text-white">
                                        {{ $ord->address_snapshot['name'] ?? $ord->customer?->user?->name ?? 'Shopper' }}
                                    </div>
                                    <div class="text-[11px] text-slate-400 truncate max-w-[180px]">
                                        {{ $ord->customer?->user?->email ?? $ord->address_snapshot['phone'] ?? 'N/A' }}
                                    </div>
                                </td>

                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        {{ $ord->fulfilmentOrders->count() }} package(s)
                                    </span>
                                </td>

                                <td class="p-4">
                                    <div class="font-bold text-slate-900 dark:text-white">
                                        {{ format_currency((float) $ord->total) }}
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        Subtotal: {{ format_currency((float) $ord->subtotal) }}
                                    </div>
                                </td>

                                <td class="p-4">
                                    <div class="font-semibold text-slate-800 dark:text-slate-200 uppercase text-[11px]">
                                        {{ $ord->payment_method->value ?? 'Online' }}
                                    </div>
                                    <span class="inline-block mt-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $ord->payment_status === \App\Enums\PaymentStatus::PAID ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400' }}">
                                        {{ strtoupper(str_replace('_', ' ', $ord->payment_status->value ?? 'Pending')) }}
                                    </span>
                                </td>

                                <td class="p-4">
                                    <x-order-status-badge :status="$ord->status" size="sm" />
                                </td>

                                <td class="p-4 text-right">
                                    <a 
                                        href="{{ route('admin.orders.show', $ord) }}" 
                                        class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition inline-block"
                                    >
                                        Inspect &rarr;
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
