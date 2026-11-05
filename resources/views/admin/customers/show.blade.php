@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Customer: ' . ($customer->user?->name ?? 'Details')])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        :title="$customer->user?->name ?? 'Customer Profile'" 
        :subtitle="'Customer Account #' . $customer->id . ' • Member since ' . $customer->created_at->format('d M Y')"
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Customers' => route('admin.customers.index'), ($customer->user?->name ?? 'Profile') => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase {{ ($customer->user?->status ?? 'active') === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400' }}">
                {{ $customer->user?->status ?? 'active' }}
            </span>
        </x-slot>

        <x-slot name="actions">
            <form method="POST" action="{{ route('admin.customers.toggle_status', $customer) }}" class="inline">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold text-white shadow-sm transition {{ ($customer->user?->status ?? 'active') === 'active' ? 'bg-rose-600 hover:bg-rose-500' : 'bg-emerald-600 hover:bg-emerald-500' }}">
                    {{ ($customer->user?->status ?? 'active') === 'active' ? 'Block Account' : 'Unblock Account' }}
                </button>
            </form>
        </x-slot>
    </x-page-header>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-slate-500 mb-1">Lifetime Spend</div>
            <div class="text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">{{ format_currency($lifetimeSpend) }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-slate-500 mb-1">Total Orders Placed</div>
            <div class="text-2xl font-black font-mono text-indigo-600 dark:text-indigo-400">{{ $ordersCount }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-slate-500 mb-1">Saved Delivery Addresses</div>
            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ $customer->addresses->count() }}</div>
        </div>
    </div>

    <!-- Contact & Addresses -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white">Profile &amp; Authentication</h3>
            <div class="text-xs space-y-2 text-slate-600 dark:text-slate-300">
                <div><span class="text-slate-400 w-24 inline-block">Full Name:</span> <strong>{{ $customer->user?->name }}</strong></div>
                <div><span class="text-slate-400 w-24 inline-block">Email:</span> {{ $customer->user?->email }}</div>
                <div><span class="text-slate-400 w-24 inline-block">Phone:</span> {{ $customer->user?->phone ?? 'Not provided' }}</div>
                <div><span class="text-slate-400 w-24 inline-block">Gender:</span> {{ ucfirst($customer->gender ?? 'Unspecified') }}</div>
                <div><span class="text-slate-400 w-24 inline-block">Date of Birth:</span> {{ $customer->dob ? $customer->dob->format('d M Y') : 'Not set' }}</div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white">Saved Delivery Addresses</h3>
            <div class="space-y-2">
                @forelse($customer->addresses as $addr)
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 text-xs">
                        <div class="flex items-center justify-between font-bold text-slate-900 dark:text-white mb-1">
                            <span>{{ $addr->name }} ({{ $addr->phone }})</span>
                            @if($addr->is_default)
                                <span class="px-2 py-0.5 rounded text-[10px] bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 font-bold uppercase">Default</span>
                            @endif
                        </div>
                        <div class="text-slate-500">{{ $addr->line1 }}, {{ $addr->city }}, {{ $addr->state }} - {{ $addr->pincode }}</div>
                    </div>
                @empty
                    <div class="text-xs text-slate-400">No saved addresses on file.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Order History -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Order History ({{ $customer->orders->count() }})</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Order #</th>
                        <th class="px-6 py-3.5">Date</th>
                        <th class="px-6 py-3.5">Payment</th>
                        <th class="px-6 py-3.5">Order Status</th>
                        <th class="px-6 py-3.5 text-right">Total</th>
                        <th class="px-6 py-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($customer->orders as $o)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-3.5 font-mono font-bold text-slate-900 dark:text-white">
                            <a href="{{ route('admin.orders.show', $o) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $o->order_number }}
                            </a>
                        </td>
                        <td class="px-6 py-3.5 text-slate-500 font-mono text-[11px]">{{ $o->created_at->format('d M Y, h:i A') }}</td>
                        <td class="px-6 py-3.5">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $o->payment_status->value === 'paid' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400' }}">
                                {{ $o->payment_status->value }}
                            </span>
                        </td>
                        <td class="px-6 py-3.5">
                            <x-order-status-badge :status="$o->status" />
                        </td>
                        <td class="px-6 py-3.5 text-right font-mono font-bold text-slate-900 dark:text-white">
                            {{ format_currency((float)$o->total) }}
                        </td>
                        <td class="px-6 py-3.5 text-right">
                            <a href="{{ route('admin.orders.show', $o) }}" class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded text-[11px] font-semibold transition">
                                View
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-slate-400">No orders placed yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
