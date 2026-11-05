@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Product Moderation Queue'])

@section('content')
<div class="space-y-6" x-data="moderationQueue()">
    <x-page-header 
        title="Product Moderation & Quality Queue" 
        subtitle="Review merchant product listing submissions, inspect specifications, ensure compliance, and approve into live catalogue."
        :breadcrumbs="['Command Central' => route('admin.dashboard'), 'Catalogue' => '#', 'Moderation Queue' => '#']"
    >
        <x-slot name="badge">
            <x-badge variant="warning" dot pulse size="sm">
                {{ $stats['pending_count'] }} Awaiting Review
            </x-badge>
        </x-slot>
    </x-page-header>

    <!-- 4 Stats Counters Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card 
            label="Pending Moderation" 
            :value="number_format($stats['pending_count'])" 
            change="Awaiting Decision" 
            changeType="neutral" 
            color="amber"
        />
        <x-stat-card 
            label="Approved Today" 
            :value="number_format($stats['approved_today'])" 
            change="Published to Catalog" 
            changeType="up" 
            color="emerald"
        />
        <x-stat-card 
            label="Rejected Listings" 
            :value="number_format($stats['rejected_count'])" 
            change="Feedback Sent" 
            changeType="down" 
            color="rose"
        />
        <x-stat-card 
            label="Total Active SKUs" 
            :value="number_format($stats['total_active'])" 
            change="Live Omnichannel" 
            changeType="up" 
            color="primary"
        />
    </div>

    <!-- Filters Bar Card -->
    <x-card padding="p-5">
        <form method="GET" action="{{ route('admin.catalogue.moderation.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Search Products</label>
                <input 
                    type="text" 
                    name="search" 
                    value="{{ $filters['search'] ?? '' }}" 
                    placeholder="Product title, SKU, HSN, or seller..." 
                    class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                />
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Moderation Status</label>
                <select name="status" class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                    <option value="pending_review" {{ ($filters['status'] ?? '') === 'pending_review' ? 'selected' : '' }}>Pending Review</option>
                    <option value="rejected" {{ ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' }}>Rejected by Admin</option>
                    <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Live in Catalog</option>
                    <option value="all" {{ ($filters['status'] ?? '') === 'all' ? 'selected' : '' }}>All Statuses</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Category</label>
                <select name="category_id" class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ ($filters['category_id'] ?? '') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit" class="flex-1 py-2 px-3 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition">Filter</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.catalogue.moderation.index') }}" class="py-2 px-3 text-xs font-semibold rounded-xl bg-slate-200 dark:bg-slate-750 text-slate-700 dark:text-slate-300">Reset</a>
                @endif
            </div>
        </form>
    </x-card>

    <!-- Bulk Approve Floating Banner -->
    <div 
        x-show="selectedIds.length > 0" 
        x-cloak
        class="p-4 rounded-2xl bg-indigo-50 dark:bg-indigo-950/70 border border-indigo-200 dark:border-indigo-800 shadow-lg flex items-center justify-between"
    >
        <div class="text-xs font-semibold text-indigo-900 dark:text-indigo-200">
            <span class="font-bold" x-text="selectedIds.length"></span> product(s) selected for bulk moderation
        </div>
        <form method="POST" action="{{ route('admin.catalogue.moderation.bulk_approve') }}" class="flex items-center space-x-2">
            @csrf
            <template x-for="id in selectedIds" :key="id">
                <input type="hidden" name="product_ids[]" :value="id">
            </template>
            <button 
                type="submit" 
                onclick="return confirm('Approve all selected listings into active catalogue? Sellers will be notified.')"
                class="px-4 py-1.5 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition"
            >
                Bulk Approve Selected
            </button>
            <button type="button" @click="selectedIds = []" class="px-2.5 py-1.5 text-xs text-slate-500 hover:text-slate-700">Cancel</button>
        </form>
    </div>

    <!-- Products Queue Table Card -->
    <x-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40 text-slate-400 font-semibold uppercase">
                        <th class="py-3 px-4 w-10 text-center">
                            <input type="checkbox" @change="toggleAll($event)" class="rounded-sm border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500">
                        </th>
                        <th class="py-3 px-4">Product Details</th>
                        <th class="py-3 px-4">Merchant Origin</th>
                        <th class="py-3 px-4">Category & Brand</th>
                        <th class="py-3 px-4 text-right">Pricing (MRP / Sell)</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-6 text-right">Moderate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($products as $product)
                        @php
                            $defaultVar = $product->variants->first();
                        @endphp
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-850/50">
                            <td class="py-3.5 px-4 text-center">
                                <input type="checkbox" value="{{ $product->id }}" x-model="selectedIds" class="rounded-sm border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500">
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center font-bold text-slate-400 shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.catalogue.moderation.show', $product) }}" class="font-bold text-slate-900 dark:text-white hover:text-indigo-600 transition">
                                            {{ $product->title }}
                                        </a>
                                        <div class="text-[11px] text-slate-400">
                                            SKU: <span class="font-mono">{{ $defaultVar?->sku ?? 'N/A' }}</span> &bull; HSN: <span class="font-mono">{{ $product->hsn_code ?? 'N/A' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-slate-700 dark:text-slate-300">
                                <span class="font-medium text-slate-900 dark:text-white">{{ $product->seller?->business_name ?? 'N/A' }}</span>
                                <div class="text-[10px] text-slate-400">Owner: {{ $product->seller?->user?->name ?? 'N/A' }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-medium text-slate-900 dark:text-white">{{ $product->category?->name ?? 'Uncategorized' }}</div>
                                <div class="text-[10px] text-slate-400">Brand: {{ $product->brand?->name ?? 'Generic / No Brand' }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                @if($defaultVar)
                                    <div class="font-mono font-bold text-slate-900 dark:text-white">₹{{ number_format((float) $defaultVar->selling_price, 2) }}</div>
                                    <div class="text-[10px] line-through text-slate-400 font-mono">₹{{ number_format((float) $defaultVar->mrp, 2) }}</div>
                                @else
                                    <span class="text-slate-400 italic">No variants</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $product->status->badgeClass() }}">
                                    {{ $product->status->label() }}
                                </span>
                            </td>
                            <td class="py-3.5 px-6 text-right">
                                <div class="inline-flex items-center space-x-1.5">
                                    <a 
                                        href="{{ route('admin.catalogue.moderation.show', $product) }}" 
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition"
                                    >
                                        Inspect &rarr;
                                    </a>

                                    @if($product->status !== \App\Enums\ProductStatus::ACTIVE)
                                        <form method="POST" action="{{ route('admin.catalogue.moderation.approve', $product) }}" class="inline">
                                            @csrf
                                            <button 
                                                type="submit" 
                                                onclick="return confirm('Approve this listing into live catalogue?')"
                                                class="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition"
                                            >
                                                Approve
                                            </button>
                                        </form>

                                        <button 
                                            type="button" 
                                            @click="openRejectModal({{ $product->id }}, '{{ addslashes($product->title) }}')"
                                            class="px-2.5 py-1 text-xs font-bold rounded-lg bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 transition"
                                        >
                                            Reject
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-slate-400">
                                <x-empty-state 
                                    title="Moderation queue is empty" 
                                    description="No products currently match your moderation criteria. New seller submissions will appear here." 
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 dark:border-slate-800 px-4 py-3">
            <x-pagination :paginator="$products" />
        </div>
    </x-card>

    <!-- Reject Product Modal -->
    <x-modal name="reject-product-modal" title="Reject Product Listing" size="md">
        <form :action="'{{ url('/admin/catalogue/moderation') }}/' + rejectProductId + '/reject'" method="POST" class="space-y-4">
            @csrf
            <div>
                <p class="text-xs text-slate-500 mb-3 leading-relaxed">
                    Provide clear, constructive feedback on why <strong class="text-slate-800 dark:text-slate-200" x-text="rejectProductTitle"></strong> was rejected so the merchant can correct and resubmit.
                </p>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Mandatory Rejection Remarks <span class="text-rose-500">*</span>
                </label>
                <textarea 
                    name="reason" 
                    rows="3" 
                    required 
                    minlength="5"
                    placeholder="e.g. Incomplete nutritional table, image resolution too low, incorrect HSN tax code..." 
                    class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                ></textarea>
            </div>

            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                <button type="button" @click="$dispatch('close-modal', 'reject-product-modal')" class="px-3 py-1.5 text-xs text-slate-600">Cancel</button>
                <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-rose-600 hover:bg-rose-700 text-white shadow-xs transition">Confirm Rejection</button>
            </div>
        </form>
    </x-modal>
</div>

<script>
    function moderationQueue() {
        return {
            selectedIds: [],
            rejectProductId: null,
            rejectProductTitle: '',
            allIds: @js($products->pluck('id')->toArray()),

            toggleAll(e) {
                if (e.target.checked) {
                    this.selectedIds = [...this.allIds];
                } else {
                    this.selectedIds = [];
                }
            },

            openRejectModal(id, title) {
                this.rejectProductId = id;
                this.rejectProductTitle = title;
                this.$dispatch('open-modal', 'reject-product-modal');
            }
        };
    }
</script>
@endsection
