@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Product Inspection: ' . $product->title])

@section('content')
@php
    $initialImage = $product->media->first()?->url ?? $product->primaryImageUrl();
@endphp
<div class="space-y-6" x-data="{ activeImage: '{{ $initialImage }}', rejectModalOpen: false }">
    <!-- Top Navigation & Action Toolbar -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-2 border-b border-slate-200 dark:border-slate-800">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-indigo-600">Command Central</a>
                <span>&bull;</span>
                <a href="{{ route('admin.catalogue.moderation.index') }}" class="hover:text-indigo-600">Moderation Queue</a>
                <span>&bull;</span>
                <span class="font-bold text-slate-700 dark:text-slate-300">Listing #{{ $product->id }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    {{ $product->title }}
                </h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $product->status->badgeClass() }}">
                    {{ $product->status->label() }}
                </span>
                @if($product->is_quick_capable)
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                        10-30m Quick Delivery
                    </span>
                @endif
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Submitted by: <a href="{{ route('admin.sellers.show', $product->seller) }}" class="font-bold text-slate-700 dark:text-slate-200 hover:text-indigo-600">{{ $product->seller->business_name }}</a> &bull; HSN: <span class="font-mono">{{ $product->hsn_code ?? 'N/A' }}</span> &bull; Tax: {{ $product->tax_percent }}%
            </p>
        </div>

        <div class="flex items-center space-x-2.5">
            @if($product->status === \App\Enums\ProductStatus::ACTIVE && $product->slug)
                <a 
                    href="{{ route('product.show', $product->slug) }}" 
                    target="_blank" 
                    class="px-3.5 py-2 text-xs font-bold rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 border border-indigo-200 dark:border-indigo-800 transition flex items-center"
                >
                    <span>Customer Storefront</span>
                    <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            @endif

            <a 
                href="{{ route('admin.catalogue.moderation.edit', $product) }}" 
                class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 transition flex items-center"
            >
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit on Behalf
            </a>

            @if($product->status !== \App\Enums\ProductStatus::ACTIVE)
                <form method="POST" action="{{ route('admin.catalogue.moderation.approve', $product) }}">
                    @csrf
                    <button 
                        type="submit" 
                        onclick="return confirm('Approve listing into live catalogue?')"
                        class="px-4 py-2 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition flex items-center"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        Approve Product
                    </button>
                </form>

                <button 
                    type="button" 
                    @click="$dispatch('open-modal', 'reject-product-modal')"
                    class="px-3.5 py-2 text-xs font-bold rounded-xl bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 transition flex items-center"
                >
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Reject with Feedback
                </button>
            @else
                <button 
                    type="button" 
                    @click="$dispatch('open-modal', 'reject-product-modal')"
                    class="px-3.5 py-2 text-xs font-bold rounded-xl bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 transition flex items-center"
                >
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    Revoke & Reject
                </button>
            @endif
        </div>
    </div>

    <!-- Rejection Callout if already rejected -->
    @if($product->status === \App\Enums\ProductStatus::REJECTED && $product->rejection_reason)
        <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-xs text-rose-900 dark:text-rose-200 flex items-start space-x-3">
            <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <strong class="font-bold">Rejection Feedback Sent to Merchant:</strong> {{ $product->rejection_reason }}
            </div>
        </div>
    @endif

    <!-- Side-by-Side Split Preview -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left Side: Consumer Storefront Presentation (7 Columns) -->
        <div class="lg:col-span-7 space-y-6">
            <!-- Media Gallery Box -->
            <x-card title="Product Gallery & Visual Assets" subtitle="Media previews submitted by the merchant">
                <div class="space-y-3">
                    <div class="w-full h-80 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden flex items-center justify-center p-2 relative group">
                        <img 
                            :src="activeImage" 
                            alt="{{ $product->title }}" 
                            class="max-h-full max-w-full object-contain rounded-xl transition-all duration-300"
                            onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\' width=\'100\' height=\'100\'><rect width=\'100\' height=\'100\' rx=\'16\' fill=\'%23f1f5f9\'/><text x=\'50\' y=\'58\' font-size=\'32\' text-anchor=\'middle\'>🛍️</text></svg>';"
                        />
                        <a 
                            :href="activeImage" 
                            target="_blank" 
                            class="absolute bottom-3 right-3 px-3 py-1.5 text-[11px] font-bold rounded-lg bg-black/70 hover:bg-black text-white backdrop-blur-xs opacity-0 group-hover:opacity-100 transition flex items-center space-x-1"
                        >
                            <span>Open Full Size</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>

                    @if($product->media->count() > 1)
                        <div class="flex items-center space-x-2 overflow-x-auto pb-1 pt-1">
                            @foreach($product->media as $media)
                                @php $mUrl = $media->url ?: image_url($media->path); @endphp
                                <button 
                                    type="button" 
                                    @click="activeImage = '{{ $mUrl }}'"
                                    :class="activeImage === '{{ $mUrl }}' ? 'ring-2 ring-indigo-600 border-indigo-600 shadow-sm' : 'border-slate-200 dark:border-slate-700 opacity-60 hover:opacity-100'"
                                    class="w-16 h-16 rounded-xl bg-white dark:bg-slate-800 border overflow-hidden shrink-0 flex items-center justify-center transition p-1"
                                >
                                    <img src="{{ $mUrl }}" alt="Thumbnail" class="w-full h-full object-cover rounded-lg" />
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-card>

            <!-- Description & Copywriting -->
            <x-card title="Product Copy & Statutory Details" subtitle="Customer facing descriptions and specifications">
                <div class="space-y-4 text-xs">
                    @if($product->short_desc)
                        <div>
                            <span class="font-bold uppercase tracking-wider text-slate-400 block mb-1">Short Summary</span>
                            <p class="text-slate-800 dark:text-slate-200 leading-relaxed bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
                                {{ $product->short_desc }}
                            </p>
                        </div>
                    @endif

                    <div>
                        <span class="font-bold uppercase tracking-wider text-slate-400 block mb-1">Full Detailed Description</span>
                        <div class="text-slate-700 dark:text-slate-300 leading-relaxed bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl border border-slate-100 dark:border-slate-800 prose dark:prose-invert max-w-none text-xs">
                            {!! nl2br(e($product->description ?? 'No detailed description provided.')) !!}
                        </div>
                    </div>

                    <!-- Commercial Capabilities -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 text-center">
                            <span class="text-[10px] text-slate-400 block">Quick Commerce</span>
                            <strong class="{{ $product->is_quick_capable ? 'text-amber-600' : 'text-slate-400' }} text-xs">
                                {{ $product->is_quick_capable ? 'Yes (10-30m)' : 'No' }}
                            </strong>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 text-center">
                            <span class="text-[10px] text-slate-400 block">Cash on Delivery</span>
                            <strong class="{{ $product->is_cod_allowed ? 'text-emerald-600' : 'text-slate-400' }} text-xs">
                                {{ $product->is_cod_allowed ? 'Allowed' : 'Prepaid Only' }}
                            </strong>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 text-center">
                            <span class="text-[10px] text-slate-400 block">Returns Allowed</span>
                            <strong class="{{ $product->is_returnable ? 'text-emerald-600' : 'text-rose-500' }} text-xs">
                                {{ $product->is_returnable ? 'Yes (' . $product->return_window_days . 'd)' : 'Non-returnable' }}
                            </strong>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 text-center">
                            <span class="text-[10px] text-slate-400 block">GST / Tax Slab</span>
                            <strong class="text-indigo-600 text-xs font-mono">
                                {{ $product->tax_percent }}%
                            </strong>
                        </div>
                    </div>
                </div>
            </x-card>
        </div>

        <!-- Right Side: Commercials, Variants & Seller (5 Columns) -->
        <div class="lg:col-span-5 space-y-6">
            <!-- Merchant Origin Card -->
            <x-card title="Merchant Business & Brand" subtitle="Origin store identity">
                <div class="space-y-3 text-xs">
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-900 dark:text-white">{{ $product->seller->business_name }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $product->seller->status->badgeClass() }}">
                                {{ $product->seller->status->label() }}
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-400 mt-0.5">
                            {{ $product->seller->legal_name }} &bull; Owner: {{ $product->seller->user?->name ?? 'N/A' }}
                        </div>
                        <div class="mt-2 pt-2 border-t border-slate-200 dark:border-slate-700 flex justify-between items-center text-[11px]">
                            <a href="{{ route('admin.sellers.show', $product->seller) }}" class="text-indigo-600 font-bold hover:underline">
                                View Merchant Profile &rarr;
                            </a>
                            <span class="font-mono text-slate-400">#{{ $product->seller->id }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500">Taxonomy Category:</span>
                        <strong class="text-slate-900 dark:text-white">{{ $product->category->name }}</strong>
                    </div>

                    <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500">Brand Affiliation:</span>
                        <div class="flex items-center space-x-1.5">
                            <strong class="text-slate-900 dark:text-white">{{ $product->brand?->name ?? 'No Brand' }}</strong>
                            @if($product->brand?->is_verified)
                                <svg class="w-3.5 h-3.5 text-indigo-500 fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            @endif
                        </div>
                    </div>
                </div>
            </x-card>

            <!-- Product Variants & Pricing Table -->
            <x-card title="Variants & Stock Inventory" subtitle="SKU combinations, pricing, and live inventory">
                <div class="space-y-3">
                    @forelse($product->variants as $variant)
                        @php
                            $totalOnHand = $variant->inventories->sum('quantity_on_hand');
                            $totalReserved = $variant->inventories->sum('quantity_reserved');
                            $available = max(0, $totalOnHand - $totalReserved);
                        @endphp
                        <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-900 dark:text-white">{{ $variant->name }}</span>
                                <div class="flex items-center space-x-1.5">
                                    @if($variant->is_default)
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                            Default SKU
                                        </span>
                                    @endif
                                    @if($available > 0)
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            {{ $available }} Available
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            0 in Stock
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="text-[11px] font-mono text-slate-400">
                                SKU: <span class="font-bold text-slate-600 dark:text-slate-300">{{ $variant->sku }}</span> &bull; Barcode: {{ $variant->barcode ?? 'N/A' }}
                            </div>

                            <div class="grid grid-cols-3 gap-2 pt-1 text-[11px] font-mono">
                                <div>
                                    <span class="text-slate-400 block text-[9px]">MRP</span>
                                    <span class="line-through text-slate-500">₹{{ number_format((float) $variant->mrp, 2) }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[9px]">Selling Price</span>
                                    <span class="font-bold text-slate-900 dark:text-white">₹{{ number_format((float) $variant->selling_price, 2) }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[9px]">Cost Price</span>
                                    <span class="text-slate-500">₹{{ number_format((float) $variant->cost_price, 2) }}</span>
                                </div>
                            </div>

                            @if($variant->inventories->isNotEmpty())
                                <div class="pt-2 border-t border-slate-100 dark:border-slate-800 text-[10px] text-slate-500 flex flex-wrap gap-2">
                                    <span class="font-bold text-slate-400">Inventory Distribution:</span>
                                    @foreach($variant->inventories as $inv)
                                        <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono">
                                            {{ $inv->location?->name ?? 'Hub #' . $inv->location_id }}: {{ $inv->quantity_on_hand }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-xs text-slate-400 italic py-3 text-center">No variants attached to this listing.</div>
                    @endforelse
                </div>
            </x-card>

            <!-- Specifications Table -->
            @if($product->specifications->count() > 0)
                <x-card title="Technical Specifications" subtitle="Key-value product specifications">
                    <div class="space-y-1.5 text-xs">
                        @foreach($product->specifications as $spec)
                            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                                <span class="text-slate-400 font-medium">
                                    {{ $spec->key }}
                                    @if($spec->group)
                                        <span class="text-[10px] text-slate-400 font-normal">({{ $spec->group }})</span>
                                    @endif
                                </span>
                                <strong class="text-slate-800 dark:text-slate-200">{{ $spec->value }}</strong>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

            <!-- Audit Trail & Moderation History -->
            @if(isset($activities) && $activities->count() > 0)
                <x-card title="Moderation Audit Trail" subtitle="Accountability log of decisions and edits">
                    <div class="space-y-2.5 text-xs">
                        @foreach($activities as $act)
                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ $act->description }}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">{{ $act->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    By: <strong class="text-slate-700 dark:text-slate-300">{{ $act->causer?->name ?? 'System' }}</strong>
                                </div>
                                @if(!empty($act->properties['reason']))
                                    <div class="text-[11px] text-rose-600 dark:text-rose-400 bg-rose-50/50 dark:bg-rose-950/30 p-1.5 rounded-lg border border-rose-100 dark:border-rose-900/40">
                                        Reason: {{ $act->properties['reason'] }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>
    </div>

    <!-- Rejection Modal -->
    <x-modal name="reject-product-modal" title="Reject Product Listing" size="md">
        <form action="{{ route('admin.catalogue.moderation.reject', $product) }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <p class="text-xs text-slate-500 mb-3 leading-relaxed">
                    Provide detailed feedback for <strong class="text-slate-900 dark:text-white">{{ $product->title }}</strong> so the merchant can fix the listing and resubmit.
                </p>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Mandatory Rejection Reason <span class="text-rose-500">*</span>
                </label>
                <textarea 
                    name="reason" 
                    rows="3" 
                    required 
                    minlength="5"
                    placeholder="e.g. Inappropriate images, HSN code mismatch with 18% tax category, incorrect weight dimensions..." 
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
@endsection
