@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Stores Management'])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        title="Physical &amp; Dark Stores Network" 
        subtitle="Manage merchant retail outlets, 10-minute dark stores, delivery radiuses, and store operating states."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Stores' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                {{ $metrics['total_stores'] }} Outlets
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.stores.map') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm flex items-center space-x-1.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    <span>Interactive Map View</span>
                </a>
                <a href="{{ route('admin.stores.export') }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Export CSV</span>
                </a>
            </div>
        </x-slot>
    </x-page-header>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-slate-500 mb-1">Total Stores</div>
            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ $metrics['total_stores'] }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-emerald-600 dark:text-emerald-400 mb-1 font-semibold">Active &amp; Live</div>
            <div class="text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">{{ $metrics['active_stores'] }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-amber-500 mb-1 font-semibold">Quick Commerce (10-30m)</div>
            <div class="text-2xl font-black font-mono text-amber-500">{{ $metrics['quick_stores'] }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-rose-600 dark:text-rose-400 mb-1 font-semibold">Suspended Stores</div>
            <div class="text-2xl font-black font-mono text-rose-600 dark:text-rose-400">{{ $metrics['suspended_stores'] }}</div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="flex items-center space-x-2">
            @php $currentStatus = request('status', 'all'); @endphp
            <a href="{{ route('admin.stores.index', ['status' => 'all']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $currentStatus === 'all' && !request('quick_only') ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">All</a>
            <a href="{{ route('admin.stores.index', ['status' => 'active']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $currentStatus === 'active' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">Active</a>
            <a href="{{ route('admin.stores.index', ['status' => 'suspended']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $currentStatus === 'suspended' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">Suspended</a>
            <a href="{{ route('admin.stores.index', ['quick_only' => 1]) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ request('quick_only') ? 'bg-amber-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">Quick Commerce</a>
        </div>

        <form method="GET" action="{{ route('admin.stores.index') }}" class="flex items-center space-x-2">
            <input 
                type="text" 
                name="search" 
                value="{{ request('search') }}" 
                placeholder="Search store name, seller..." 
                class="text-xs px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500 w-60"
            >
            <button type="submit" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-medium">Search</button>
        </form>
    </div>

    <!-- Stores Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Store Name</th>
                        <th class="px-6 py-3.5">Merchant / Seller</th>
                        <th class="px-6 py-3.5">Location &amp; Radius</th>
                        <th class="px-6 py-3.5">Fulfillment Mode</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($stores as $s)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">
                            <a href="{{ route('admin.stores.show', $s) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                {{ $s->name }}
                            </a>
                            <div class="text-[10px] text-slate-400 font-mono">/store/{{ $s->slug }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-800 dark:text-slate-200">{{ $s->seller?->business_name ?? 'N/A' }}</div>
                            <div class="text-[10px] text-slate-400">Seller ID #{{ $s->seller_id }}</div>
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                            <div>{{ $s->location?->city ?? 'City N/A' }}</div>
                            <div class="text-[11px] text-slate-400">Radius: <strong class="text-slate-700 dark:text-slate-300">{{ $s->effectiveDeliveryRadius() }} km</strong></div>
                        </td>
                        <td class="px-6 py-4">
                            @if($s->accepts_quick_commerce)
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400">⚡ 10-30m Quick</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">Standard</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $s->status->value === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400' }}">
                                {{ $s->status->label() }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                            <a href="{{ route('admin.stores.show', $s) }}" class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded text-[11px] font-semibold transition">
                                Console
                            </a>

                            @if($s->status->value === 'suspended')
                                <form method="POST" action="{{ route('admin.stores.approve', $s) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-[11px] font-semibold transition shadow-sm">
                                        Activate
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.stores.suspend', $s) }}" class="inline" onsubmit="return confirm('Suspend this store outlet?');">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1 bg-rose-600 hover:bg-rose-500 text-white rounded text-[11px] font-semibold transition shadow-sm">
                                        Suspend
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400">No store outlets found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
            {{ $stores->links() }}
        </div>
    </div>

</div>
@endsection
