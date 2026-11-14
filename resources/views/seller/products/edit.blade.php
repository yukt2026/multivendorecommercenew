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

<div class="space-y-6 pb-20" x-data="sellerProductForm({
    isEdit: true,
    productType: @js($product->product_type->value),
    title: @js($product->title),
    checkSkuUrl: '{{ route("seller.products.check_sku") }}',
    initialVariants: @js($existingVariants),
    initialSpecs: @js($existingSpecs),
    initialSku: @js($defaultVariant?->sku ?? ''),
    initialMrp: {{ (float) ($defaultVariant?->mrp ?? 0) }},
    initialPrice: {{ (float) ($defaultVariant?->selling_price ?? 0) }},
    initialCost: {{ (float) ($defaultVariant?->cost_price ?? 0) }}
})">
    <!-- Header with Breadcrumbs & Primary Actions -->
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
            <div class="flex items-center gap-2">
                @if($product->status->value === 'active')
                    <x-button variant="outline" size="sm" href="{{ route('store.show', $seller->stores->first()?->slug ?? 'store') }}" target="_blank">
                        View on Storefront &rarr;
                    </x-button>
                @endif
                <x-button variant="outline" size="sm" href="{{ route('seller.products.index') }}">
                    &larr; Back to Catalogue
                </x-button>
            </div>
        </x-slot>
    </x-page-header>

    @if($product->status->value === 'rejected' && $product->rejection_reason)
        <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-900 text-xs shadow-2xs">
            <div class="flex items-center space-x-2 text-rose-700 dark:text-rose-300 font-bold mb-1">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Rejection Notice from Quality Control:</span>
            </div>
            <p class="text-rose-800 dark:text-rose-200 pl-6">{{ $product->rejection_reason }}</p>
            <p class="text-rose-600 dark:text-rose-400 pl-6 mt-1 text-[11px]">Please revise the highlighted details and click <strong>Submit for QC Review</strong>.</p>
        </div>
    @endif

    @if($product->status->value === 'active')
        <div class="p-4 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 text-indigo-900 dark:text-indigo-200 text-xs flex items-center space-x-2 shadow-2xs">
            <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
            <span><strong>Policy Guard:</strong> Editing sensitive fields (Title, Category, Brand, Tax, or Variant Selling Prices) on an active product will automatically re-trigger QC moderation review.</span>
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-900 text-rose-800 dark:text-rose-200 text-xs shadow-2xs">
            <div class="font-bold mb-1.5 flex items-center space-x-1.5">
                <svg class="w-4 h-4 shrink-0 text-rose-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <span>Please correct the following errors:</span>
            </div>
            <ul class="list-disc pl-6 space-y-1">
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
        class="space-y-6"
    >
        @csrf
        @method('PUT')
        <input type="hidden" name="submit_action" x-model="submitAction" value="save_changes" />

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
                        class="w-full p-3 text-sm font-semibold rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition" 
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
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition" 
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
                        class="w-full p-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition"
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
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition"
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
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition"
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

        <!-- 3. Product Media & Gallery (Interactive Previews & Lightbox Zoom) -->
        <x-card title="3. Product Media & Gallery" subtitle="Existing product photos, instant upload previews, and high-res lightbox inspection">
            <div class="space-y-5">
                <!-- Existing Saved Media -->
                @if($product->media->count() > 0)
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                Existing Photos &amp; Videos ({{ $product->media->count() }})
                            </span>
                            <span class="text-[11px] text-slate-400">Click photo to zoom • Click delete to mark for removal</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                            @foreach($product->media as $idx => $m)
                                <div class="relative group rounded-2xl border transition-all duration-200 overflow-hidden bg-slate-100 dark:bg-slate-900 aspect-square shadow-2xs"
                                     :class="isMediaDeleted({{ $m->id }}) ? 'border-rose-400 ring-2 ring-rose-400/50 opacity-60 grayscale' : 'border-slate-200 dark:border-slate-700 hover:border-indigo-400 hover:shadow-md'">
                                    
                                    @if($m->type === \App\Enums\MediaType::VIDEO)
                                        <video src="{{ $m->url }}" class="w-full h-full object-cover"></video>
                                        <span class="absolute top-2 left-2 px-1.5 py-0.5 rounded-md bg-black/80 text-white text-[9px] font-bold">🎬 VIDEO</span>
                                    @else
                                        <img src="{{ $m->url }}" alt="Product Media" class="w-full h-full object-cover cursor-pointer hover:scale-105 transition-transform" @click="openPreview('{{ $m->url }}')" />
                                    @endif

                                    @if($idx === 0)
                                        <span class="absolute top-2 left-2 px-2 py-0.5 rounded-md bg-indigo-600 text-white text-[9px] font-black uppercase tracking-wider shadow-sm">
                                            Primary
                                        </span>
                                    @endif

                                    <!-- Hidden input when deleted -->
                                    <template x-if="isMediaDeleted({{ $m->id }})">
                                        <input type="hidden" name="deleted_media_ids[]" value="{{ $m->id }}" />
                                    </template>

                                    <!-- Hover actions bar -->
                                    <div class="absolute inset-x-0 bottom-0 p-1.5 bg-gradient-to-t from-slate-950/80 via-slate-950/40 to-transparent flex items-center justify-between">
                                        <button type="button" @click="openPreview('{{ $m->url }}')" class="p-1 rounded-lg bg-white/20 hover:bg-white/40 text-white text-xs transition cursor-pointer" title="Enlarge preview">
                                            🔍
                                        </button>
                                        <button type="button" @click="toggleDeleteExisting({{ $m->id }})" class="px-2 py-0.5 rounded-lg text-[10px] font-bold transition flex items-center space-x-1 cursor-pointer"
                                                :class="isMediaDeleted({{ $m->id }}) ? 'bg-amber-500 text-white hover:bg-amber-600' : 'bg-rose-600 text-white hover:bg-rose-700'">
                                            <span x-text="isMediaDeleted({{ $m->id }}) ? 'Undo' : 'Delete'"></span>
                                        </button>
                                    </div>

                                    <!-- Marked for Deletion Overlay -->
                                    <div x-show="isMediaDeleted({{ $m->id }})" x-cloak class="absolute inset-0 bg-rose-950/80 backdrop-blur-xs flex flex-col items-center justify-center p-2 text-center text-white">
                                        <span class="text-rose-300 font-bold text-xs">Marked for Deletion</span>
                                        <button type="button" @click="toggleDeleteExisting({{ $m->id }})" class="mt-2 px-2.5 py-1 rounded-lg bg-white text-rose-700 font-extrabold text-[10px] shadow-sm hover:bg-rose-50 cursor-pointer">
                                            Undo
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Upload Additional Photos Dropzone -->
                <div 
                    class="p-6 rounded-2xl border-2 border-dashed transition-all text-center cursor-pointer relative"
                    :class="isDragging ? 'border-indigo-500 bg-indigo-50/60 dark:bg-indigo-950/40 ring-2 ring-indigo-500/20' : 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/40 hover:bg-slate-100/50'"
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="isDragging = false; handleDrop($event)"
                    @click="$refs.editFileInput.click()"
                >
                    <svg class="w-10 h-10 mx-auto text-indigo-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
                        Drag and drop additional photos or <span class="text-indigo-600 dark:text-indigo-400 underline">browse files</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Supports JPG, PNG, WebP or MP4 up to 10MB each. Previews render instantly below.</p>
                    <input 
                        type="file" 
                        id="edit-product-media-input" 
                        x-ref="editFileInput" 
                        name="media[]" 
                        multiple 
                        accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime" 
                        @change="handleFileSelect($event)" 
                        class="hidden" 
                    />
                </div>

                <!-- Instant Live Previews for Newly Selected Files -->
                <template x-if="mediaFiles.length > 0">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                Newly Added Media (<span x-text="mediaFiles.length"></span>)
                            </span>
                            <span class="text-[11px] text-emerald-600 font-bold">Ready to upload on save</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                            <template x-for="(m, mIdx) in mediaFiles" :key="mIdx">
                                <div class="relative group rounded-2xl border border-indigo-200 dark:border-indigo-800 overflow-hidden bg-slate-100 dark:bg-slate-900 aspect-square shadow-sm">
                                    <template x-if="m.isVideo">
                                        <div class="w-full h-full flex flex-col items-center justify-center bg-slate-900 text-white p-2">
                                            <svg class="w-8 h-8 text-indigo-400" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11v11.78a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                                            <span class="text-[10px] truncate max-w-full font-mono mt-1" x-text="m.name"></span>
                                        </div>
                                    </template>
                                    <template x-if="!m.isVideo">
                                        <img :src="m.url" :alt="m.name" class="w-full h-full object-cover cursor-pointer hover:scale-105 transition-transform" @click="openPreview(m.url)" />
                                    </template>

                                    <span class="absolute top-2 left-2 px-1.5 py-0.5 rounded-md bg-emerald-600 text-white text-[9px] font-black uppercase shadow-xs">
                                        New Upload
                                    </span>

                                    <button 
                                        type="button" 
                                        @click="removeNewMedia(mIdx)" 
                                        class="absolute top-2 right-2 w-6 h-6 rounded-full bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center text-xs font-black shadow-md transition cursor-pointer" 
                                        title="Remove this photo"
                                    >
                                        &times;
                                    </button>

                                    <div class="absolute bottom-0 inset-x-0 bg-slate-950/70 p-1 text-[9px] text-white truncate font-mono">
                                        <span x-text="m.name"></span> (<span x-text="m.size"></span>)
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </x-card>

        <!-- 4. Pricing, Inventory & Variants -->
        @if($product->product_type->value === 'simple')
            @php
                $stockQty = $defaultVariant?->inventories->sum('quantity_on_hand') ?? 0;
            @endphp
            <x-card title="4. Pricing & Inventory (Simple Item)" subtitle="Configure price points, stock units, and shipping dimensions">
                <div class="space-y-4">
                    <!-- Live Discount & Profit Margin Chips -->
                    <div class="flex flex-wrap items-center gap-2 pb-1" x-show="discountPercent > 0 || profitMargin" x-cloak>
                        <template x-if="discountPercent > 0">
                            <span class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                <span x-text="`${discountPercent}% OFF Customer Discount`"></span>
                            </span>
                        </template>
                        <template x-if="profitMargin">
                            <span class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span>Est. Gross Profit: ₹<span x-text="profitMargin.profit"></span> (<span x-text="profitMargin.margin"></span>% Margin)</span>
                            </span>
                        </template>
                    </div>

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
                                class="w-full p-2.5 text-xs rounded-xl font-mono uppercase bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-bold focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition"
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
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition"
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
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-bold focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition"
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
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-bold focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition"
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
                                x-model="simpleCost"
                                @input="markDirty()"
                                value="{{ old('simple_cost_price', (float) ($defaultVariant?->cost_price ?? 0)) }}" 
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition"
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
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition"
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
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition"
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
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition"
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
                    <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs">
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
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900 transition">
                                        <td class="p-2.5 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                            <span x-text="v.name"></span>
                                            <input type="hidden" :name="`variants[${vIdx}][id]`" :value="v.id" />
                                            <input type="hidden" :name="`variants[${vIdx}][name]`" :value="v.name" />
                                            <input type="hidden" :name="`variants[${vIdx}][attributes]`" :value="JSON.stringify(v.attributes)" />
                                        </td>
                                        <td class="p-2.5">
                                            <input type="text" :name="`variants[${vIdx}][sku]`" x-model="v.sku" required @input="markDirty()" class="w-28 p-1 text-xs font-mono rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
                                        </td>
                                        <td class="p-2.5">
                                            <input type="number" step="0.01" min="0.01" :name="`variants[${vIdx}][mrp]`" x-model="v.mrp" required @input="markDirty()" class="w-20 p-1 text-xs font-mono rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
                                        </td>
                                        <td class="p-2.5">
                                            <input type="number" step="0.01" min="0.01" :name="`variants[${vIdx}][selling_price]`" x-model="v.selling_price" required @input="markDirty()" class="w-20 p-1 text-xs font-mono rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
                                        </td>
                                        <td class="p-2.5">
                                            <input type="number" step="1" min="0" :name="`variants[${vIdx}][stock]`" x-model="v.stock" @input="markDirty()" class="w-16 p-1 text-xs font-mono rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
                                        </td>
                                        <td class="p-2.5">
                                            <input type="number" step="1" min="0" :name="`variants[${vIdx}][weight_grams]`" x-model="v.weight_grams" @input="markDirty()" class="w-16 p-1 text-xs font-mono rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
                                        </td>
                                        <td class="p-2.5 text-center">
                                            <input type="checkbox" :name="`variants[${vIdx}][is_active]`" value="1" x-model="v.is_active" @change="markDirty()" class="rounded text-indigo-600 focus:ring-indigo-500" />
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-card>
        @endif

        <!-- 5. Dynamic Specifications -->
        <x-card title="5. Dynamic Product Specifications" subtitle="Key-value pairs grouped by technical, general, or dietary highlights">
            <div class="space-y-3">
                <template x-for="(spec, sIdx) in specifications" :key="sIdx">
                    <div class="flex items-center space-x-2">
                        <input type="text" :name="`specifications[${sIdx}][group]`" x-model="spec.group" placeholder="Group (e.g. General)" @input="markDirty()" class="w-1/4 p-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60" />
                        <input type="text" :name="`specifications[${sIdx}][key]`" x-model="spec.key" placeholder="Key (e.g. Shelf Life)" @input="markDirty()" class="w-1/3 p-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60" />
                        <input type="text" :name="`specifications[${sIdx}][value]`" x-model="spec.value" placeholder="Value (e.g. 6 Months)" @input="markDirty()" class="w-1/2 p-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60" />
                        <button type="button" @click="specifications.splice(sIdx, 1); markDirty()" class="text-rose-500 font-bold px-2 cursor-pointer">&times;</button>
                    </div>
                </template>
                <button type="button" @click="specifications.push({ group: 'General', key: '', value: '' }); markDirty()" class="px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 font-bold text-xs text-indigo-600 hover:bg-slate-100 transition cursor-pointer">
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
                            class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition"
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
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition"
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
                            class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <label class="p-3.5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex items-start space-x-3 cursor-pointer hover:bg-slate-100/60 transition">
                        <input type="checkbox" name="is_quick_capable" value="1" {{ old('is_quick_capable', $product->is_quick_capable) ? 'checked' : '' }} @change="markDirty()" class="mt-0.5 rounded text-amber-500 focus:ring-amber-500" />
                        <div>
                            <span class="text-xs font-bold text-slate-900 dark:text-white block">⚡ 10-30m Quick Delivery Candidate</span>
                            <span class="text-[11px] text-slate-500">Eligible for instant hyper-local dark store rider dispatch.</span>
                        </div>
                    </label>

                    <label class="p-3.5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex items-start space-x-3 cursor-pointer hover:bg-slate-100/60 transition">
                        <input type="checkbox" name="is_returnable" value="1" {{ old('is_returnable', $product->is_returnable) ? 'checked' : '' }} @change="markDirty()" class="mt-0.5 rounded text-indigo-600 focus:ring-indigo-500" />
                        <div>
                            <span class="text-xs font-bold text-slate-900 dark:text-white block">Customer Returns Allowed</span>
                            <span class="text-[11px] text-slate-500">Permit buyers to raise a return pickup within window.</span>
                        </div>
                    </label>

                    <label class="p-3.5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex items-start space-x-3 cursor-pointer hover:bg-slate-100/60 transition">
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
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition"
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
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition"
                        >{{ old('meta_description', $product->meta_description) }}</textarea>
                    </div>
                </div>

                <div class="p-3.5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-1 font-sans">
                    <div class="text-[11px] text-emerald-700 dark:text-emerald-400 font-mono">https://onestall.in &rsaquo; product &rsaquo; {{ $product->slug }}</div>
                    <div class="text-sm font-semibold text-indigo-700 dark:text-indigo-400 hover:underline cursor-pointer" x-text="metaTitle || title || 'Product Title on OneStall'"></div>
                    <div class="text-xs text-slate-600 dark:text-slate-400 line-clamp-2" x-text="metaDesc || 'Product description snippet will appear here in search engine results.'"></div>
                </div>
            </div>
        </x-card>

        <!-- Form Bottom Action Bar (Niche) -->
        <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-lg flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center space-x-3">
                <span class="w-3 h-3 rounded-full shrink-0" :class="isDirty ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500'"></span>
                <div>
                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white" x-text="isDirty ? 'Unsaved modifications pending' : 'All changes saved'"></h4>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Save your changes, update draft status, or submit for quality control review.</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 shrink-0">
                <a href="{{ route('seller.products.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300 transition">
                    Cancel
                </a>

                @if(in_array($product->status->value, ['draft', 'rejected'], true))
                    <button 
                        type="button" 
                        @click="submitForm('save_draft')"
                        :disabled="isSubmitting"
                        class="px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-200 text-xs font-bold transition disabled:opacity-50 cursor-pointer flex items-center space-x-1.5 shadow-2xs"
                    >
                        <span x-show="!isSubmitting || submitAction !== 'save_draft'">Save as Draft</span>
                        <span x-show="isSubmitting && submitAction === 'save_draft'" x-cloak class="flex items-center space-x-1.5">
                            <svg class="animate-spin h-3.5 w-3.5 text-slate-600" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>Saving Draft...</span>
                        </span>
                    </button>
                    <button 
                        type="button" 
                        @click="submitForm('submit_review')"
                        :disabled="isSubmitting"
                        class="px-6 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 active:scale-95 text-white text-xs font-bold shadow-md transition disabled:opacity-50 cursor-pointer flex items-center space-x-2"
                    >
                        <span x-show="!isSubmitting || submitAction !== 'submit_review'">Submit for QC Review &rarr;</span>
                        <span x-show="isSubmitting && submitAction === 'submit_review'" x-cloak class="flex items-center space-x-1.5">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>Submitting for QC...</span>
                        </span>
                    </button>
                @else
                    <button 
                        type="button" 
                        @click="submitForm('save_changes')"
                        :disabled="isSubmitting"
                        class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white text-xs font-bold shadow-md shadow-indigo-600/25 transition disabled:opacity-50 cursor-pointer flex items-center space-x-2"
                    >
                        <span x-show="!isSubmitting">Save Changes</span>
                        <span x-show="isSubmitting" x-cloak class="flex items-center space-x-1.5">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>Saving Changes...</span>
                        </span>
                    </button>
                @endif
            </div>
        </div>
    </form>

    <!-- Image Lightbox Modal for Full Resolution Zoom -->
    <div 
        x-show="previewModalUrl" 
        @click="closePreview()" 
        x-cloak 
        class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 transition-all"
    >
        <div class="relative max-w-3xl max-h-[90vh] bg-white dark:bg-slate-900 rounded-3xl overflow-hidden shadow-2xl p-2" @click.stop>
            <button 
                type="button" 
                @click="closePreview()" 
                class="absolute top-3 right-3 w-8 h-8 rounded-full bg-slate-900/80 hover:bg-slate-900 text-white flex items-center justify-center text-base font-bold shadow-md cursor-pointer z-10"
            >
                &times;
            </button>
            <img :src="previewModalUrl" class="w-full max-h-[80vh] object-contain rounded-2xl" alt="High-Res Product Preview" />
        </div>
    </div>
