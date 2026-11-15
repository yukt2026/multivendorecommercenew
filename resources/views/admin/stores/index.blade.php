@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Stores & Outlets Network'])

@section('content')
<div class="space-y-6">

    <!-- Page Header Component -->
    <x-page-header 
        title="Physical & Dark Stores Network" 
        subtitle="Manage merchant retail outlets, 10-minute dark stores, delivery radiuses, and store operating states."
        :breadcrumbs="['Command Central' => route('admin.dashboard'), 'Stores Directory' => '#']"
    >
        <x-slot name="badge">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                <span class="w-2 h-2 rounded-full bg-emerald-500 mr-2 animate-pulse"></span>
                {{ $metrics['total_stores'] }} Outlets
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <a 
                    href="{{ route('admin.stores.map') }}" 
                    class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs flex items-center space-x-1.5 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    <span>Interactive Map</span>
                </a>
                <a 
                    href="{{ route('admin.stores.export', request()->query()) }}" 
                    class="px-3.5 py-2 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold flex items-center space-x-1.5 shadow-xs transition"
                >
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Export CSV</span>
                </a>
            </div>
        </x-slot>
    </x-page-header>

    <!-- 4 KPI Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Total Stores</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ $metrics['total_stores'] }}</span>
                <span class="text-[11px] font-medium text-slate-500">{{ $metrics['onestall_owned'] }} OneStall Hubs</span>
            </div>
        </div>
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 block mb-1">Active &amp; Live</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">{{ $metrics['active_stores'] }}</span>
                <span class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">Marketplace Ready</span>
            </div>
        </div>
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-500 block mb-1">Quick Commerce (10-30m)</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-black font-mono text-amber-500">{{ $metrics['quick_stores'] }}</span>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">Express Delivery</span>
            </div>
        </div>
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 block mb-1">Suspended Stores</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-black font-mono text-rose-600 dark:text-rose-400">{{ $metrics['suspended_stores'] }}</span>
                <span class="text-[11px] font-semibold text-rose-600">Hidden from Buyers</span>
            </div>
        </div>
    </div>

    <!-- Filters Bar Card -->
    <x-card padding="p-4 sm:p-5">
        <form method="GET" action="{{ route('admin.stores.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <!-- Search input -->
                <div class="lg:col-span-2">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                        Search Store / Location
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <input 
                            type="text" 
                            name="search" 
                            value="{{ request('search') }}" 
                            placeholder="Store name, slug, merchant, city, pincode..." 
                            class="w-full pl-9 pr-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                        />
                    </div>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                        Operating Status
                    </label>
                    <select 
                        name="status" 
                        class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                    >
                        <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>All Statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active &amp; Live</option>
                        <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                    </select>
                </div>

                <!-- Fulfillment Mode Filter -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                        Fulfillment Model
                    </label>
                    <select 
                        name="quick_only" 
                        class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                    >
                        <option value="">All Fulfillment Models</option>
                        <option value="1" {{ request('quick_only') === '1' ? 'selected' : '' }}>⚡ Quick Commerce (10-30m)</option>
                        <option value="0" {{ request('quick_only') === '0' ? 'selected' : '' }}>Standard Delivery Only</option>
                    </select>
                </div>

                <!-- Action buttons -->
                <div class="flex items-end space-x-2">
                    <button 
                        type="submit" 
                        class="flex-1 py-2 px-3 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition-all"
                    >
                        Filter
                    </button>
                    @if(request()->hasAny(['search', 'status', 'quick_only', 'city']))
                        <a 
                            href="{{ route('admin.stores.index') }}" 
                            class="py-2 px-3 text-xs font-semibold rounded-xl bg-slate-200 dark:bg-slate-750 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-all"
                            title="Reset all filters"
                        >
                            Reset
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </x-card>

    <!-- Stores Table Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-50/70 dark:bg-slate-950/40 text-slate-400 dark:text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200/80 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-5 py-3.5">Store Outlet</th>
                        <th class="px-5 py-3.5">Merchant / Owner</th>
                        <th class="px-5 py-3.5">City &amp; Coverage Radius</th>
                        <th class="px-5 py-3.5">Fulfillment Model</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @forelse($stores as $s)
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-850/50 transition">
                        <td class="px-5 py-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-9 h-9 rounded-xl {{ $s->accepts_quick_commerce ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800/60' : 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800/60' }} flex items-center justify-center font-bold text-sm shrink-0">
                                    {{ $s->accepts_quick_commerce ? '⚡' : '🏪' }}
                                </div>
                                <div>
                                    <div class="flex items-center space-x-1.5">
                                        <a href="{{ route('admin.stores.show', $s) }}" class="font-bold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                                            {{ $s->name }}
                                        </a>
                                        @if($s->is_onestall_owned)
                                            <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">Direct Hub</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                        /store/{{ $s->slug }} &bull; <span class="text-slate-400 font-mono">#{{ $s->id }}</span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            @if($s->seller)
                                <a href="{{ route('admin.sellers.show', $s->seller) }}" class="font-medium text-slate-800 dark:text-slate-200 hover:text-indigo-600 transition">
                                    {{ $s->seller->business_name }}
                                </a>
                                <div class="text-[11px] text-slate-400">{{ $s->seller->legal_name }} &bull; Seller #{{ $s->seller_id }}</div>
                            @else
                                <span class="text-slate-400">Unassigned Merchant</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-slate-600 dark:text-slate-300">
                            <div class="font-semibold text-slate-800 dark:text-slate-200">{{ $s->location?->city ?? 'City N/A' }}</div>
                            <div class="text-[11px] text-slate-400">Radius: <strong class="text-indigo-600 dark:text-indigo-400 font-mono">{{ $s->effectiveDeliveryRadius() }} km</strong></div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex flex-col space-y-1">
                                @if($s->accepts_quick_commerce)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950 shadow-xs w-max">
                                        ⚡ 10-30m Quick
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 w-max">
                                        Standard Delivery
                                    </span>
                                @endif
                                @if($s->accepts_pickup)
                                    <span class="text-[10px] font-semibold text-sky-600 dark:text-sky-400">
                                        ✓ Customer Pickup Enabled
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $s->status->value === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400' }}">
                                {{ $s->status->label() }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right space-x-1.5 whitespace-nowrap">
                            <a 
                                href="{{ route('admin.stores.show', $s) }}" 
                                class="inline-flex items-center px-2.5 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-[11px] font-semibold transition"
                            >
                                Console &rarr;
                            </a>
                            <a 
                                href="{{ route('store.show', $s->slug) }}" 
                                target="_blank" 
                                class="inline-flex items-center px-2 py-1 bg-slate-50 dark:bg-slate-850 hover:bg-slate-100 text-slate-500 rounded-lg text-[11px] transition"
                                title="Open Live Storefront"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>

                            @if($s->status->value === 'suspended')
                                <form method="POST" action="{{ route('admin.stores.approve', $s) }}" class="inline">
                                    @csrf
                                    <button 
                                        type="submit" 
                                        onclick="return confirm('Reactivate this store outlet?')"
                                        class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[11px] font-bold transition shadow-xs"
                                    >
                                        Activate
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.stores.suspend', $s) }}" class="inline" onsubmit="return confirm('Suspend this store outlet? It will be hidden from public buyers.');">
                                    @csrf
                                    <button 
                                        type="submit" 
                                        class="px-2.5 py-1 bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 rounded-lg text-[11px] font-bold transition"
                                    >
                                        Suspend
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <x-empty-state 
                                title="No store outlets match criteria" 
                                description="Try adjusting search query, status, or fulfillment filters."
                            />
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-800/80">
            <x-pagination :paginator="$stores" />
        </div>
    </div>

</div>
@endsection
