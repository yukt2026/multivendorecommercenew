@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Product Inspection: ' . $product->title])

@section('content')
<div class="space-y-6" x-data="{ rejectModalOpen: false }">
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
                Submitted by: <strong class="text-slate-700 dark:text-slate-200">{{ $product->seller->business_name }}</strong> &bull; HSN: <span class="font-mono">{{ $product->hsn_code ?? 'N/A' }}</span> &bull; Tax: {{ $product->tax_percent }}%
            </p>
        </div>

        <div class="flex items-center space-x-2.5">
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
                    <div class="w-full h-72 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex flex-col items-center justify-center p-6 text-center text-slate-400">
                        @if($product->media->count() > 0)
                            <span class="text-xs font-mono">{{ $product->media->first()->path }}</span>
                        @else
                            <svg class="w-12 h-12 text-slate-300 dark:text-slate-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span class="text-xs font-medium">Standard High-Res Primary Placeholder</span>
                        @endif
                    </div>

                    @if($product->media->count() > 1)
                        <div class="flex items-center space-x-2 overflow-x-auto pb-1">
                            @foreach($product->media as $media)
                                <div class="w-16 h-16 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shrink-0 flex items-center justify-center text-[10px] text-slate-400 font-mono">
                                    {{ $media->type }}
                                </div>
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
            <x-card title="Variants & Stock Inventory" subtitle="SKU combinations, pricing, and stock levels">
                <div class="space-y-3">
                    @forelse($product->variants as $variant)
                        <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-900 dark:text-white">{{ $variant->name }}</span>
                                @if($variant->is_default)
                                    <span class="px-2 py-0.2 rounded-full text-[9px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        Default SKU
                                    </span>
                                @endif
                            </div>

                            <div class="text-[11px] font-mono text-slate-400">
                                SKU: {{ $variant->sku }} &bull; Barcode: {{ $variant->barcode ?? 'N/A' }}
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
                        </div>
                    @empty
                        <div class="text-xs text-slate-400 italic py-3 text-center">No variants attached to this listing.</div>
                    @endforelse
                </div>
            </x-card>

            <!-- Specifications Table -->
            @if($product->specifications->count() > 0)
                <x-card title="Technical Specifications" subtitle="Additional key-value specifications">
                    <div class="space-y-1.5 text-xs">
                        @foreach($product->specifications as $spec)
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span class="text-slate-400">{{ $spec->key }}</span>
                                <strong class="text-slate-800 dark:text-slate-200">{{ $spec->value }}</strong>
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
