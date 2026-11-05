@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Edit Product'])

@section('content')
@php
    $defaultVariant = $product->defaultVariant ?: $product->variants->first();
    $existingSpecs = $product->specifications->map(fn($s) => ['group' => $s->group, 'key' => $s->key, 'value' => $s->value])->values();
    $existingVariants = $product->variants->map(function ($v) {
        return [
            'id' => $v->id,
            'name' => $v->name,
            'sku' => $v->sku,
            'mrp' => (float) $v->mrp,
            'selling_price' => (float) $v->selling_price,
            'stock' => $v->inventories->sum('quantity_on_hand'),
            'weight_grams' => (int) $v->weight_grams,
            'is_active' => (bool) $v->is_active,
            'attributes' => $v->attributes ?: [],
        ];
    })->values();
@endphp

<div class="space-y-6" x-data="sellerProductForm({
    isEdit: true,
    productType: @js($product->product_type->value),
    title: @js($product->title),
    checkSkuUrl: '{{ route("seller.products.check_sku") }}',
    initialVariants: @js($existingVariants),
    initialSpecs: @js($existingSpecs),
    initialSku: @js($defaultVariant?->sku ?? ''),
    initialMrp: {{ (float) ($defaultVariant?->mrp ?? 0) }},
    initialPrice: {{ (float) ($defaultVariant?->selling_price ?? 0) }}
})">
    <!-- Header -->
    <x-page-header 
        title="Edit: {{ $product->title }}" 
        subtitle="Manage product specifications, variant pricing matrix, media gallery, and logistics settings."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Products' => route('seller.products.index'), 'Edit' => '#']"
    >
        <x-slot name="badge">
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border {{ $product->status->badgeClass() }}">
                @if($product->status->value === 'pending_review')
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping mr-1.5"></span>
                @endif
                {{ $product->status->label() }}
            </span>
        </x-slot>

        <x-slot name="actions">
            @if($product->status->value === 'active')
                <x-button variant="outline" size="sm" href="{{ route('store.show', $seller->stores->first()?->slug ?? 'store') }}" target="_blank">
                    View on Storefront &rarr;
                </x-button>
            @endif
            <x-button variant="outline" size="sm" href="{{ route('seller.products.index') }}">
                &larr; Back to Catalogue
            </x-button>
        </x-slot>
    </x-page-header>

    @if($product->status->value === 'rejected' && $product->rejection_reason)
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-900 text-xs">
            <div class="flex items-center space-x-2 text-rose-700 dark:text-rose-300 font-bold mb-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Rejection Notice from Quality Control:</span>
            </div>
            <p class="text-rose-800 dark:text-rose-200 pl-6">{{ $product->rejection_reason }}</p>
            <p class="text-rose-600 dark:text-rose-400 pl-6 mt-1 text-[11px]">Please revise the highlighted details and click <strong>Submit for Review</strong>.</p>
        </div>
    @endif

    @if($product->status->value === 'active')
        <div class="p-4 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 text-indigo-900 dark:text-indigo-200 text-xs flex items-center space-x-2">
            <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
            <span><strong>Policy Guard:</strong> Editing sensitive fields (Title, Category, Brand, Tax, or Variant Selling Prices) on an active product will automatically re-trigger QC moderation review.</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-900 text-rose-800 dark:text-rose-200 text-xs">
            <div class="font-bold mb-1">Please correct the following errors:</div>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form 
        id="product-form"
        method="POST" 
        action="{{ route('seller.products.update', $product) }}" 
        enctype="multipart/form-data"
        @submit="isSubmitting = true"
        class="space-y-6 pb-24"
    >
        @csrf
        @method('PUT')
        <input type="hidden" name="submit_action" x-model="submitAction" value="save_changes" />

        <!-- Sticky Save Bar -->
        <div class="sticky top-16 z-30 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-lg flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center space-x-2">
                <span class="w-2.5 h-2.5 rounded-full" :class="isDirty ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500'"></span>
                <span class="text-xs font-bold text-slate-800 dark:text-slate-200" x-text="isDirty ? 'Unsaved modifications pending' : 'All changes saved'"></span>
            </div>
            <div class="flex items-center space-x-2.5">
                @if(in_array($product->status->value, ['draft', 'rejected'], true))
                    <button 
                        type="button" 
                        @click="submitForm('save_draft')"
                        class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs hover:bg-slate-50 transition"
                    >
                        Save as Draft
                    </button>
                    <button 
                        type="button" 
                        @click="submitForm('submit_review')"
                        class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shadow-md transition flex items-center space-x-1.5"
                    >
                        <span>Submit for QC Review</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                @else
                    <button 
                        type="button" 
                        @click="submitForm('save_changes')"
                        class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-600/20 transition flex items-center space-x-1.5"
                    >
                        <span>Save Changes</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </button>
                @endif
            </div>
        </div>

        <!-- 1. Basic Details -->
        <x-card title="1. Basic Information" subtitle="Update title, product type, and rich description">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Product Title <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="title" 
                        x-model="title"
                        @input="markDirty()"
                        value="{{ old('title', $product->title) }}" 
                        required
                        class="w-full p-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-medium" 
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Structure: <span class="uppercase text-indigo-600 font-extrabold">{{ $product->product_type->value }}</span>
                    </label>
                    <input type="hidden" name="product_type" value="{{ $product->product_type->value }}" />
                    <p class="text-[11px] text-slate-500">Product structure type (simple vs variable) is locked after creation to preserve SKU tracking history.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Short Summary / Tagline
                    </label>
                    <input 
                        type="text" 
                        name="short_desc" 
                        @input="markDirty()"
                        value="{{ old('short_desc', $product->short_desc) }}" 
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white" 
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Full Product Description (Rich HTML)
                    </label>
                    <textarea 
                        name="description" 
                        rows="6"
                        @input="markDirty()"
                        class="w-full p-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
                    >{{ old('description', $product->description) }}</textarea>
                </div>
            </div>
        </x-card>

        <!-- 2. Category & Brand -->
        <x-card title="2. Category & Brand Classification" subtitle="Tag the item into the correct storefront aisle and brand registry">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Storefront Category <span class="text-rose-500">*</span>
                    </label>
                    <select 
                        name="category_id" 
                        required 
                        @change="markDirty()"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-medium"
                    >
                        @foreach($categories as $cat)
                            <option value="{{ $cat['id'] }}" {{ old('category_id', $product->category_id) == $cat['id'] ? 'selected' : '' }}>
                                {!! $cat['path'] !!}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Brand / Manufacturer
                    </label>
                    <select 
                        name="brand_id" 
                        @change="markDirty()"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-medium"
                    >
                        <option value="">No Brand / Generic</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" {{ old('brand_id', $product->brand_id) == $brand->id ? 'selected' : '' }}>
                                {{ $brand->name }} {{ $brand->is_verified ? '✓' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-card>

        <!-- 3. Media & Gallery -->
        <x-card title="3. Product Media & Gallery" subtitle="Existing media assets and multi-upload additions">
            <div class="space-y-4">
                @if($product->media->count() > 0)
                    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3">
                        @foreach($product->media as $idx => $m)
                            <div class="relative group rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden bg-slate-100 dark:bg-slate-900 aspect-square">
                                @if($m->type === 'video')
                                    <video src="{{ $m->path }}" class="w-full h-full object-cover"></video>
                                    <span class="absolute top-1 left-1 px-1.5 py-0.5 rounded bg-black/70 text-white text-[9px] font-bold">VIDEO</span>
                                @else
                                    <img src="{{ $m->path }}" alt="Product Media" class="w-full h-full object-cover" />
                                @endif

                                @if($idx === 0)
                                    <span class="absolute bottom-1 left-1 px-1.5 py-0.5 rounded bg-indigo-600 text-white text-[9px] font-bold">PRIMARY</span>
                                @endif

                                <label class="absolute top-1 right-1 p-1 bg-rose-600/90 text-white rounded cursor-pointer hover:bg-rose-700 transition" title="Delete media">
                                    <input type="checkbox" name="deleted_media_ids[]" value="{{ $m->id }}" @change="markDirty()" class="w-3.5 h-3.5 text-rose-600 rounded" />
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="p-6 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/40 text-center">
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
                        Upload Additional Photos / Video Clips
                    </div>
                    <input 
                        type="file" 
                        name="media[]" 
                        multiple 
                        accept="image/*,video/*"
                        @change="markDirty()"
                        class="mt-3 block mx-auto text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" 
                    />
                </div>
            </div>
        </x-card>

        <!-- 4. Pricing, Inventory & Variants -->
        @if($product->product_type->value === 'simple')
            @php
                $stockQty = $defaultVariant?->inventories->sum('quantity_on_hand') ?? 0;
            @endphp
            <x-card title="4. Pricing & Inventory (Simple Item)" subtitle="Configure price points, stock units, and shipping dimensions">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                SKU Code <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="simple_sku" 
                                x-model="simpleSku"
                                @input="markDirty()"
                                value="{{ old('simple_sku', $defaultVariant?->sku) }}" 
                                required
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Barcode / EAN
                            </label>
                            <input 
                                type="text" 
                                name="simple_barcode" 
                                @input="markDirty()"
                                value="{{ old('simple_barcode', $defaultVariant?->barcode) }}" 
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Maximum Retail Price (₹) <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="number" 
                                step="0.01" 
                                min="0.01"
                                name="simple_mrp" 
                                x-model="simpleMrp"
                                @input="markDirty()"
                                value="{{ old('simple_mrp', (float) ($defaultVariant?->mrp ?? 0)) }}" 
                                required
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Selling Price (₹) <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="number" 
                                step="0.01" 
                                min="0.01"
                                name="simple_selling_price" 
                                x-model="simplePrice"
                                @input="markDirty()"
                                value="{{ old('simple_selling_price', (float) ($defaultVariant?->selling_price ?? 0)) }}" 
                                required
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Purchase Cost (₹)
                            </label>
                            <input 
                                type="number" 
                                step="0.01" 
                                min="0"
                                name="simple_cost_price" 
                                @input="markDirty()"
                                value="{{ old('simple_cost_price', (float) ($defaultVariant?->cost_price ?? 0)) }}" 
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Stock on Hand
                            </label>
                            <input 
                                type="number" 
                                step="1" 
                                min="0"
                                name="simple_stock" 
                                @input="markDirty()"
                                value="{{ old('simple_stock', $stockQty) }}" 
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Low Stock Alert Limit
                            </label>
                            <input 
                                type="number" 
                                step="1" 
                                min="1"
                                name="simple_low_stock_threshold" 
                                @input="markDirty()"
                                value="{{ old('simple_low_stock_threshold', $defaultVariant?->inventories->first()?->low_stock_threshold ?? 5) }}" 
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Weight (grams)
                            </label>
                            <input 
                                type="number" 
                                step="1" 
                                min="0"
                                name="simple_weight_grams" 
                                @input="markDirty()"
                                value="{{ old('simple_weight_grams', $defaultVariant?->weight_grams ?? 500) }}" 
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Shipping Dimensions (cm) - Required for Cargo Logistics
                        </label>
                        <div class="grid grid-cols-3 gap-3">
                            <input type="number" step="0.1" min="0" name="simple_length" placeholder="Length (cm)" value="{{ old('simple_length', $defaultVariant?->length ?? 10) }}" @input="markDirty()" class="p-2 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700" />
                            <input type="number" step="0.1" min="0" name="simple_width" placeholder="Width (cm)" value="{{ old('simple_width', $defaultVariant?->width ?? 10) }}" @input="markDirty()" class="p-2 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700" />
                            <input type="number" step="0.1" min="0" name="simple_height" placeholder="Height (cm)" value="{{ old('simple_height', $defaultVariant?->height ?? 15) }}" @input="markDirty()" class="p-2 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700" />
                        </div>
                    </div>
                </div>
            </x-card>
        @else
            <!-- VARIABLE PRODUCTS MATRIX -->
            <x-card title="4. Variants Matrix" subtitle="Update pricing, stock on hand, and active status for each variant">
                <div class="space-y-4">
                    <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-100 dark:bg-slate-950 text-[10px] uppercase font-bold text-slate-500">
                                <tr>
                                    <th class="p-2.5">Variant</th>
                                    <th class="p-2.5">SKU *</th>
                                    <th class="p-2.5">MRP *</th>
                                    <th class="p-2.5">Selling Price *</th>
                                    <th class="p-2.5">Stock</th>
                                    <th class="p-2.5">Weight (g)</th>
                                    <th class="p-2.5 text-center">Active</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <template x-for="(v, vIdx) in variants" :key="vIdx">
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900">
                                        <td class="p-2.5 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                            <span x-text="v.name"></span>
                                            <input type="hidden" :name="`variants[${vIdx}][id]`" :value="v.id" />
                                            <input type="hidden" :name="`variants[${vIdx}][name]`" :value="v.name" />
                                            <input type="hidden" :name="`variants[${vIdx}][attributes]`" :value="JSON.stringify(v.attributes)" />
                                        </td>
                                        <td class="p-2.5">
                                            <input type="text" :name="`variants[${vIdx}][sku]`" x-model="v.sku" required @input="markDirty()" class="w-28 p-1 text-xs font-mono rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
                                        </td>
                                        <td class="p-2.5">
                                            <input type="number" step="0.01" min="0.01" :name="`variants[${vIdx}][mrp]`" x-model="v.mrp" required @input="markDirty()" class="w-20 p-1 text-xs font-mono rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
                                        </td>
                                        <td class="p-2.5">
                                            <input type="number" step="0.01" min="0.01" :name="`variants[${vIdx}][selling_price]`" x-model="v.selling_price" required @input="markDirty()" class="w-20 p-1 text-xs font-mono rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
                                        </td>
                                        <td class="p-2.5">
                                            <input type="number" step="1" min="0" :name="`variants[${vIdx}][stock]`" x-model="v.stock" @input="markDirty()" class="w-16 p-1 text-xs font-mono rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
                                        </td>
                                        <td class="p-2.5">
                                            <input type="number" step="1" min="0" :name="`variants[${vIdx}][weight_grams]`" x-model="v.weight_grams" @input="markDirty()" class="w-16 p-1 text-xs font-mono rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
                                        </td>
                                        <td class="p-2.5 text-center">
                                            <input type="checkbox" :name="`variants[${vIdx}][is_active]`" value="1" x-model="v.is_active" @change="markDirty()" class="rounded text-indigo-600" />
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-card>
        @endif

        <!-- 5. Specifications -->
        <x-card title="5. Dynamic Product Specifications" subtitle="Key-value pairs grouped by technical, general, or dietary highlights">
            <div class="space-y-3">
                <template x-for="(spec, sIdx) in specifications" :key="sIdx">
                    <div class="flex items-center space-x-2">
                        <input type="text" :name="`specifications[${sIdx}][group]`" x-model="spec.group" placeholder="Group (e.g. General)" @input="markDirty()" class="w-1/4 p-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60" />
                        <input type="text" :name="`specifications[${sIdx}][key]`" x-model="spec.key" placeholder="Key (e.g. Shelf Life)" @input="markDirty()" class="w-1/3 p-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60" />
                        <input type="text" :name="`specifications[${sIdx}][value]`" x-model="spec.value" placeholder="Value (e.g. 6 Months)" @input="markDirty()" class="w-1/2 p-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60" />
                        <button type="button" @click="specifications.splice(sIdx, 1); markDirty()" class="text-rose-500 font-bold px-2">&times;</button>
                    </div>
                </template>
                <button type="button" @click="specifications.push({ group: 'General', key: '', value: '' }); markDirty()" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 font-bold text-xs text-indigo-600 hover:bg-slate-100">
                    + Add Specification Row
                </button>
            </div>
        </x-card>

        <!-- 6. Tax, Logistics & Omnichannel Flags -->
        <x-card title="6. Tax, Return Policy & Omnichannel Flags" subtitle="Mandatory GST tax codes, return windows, COD eligibility, and 10-30m instant dispatch capability">
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            HSN Code
                        </label>
                        <input 
                            type="text" 
                            name="hsn_code" 
                            @input="markDirty()"
                            value="{{ old('hsn_code', $product->hsn_code) }}" 
                            class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            GST / Tax Percent (%) <span class="text-rose-500">*</span>
                        </label>
                        <select 
                            name="tax_percent" 
                            required 
                            @change="markDirty()"
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-medium"
                        >
                            <option value="0.00" {{ old('tax_percent', (string) $product->tax_percent) == '0.00' ? 'selected' : '' }}>0% (Exempt)</option>
                            <option value="5.00" {{ old('tax_percent', (string) $product->tax_percent) == '5.00' ? 'selected' : '' }}>5% (Essentials)</option>
                            <option value="12.00" {{ old('tax_percent', (string) $product->tax_percent) == '12.00' ? 'selected' : '' }}>12% (Standard Low)</option>
                            <option value="18.00" {{ old('tax_percent', (string) $product->tax_percent) == '18.00' ? 'selected' : '' }}>18% (Standard High)</option>
                            <option value="28.00" {{ old('tax_percent', (string) $product->tax_percent) == '28.00' ? 'selected' : '' }}>28% (Luxury / Aerated)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Return Window (Days) <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="number" 
                            step="1" 
                            min="0" 
                            max="90" 
                            name="return_window_days" 
                            @input="markDirty()"
                            value="{{ old('return_window_days', $product->return_window_days) }}" 
                            class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <label class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex items-start space-x-3 cursor-pointer">
                        <input type="checkbox" name="is_quick_capable" value="1" {{ old('is_quick_capable', $product->is_quick_capable) ? 'checked' : '' }} @change="markDirty()" class="mt-0.5 rounded text-amber-500 focus:ring-amber-500" />
                        <div>
                            <span class="text-xs font-bold text-slate-900 dark:text-white block">⚡ 10-30m Quick Delivery Candidate</span>
                            <span class="text-[11px] text-slate-500">Eligible for instant hyper-local dark store rider dispatch.</span>
                        </div>
                    </label>

                    <label class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex items-start space-x-3 cursor-pointer">
                        <input type="checkbox" name="is_returnable" value="1" {{ old('is_returnable', $product->is_returnable) ? 'checked' : '' }} @change="markDirty()" class="mt-0.5 rounded text-indigo-600 focus:ring-indigo-500" />
                        <div>
                            <span class="text-xs font-bold text-slate-900 dark:text-white block">Customer Returns Allowed</span>
                            <span class="text-[11px] text-slate-500">Permit buyers to raise a return pickup within window.</span>
                        </div>
                    </label>

                    <label class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex items-start space-x-3 cursor-pointer">
                        <input type="checkbox" name="is_cod_allowed" value="1" {{ old('is_cod_allowed', $product->is_cod_allowed) ? 'checked' : '' }} @change="markDirty()" class="mt-0.5 rounded text-emerald-600 focus:ring-emerald-500" />
                        <div>
                            <span class="text-xs font-bold text-slate-900 dark:text-white block">Cash on Delivery (COD) Allowed</span>
                            <span class="text-[11px] text-slate-500">Allow customers to pay via cash/UPI at doorstep.</span>
                        </div>
                    </label>
                </div>
            </div>
        </x-card>

        <!-- 7. SEO Meta -->
        <x-card title="7. Search Engine Optimization (SEO)" subtitle="Custom title and meta description for Google indexing and social sharing">
            <div class="space-y-4" x-data="{ metaTitle: @js($product->meta_title ?: $product->title), metaDesc: @js($product->meta_description ?: $product->short_desc) }">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Meta Title
                        </label>
                        <input 
                            type="text" 
                            name="meta_title" 
                            x-model="metaTitle"
                            @input="markDirty()"
                            value="{{ old('meta_title', $product->meta_title) }}" 
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Meta Description
                        </label>
                        <textarea 
                            name="meta_description" 
                            x-model="metaDesc"
                            @input="markDirty()"
                            rows="2" 
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        >{{ old('meta_description', $product->meta_description) }}</textarea>
                    </div>
                </div>

                <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-1 font-sans">
                    <div class="text-[11px] text-emerald-700 dark:text-emerald-400 font-mono">https://onestall.in &rsaquo; product &rsaquo; {{ $product->slug }}</div>
                    <div class="text-sm font-semibold text-indigo-700 dark:text-indigo-400 hover:underline cursor-pointer" x-text="metaTitle || title || 'Product Title on OneStall'"></div>
                    <div class="text-xs text-slate-600 dark:text-slate-400 line-clamp-2" x-text="metaDesc || 'Product description snippet will appear here in search engine results.'"></div>
                </div>
            </div>
        </x-card>
    </form>
</div>

<script>
function sellerProductForm(config) {
    return {
        isDirty: false,
        isSubmitting: false,
        productType: config.productType || 'simple',
        title: config.title || '',
        submitAction: 'save_changes',
        simpleSku: config.initialSku || '',
        simpleMrp: config.initialMrp || 0,
        simplePrice: config.initialPrice || 0,
        skuAvailable: true,
        skuMessage: '',

        variants: config.initialVariants || [],
        specifications: config.initialSpecs || [],

        init() {
            window.addEventListener('beforeunload', (e) => {
                if (this.isDirty && !this.isSubmitting) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },

        markDirty() {
            this.isDirty = true;
        },

        submitForm(action) {
            this.submitAction = action;
            this.isSubmitting = true;
            document.getElementById('product-form').submit();
        }
    };
}
</script>
@endsection
