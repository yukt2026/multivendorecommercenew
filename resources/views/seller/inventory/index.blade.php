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

    <!-- Top KPI Stat Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
        <x-card class="!p-4 border-l-4 border-l-indigo-500">
            <div class="text-[11px] font-bold uppercase text-slate-500">Total On-Hand</div>
            <div class="text-xl font-extrabold text-slate-900 dark:text-white mt-1">{{ number_format($metrics['total_on_hand']) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Physical ledger balance</div>
        </x-card>

        <x-card class="!p-4 border-l-4 border-l-amber-500">
            <div class="text-[11px] font-bold uppercase text-slate-500">Active Reserved</div>
            <div class="text-xl font-extrabold text-amber-600 mt-1">{{ number_format($metrics['total_reserved']) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Locked in checkout carts</div>
        </x-card>

        <x-card class="!p-4 border-l-4 border-l-emerald-500">
            <div class="text-[11px] font-bold uppercase text-slate-500">Total Available</div>
            <div class="text-xl font-extrabold text-emerald-600 mt-1">{{ number_format($metrics['total_available']) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Ready for instant sale</div>
        </x-card>

        <x-card class="!p-4 border-l-4 border-l-yellow-500">
            <div class="text-[11px] font-bold uppercase text-slate-500">Low Stock Items</div>
            <div class="text-xl font-extrabold text-yellow-600 mt-1">{{ number_format($metrics['low_stock_count']) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">&le; alert threshold limit</div>
        </x-card>

        <x-card class="!p-4 border-l-4 border-l-rose-500">
            <div class="text-[11px] font-bold uppercase text-slate-500">Out of Stock</div>
            <div class="text-xl font-extrabold text-rose-600 mt-1">{{ number_format($metrics['out_of_stock_count']) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Zero available units</div>
        </x-card>
    </div>

    <!-- Filters Bar -->
    <x-card>
        <form method="GET" action="{{ route('seller.inventory.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
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

            <!-- Location Filter -->
            <div>
                <select name="location_id" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
                    <option value="">All Locations (Store &amp; Warehouse)</option>
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
                </select>
            </div>

            <!-- Quick Delivery & Submit -->
            <div class="flex items-center space-x-2">
                <select name="quick_available" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
                    <option value="">Quick: All</option>
                    <option value="1" {{ request('quick_available') === '1' ? 'selected' : '' }}>⚡ Quick Available</option>
                    <option value="0" {{ request('quick_available') === '0' ? 'selected' : '' }}>Standard Logistics</option>
                </select>
                <button type="submit" class="p-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>
                @if(request()->anyFilled(['search', 'location_id', 'stock_status', 'quick_available']))
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
                        <th class="py-4 px-3">Location Hub</th>
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
                                        {{ $inv->variant->product->title }}
                                    </div>
                                    <div class="flex items-center space-x-2 text-[10px]">
                                        <span class="font-mono text-indigo-600 dark:text-indigo-400 font-bold">{{ $inv->variant->sku }}</span>
                                        @if($inv->variant->barcode)
                                            <span class="text-slate-400 font-mono">{{ $inv->variant->barcode }}</span>
                                        @endif
                                        @if($inv->is_quick_available)
                                            <span class="px-1 py-0.2 rounded bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300 font-bold">⚡ Quick</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- Location -->
                            <td class="py-3 px-3">
                                <div class="space-y-0.5">
                                    <div class="text-slate-900 dark:text-white font-medium">{{ $inv->location->name }}</div>
                                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $inv->location->type->value === 'store' ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                                        {{ $inv->location->type->value }}
                                    </span>
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

    <!-- Inline Edit Modal -->
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
                    <select name="variant_id" required class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 text-xs">
                        <option value="">Choose variant to transfer...</option>
                        @foreach($inventories as $item)
                            <option value="{{ $item->variant_id }}">
                                {{ $item->variant->sku }} - {{ $item->variant->product->title }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">From Location</label>
                        <select name="from_location_id" required class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 text-xs">
                            @foreach($locations as $l)
                                <option value="{{ $l->id }}">{{ $l->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">To Location</label>
                        <select name="to_location_id" required class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 text-xs">
                            @foreach($locations as $l)
                                <option value="{{ $l->id }}">{{ $l->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Transfer Units</label>
                    <input type="number" min="1" name="quantity" required placeholder="Units" class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 font-mono text-xs" />
                </div>

                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Transfer Note</label>
                    <input type="text" name="note" placeholder="e.g. Dark store dispatch replenish" class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 text-xs" />
                </div>

                <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <x-button variant="outline" size="sm" @click="openTransferModal = false" type="button">
                        Cancel
                    </x-button>
                    <x-button variant="primary" size="sm" type="submit">
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
</div>

<script>
function sellerInventoryManager() {
    return {
        openEditModal: false,
        openMovementsModal: false,
        openTransferModal: false,
        openImportModal: false,
        editForm: {
            id: null,
            quantity_on_hand: 0,
            low_stock_threshold: 5,
            is_quick_available: false,
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
                    alert(data.message);
                } else {
                    alert(data.message || 'Error updating inventory.');
                }
            } catch (e) {
                alert('Server error occurred during inventory update.');
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
                }
            } catch (e) {
                alert('Unable to load movement history.');
            }
        }
    };
}
</script>
@endsection
