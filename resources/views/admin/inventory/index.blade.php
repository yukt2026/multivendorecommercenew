@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Global Inventory Overview'])

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <x-page-header 
        title="Global Marketplace Inventory" 
        subtitle="Read-only platform-wide inventory health audit, dark store balances, and seller warehouse levels."
        :breadcrumbs="['Governance' => route('admin.dashboard'), 'Global Inventory' => '#']"
    >
        <x-slot name="actions">
            <x-button variant="outline" size="sm" href="{{ route('admin.inventory.sync_logs') }}">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                View Sync Logs &rarr;
            </x-button>
        </x-slot>
    </x-page-header>

    <!-- KPI Summary -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <x-card class="!p-4 border-l-4 border-l-indigo-500">
            <div class="text-[11px] font-bold uppercase text-slate-500">Tracked SKU Allocations</div>
            <div class="text-xl font-extrabold text-slate-900 dark:text-white mt-1">{{ number_format($metrics['total_items']) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Across all locations</div>
        </x-card>

        <x-card class="!p-4 border-l-4 border-l-emerald-500">
            <div class="text-[11px] font-bold uppercase text-slate-500">Total Physical Units</div>
            <div class="text-xl font-extrabold text-emerald-600 mt-1">{{ number_format($metrics['total_on_hand']) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Global on-hand inventory</div>
        </x-card>

        <x-card class="!p-4 border-l-4 border-l-amber-500">
            <div class="text-[11px] font-bold uppercase text-slate-500">Cart Locked Units</div>
            <div class="text-xl font-extrabold text-amber-600 mt-1">{{ number_format($metrics['total_reserved']) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Under checkout reservation</div>
        </x-card>

        <x-card class="!p-4 border-l-4 border-l-rose-500">
            <div class="text-[11px] font-bold uppercase text-slate-500">Stocked-Out SKUs</div>
            <div class="text-xl font-extrabold text-rose-600 mt-1">{{ number_format($metrics['out_of_stock']) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Zero available balance</div>
        </x-card>
    </div>

    <!-- Filter Bar -->
    <x-card>
        <form method="GET" action="{{ route('admin.inventory.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
            <!-- Search -->
            <div class="sm:col-span-2">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ request('search') }}" 
                    placeholder="Search title, SKU, or merchant..." 
                    class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white"
                />
            </div>

            <!-- Seller Filter -->
            <div>
                <select name="seller_id" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
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
                <select name="location_type" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
                    <option value="">All Location Types</option>
                    <option value="store" {{ request('location_type') === 'store' ? 'selected' : '' }}>Physical Retail Store</option>
                    <option value="warehouse" {{ request('location_type') === 'warehouse' ? 'selected' : '' }}>Hub Warehouse</option>
                    <option value="onestall_store" {{ request('location_type') === 'onestall_store' ? 'selected' : '' }}>Dark Store / Instant Hub</option>
                </select>
            </div>

            <!-- Health Status & Submit -->
            <div class="flex items-center space-x-2">
                <select name="stock_status" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
                    <option value="">All Statuses</option>
                    <option value="in_stock" {{ request('stock_status') === 'in_stock' ? 'selected' : '' }}>In Stock</option>
                    <option value="low_stock" {{ request('stock_status') === 'low_stock' ? 'selected' : '' }}>Low Stock</option>
                    <option value="out_of_stock" {{ request('stock_status') === 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
                </select>
                <button type="submit" class="p-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>
            </div>
        </form>
    </x-card>

    <!-- Global Inventory Table -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                    <tr>
                        <th class="py-4 px-4">Merchant</th>
                        <th class="py-4 px-3">Product &amp; SKU</th>
                        <th class="py-4 px-3">Location &amp; Type</th>
                        <th class="py-4 px-3 text-center">On Hand</th>
                        <th class="py-4 px-3 text-center">Reserved</th>
                        <th class="py-4 px-3 text-center">Available</th>
                        <th class="py-4 px-3 text-center">Threshold</th>
                        <th class="py-4 px-4 text-right">Quick Eligible</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium text-slate-700 dark:text-slate-300">
                    @forelse($inventories as $inv)
                        @php
                            $available = $inv->quantity_on_hand - $inv->quantity_reserved;
                            $isLow = ($available > 0 && $available <= $inv->low_stock_threshold);
                            $isOut = ($available <= 0);
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                            <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">
                                {{ $inv->location->seller->business_name ?? 'OneStall Direct' }}
                            </td>
                            <td class="py-3 px-3">
                                <div class="font-bold text-slate-900 dark:text-white line-clamp-1">
                                    {{ $inv->variant->product->title }}
                                </div>
                                <div class="text-[10px] font-mono text-indigo-600 dark:text-indigo-400">
                                    {{ $inv->variant->sku }}
                                </div>
                            </td>
                            <td class="py-3 px-3">
                                <div class="text-slate-900 dark:text-white font-medium">{{ $inv->location->name }}</div>
                                <span class="inline-block px-1.5 py-0.2 rounded text-[9px] font-bold uppercase bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                    {{ $inv->location->type->value }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center font-mono font-bold text-slate-900 dark:text-white">
                                {{ $inv->quantity_on_hand }}
                            </td>
                            <td class="py-3 px-3 text-center font-mono font-bold text-amber-600">
                                {{ $inv->quantity_reserved }}
                            </td>
                            <td class="py-3 px-3 text-center font-mono font-bold {{ $isOut ? 'text-rose-600' : ($isLow ? 'text-yellow-600' : 'text-emerald-600') }}">
                                {{ max(0, $available) }}
                            </td>
                            <td class="py-3 px-3 text-center font-mono text-slate-500">
                                {{ $inv->low_stock_threshold }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                @if($inv->is_quick_available)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300 dark:bg-amber-950/60 dark:text-amber-300">
                                        ⚡ Quick Active
                                    </span>
                                @else
                                    <span class="text-slate-400 text-[10px]">Standard</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-500">
                                No global inventory balances found matching criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($inventories->hasPages())
            <div class="p-4 border-t border-slate-200/80 dark:border-slate-800">
                {{ $inventories->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