</div>

<script>
function sellerProductForm(config) {
    return {
        isDirty: false,
        isSubmitting: false,
        isDragging: false,
        productType: config.productType || 'simple',
        title: config.title || '',
        submitAction: 'save_changes',
        simpleSku: config.initialSku || '',
        simpleMrp: config.initialMrp || 0,
        simplePrice: config.initialPrice || 0,
        simpleCost: config.initialCost || 0,

        // Media Management
        mediaFiles: [], // new files chosen for upload
        deletedMediaIds: [], // existing media marked for deletion
        previewModalUrl: null, // modal zoom preview

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

        get discountPercent() {
            const mrp = Number(this.simpleMrp) || 0;
            const price = Number(this.simplePrice) || 0;
            if (mrp > 0 && price > 0 && price < mrp) {
                return Math.round(((mrp - price) / mrp) * 100);
            }
            return 0;
        },

        get profitMargin() {
            const cost = Number(this.simpleCost) || 0;
            const price = Number(this.simplePrice) || 0;
            if (price > 0 && cost > 0) {
                const profit = price - cost;
                const margin = Math.round((profit / price) * 100);
                return { profit: profit.toFixed(2), margin: margin };
            }
            return null;
        },

        // Media Handlers
        handleFileSelect(event) {
            const files = Array.from(event.target.files || []);
            this.addFiles(files);
        },

        handleDrop(event) {
            const files = Array.from(event.dataTransfer.files || []);
            this.addFiles(files);
        },

        addFiles(newFiles) {
            newFiles.forEach(file => {
                const isVideo = file.type.startsWith('video/');
                const url = URL.createObjectURL(file);
                this.mediaFiles.push({
                    file: file,
                    url: url,
                    name: file.name,
                    size: (file.size / 1024 / 1024).toFixed(2) + ' MB',
                    isVideo: isVideo
                });
            });
            this.syncFileInput();
            this.markDirty();
        },

        removeNewMedia(index) {
            if (this.mediaFiles[index] && this.mediaFiles[index].url) {
                URL.revokeObjectURL(this.mediaFiles[index].url);
            }
            this.mediaFiles.splice(index, 1);
            this.syncFileInput();
            this.markDirty();
        },

        syncFileInput() {
            const input = document.getElementById('edit-product-media-input');
            if (!input) return;
            try {
                const dt = new DataTransfer();
                this.mediaFiles.forEach(item => {
                    if (item.file) {
                        dt.items.add(item.file);
                    }
                });
                input.files = dt.files;
            } catch (e) {
                console.warn('DataTransfer sync fallback:', e);
            }
        },

        toggleDeleteExisting(mediaId) {
            const idNum = Number(mediaId);
            const idx = this.deletedMediaIds.indexOf(idNum);
            if (idx > -1) {
                this.deletedMediaIds.splice(idx, 1);
            } else {
                this.deletedMediaIds.push(idNum);
            }
            this.markDirty();
        },

        isMediaDeleted(mediaId) {
            return this.deletedMediaIds.includes(Number(mediaId));
        },

        openPreview(url) {
            this.previewModalUrl = url;
        },

        closePreview() {
            this.previewModalUrl = null;
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
