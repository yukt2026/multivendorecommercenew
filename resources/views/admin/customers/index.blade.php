@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Customers Directory'])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        title="Customers Directory" 
        subtitle="Manage customer user accounts, order histories, account states, and security access."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Customers' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                {{ number_format($metrics['total_customers']) }} Registered
            </span>
        </x-slot>

        <x-slot name="actions">
            <a href="{{ route('admin.customers.export') }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Export CSV</span>
            </a>
        </x-slot>
    </x-page-header>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mb-1">Total Customers</div>
            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ number_format($metrics['total_customers']) }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold mb-1">Active Accounts</div>
            <div class="text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">{{ number_format($metrics['active_customers']) }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-rose-600 dark:text-rose-400 font-semibold mb-1">Blocked Accounts</div>
            <div class="text-2xl font-black font-mono text-rose-600 dark:text-rose-400">{{ number_format($metrics['blocked_customers']) }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold mb-1">New This Month</div>
            <div class="text-2xl font-black font-mono text-indigo-600 dark:text-indigo-400">{{ number_format($metrics['new_this_month']) }}</div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="flex items-center space-x-2">
            @php $currentStatus = request('status', 'all'); @endphp
            <a href="{{ route('admin.customers.index', ['status' => 'all']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $currentStatus === 'all' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">All</a>
            <a href="{{ route('admin.customers.index', ['status' => 'active']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $currentStatus === 'active' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">Active</a>
            <a href="{{ route('admin.customers.index', ['status' => 'blocked']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $currentStatus === 'blocked' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">Blocked</a>
        </div>

        <form method="GET" action="{{ route('admin.customers.index') }}" class="flex items-center space-x-2">
            <input type="hidden" name="status" value="{{ $currentStatus }}">
            <input 
                type="text" 
                name="search" 
                value="{{ request('search') }}" 
                placeholder="Search name, email, phone..." 
                class="text-xs px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500 w-60"
            >
            <button type="submit" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-medium">Search</button>
        </form>
    </div>

    <!-- Customers Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Customer</th>
                        <th class="px-6 py-3.5">Contact Details</th>
                        <th class="px-6 py-3.5">Primary Location</th>
                        <th class="px-6 py-3.5 text-center">Status</th>
                        <th class="px-6 py-3.5">Registered</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($customers as $c)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-full bg-indigo-600/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs uppercase">
                                    {{ substr($c->user?->name ?? 'U', 0, 1) }}
                                </div>
                                <div>
                                    <a href="{{ route('admin.customers.show', $c) }}" class="font-bold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400">
                                        {{ $c->user?->name ?? 'Guest Customer' }}
                                    </a>
                                    <div class="text-[10px] text-slate-400 font-mono">ID #{{ $c->id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                            <div>{{ $c->user?->email }}</div>
                            <div class="text-[11px] text-slate-400 font-mono">{{ $c->user?->phone ?? 'No phone' }}</div>
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                            @if($c->defaultAddress)
                                <div>{{ $c->defaultAddress->city }}, {{ $c->defaultAddress->state }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $c->defaultAddress->pincode }}</div>
                            @else
                                <span class="text-slate-400">No default address</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ ($c->user?->status ?? 'active') === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400' }}">
                                {{ $c->user?->status ?? 'active' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-500 font-mono text-[11px]">
                            {{ $c->created_at->format('d M Y') }}
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <a href="{{ route('admin.customers.show', $c) }}" class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded text-[11px] font-semibold transition">
                                Profile
                            </a>
                            <form method="POST" action="{{ route('admin.customers.toggle_status', $c) }}" class="inline" onsubmit="return confirm('Change status for this customer?');">
                                @csrf
                                <button type="submit" class="px-2.5 py-1 rounded text-[11px] font-semibold transition {{ ($c->user?->status ?? 'active') === 'active' ? 'bg-rose-600 hover:bg-rose-500 text-white' : 'bg-emerald-600 hover:bg-emerald-500 text-white' }}">
                                    {{ ($c->user?->status ?? 'active') === 'active' ? 'Block' : 'Unblock' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-500">No customers found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
            {{ $customers->links() }}
        </div>
    </div>

</div>
@endsection
