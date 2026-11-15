@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Global Inventory Overview'])

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <x-page-header 
        title="Global Marketplace Inventory" 
        subtitle="Read-only platform-wide inventory health audit, dark store balances, and seller warehouse levels."
        :breadcrumbs="['Command Central' => route('admin.dashboard'), 'Global Inventory' => '#']"
    >
        <x-slot name="badge">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                <span class="w-2 h-2 rounded-full bg-emerald-500 mr-2 animate-pulse"></span>
                {{ number_format($metrics['total_items']) }} Tracked Allocations
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <a 
                    href="{{ route('admin.inventory.export', request()->query()) }}" 
                    class="px-3.5 py-2 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold flex items-center space-x-1.5 shadow-xs transition"
                >
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Export CSV</span>
                </a>
                <a 
                    href="{{ route('admin.inventory.sync_logs') }}" 
                    class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-xs flex items-center space-x-1.5 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>View Sync Logs &rarr;</span>
                </a>
            </div>
        </x-slot>
    </x-page-header>

    <!-- 4 KPI Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs border-l-4 border-l-indigo-500">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Tracked SKU Allocations</span>
            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ number_format($metrics['total_items']) }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Across all locations</div>
        </div>

        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs border-l-4 border-l-emerald-500">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Total Physical Units</span>
            <div class="text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">{{ number_format($metrics['total_on_hand']) }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Global on-hand inventory</div>
        </div>

        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs border-l-4 border-l-amber-500">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Cart Locked Units</span>
            <div class="text-2xl font-black font-mono text-amber-500">{{ number_format($metrics['total_reserved']) }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Under checkout reservation</div>
        </div>

        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs border-l-4 border-l-rose-500">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Stocked-Out SKUs</span>
            <div class="text-2xl font-black font-mono text-rose-600 dark:text-rose-400">{{ number_format($metrics['out_of_stock']) }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Zero available balance</div>
        </div>
    </div>

    <!-- Filter Bar Card -->
    <x-card padding="p-4 sm:p-5">
        <form method="GET" action="{{ route('admin.inventory.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                <!-- Search input -->
                <div class="lg:col-span-2">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                        Search Item / Location
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <input 
                            type="text" 
                            name="search" 
                            value="{{ request('search') }}" 
                            placeholder="Product title, SKU, location, or merchant..." 
                            class="w-full pl-9 pr-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                        />
                    </div>
                </div>

                <!-- Seller Filter -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                        Merchant Seller
                    </label>
                    <select 
                        name="seller_id" 
                        class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                    >
                        <option value="">All Merchants</option>
                        @foreach($sellers as $seller)
                            <option value="{{ $seller->id }}" {{ request('seller_id') == $seller->id ? 'selected' : '' }}>
                                {{ $seller->business_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Location Type Filter -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                        Location Type
                    </label>
                    <select 
                        name="location_type" 
                        class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                    >
                        <option value="">All Location Types</option>
                        <option value="store" {{ request('location_type') === 'store' ? 'selected' : '' }}>Physical Retail Store</option>
                        <option value="warehouse" {{ request('location_type') === 'warehouse' ? 'selected' : '' }}>Hub Warehouse</option>
                        <option value="onestall_store" {{ request('location_type') === 'onestall_store' ? 'selected' : '' }}>Dark Store / Instant Hub</option>
                    </select>
                </div>

                <!-- Health Status -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                        Stock Health
                    </label>
                    <select 
                        name="stock_status" 
                        class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                    >
                        <option value="">All Health States</option>
                        <option value="in_stock" {{ request('stock_status') === 'in_stock' ? 'selected' : '' }}>In Stock</option>
                        <option value="low_stock" {{ request('stock_status') === 'low_stock' ? 'selected' : '' }}>Low Stock Warning</option>
                        <option value="out_of_stock" {{ request('stock_status') === 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
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
                    @if(request()->hasAny(['search', 'seller_id', 'location_type', 'stock_status', 'quick_available']))
                        <a 
                            href="{{ route('admin.inventory.index') }}" 
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

    <!-- Global Inventory Table Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-50/70 dark:bg-slate-950/40 text-slate-400 dark:text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200/80 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="py-3.5 px-4">Merchant Business</th>
                        <th class="py-3.5 px-4">Product Title &amp; SKU</th>
                        <th class="py-3.5 px-4">Location &amp; Type</th>
                        <th class="py-3.5 px-3 text-center">On Hand</th>
                        <th class="py-3.5 px-3 text-center">Reserved</th>
                        <th class="py-3.5 px-3 text-center">Available</th>
                        <th class="py-3.5 px-3 text-center">Threshold</th>
                        <th class="py-3.5 px-4 text-right">Quick Eligible</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-medium text-slate-700 dark:text-slate-300">
                    @forelse($inventories as $inv)
                        @php
                            $available = $inv->quantity_on_hand - $inv->quantity_reserved;
                            $isLow = ($available > 0 && $available <= $inv->low_stock_threshold);
                            $isOut = ($available <= 0);
                        @endphp
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-850/50 transition">
                            <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                                @if($inv->location?->seller)
                                    <a href="{{ route('admin.sellers.show', $inv->location->seller) }}" class="hover:text-indigo-600 transition">
                                        {{ $inv->location->seller->business_name }}
                                    </a>
                                @else
                                    <span>OneStall Direct</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900 dark:text-white line-clamp-1">
                                    {{ $inv->variant?->product?->title ?? 'N/A' }}
                                </div>
                                <div class="text-[10px] font-mono text-indigo-600 dark:text-indigo-400 mt-0.5">
                                    {{ $inv->variant?->sku ?? 'No SKU' }}
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="text-slate-900 dark:text-white font-medium">{{ $inv->location?->name ?? 'Default Location' }}</div>
                                <span class="inline-block px-1.5 py-0.2 rounded text-[9px] font-bold uppercase bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 mt-0.5">
                                    {{ $inv->location?->type?->value ?? $inv->location?->type ?? 'Location' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-3 text-center font-mono font-bold text-slate-900 dark:text-white">
                                {{ number_format($inv->quantity_on_hand) }}
                            </td>
                            <td class="py-3.5 px-3 text-center font-mono font-bold text-amber-600">
                                {{ number_format($inv->quantity_reserved) }}
                            </td>
                            <td class="py-3.5 px-3 text-center font-mono font-bold {{ $isOut ? 'text-rose-600' : ($isLow ? 'text-amber-600' : 'text-emerald-600') }}">
                                {{ number_format(max(0, $available)) }}
                            </td>
                            <td class="py-3.5 px-3 text-center font-mono text-slate-500">
                                {{ number_format($inv->low_stock_threshold) }}
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                @if($inv->is_quick_available)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                        ⚡ Quick Active
                                    </span>
                                @else
                                    <span class="text-slate-400 text-[10px]">Standard</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center">
                                <x-empty-state 
                                    title="No global inventory balances match criteria" 
                                    description="Try clearing search keywords, merchant selection, or stock health filters."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-800/80">
            <x-pagination :paginator="$inventories" />
        </div>
    </div>
</div>
@endsection
