@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Products Catalogue'])

@section('content')
<div class="space-y-6" x-data="sellerProductList()">
    <!-- Header -->
    <x-page-header 
        title="Products Catalogue" 
        subtitle="Manage your inventory catalog, variant pricing, quick-commerce capabilities, and moderation approvals."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Products' => '#']"
    >
        <x-slot name="actions">
            <x-button variant="outline" size="sm" href="{{ route('seller.products.export', request()->query()) }}">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export CSV
            </x-button>
            <x-button variant="primary" size="sm" href="{{ route('seller.products.create') }}">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add New Product
            </x-button>
        </x-slot>
    </x-page-header>

    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs font-semibold flex items-center space-x-2">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if (session('info'))
        <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-xs font-semibold flex items-center space-x-2">
            <svg class="w-5 h-5 text-amber-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <span>{{ session('info') }}</span>
        </div>
    @endif

    <!-- Status Chips Tabs -->
    <div class="flex items-center space-x-2 overflow-x-auto pb-1 text-xs">
        <a 
            href="{{ route('seller.products.index', array_merge(request()->except('status', 'page'), ['status' => 'all'])) }}"
            class="px-3.5 py-2 rounded-xl font-bold whitespace-nowrap transition {{ !request('status') || request('status') === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50' }}"
        >
            All Products <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] {{ !request('status') || request('status') === 'all' ? 'bg-indigo-500 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600' }}">{{ $counts['all'] }}</span>
        </a>
        <a 
            href="{{ route('seller.products.index', array_merge(request()->except('status', 'page'), ['status' => 'active'])) }}"
            class="px-3.5 py-2 rounded-xl font-bold whitespace-nowrap transition {{ request('status') === 'active' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50' }}"
        >
            Active / Live <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] {{ request('status') === 'active' ? 'bg-emerald-500 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600' }}">{{ $counts['active'] }}</span>
        </a>
        <a 
            href="{{ route('seller.products.index', array_merge(request()->except('status', 'page'), ['status' => 'pending_review'])) }}"
            class="px-3.5 py-2 rounded-xl font-bold whitespace-nowrap transition {{ request('status') === 'pending_review' ? 'bg-amber-600 text-white shadow-sm' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50' }}"
        >
            Pending Review <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] {{ request('status') === 'pending_review' ? 'bg-amber-500 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600' }}">{{ $counts['pending'] }}</span>
        </a>
        <a 
            href="{{ route('seller.products.index', array_merge(request()->except('status', 'page'), ['status' => 'rejected'])) }}"
            class="px-3.5 py-2 rounded-xl font-bold whitespace-nowrap transition {{ request('status') === 'rejected' ? 'bg-rose-600 text-white shadow-sm' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50' }}"
        >
            Needs Revision <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] {{ request('status') === 'rejected' ? 'bg-rose-500 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600' }}">{{ $counts['rejected'] }}</span>
        </a>
        <a 
            href="{{ route('seller.products.index', array_merge(request()->except('status', 'page'), ['status' => 'draft'])) }}"
            class="px-3.5 py-2 rounded-xl font-bold whitespace-nowrap transition {{ request('status') === 'draft' ? 'bg-slate-700 text-white shadow-sm' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50' }}"
        >
            Drafts <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] {{ request('status') === 'draft' ? 'bg-slate-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600' }}">{{ $counts['draft'] }}</span>
        </a>
    </div>

    <!-- Filters Bar -->
    <x-card>
        <form method="GET" action="{{ route('seller.products.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif

            <!-- Search -->
            <div class="sm:col-span-2">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ request('search') }}" 
                    placeholder="Search title, SKU, barcode..." 
                    class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white"
                />
            </div>

            <!-- Category Filter -->
            <div>
                <select name="category_id" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Brand Filter -->
            <div>
                <select name="brand_id" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
                    <option value="">All Brands</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" {{ request('brand_id') == $brand->id ? 'selected' : '' }}>
                            {{ $brand->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Stock Filter -->
            <div>
                <select name="stock" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
                    <option value="all">All Stock Levels</option>
                    <option value="in_stock" {{ request('stock') === 'in_stock' ? 'selected' : '' }}>In Stock</option>
                    <option value="low_stock" {{ request('stock') === 'low_stock' ? 'selected' : '' }}>Low Stock Warning</option>
                    <option value="out_of_stock" {{ request('stock') === 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
                </select>
            </div>

            <!-- Quick Capable & Filter Submit -->
            <div class="flex items-center space-x-2">
                <select name="quick_capable" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
                    <option value="">Delivery: All</option>
                    <option value="1" {{ request('quick_capable') === '1' ? 'selected' : '' }}>⚡ Quick 10-30m Only</option>
                    <option value="0" {{ request('quick_capable') === '0' ? 'selected' : '' }}>Standard Cargo Only</option>
                </select>
                <button type="submit" class="p-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>
                @if(request()->anyFilled(['search', 'category_id', 'brand_id', 'stock', 'quick_capable', 'status']))
                    <a href="{{ route('seller.products.index') }}" class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 shrink-0" title="Clear Filters">
                        &times;
                    </a>
                @endif
            </div>
        </form>
    </x-card>

    <!-- Bulk Actions Form -->
    <form id="bulk-action-form" method="POST" action="{{ route('seller.products.bulk_action') }}">
        @csrf
        <div class="flex items-center justify-between mb-3 px-1 text-xs">
            <div class="flex items-center space-x-2">
                <span class="text-slate-500">Selected: <strong x-text="selectedIds.length">0</strong> items</span>
                <template x-if="selectedIds.length > 0">
                    <div class="flex items-center space-x-2 animate-fade-in">
                        <select name="action" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 py-1 px-2.5 bg-white dark:bg-slate-900 font-medium">
                            <option value="">Bulk Actions...</option>
                            <option value="activate">Mark Active</option>
                            <option value="deactivate">Mark Inactive</option>
                            <option value="delete">Delete Selected</option>
                        </select>
                        <button 
                            type="button" 
                            @click="submitBulkAction()"
                            class="px-3 py-1 rounded-lg bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700"
                        >
                            Apply
                        </button>
                    </div>
                </template>
            </div>
            <div class="text-slate-400 text-[11px]">
                Showing {{ $products->firstItem() ?? 0 }} - {{ $products->lastItem() ?? 0 }} of {{ $products->total() }}
            </div>
        </div>

        <!-- Products Table -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <tr>
                            <th class="p-4 w-4">
                                <input 
                                    type="checkbox" 
                                    @click="toggleSelectAll($event)"
                                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" 
                                />
                            </th>
                            <th class="py-4 px-3">Product</th>
                            <th class="py-4 px-3">Category &amp; Brand</th>
                            <th class="py-4 px-3">Status</th>
                            <th class="py-4 px-3">Price (MRP / Selling)</th>
                            <th class="py-4 px-3">Stock On Hand</th>
                            <th class="py-4 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium text-slate-700 dark:text-slate-300">
                        @forelse($products as $product)
                            @php
                                $defaultVariant = $product->defaultVariant ?: $product->variants->first();
                                $totalStock = $product->variants->sum(fn ($v) => $v->inventories->sum('quantity_on_hand'));
                                $primaryMedia = $product->media->first();
                            @endphp
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                                <!-- Checkbox -->
                                <td class="p-4">
                                    <input 
                                        type="checkbox" 
                                        name="product_ids[]" 
                                        value="{{ $product->id }}" 
                                        x-model="selectedIds"
                                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 product-checkbox"
                                    />
                                </td>

                                <!-- Product Info -->
                                <td class="py-3 px-3">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-12 h-12 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden shrink-0 flex items-center justify-center">
                                            @if($primaryMedia)
                                                <img src="{{ $primaryMedia->path }}" alt="{{ $product->title }}" class="w-full h-full object-cover" />
                                            @else
                                                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            @endif
                                        </div>
                                        <div class="space-y-1 min-w-0">
                                            <a href="{{ route('seller.products.edit', $product) }}" class="font-bold text-slate-900 dark:text-white hover:text-indigo-600 line-clamp-1">
                                                {{ $product->title }}
                                            </a>
                                            <div class="flex items-center space-x-2 text-[10px]">
                                                <span class="font-mono text-slate-500 font-semibold">{{ $defaultVariant?->sku ?? 'NO-SKU' }}</span>
                                                <span class="text-slate-300 dark:text-slate-700">&bull;</span>
                                                <span class="px-1.5 py-0.5 rounded uppercase font-bold {{ $product->product_type->value === 'variable' ? 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                                                    {{ $product->product_type->value }}
                                                </span>
                                                @if($product->is_quick_capable)
                                                    <span class="px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300 font-bold">
                                                        ⚡ Quick
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Category & Brand -->
                                <td class="py-3 px-3">
                                    <div class="space-y-0.5">
                                        <div class="text-slate-900 dark:text-white font-medium">{{ $product->category?->name ?? 'Uncategorized' }}</div>
                                        <div class="text-[11px] text-slate-500">
                                            {{ $product->brand?->name ?: 'No Brand' }}
                                            @if($product->brand?->is_verified)
                                                <span class="text-emerald-500 ml-0.5" title="Verified Brand">&check;</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-3 px-3">
                                    <div class="space-y-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $product->status->badgeClass() }}">
                                            @if($product->status->value === 'pending_review')
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping mr-1.5"></span>
                                            @endif
                                            {{ $product->status->label() }}
                                        </span>
                                        @if($product->status->value === 'rejected' && $product->rejection_reason)
                                            <button 
                                                type="button" 
                                                @click="showRejectionModal(@js($product->title), @js($product->rejection_reason))"
                                                class="text-[10px] text-rose-600 hover:underline block font-semibold"
                                            >
                                                View Remarks &rarr;
                                            </button>
                                        @endif
                                    </div>
                                </td>

                                <!-- Pricing (Inline Quick Edit) -->
                                <td class="py-3 px-3">
                                    <div class="flex items-center space-x-2">
                                        <div>
                                            <div class="font-bold text-slate-900 dark:text-white" id="price-display-{{ $defaultVariant?->id }}">
                                                ₹{{ number_format((float) ($defaultVariant?->selling_price ?? 0), 2) }}
                                            </div>
                                            <div class="text-[10px] text-slate-400 line-through" id="mrp-display-{{ $defaultVariant?->id }}">
                                                ₹{{ number_format((float) ($defaultVariant?->mrp ?? 0), 2) }}
                                            </div>
                                        </div>
                                        @if($defaultVariant)
                                            <button 
                                                type="button" 
                                                @click="openQuickEdit({{ $defaultVariant->id }}, {{ $defaultVariant->mrp }}, {{ $defaultVariant->selling_price }}, {{ $totalStock }})"
                                                class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-400 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition" 
                                                title="Quick edit price &amp; stock"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>

                                <!-- Stock Level -->
                                <td class="py-3 px-3">
                                    <div class="space-y-1">
                                        <div class="flex items-center space-x-1.5">
                                            <span class="w-2 h-2 rounded-full {{ $totalStock > 5 ? 'bg-emerald-500' : ($totalStock > 0 ? 'bg-amber-500' : 'bg-rose-500') }}"></span>
                                            <span class="font-mono font-bold text-slate-900 dark:text-white" id="stock-display-{{ $defaultVariant?->id }}">
                                                {{ $totalStock }} units
                                            </span>
                                        </div>
                                        <div class="text-[10px] text-slate-400">
                                            {{ $totalStock > 5 ? 'In Stock' : ($totalStock > 0 ? 'Low Stock' : 'Out of Stock') }}
                                        </div>
                                    </div>
                                </td>

                                <!-- Actions Dropdown -->
                                <td class="py-3 px-3 text-right">
                                    <div class="flex items-center justify-end space-x-2">
                                        <x-button variant="outline" size="sm" href="{{ route('seller.products.edit', $product) }}">
                                            Edit
                                        </x-button>
                                        <button 
                                            type="button" 
                                            @click="confirmDelete({{ $product->id }}, @js($product->title))"
                                            class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition" 
                                            title="Delete Product"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-500">
                                    <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    <div class="font-bold text-sm text-slate-700 dark:text-slate-300">No products found matching criteria</div>
                                    <p class="text-xs text-slate-400 mt-1">Try adjusting your search filters or add a new catalogue item.</p>
                                    <div class="mt-4">
                                        <x-button variant="primary" size="sm" href="{{ route('seller.products.create') }}">
                                            Add New Product &rarr;
                                        </x-button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($products->hasPages())
                <div class="p-4 border-t border-slate-200/80 dark:border-slate-800">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </form>

    <!-- Delete Single Form -->
    <form id="delete-single-form" method="POST" action="" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    <!-- Inline Price & Stock Quick-Edit Modal -->
    <div 
        x-show="quickEditOpen" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
    >
        <div 
            class="bg-white dark:bg-slate-900 rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4"
            @click.outside="quickEditOpen = false"
        >
            <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-800">
                <h3 class="font-bold text-sm text-slate-900 dark:text-white">Inline Quick Update</h3>
                <button type="button" @click="quickEditOpen = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Maximum Retail Price (MRP ₹)</label>
                    <input 
                        type="number" 
                        step="0.01" 
                        min="0.01" 
                        x-model="quickForm.mrp" 
                        class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 font-mono text-xs" 
                    />
                </div>

                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Selling Price (₹)</label>
                    <input 
                        type="number" 
                        step="0.01" 
                        min="0.01" 
                        x-model="quickForm.selling_price" 
                        class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 font-mono text-xs" 
                    />
                    <template x-if="Number(quickForm.selling_price) > Number(quickForm.mrp)">
                        <p class="text-[11px] text-rose-500 mt-1 font-semibold">Selling price cannot exceed MRP.</p>
                    </template>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">On Hand Stock Quantity</label>
                    <input 
                        type="number" 
                        step="1" 
                        min="0" 
                        x-model="quickForm.quantity_on_hand" 
                        class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 font-mono text-xs" 
                    />
                </div>
            </div>

            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                <x-button variant="outline" size="sm" @click="quickEditOpen = false">
                    Cancel
                </x-button>
                <button 
                    type="button" 
                    @click="submitQuickEdit()" 
                    :disabled="Number(quickForm.selling_price) > Number(quickForm.mrp)"
                    class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-bold text-xs shadow-md transition"
                >
                    Save Updates
                </button>
            </div>
        </div>
    </div>

    <!-- Rejection Remarks Modal -->
    <div 
        x-show="rejectionModalOpen" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
    >
        <div 
            class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4"
            @click.outside="rejectionModalOpen = false"
        >
            <div class="flex items-center space-x-2 text-rose-600">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <h3 class="font-bold text-sm text-slate-900 dark:text-white" x-text="rejectionTitle">Moderation Remarks</h3>
            </div>
            <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-xs text-rose-800 dark:text-rose-200" x-text="rejectionRemarks">
            </div>
            <p class="text-[11px] text-slate-500">
                Please edit the requested attributes and click <strong>Submit for Review</strong> to resubmit to platform quality control.
            </p>
            <div class="flex justify-end">
                <x-button variant="outline" size="sm" @click="rejectionModalOpen = false">
                    Close
                </x-button>
            </div>
        </div>
    </div>
</div>

<script>
function sellerProductList() {
    return {
        selectedIds: [],
        quickEditOpen: false,
        rejectionModalOpen: false,
        rejectionTitle: '',
        rejectionRemarks: '',
        quickForm: {
            variant_id: null,
            mrp: 0,
            selling_price: 0,
            quantity_on_hand: 0
        },

        toggleSelectAll(e) {
            if (e.target.checked) {
                const boxes = document.querySelectorAll('.product-checkbox');
                this.selectedIds = Array.from(boxes).map(b => b.value);
            } else {
                this.selectedIds = [];
            }
        },

        submitBulkAction() {
            if (this.selectedIds.length === 0) return;
            const actionSelect = document.querySelector('select[name="action"]');
            if (!actionSelect.value) {
                alert('Please select an action to apply.');
                return;
            }
            if (actionSelect.value === 'delete' && !confirm('Are you sure you want to delete these products?')) {
                return;
            }
            document.getElementById('bulk-action-form').submit();
        },

        confirmDelete(productId, title) {
            if (!confirm(`Are you sure you want to delete "${title}"?`)) {
                return;
            }
            const form = document.getElementById('delete-single-form');
            form.action = `/seller/products/${productId}`;
            form.submit();
        },

        showRejectionModal(title, remarks) {
            this.rejectionTitle = title;
            this.rejectionRemarks = remarks;
            this.rejectionModalOpen = true;
        },

        openQuickEdit(variantId, mrp, sellingPrice, stock) {
            this.quickForm.variant_id = variantId;
            this.quickForm.mrp = mrp;
            this.quickForm.selling_price = sellingPrice;
            this.quickForm.quantity_on_hand = stock;
            this.quickEditOpen = true;
        },

        async submitQuickEdit() {
            try {
                const res = await fetch(`/seller/products/variants/${this.quickForm.variant_id}/quick-update`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(this.quickForm)
                });

                const data = await res.json();
                if (data.success) {
                    const priceEl = document.getElementById(`price-display-${this.quickForm.variant_id}`);
                    const mrpEl = document.getElementById(`mrp-display-${this.quickForm.variant_id}`);
                    const stockEl = document.getElementById(`stock-display-${this.quickForm.variant_id}`);
                    if (priceEl) priceEl.innerText = `₹${data.variant.selling_price}`;
                    if (mrpEl) mrpEl.innerText = `₹${data.variant.mrp}`;
                    if (stockEl) stockEl.innerText = `${data.variant.stock} units`;

                    this.quickEditOpen = false;
                    alert(data.message);
                } else {
                    alert(data.message || 'Error updating product.');
                }
            } catch (e) {
                alert('Server error occurred during quick update.');
            }
        }
    };
}
</script>
@endsection
