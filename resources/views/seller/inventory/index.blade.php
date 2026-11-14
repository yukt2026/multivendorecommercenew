@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Inventory Balances'])

@section('content')
<div class="space-y-6" x-data="sellerInventoryManager()">
    <!-- Header -->
    <x-page-header 
        title="Store Inventory & Stock Balances" 
        subtitle="Real-time multi-location physical store & warehouse inventory balances, atomic reservations, and audit history."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Inventory' => '#']"
    >
        <x-slot name="actions">
            <x-button variant="outline" size="sm" href="{{ route('seller.inventory.export', request()->query()) }}">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export CSV
            </x-button>
            <x-button variant="outline" size="sm" href="{{ route('seller.inventory.sample_csv') }}">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Sample CSV
            </x-button>
            <button 
                type="button" 
                @click="openImportModal = true"
                class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs hover:bg-slate-50 transition flex items-center space-x-1.5"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                <span>Bulk CSV Import</span>
            </button>
            <button 
                type="button" 
                @click="openTransferModal = true"
                class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-600/20 transition flex items-center space-x-1.5"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span>Transfer Stock</span>
            </button>
        </x-slot>
    </x-page-header>

    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs font-semibold flex items-center space-x-2">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if (session('warning'))
        <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-xs font-semibold space-y-1">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-amber-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <span>{{ session('warning') }}</span>
            </div>
            @if(session('import_errors'))
                <ul class="list-disc pl-7 space-y-0.5 text-[11px] text-amber-900 dark:text-amber-300 font-normal">
                    @foreach(session('import_errors') as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <!-- Top KPI Stat Cards with 1-Click Interactive Filters -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
        <a href="{{ route('seller.inventory.index', request()->except('stock_status', 'page')) }}" 
           class="block transition transform active:scale-95 focus:outline-none">
            <x-card class="!p-4 border-l-4 border-l-indigo-500 hover:border-indigo-600 hover:shadow-md transition {{ empty(request('stock_status')) ? 'ring-2 ring-indigo-500/40' : '' }}">
                <div class="text-[11px] font-bold uppercase text-slate-500">Total On-Hand</div>
                <div class="text-xl font-extrabold text-slate-900 dark:text-white mt-1">{{ number_format($metrics['total_on_hand']) }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5">Physical ledger balance</div>
            </x-card>
        </a>

        <a href="{{ route('seller.inventory.index', array_merge(request()->except('stock_status', 'page'), ['stock_status' => 'reserved'])) }}" 
           class="block transition transform active:scale-95 focus:outline-none">
            <x-card class="!p-4 border-l-4 border-l-amber-500 hover:border-amber-600 hover:shadow-md transition {{ request('stock_status') === 'reserved' ? 'ring-2 ring-amber-500/40' : '' }}">
                <div class="text-[11px] font-bold uppercase text-slate-500">Active Reserved</div>
                <div class="text-xl font-extrabold text-amber-600 mt-1">{{ number_format($metrics['total_reserved']) }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5">Locked in checkout carts</div>
            </x-card>
        </a>

        <a href="{{ route('seller.inventory.index', array_merge(request()->except('stock_status', 'page'), ['stock_status' => 'in_stock'])) }}" 
           class="block transition transform active:scale-95 focus:outline-none">
            <x-card class="!p-4 border-l-4 border-l-emerald-500 hover:border-emerald-600 hover:shadow-md transition {{ request('stock_status') === 'in_stock' ? 'ring-2 ring-emerald-500/40' : '' }}">
                <div class="text-[11px] font-bold uppercase text-slate-500">Total Available</div>
                <div class="text-xl font-extrabold text-emerald-600 mt-1">{{ number_format($metrics['total_available']) }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5">Ready for instant sale</div>
            </x-card>
        </a>

        <a href="{{ route('seller.inventory.index', array_merge(request()->except('stock_status', 'page'), ['stock_status' => 'low_stock'])) }}" 
           class="block transition transform active:scale-95 focus:outline-none">
            <x-card class="!p-4 border-l-4 border-l-yellow-500 hover:border-yellow-600 hover:shadow-md transition {{ request('stock_status') === 'low_stock' ? 'ring-2 ring-yellow-500/40' : '' }}">
                <div class="text-[11px] font-bold uppercase text-slate-500">Low Stock Items</div>
                <div class="text-xl font-extrabold text-yellow-600 mt-1">{{ number_format($metrics['low_stock_count']) }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5">&le; alert threshold limit</div>
            </x-card>
        </a>

        <a href="{{ route('seller.inventory.index', array_merge(request()->except('stock_status', 'page'), ['stock_status' => 'out_of_stock'])) }}" 
           class="block transition transform active:scale-95 focus:outline-none">
            <x-card class="!p-4 border-l-4 border-l-rose-500 hover:border-rose-600 hover:shadow-md transition {{ request('stock_status') === 'out_of_stock' ? 'ring-2 ring-rose-500/40' : '' }}">
                <div class="text-[11px] font-bold uppercase text-slate-500">Out of Stock</div>
                <div class="text-xl font-extrabold text-rose-600 mt-1">{{ number_format($metrics['out_of_stock_count']) }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5">Zero available units</div>
            </x-card>
        </a>
    </div>

    <!-- Filters Bar -->
    <x-card>
        <form method="GET" action="{{ route('seller.inventory.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3">
            <!-- Search -->
            <div class="sm:col-span-2">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ request('search') }}" 
                    placeholder="Search product title, SKU, or barcode..." 
                    class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white"
                />
            </div>

            <!-- Store Outlet Filter -->
            <div>
                <select name="store_id" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
                    <option value="">All Store Outlets</option>
                    @foreach($stores as $st)
                        <option value="{{ $st->id }}" {{ request('store_id') == $st->id ? 'selected' : '' }}>
                            {{ $st->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Location Hub Filter -->
            <div>
                <select name="location_id" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
                    <option value="">All Locations Hubs</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>
                            {{ $loc->name }} ({{ strtoupper($loc->type->value) }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Stock Status Filter -->
            <div>
                <select name="stock_status" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
                    <option value="">All Health Statuses</option>
                    <option value="in_stock" {{ request('stock_status') === 'in_stock' ? 'selected' : '' }}>Healthy (&gt; Threshold)</option>
                    <option value="low_stock" {{ request('stock_status') === 'low_stock' ? 'selected' : '' }}>Low Stock Alert (&le; Threshold)</option>
                    <option value="out_of_stock" {{ request('stock_status') === 'out_of_stock' ? 'selected' : '' }}>Out of Stock (&le; 0)</option>
                    <option value="reserved" {{ request('stock_status') === 'reserved' ? 'selected' : '' }}>Locked in Carts (Reserved &gt; 0)</option>
                </select>
            </div>

            <!-- Quick Delivery & Submit -->
            <div class="flex items-center space-x-2">
                <select name="quick_available" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
                    <option value="">Quick: All</option>
                    <option value="1" {{ request('quick_available') === '1' ? 'selected' : '' }}>⚡ Quick Available</option>
                    <option value="0" {{ request('quick_available') === '0' ? 'selected' : '' }}>Standard Logistics</option>
                </select>
                <button type="submit" class="p-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shrink-0" title="Apply Filters">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>
                @if(request()->anyFilled(['search', 'store_id', 'location_id', 'stock_status', 'quick_available']))
                    <a href="{{ route('seller.inventory.index') }}" class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 shrink-0" title="Clear Filters">
                        &times;
                    </a>
                @endif
            </div>
        </form>
    </x-card>

    <!-- Inventory Table -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                    <tr>
                        <th class="py-4 px-4">Item &amp; Variant SKU</th>
                        <th class="py-4 px-3">Location &amp; Outlet</th>
                        <th class="py-4 px-3 text-center">On Hand</th>
                        <th class="py-4 px-3 text-center">Reserved</th>
                        <th class="py-4 px-3 text-center">Available</th>
                        <th class="py-4 px-3 text-center">Alert Limit</th>
                        <th class="py-4 px-3">Stock Health</th>
                        <th class="py-4 px-3 text-right">Actions</th>
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
                            <!-- Product & SKU -->
                            <td class="py-3 px-4">
                                <div class="space-y-0.5">
                                    <div class="font-bold text-slate-900 dark:text-white line-clamp-1">
                                        <a href="{{ route('seller.products.edit', $inv->variant?->product_id) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition inline-flex items-center space-x-1" title="Edit Catalogue Details">
                                            <span>{{ $inv->variant?->product?->title ?? 'Product SKU' }}</span>
                                            <svg class="w-3 h-3 text-slate-400 shrink-0 opacity-60 hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </div>
                                    <div class="flex items-center space-x-2 text-[10px]">
                                        <span class="font-mono text-indigo-600 dark:text-indigo-400 font-bold">{{ $inv->variant?->sku ?? 'N/A' }}</span>
                                        @if($inv->variant?->name)
                                            <span class="text-slate-500 font-medium">({{ $inv->variant->name }})</span>
                                        @endif
                                        @if($inv->variant?->barcode)
                                            <span class="text-slate-400 font-mono">{{ $inv->variant->barcode }}</span>
                                        @endif
                                        @if($inv->is_quick_available)
                                            <span class="px-1 py-0.2 rounded bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300 font-bold">⚡ Quick</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- Location & Outlet -->
                            <td class="py-3 px-3">
                                <div class="space-y-0.5">
                                    <div class="text-slate-900 dark:text-white font-medium">{{ $inv->location->name }}</div>
                                    <div class="flex items-center space-x-1.5">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $inv->location->type->value === 'store' ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                                            {{ $inv->location->type->value }}
                                        </span>
                                        @if($inv->location->store)
                                            <span class="text-[10px] text-slate-400">
                                                &bull; {{ $inv->location->store->name }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- On Hand -->
                            <td class="py-3 px-3 text-center font-mono font-bold text-slate-900 dark:text-white text-sm" id="on-hand-{{ $inv->id }}">
                                {{ $inv->quantity_on_hand }}
                            </td>

                            <!-- Reserved -->
                            <td class="py-3 px-3 text-center font-mono font-bold text-amber-600 text-sm" id="reserved-{{ $inv->id }}">
                                {{ $inv->quantity_reserved }}
                            </td>

                            <!-- Available -->
                            <td class="py-3 px-3 text-center font-mono font-bold text-sm {{ $isOut ? 'text-rose-600' : ($isLow ? 'text-yellow-600' : 'text-emerald-600') }}" id="avail-{{ $inv->id }}">
                                {{ max(0, $available) }}
                            </td>

                            <!-- Threshold -->
                            <td class="py-3 px-3 text-center font-mono text-slate-500 text-xs" id="threshold-{{ $inv->id }}">
                                {{ $inv->low_stock_threshold }}
                            </td>

                            <!-- Status Badge -->
                            <td class="py-3 px-3">
                                @if($isOut)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800">
                                        Out of Stock
                                    </span>
                                @elseif($isLow)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping mr-1"></span>
                                        Low Stock Alert
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800">
                                        Healthy
                                    </span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end space-x-1.5">
                                    <!-- Quick 1-Click Restock Button -->
                                    <button 
                                        type="button" 
                                        @click="openQuickRestockModal({{ $inv->id }}, '{{ addslashes($inv->variant?->sku ?? '') }}', '{{ addslashes($inv->variant?->product?->title ?? '') }}', '{{ addslashes($inv->location?->name ?? '') }}')"
                                        class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition" 
                                        title="Quick Restock Units"
                                    >
                                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    </button>

                                    <!-- Inline Edit Button -->
                                    <button 
                                        type="button" 
                                        @click="openInlineEdit({{ $inv->id }}, {{ $inv->quantity_on_hand }}, {{ $inv->low_stock_threshold }}, {{ $inv->is_quick_available ? 'true' : 'false' }})"
                                        class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition" 
                                        title="Quick Adjust Stock"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>

                                    <!-- Movement History Button -->
                                    <button 
                                        type="button" 
                                        @click="loadMovements({{ $inv->id }})"
                                        class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:text-amber-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition" 
                                        title="View Stock Movements Audit"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-500">
                                <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                <div class="font-bold text-sm text-slate-700 dark:text-slate-300">No inventory entries found</div>
                                <p class="text-xs text-slate-400 mt-1">Use Bulk CSV Import or create products to populate store locations.</p>
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

    <!-- Quick Restock Modal -->
    <div 
        x-show="openRestockModal" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
    >
        <div 
            class="bg-white dark:bg-slate-900 rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4"
            @click.outside="openRestockModal = false"
        >
            <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-800">
                <div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">Quick Restock Units</h3>
                    <p class="text-[11px] text-slate-400" x-text="`${restockForm.sku} @ ${restockForm.location}`"></p>
                </div>
                <button type="button" @click="openRestockModal = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Units to Add to On-Hand Ledger</label>
                    <input 
                        type="number" 
                        min="1" 
                        x-model="restockForm.added_quantity" 
                        class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 font-mono text-xs" 
                    />
                </div>

                <!-- Quick Presets -->
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Presets</label>
                    <div class="flex items-center space-x-2">
                        <button type="button" @click="restockForm.added_quantity = 10" class="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold hover:bg-slate-100 dark:hover:bg-slate-800">+10</button>
                        <button type="button" @click="restockForm.added_quantity = 25" class="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold hover:bg-slate-100 dark:hover:bg-slate-800">+25</button>
                        <button type="button" @click="restockForm.added_quantity = 50" class="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold hover:bg-slate-100 dark:hover:bg-slate-800">+50</button>
                        <button type="button" @click="restockForm.added_quantity = 100" class="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold hover:bg-slate-100 dark:hover:bg-slate-800">+100</button>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                <x-button variant="outline" size="sm" @click="openRestockModal = false">
                    Cancel
                </x-button>
                <x-button variant="primary" size="sm" @click="submitQuickRestock()">
                    Add Stock Now
                </x-button>
            </div>
        </div>
    </div>

    <!-- Inline Adjust Modal -->
    <div 
        x-show="openEditModal" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
    >
        <div 
            class="bg-white dark:bg-slate-900 rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4"
            @click.outside="openEditModal = false"
        >
            <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-800">
                <h3 class="font-bold text-sm text-slate-900 dark:text-white">Adjust Location Stock</h3>
                <button type="button" @click="openEditModal = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Physical On-Hand Quantity</label>
                    <input 
                        type="number" 
                        min="0" 
                        x-model="editForm.quantity_on_hand" 
                        class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 font-mono text-xs" 
                    />
                    <p class="text-[10px] text-slate-400 mt-1">Updates on-hand ledger and logs adjustment movement.</p>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Low Stock Alert Threshold</label>
                    <input 
                        type="number" 
                        min="1" 
                        x-model="editForm.low_stock_threshold" 
                        class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 font-mono text-xs" 
                    />
                    <p class="text-[10px] text-slate-400 mt-1">Triggers email and in-app alerts when available drops to this level.</p>
                </div>

                <div class="pt-2">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" x-model="editForm.is_quick_available" class="rounded text-amber-500" />
                        <span class="font-bold text-slate-800 dark:text-slate-200">⚡ Available for 10-30m Quick Delivery</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                <x-button variant="outline" size="sm" @click="openEditModal = false">
                    Cancel
                </x-button>
                <x-button variant="primary" size="sm" @click="submitInlineEdit()">
                    Save Changes
                </x-button>
            </div>
        </div>
    </div>

    <!-- Stock Movements Timeline Modal -->
    <div 
        x-show="openMovementsModal" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
    >
        <div 
            class="bg-white dark:bg-slate-900 rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4"
            @click.outside="openMovementsModal = false"
        >
            <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-800">
                <div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white" x-text="`Stock Movements: ${movementData.sku}`"></h3>
                    <p class="text-[11px] text-slate-400" x-text="`${movementData.product_title} @ ${movementData.location}`"></p>
                </div>
                <button type="button" @click="openMovementsModal = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <!-- Movement History List -->
            <div class="max-h-80 overflow-y-auto space-y-2 pr-1">
                <template x-if="movementData.movements.length === 0">
                    <div class="text-center py-8 text-xs text-slate-400">No stock movements recorded yet.</div>
                </template>
                <template x-for="m in movementData.movements" :key="m.id">
                    <div class="p-3 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40 flex items-center justify-between text-xs">
                        <div class="space-y-0.5">
                            <div class="flex items-center space-x-2">
                                <span class="font-bold text-slate-900 dark:text-white uppercase text-[10px] px-1.5 py-0.5 rounded" :class="m.quantity > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'" x-text="m.type_label"></span>
                                <span class="text-slate-500 font-mono text-[11px]" x-text="m.created_at"></span>
                            </div>
                            <p class="text-[11px] text-slate-600 dark:text-slate-400" x-text="m.note || 'No notes attached'"></p>
                            <span class="text-[10px] text-slate-400" x-text="`By: ${m.user}`"></span>
                        </div>
                        <div class="font-mono font-extrabold text-sm text-right" :class="m.quantity > 0 ? 'text-emerald-600' : 'text-rose-600'">
                            <span x-text="m.quantity > 0 ? `+${m.quantity}` : m.quantity"></span>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex justify-end pt-2 border-t border-slate-100 dark:border-slate-800">
                <x-button variant="outline" size="sm" @click="openMovementsModal = false">
                    Close
                </x-button>
            </div>
        </div>
    </div>

    <!-- Inter-Location Transfer Modal -->
    <div 
        x-show="openTransferModal" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
    >
        <div 
            class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4"
            @click.outside="openTransferModal = false"
        >
            <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-800">
                <h3 class="font-bold text-sm text-slate-900 dark:text-white">Inter-Location Stock Transfer</h3>
                <button type="button" @click="openTransferModal = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <form method="POST" action="{{ route('seller.inventory.transfer') }}" class="space-y-3 text-xs">
                @csrf
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Select Variant / Item</label>
                    <select name="variant_id" x-model="transferForm.variant_id" required class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 text-xs text-slate-900 dark:text-white">
                        <option value="">Choose variant to transfer...</option>
                        @foreach($transferVariants as $tv)
                            <option value="{{ $tv->id }}">
                                {{ $tv->sku }} - {{ $tv->product?->title }} {{ $tv->name ? '('.$tv->name.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">From Source Location</label>
                        <select name="from_location_id" x-model="transferForm.from_location_id" required class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 text-xs text-slate-900 dark:text-white">
                            @foreach($locations as $l)
                                <option value="{{ $l->id }}">{{ $l->name }} ({{ strtoupper($l->type->value) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">To Destination Location</label>
                        <select name="to_location_id" x-model="transferForm.to_location_id" required class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 text-xs text-slate-900 dark:text-white">
                            @foreach($locations as $l)
                                <option value="{{ $l->id }}">{{ $l->name }} ({{ strtoupper($l->type->value) }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Live Stock Availability Indicator -->
                <div x-show="transferForm.variant_id" class="p-3 rounded-xl border text-xs"
                     :class="maxTransferAvailable > 0 ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 text-emerald-800 dark:text-emerald-300' : 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 text-rose-800 dark:text-rose-300'">
                    <div class="flex items-center justify-between">
                        <span class="font-bold">Available at Source Hub:</span>
                        <span class="font-mono font-black text-sm" x-text="`${maxTransferAvailable} units`"></span>
                    </div>
                    <template x-if="maxTransferAvailable <= 0">
                        <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1 font-medium">
                            ⚠️ No available stock to transfer at this source location.
                        </p>
                    </template>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block font-bold text-slate-700 dark:text-slate-300">Transfer Units</label>
                        <button type="button" 
                                x-show="maxTransferAvailable > 0"
                                @click="transferForm.quantity = maxTransferAvailable"
                                class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                            Set Max (<span x-text="maxTransferAvailable"></span>)
                        </button>
                    </div>
                    <input type="number" 
                           min="1" 
                           :max="maxTransferAvailable > 0 ? maxTransferAvailable : 1" 
                           x-model.number="transferForm.quantity"
                           name="quantity" 
                           required 
                           placeholder="Units to transfer" 
                           class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 font-mono text-xs text-slate-900 dark:text-white" />
                    <template x-if="transferForm.quantity > maxTransferAvailable && maxTransferAvailable > 0">
                        <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1 font-semibold">
                            ⚠️ Requested quantity (<span x-text="transferForm.quantity"></span>) exceeds available stock (<span x-text="maxTransferAvailable"></span> units).
                        </p>
                    </template>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Transfer Note</label>
                    <input type="text" x-model="transferForm.note" name="note" placeholder="e.g. Dark store dispatch replenish" class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 text-xs text-slate-900 dark:text-white" />
                </div>

                <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <x-button variant="outline" size="sm" @click="openTransferModal = false" type="button">
                        Cancel
                    </x-button>
                    <x-button variant="primary" size="sm" type="submit" ::disabled="maxTransferAvailable <= 0 || transferForm.quantity > maxTransferAvailable || transferForm.from_location_id == transferForm.to_location_id">
                        Execute Transfer
                    </x-button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk CSV Import Modal -->
    <div 
        x-show="openImportModal" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
    >
        <div 
            class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4"
            @click.outside="openImportModal = false"
        >
            <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-800">
                <h3 class="font-bold text-sm text-slate-900 dark:text-white">Bulk Stock CSV Import</h3>
                <button type="button" @click="openImportModal = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <form method="POST" action="{{ route('seller.inventory.import_csv') }}" enctype="multipart/form-data" class="space-y-4 text-xs">
                @csrf
                <p class="text-slate-500">
                    Upload a CSV file containing columns: <code class="bg-slate-100 dark:bg-slate-800 px-1 py-0.5 rounded font-mono">sku, quantity_on_hand, location_id, low_stock_threshold</code>.
                </p>

                <div class="p-6 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-2xl text-center bg-slate-50 dark:bg-slate-950/40">
                    <input type="file" name="csv_file" accept=".csv,text/csv" required class="block mx-auto text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
                    <a href="{{ route('seller.inventory.sample_csv') }}" class="text-indigo-600 font-bold hover:underline">
                        &darr; Download Sample CSV
                    </a>
                    <div class="space-x-2">
                        <x-button variant="outline" size="sm" @click="openImportModal = false" type="button">
                            Cancel
                        </x-button>
                        <x-button variant="primary" size="sm" type="submit">
                            Upload &amp; Sync
                        </x-button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Floating Feedback Toast Banner -->
    <div 
        x-show="toast.visible" 
        x-cloak 
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-2"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-2"
        class="fixed bottom-6 right-6 z-50 max-w-sm rounded-2xl p-4 shadow-xl border flex items-center space-x-3 text-xs font-semibold"
        :class="toast.type === 'error' ? 'bg-rose-50 text-rose-800 border-rose-200 dark:bg-rose-950 dark:text-rose-200 dark:border-rose-800' : 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:border-emerald-800'"
    >
        <span x-text="toast.message"></span>
        <button type="button" @click="toast.visible = false" class="text-slate-400 hover:text-slate-600 ml-auto">&times;</button>
    </div>
</div>

<script>
function sellerInventoryManager() {
    return {
        openEditModal: false,
        openRestockModal: false,
        openMovementsModal: false,
        openTransferModal: false,
        openImportModal: false,
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeout: null
        },
        showToast(message, type = 'success') {
            this.toast.message = message;
            this.toast.type = type;
            this.toast.visible = true;
            if (this.toast.timeout) clearTimeout(this.toast.timeout);
            this.toast.timeout = setTimeout(() => {
                this.toast.visible = false;
            }, 4000);
        },
        stockMap: @json($stockMap ?? []),
        transferForm: {
            variant_id: '{{ $transferVariants->first()?->id ?? "" }}',
            from_location_id: '{{ $locations->first()?->id ?? "" }}',
            to_location_id: '{{ $locations->count() > 1 ? $locations[1]->id : "" }}',
            quantity: 1,
            note: '',
        },
        get maxTransferAvailable() {
            if (!this.transferForm.variant_id || !this.transferForm.from_location_id) return 0;
            return this.stockMap[this.transferForm.variant_id]?.[this.transferForm.from_location_id] ?? 0;
        },
        editForm: {
            id: null,
            quantity_on_hand: 0,
            low_stock_threshold: 5,
            is_quick_available: false,
        },
        restockForm: {
            id: null,
            sku: '',
            location: '',
            added_quantity: 10,
        },
        movementData: {
            sku: '',
            product_title: '',
            location: '',
            movements: [],
        },

        openInlineEdit(id, onHand, threshold, isQuick) {
            this.editForm.id = id;
            this.editForm.quantity_on_hand = onHand;
            this.editForm.low_stock_threshold = threshold;
            this.editForm.is_quick_available = isQuick;
            this.openEditModal = true;
        },

        openQuickRestockModal(id, sku, productTitle, location) {
            this.restockForm.id = id;
            this.restockForm.sku = sku;
            this.restockForm.location = location;
            this.restockForm.added_quantity = 10;
            this.openRestockModal = true;
        },

        async submitQuickRestock() {
            try {
                const res = await fetch(`/seller/inventory/${this.restockForm.id}/quick-restock`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        added_quantity: this.restockForm.added_quantity
                    })
                });

                const data = await res.json();
                if (data.success) {
                    const onHandEl = document.getElementById(`on-hand-${this.restockForm.id}`);
                    const availEl = document.getElementById(`avail-${this.restockForm.id}`);
                    const resEl = document.getElementById(`reserved-${this.restockForm.id}`);

                    if (onHandEl) {
                        const curOnHand = parseInt(onHandEl.innerText.trim(), 10) || 0;
                        const newOnHand = curOnHand + parseInt(this.restockForm.added_quantity, 10);
                        onHandEl.innerText = newOnHand;
                        if (availEl && resEl) {
                            const curRes = parseInt(resEl.innerText.trim(), 10) || 0;
                            availEl.innerText = Math.max(0, newOnHand - curRes);
                        }
                    }

                    this.openRestockModal = false;
                    this.showToast(data.message || 'Stock added successfully!');
                } else {
                    this.showToast(data.message || 'Error updating stock.', 'error');
                }
            } catch (e) {
                this.showToast('Server error during quick restock.', 'error');
            }
        },

        async submitInlineEdit() {
            try {
                const res = await fetch(`/seller/inventory/${this.editForm.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(this.editForm)
                });

                const data = await res.json();
                if (data.success) {
                    const inv = data.inventory;
                    const onHandEl = document.getElementById(`on-hand-${inv.id}`);
                    const availEl = document.getElementById(`avail-${inv.id}`);
                    const thresholdEl = document.getElementById(`threshold-${inv.id}`);

                    if (onHandEl) onHandEl.innerText = inv.quantity_on_hand;
                    if (availEl) availEl.innerText = inv.available_quantity;
                    if (thresholdEl) thresholdEl.innerText = inv.low_stock_threshold;

                    this.openEditModal = false;
                    this.showToast(data.message);
                } else {
                    this.showToast(data.message || 'Error updating inventory.', 'error');
                }
            } catch (e) {
                this.showToast('Server error occurred during inventory update.', 'error');
            }
        },

        async loadMovements(id) {
            try {
                const res = await fetch(`/seller/inventory/${id}/movements`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.movementData = data;
                    this.openMovementsModal = true;
                } else {
                    this.showToast('Failed to retrieve movements.', 'error');
                }
            } catch (e) {
                this.showToast('Unable to load movement history.', 'error');
            }
        }
    };
}
</script>
@endsection
