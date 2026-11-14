@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Add New Product'])

@section('content')
@php
    $oldVariants = old('variants', []);
    $oldSpecs = old('specifications', [
        ['group' => 'General', 'key' => 'Brand Origin', 'value' => 'India']
    ]);
@endphp

<div class="space-y-6" x-data="sellerProductForm({
    isEdit: false,
    productType: @js(old('product_type', 'simple')),
    title: @js(old('title', '')),
    shortDesc: @js(old('short_desc', '')),
    metaTitle: @js(old('meta_title', '')),
    metaDesc: @js(old('meta_description', '')),
    initialSku: @js(old('simple_sku', '')),
    initialMrp: {{ old('simple_mrp') !== null ? (float) old('simple_mrp') : 0 }},
    initialPrice: {{ old('simple_selling_price') !== null ? (float) old('simple_selling_price') : 0 }},
    initialCost: {{ old('simple_cost_price') !== null ? (float) old('simple_cost_price') : 0 }},
    initialVariants: @js($oldVariants),
    initialSpecs: @js($oldSpecs),
    checkSkuUrl: '{{ route("seller.products.check_sku") }}'
})">
    <!-- Header -->
    <x-page-header 
        title="Create New Catalogue Product" 
        subtitle="List a standard or multi-variant product, assign categories, configure quick-commerce eligibility, and submit for QC."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Products' => route('seller.products.index'), 'New' => '#']"
    >
        <x-slot name="actions">
            <x-button variant="outline" size="sm" href="{{ route('seller.products.index') }}">
                &larr; Back to Catalogue
            </x-button>
        </x-slot>
    </x-page-header>

    @if(isset($errors) && $errors->any())
        <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-900 text-rose-800 dark:text-rose-200 text-xs shadow-sm">
            <div class="font-bold mb-1.5 flex items-center space-x-1.5">
                <svg class="w-4 h-4 shrink-0 text-rose-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span>Please correct the following errors before submitting:</span>
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
        action="{{ route('seller.products.store') }}" 
        enctype="multipart/form-data"
        @submit="handleFormSubmit($event)"
        class="space-y-6 pb-20"
    >
        @csrf
        <input type="hidden" name="submit_action" x-model="submitAction" value="save_draft" />

        <!-- 1. Basic Details -->
        <x-card title="1. Basic Information" subtitle="Provide the fundamental title, type, and rich description for this item">
            <div class="space-y-4">
                <!-- Product Title -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            Product Title <span class="text-rose-500">*</span>
                        </label>
                        <span class="text-[10px] font-mono text-slate-400" x-text="`${title.length} / 255`"></span>
                    </div>
                    <input 
                        type="text" 
                        name="title" 
                        x-model="title"
                        @input="markDirty()"
                        maxlength="255"
                        placeholder="e.g. Organic Pure Desi Cow Ghee (500ml Glass Jar)"
                        required
                        class="w-full p-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" 
                    />
                </div>

                <!-- Product Type Selector -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                        Product Structure <span class="text-rose-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="p-4 rounded-xl border cursor-pointer transition flex items-start space-x-3" :class="productType === 'simple' ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/30 ring-2 ring-indigo-500/20' : 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50 hover:bg-slate-100/50'">
                            <input type="radio" name="product_type" value="simple" x-model="productType" @change="markDirty()" class="mt-0.5 text-indigo-600 focus:ring-indigo-500" />
                            <div>
                                <span class="text-xs font-bold text-slate-900 dark:text-white block flex items-center gap-1.5">
                                    <span>Simple Product</span>
                                    <span class="px-1.5 py-0.2 rounded text-[10px] bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300 font-normal">Standard</span>
                                </span>
                                <span class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 block">A standalone item with a single SKU, price, and inventory level (e.g. 500ml Ghee, Pack of Biscuits).</span>
                            </div>
                        </label>
                        <label class="p-4 rounded-xl border cursor-pointer transition flex items-start space-x-3" :class="productType === 'variable' ? 'border-purple-600 bg-purple-50/40 dark:bg-purple-950/30 ring-2 ring-purple-500/20' : 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50 hover:bg-slate-100/50'">
                            <input type="radio" name="product_type" value="variable" x-model="productType" @change="markDirty()" class="mt-0.5 text-purple-600 focus:ring-purple-500" />
                            <div>
                                <span class="text-xs font-bold text-slate-900 dark:text-white block flex items-center gap-1.5">
                                    <span>Variable Product</span>
                                    <span class="px-1.5 py-0.2 rounded text-[10px] bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300 font-normal">Multi-Option</span>
                                </span>
                                <span class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 block">Item with variations like Size, Color, Weight, or Flavor (e.g. T-Shirt with S, M, L or Rice in 1kg, 5kg).</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Short Description -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            Short Summary / Tagline
                        </label>
                        <span class="text-[10px] font-mono text-slate-400" x-text="`${(shortDesc || '').length} / 1000`"></span>
                    </div>
                    <input 
                        type="text" 
                        name="short_desc" 
                        x-model="shortDesc"
                        @input="markDirty()"
                        maxlength="1000"
                        placeholder="Brief 1-sentence value proposition for storefront listings"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" 
                    />
                </div>

                <!-- Rich Description -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Full Product Description &amp; Specifications (Rich HTML)
                    </label>
                    <textarea 
                        name="description" 
                        rows="6"
                        @input="markDirty()"
                        placeholder="Detailed product features, highlights, ingredients, directions for use, certifications..."
                        class="w-full p-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                    >{{ old('description') }}</textarea>
                    <p class="text-[10px] text-slate-400 mt-1">HTML formatting tags (&lt;p&gt;, &lt;b&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;table&gt;) are sanitized automatically on save.</p>
                </div>
            </div>
        </x-card>

        <!-- 2. Category & Brand -->
        <x-card title="2. Category & Brand Classification" subtitle="Tag the item into the correct storefront aisle and brand registry">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Nested Category Picker -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Storefront Category <span class="text-rose-500">*</span>
                    </label>
                    <select 
                        name="category_id" 
                        required 
                        @change="markDirty()"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                    >
                        <option value="">Select Nested Category...</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat['id'] }}" {{ old('category_id') == $cat['id'] ? 'selected' : '' }}>
                                {!! $cat['path'] !!}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Brand Picker -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Brand / Manufacturer
                    </label>
                    <select 
                        name="brand_id" 
                        @change="markDirty()"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                    >
                        <option value="">No Brand / Generic (Unbranded)</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>
                                {{ $brand->name }} {{ $brand->is_verified ? '✓ Verified' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-card>

        <!-- 3. Media & Visuals (Interactive Drag & Drop with Live Preview) -->
        <x-card title="3. Product Media & Gallery" subtitle="Upload high-res packaging photos, product angles, and demonstration videos">
            <div class="space-y-4">
                <!-- Dropzone -->
                <div 
                    class="p-6 rounded-2xl border-2 border-dashed transition-all text-center cursor-pointer relative"
                    :class="isDragging ? 'border-indigo-500 bg-indigo-50/60 dark:bg-indigo-950/40 ring-2 ring-indigo-500/20' : 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/40 hover:bg-slate-100/50'"
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="isDragging = false; handleDrop($event)"
                    @click="$refs.fileInput.click()"
                >
                    <svg class="w-10 h-10 mx-auto text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
                        Drag and drop product photos or <span class="text-indigo-600 dark:text-indigo-400 underline">browse files</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Supports JPG, PNG, WebP or MP4 up to 10MB each. The first image becomes the main storefront primary thumbnail.</p>
                    
                    <input 
                        type="file" 
                        id="product-media-input"
                        x-ref="fileInput"
                        name="media[]" 
                        multiple 
                        accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime"
                        @change="handleFileSelect($event)"
                        class="hidden" 
                    />
                </div>

                <!-- Live Media Previews Grid -->
                <template x-if="mediaFiles.length > 0">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                Selected Media (<span x-text="mediaFiles.length"></span>)
                            </span>
                            <span class="text-[11px] text-slate-400">First item is primary</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3">
                            <template x-for="(m, mIdx) in mediaFiles" :key="mIdx">
                                <div class="relative group rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden bg-slate-100 dark:bg-slate-900 aspect-square shadow-xs">
                                    <template x-if="m.isVideo">
                                        <div class="w-full h-full flex flex-col items-center justify-center bg-slate-900 text-white p-2">
                                            <svg class="w-8 h-8 text-indigo-400" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11v11.78a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                                            <span class="text-[10px] truncate max-w-full font-mono mt-1" x-text="m.name"></span>
                                        </div>
                                    </template>
                                    <template x-if="!m.isVideo">
                                        <img :src="m.url" :alt="m.name" class="w-full h-full object-cover cursor-pointer hover:scale-105 transition-transform" @click="openPreview(m.url)" />
                                    </template>

                                    <!-- Primary Badge on 1st item -->
                                    <template x-if="mIdx === 0">
                                        <span class="absolute top-1.5 left-1.5 px-2 py-0.5 rounded-md bg-indigo-600 text-white text-[9px] font-black uppercase tracking-wider shadow-sm">
                                            Primary
                                        </span>
                                    </template>

                                    <!-- Remove button -->
                                    <button 
                                        type="button" 
                                        @click="removeMedia(mIdx)"
                                        title="Remove photo"
                                        class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center text-xs font-black shadow-md transition cursor-pointer"
                                    >
                                        &times;
                                    </button>

                                    <!-- File Info overlay -->
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
        <!-- SIMPLE PRODUCT VARIANT SECTION -->
        <template x-if="productType === 'simple'">
            <x-card title="4. Pricing & Inventory (Simple Item)" subtitle="Configure price points, stock units, and shipping dimensions">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <!-- SKU -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    SKU Code <span class="text-rose-500">*</span>
                                </label>
                                <button type="button" @click="suggestSkuAction()" class="text-[11px] text-indigo-600 dark:text-indigo-400 font-bold hover:underline cursor-pointer">
                                    Auto-Generate
                                </button>
                            </div>
                            <input 
                                type="text" 
                                name="simple_sku" 
                                x-model="simpleSku"
                                @input="markDirty(); checkSkuLive()"
                                placeholder="e.g. GHEE-500ML" 
                                required
                                class="w-full p-2.5 text-xs rounded-xl font-mono uppercase bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-bold focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
                            />
                            <p class="text-[10px] mt-1 font-semibold" :class="skuAvailable ? 'text-emerald-500' : 'text-rose-500'" x-text="skuMessage"></p>
                        </div>

                        <!-- Barcode -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Barcode / EAN / UPC
                            </label>
                            <input 
                                type="text" 
                                name="simple_barcode" 
                                @input="markDirty()"
                                value="{{ old('simple_barcode') }}" 
                                placeholder="8901234567890" 
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                        </div>

                        <!-- MRP -->
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
                                placeholder="499.00" 
                                required
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-bold"
                            />
                        </div>

                        <!-- Selling Price with Discount Badge -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    Selling Price (₹) <span class="text-rose-500">*</span>
                                </label>
                                <template x-if="discountPercent > 0">
                                    <span class="px-1.5 py-0.2 rounded text-[10px] bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 font-extrabold" x-text="`${discountPercent}% OFF`"></span>
                                </template>
                            </div>
                            <input 
                                type="number" 
                                step="0.01" 
                                min="0.01"
                                name="simple_selling_price" 
                                x-model="simplePrice"
                                @input="markDirty()"
                                placeholder="429.00" 
                                required
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border text-slate-900 dark:text-white font-bold"
                                :class="Number(simplePrice) > Number(simpleMrp) && Number(simpleMrp) > 0 ? 'border-rose-500 ring-2 ring-rose-500/20' : 'border-slate-200 dark:border-slate-700'"
                            />
                            <template x-if="Number(simplePrice) > Number(simpleMrp) && Number(simpleMrp) > 0">
                                <p class="text-[10px] text-rose-500 font-bold mt-1">⚠️ Selling price cannot exceed MRP.</p>
                            </template>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <!-- Cost Price & Margin Calculator -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    Purchase Cost (₹)
                                </label>
                                <template x-if="profitMargin !== null">
                                    <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400" x-text="`${profitMargin.margin}% Margin`"></span>
                                </template>
                            </div>
                            <input 
                                type="number" 
                                step="0.01" 
                                min="0" 
                                name="simple_cost_price" 
                                x-model="simpleCost"
                                @input="markDirty()"
                                placeholder="320.00" 
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                            <template x-if="profitMargin !== null">
                                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">
                                    Est. Profit: <strong class="text-emerald-600">₹<span x-text="profitMargin.profit"></span></strong> / unit
                                </p>
                            </template>
                        </div>

                        <!-- Initial Stock Units -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Stock on Hand (Units)
                            </label>
                            <input 
                                type="number" 
                                step="1" 
                                min="0" 
                                name="simple_stock" 
                                @input="markDirty()"
                                value="{{ old('simple_stock', 50) }}" 
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                        </div>

                        <!-- Low Stock Threshold -->
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
                                value="{{ old('simple_low_stock_threshold', 5) }}" 
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                        </div>

                        <!-- Weight (grams) -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Net Weight (grams)
                            </label>
                            <input 
                                type="number" 
                                step="1" 
                                min="0" 
                                name="simple_weight_grams" 
                                @input="markDirty()"
                                value="{{ old('simple_weight_grams', 500) }}" 
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                        </div>
                    </div>

                    <!-- Package Dimensions for Cargo -->
                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Shipping Dimensions (cm) - Volumetric Cargo Weight Calculation
                        </label>
                        <div class="grid grid-cols-3 gap-3">
                            <input type="number" step="0.1" min="0" name="simple_length" placeholder="Length (cm)" value="{{ old('simple_length', 10) }}" @input="markDirty()" class="p-2 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white" />
                            <input type="number" step="0.1" min="0" name="simple_width" placeholder="Width (cm)" value="{{ old('simple_width', 10) }}" @input="markDirty()" class="p-2 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white" />
                            <input type="number" step="0.1" min="0" name="simple_height" placeholder="Height (cm)" value="{{ old('simple_height', 15) }}" @input="markDirty()" class="p-2 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white" />
                        </div>
                    </div>
                </div>
            </x-card>
        </template>

        <!-- VARIABLE PRODUCT OPTION BUILDER SECTION -->
        <template x-if="productType === 'variable'">
            <x-card title="4. Variants Option Builder (Size, Color, etc.)" subtitle="Define configurable variation attributes and generate the variant inventory matrix">
                <div class="space-y-6">
                    <!-- Option Attributes Inputs -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Variation Options</span>
                            <button type="button" @click="addOption()" class="px-3 py-1 rounded-lg bg-purple-50 text-purple-600 dark:bg-purple-950 dark:text-purple-300 text-xs font-bold hover:bg-purple-100 transition cursor-pointer">
                                + Add Option (e.g. Size, Flavor)
                            </button>
                        </div>

                        <template x-for="(opt, optIdx) in options" :key="optIdx">
                            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50 flex flex-col sm:flex-row sm:items-center gap-3">
                                <div class="w-full sm:w-1/3">
                                    <input type="text" x-model="opt.name" placeholder="Option Name (e.g. Size)" @input="markDirty()" class="w-full p-2 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 font-bold" />
                                </div>
                                <div class="w-full sm:w-2/3 flex items-center space-x-2">
                                    <input type="text" x-model="opt.values" placeholder="Comma-separated values (e.g. S, M, L, XL)" @input="markDirty()" class="w-full p-2 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
                                    <button type="button" @click="removeOption(optIdx)" class="text-rose-500 hover:text-rose-700 text-sm font-bold px-2 cursor-pointer">&times;</button>
                                </div>
                            </div>
                        </template>

                        <div class="flex items-center justify-between pt-2">
                            <p class="text-[11px] text-slate-400">Values must be separated by commas. Example: <code class="text-indigo-600">Red, Blue, Green</code></p>
                            <button 
                                type="button" 
                                @click="generateVariantsMatrix()"
                                class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-md transition cursor-pointer flex items-center space-x-1.5"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                <span>Generate Variants Matrix &darr;</span>
                            </button>
                        </div>
                    </div>

                    <!-- Generated Variants Matrix Table -->
                    <template x-if="variants.length > 0">
                        <div class="space-y-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">
                                        Generated Variants (<span x-text="variants.length"></span>)
                                    </span>
                                    <p class="text-[10px] text-slate-400">Configure prices, stocks, and SKUs for each combination</p>
                                </div>
                                <!-- Bulk Apply -->
                                <div class="flex items-center space-x-2 text-xs bg-slate-100 dark:bg-slate-800/80 p-1.5 rounded-xl border border-slate-200 dark:border-slate-700">
                                    <input type="number" step="0.01" placeholder="Bulk MRP" x-model="bulkMrp" class="w-20 p-1 text-xs border rounded-lg bg-white dark:bg-slate-900" />
                                    <input type="number" step="0.01" placeholder="Bulk Price" x-model="bulkPrice" class="w-20 p-1 text-xs border rounded-lg bg-white dark:bg-slate-900" />
                                    <input type="number" step="1" placeholder="Bulk Stock" x-model="bulkStock" class="w-20 p-1 text-xs border rounded-lg bg-white dark:bg-slate-900" />
                                    <button type="button" @click="applyBulkValues()" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold text-[11px] transition cursor-pointer">
                                        Apply All
                                    </button>
                                </div>
                            </div>

                            <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl shadow-xs">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-100 dark:bg-slate-950 text-[10px] uppercase font-bold text-slate-500">
                                        <tr>
                                            <th class="p-2.5">Variant Combination</th>
                                            <th class="p-2.5">SKU *</th>
                                            <th class="p-2.5">MRP (₹) *</th>
                                            <th class="p-2.5">Selling Price (₹) *</th>
                                            <th class="p-2.5">Stock</th>
                                            <th class="p-2.5">Weight (g)</th>
                                            <th class="p-2.5 text-center">Active</th>
                                            <th class="p-2.5 text-right">&times;</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <template x-for="(v, vIdx) in variants" :key="vIdx">
                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/60 transition">
                                                <td class="p-2.5 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                                    <span x-text="v.name"></span>
                                                    <input type="hidden" :name="`variants[${vIdx}][name]`" :value="v.name" />
                                                    <input type="hidden" :name="`variants[${vIdx}][attributes]`" :value="JSON.stringify(v.attributes)" />
                                                </td>
                                                <td class="p-2.5">
                                                    <input type="text" :name="`variants[${vIdx}][sku]`" x-model="v.sku" required @input="markDirty()" class="w-28 p-1 text-xs font-mono uppercase rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
                                                </td>
                                                <td class="p-2.5">
                                                    <input type="number" step="0.01" min="0.01" :name="`variants[${vIdx}][mrp]`" x-model="v.mrp" required @input="markDirty()" class="w-20 p-1 text-xs font-mono rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
                                                </td>
                                                <td class="p-2.5">
                                                    <input type="number" step="0.01" min="0.01" :name="`variants[${vIdx}][selling_price]`" x-model="v.selling_price" required @input="markDirty()" class="w-20 p-1 text-xs font-mono rounded border bg-white dark:bg-slate-900" :class="Number(v.selling_price) > Number(v.mrp) ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-200 dark:border-slate-700'" />
                                                </td>
                                                <td class="p-2.5">
                                                    <input type="number" step="1" min="0" :name="`variants[${vIdx}][stock]`" x-model="v.stock" @input="markDirty()" class="w-16 p-1 text-xs font-mono rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
                                                </td>
                                                <td class="p-2.5">
                                                    <input type="number" step="1" min="0" :name="`variants[${vIdx}][weight_grams]`" x-model="v.weight_grams" @input="markDirty()" class="w-16 p-1 text-xs font-mono rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
                                                </td>
                                                <td class="p-2.5 text-center">
                                                    <input type="checkbox" :name="`variants[${vIdx}][is_active]`" value="1" x-model="v.is_active" @change="markDirty()" class="rounded text-indigo-600 focus:ring-indigo-500" />
                                                </td>
                                                <td class="p-2.5 text-right">
                                                    <button type="button" @click="variants.splice(vIdx, 1); markDirty()" class="text-rose-500 hover:text-rose-700 font-bold cursor-pointer">&times;</button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>

                    <template x-if="variants.length === 0">
                        <div class="p-4 rounded-xl border border-dashed border-amber-200 dark:border-amber-900/60 bg-amber-50/50 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300 text-xs flex items-center space-x-2">
                            <span>ℹ️ You have selected Variable Product. Enter your option values above and click <strong>"Generate Variants Matrix"</strong> to populate variant combinations.</span>
                        </div>
                    </template>
                </div>
            </x-card>
        </template>

        <!-- 5. Specifications & Attributes -->
        <x-card title="5. Dynamic Product Specifications" subtitle="Add key-value pairs grouped by technical, general, or dietary highlights">
            <div class="space-y-3">
                <template x-for="(spec, sIdx) in specifications" :key="sIdx">
                    <div class="flex items-center space-x-2">
                        <input type="text" :name="`specifications[${sIdx}][group]`" x-model="spec.group" placeholder="Group (e.g. General, Nutrition)" @input="markDirty()" class="w-1/4 p-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60" />
                        <input type="text" :name="`specifications[${sIdx}][key]`" x-model="spec.key" placeholder="Key (e.g. Shelf Life, Ingredients)" @input="markDirty()" class="w-1/3 p-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60" />
                        <input type="text" :name="`specifications[${sIdx}][value]`" x-model="spec.value" placeholder="Value (e.g. 6 Months, Organic A2 Cow Milk)" @input="markDirty()" class="w-1/2 p-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60" />
                        <button type="button" @click="specifications.splice(sIdx, 1); markDirty()" class="text-rose-500 hover:text-rose-700 font-bold px-2 cursor-pointer">&times;</button>
                    </div>
                </template>
                <button type="button" @click="specifications.push({ group: 'General', key: '', value: '' }); markDirty()" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 font-bold text-xs text-indigo-600 dark:text-indigo-400 hover:bg-slate-100 transition cursor-pointer">
                    + Add Specification Row
                </button>
            </div>
        </x-card>

        <!-- 6. Tax, Logistics & Omnichannel Flags -->
        <x-card title="6. Tax, Return Policy & Omnichannel Flags" subtitle="Mandatory GST tax codes, return windows, COD eligibility, and 10-30m instant dispatch capability">
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- HSN Code -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            HSN Code
                        </label>
                        <input 
                            type="text" 
                            name="hsn_code" 
                            @input="markDirty()"
                            value="{{ old('hsn_code') }}" 
                            placeholder="e.g. 04012000" 
                            class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        />
                    </div>

                    <!-- GST Tax Percent -->
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
                            <option value="0.00" {{ old('tax_percent', '5.00') == '0.00' ? 'selected' : '' }}>0% (Exempt)</option>
                            <option value="5.00" {{ old('tax_percent', '5.00') == '5.00' ? 'selected' : '' }}>5% (Essentials - Milk, Ghee, Flour)</option>
                            <option value="12.00" {{ old('tax_percent') == '12.00' ? 'selected' : '' }}>12% (Standard Low - Processed foods)</option>
                            <option value="18.00" {{ old('tax_percent') == '18.00' ? 'selected' : '' }}>18% (Standard High - Electronics, Personal Care)</option>
                            <option value="28.00" {{ old('tax_percent') == '28.00' ? 'selected' : '' }}>28% (Luxury / Aerated)</option>
                        </select>
                    </div>

                    <!-- Return Window Days -->
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
                            value="{{ old('return_window_days', 7) }}" 
                            class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        />
                    </div>
                </div>

                <!-- Toggles -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <label class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex items-start space-x-3 cursor-pointer hover:bg-slate-100/50">
                        <input type="checkbox" name="is_quick_capable" value="1" {{ old('is_quick_capable', true) ? 'checked' : '' }} @change="markDirty()" class="mt-0.5 rounded text-amber-500 focus:ring-amber-500" />
                        <div>
                            <span class="text-xs font-bold text-slate-900 dark:text-white block">⚡ 10-30m Quick Delivery Candidate</span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400">Eligible for instant hyper-local dark store rider dispatch.</span>
                        </div>
                    </label>

                    <label class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex items-start space-x-3 cursor-pointer hover:bg-slate-100/50">
                        <input type="checkbox" name="is_returnable" value="1" {{ old('is_returnable', true) ? 'checked' : '' }} @change="markDirty()" class="mt-0.5 rounded text-indigo-600 focus:ring-indigo-500" />
                        <div>
                            <span class="text-xs font-bold text-slate-900 dark:text-white block">Customer Returns Allowed</span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400">Permit buyers to raise a return pickup request within window.</span>
                        </div>
                    </label>

                    <label class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex items-start space-x-3 cursor-pointer hover:bg-slate-100/50">
                        <input type="checkbox" name="is_cod_allowed" value="1" {{ old('is_cod_allowed', true) ? 'checked' : '' }} @change="markDirty()" class="mt-0.5 rounded text-emerald-600 focus:ring-emerald-500" />
                        <div>
                            <span class="text-xs font-bold text-slate-900 dark:text-white block">Cash on Delivery (COD) Allowed</span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400">Allow customers to pay via cash/UPI at doorstep.</span>
                        </div>
                    </label>
                </div>
            </div>
        </x-card>

        <!-- 7. SEO Meta -->
        <x-card title="7. Search Engine Optimization (SEO)" subtitle="Custom title and meta description for Google indexing and social sharing">
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                Meta Title
                            </label>
                            <span class="text-[10px] font-mono" :class="metaTitle.length > 60 ? 'text-amber-500 font-bold' : 'text-slate-400'" x-text="`${metaTitle.length} / 60 optimal`"></span>
                        </div>
                        <input 
                            type="text" 
                            name="meta_title" 
                            x-model="metaTitle"
                            @input="markDirty()"
                            maxlength="160"
                            placeholder="Buy Fresh Ghee Online - Best Price in India" 
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        />
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                Meta Description
                            </label>
                            <span class="text-[10px] font-mono" :class="metaDesc.length > 160 ? 'text-amber-500 font-bold' : 'text-slate-400'" x-text="`${metaDesc.length} / 160 optimal`"></span>
                        </div>
                        <textarea 
                            name="meta_description" 
                            x-model="metaDesc"
                            @input="markDirty()"
                            maxlength="320"
                            rows="2" 
                            placeholder="Order pure cow ghee with lightning-fast 15-30 minute delivery." 
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        ></textarea>
                    </div>
                </div>

                <!-- Google SERP Snippet Preview -->
                <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-1 font-sans shadow-xs">
                    <div class="text-[11px] text-emerald-700 dark:text-emerald-400 font-mono">https://onestall.in &rsaquo; product &rsaquo; ...</div>
                    <div class="text-sm font-semibold text-indigo-700 dark:text-indigo-400 hover:underline cursor-pointer" x-text="metaTitle || title || 'Product Title on OneStall'"></div>
                    <div class="text-xs text-slate-600 dark:text-slate-400 line-clamp-2" x-text="metaDesc || shortDesc || 'Product description snippet will appear here in search engine results.'"></div>
                </div>
            </div>
        </x-card>

        <!-- Form Bottom Action Bar (Niche) -->
        <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-lg flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center space-x-3">
                <span class="w-3 h-3 rounded-full shrink-0" :class="isDirty ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500'"></span>
                <div>
                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white" x-text="isDirty ? 'Unsaved modifications in form' : 'All changes saved'"></h4>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Save as draft to complete later, or submit directly to Quality Control for storefront publication.</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 shrink-0">
                <a href="{{ route('seller.products.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300 transition">
                    Cancel
                </a>
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
                    class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white text-xs font-bold shadow-md shadow-indigo-600/25 transition disabled:opacity-50 cursor-pointer flex items-center space-x-2"
                >
                    <span x-show="!isSubmitting || submitAction !== 'submit_review'">Submit for QC Review &rarr;</span>
                    <span x-show="isSubmitting && submitAction === 'submit_review'" x-cloak class="flex items-center space-x-1.5">
                        <svg class="animate-spin h-3.5 w-3.5 text-white" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        <span>Submitting for QC...</span>
                    </span>
                </button>
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
        shortDesc: config.shortDesc || '',
        metaTitle: config.metaTitle || '',
        metaDesc: config.metaDesc || '',
        submitAction: 'save_draft',
        simpleSku: config.initialSku || '',
        simpleMrp: config.initialMrp || 0,
        simplePrice: config.initialPrice || 0,
        simpleCost: config.initialCost || 0,
        skuAvailable: true,
        skuMessage: '',

        // Media files array with preview URLs & Lightbox
        mediaFiles: [],
        previewModalUrl: null,

        openPreview(url) {
            this.previewModalUrl = url;
        },

        closePreview() {
            this.previewModalUrl = null;
        },

        // Options for variable product
        options: [
            { name: 'Size', values: 'Small, Medium, Large' },
            { name: 'Color', values: 'Red, Blue' }
        ],
        variants: config.initialVariants || [],
        bulkMrp: 0,
        bulkPrice: 0,
        bulkStock: 50,

        // Specifications
        specifications: config.initialSpecs && config.initialSpecs.length > 0 
            ? config.initialSpecs 
            : [{ group: 'General', key: 'Brand Origin', value: 'India' }],

        init() {
            if (this.simpleSku) {
                this.checkSkuLive();
            }

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

        // Media handlers
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

        removeMedia(index) {
            if (this.mediaFiles[index] && this.mediaFiles[index].url) {
                URL.revokeObjectURL(this.mediaFiles[index].url);
            }
            this.mediaFiles.splice(index, 1);
            this.syncFileInput();
            this.markDirty();
        },

        syncFileInput() {
            const input = document.getElementById('product-media-input');
            if (!input) return;
            try {
                const dt = new DataTransfer();
                this.mediaFiles.forEach(item => {
                    if (item.file) {
                        dt.items.add(item.file);
                    }
                });
                input.files = dt.files;
            } catch (err) {
                // If DataTransfer is not supported, native input retains selected files
            }
        },

        submitForm(action) {
            this.submitAction = action;
            const form = document.getElementById('product-form');
            if (form) {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }
        },

        handleFormSubmit(event) {
            // Validation: Simple selling price cannot exceed MRP
            if (this.productType === 'simple') {
                const mrp = Number(this.simpleMrp) || 0;
                const price = Number(this.simplePrice) || 0;
                if (mrp > 0 && price > mrp) {
                    event.preventDefault();
                    alert(`Selling price (₹${price}) cannot exceed the MRP (₹${mrp}). Please correct the price.`);
                    this.isSubmitting = false;
                    return false;
                }
            }

            // Validation: Variable product must have generated variants
            if (this.productType === 'variable') {
                if (this.variants.length === 0) {
                    event.preventDefault();
                    alert('Please define your variation options above and click "Generate Variants Matrix" to create at least 1 variant before saving.');
                    this.isSubmitting = false;
                    return false;
                }

                // Check for selling price > mrp in variants
                for (let i = 0; i < this.variants.length; i++) {
                    const v = this.variants[i];
                    if (Number(v.selling_price) > Number(v.mrp)) {
                        event.preventDefault();
                        alert(`Variant "${v.name}" has selling price (₹${v.selling_price}) higher than its MRP (₹${v.mrp}).`);
                        this.isSubmitting = false;
                        return false;
                    }
                }
            }

            this.isSubmitting = true;
            return true;
        },

        suggestSkuAction() {
            const base = (this.title || 'PROD').replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 4) || 'ITEM';
            const rand = Math.random().toString(36).substring(2, 6).toUpperCase();
            this.simpleSku = `${base}-${rand}`;
            this.markDirty();
            this.checkSkuLive();
        },

        async checkSkuLive() {
            if (!this.simpleSku) {
                this.skuMessage = '';
                return;
            }
            try {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const res = await fetch(config.checkSkuUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfMeta ? csrfMeta.getAttribute('content') : ''
                    },
                    body: JSON.stringify({ sku: this.simpleSku })
                });
                if (res.ok) {
                    const data = await res.json();
                    this.skuAvailable = data.available;
                    this.skuMessage = data.message;
                }
            } catch (e) {
                console.warn('SKU check error:', e);
            }
        },

        addOption() {
            this.options.push({ name: '', values: '' });
            this.markDirty();
        },

        removeOption(idx) {
            this.options.splice(idx, 1);
            this.markDirty();
        },

        generateVariantsMatrix() {
            const validOptions = this.options.filter(o => o.name && o.name.trim() && o.values && o.values.trim());
            if (validOptions.length === 0) {
                alert('Please provide at least one option name and its comma-separated values (e.g. Size: S, M, L).');
                return;
            }

            const optionSets = validOptions.map(o => {
                return o.values.split(',').map(v => ({ option: o.name.trim(), value: v.trim() })).filter(v => v.value);
            });

            // Cartesian product
            const cartesian = (arr) => arr.reduce((a, b) => a.flatMap(d => b.map(e => [d, e].flat())));
            const combinations = optionSets.length === 1 
                ? optionSets[0].map(item => [item]) 
                : cartesian(optionSets);

            const generated = [];
            const prefix = (this.title || 'PROD').replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 4) || 'VAR';

            combinations.forEach((combo, index) => {
                const attributesObj = {};
                const nameParts = [];
                const skuParts = [];

                combo.forEach(c => {
                    attributesObj[c.option] = c.value;
                    nameParts.push(c.value);
                    skuParts.push(c.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 3));
                });

                const variantName = `${this.title || 'Product'} - ${nameParts.join(' / ')}`;
                const autoSku = `${prefix}-${skuParts.join('-')}-${index + 1}`;

                generated.push({
                    name: variantName,
                    attributes: attributesObj,
                    sku: autoSku,
                    mrp: Number(this.bulkMrp) || (Number(this.simpleMrp) > 0 ? Number(this.simpleMrp) : 99.00),
                    selling_price: Number(this.bulkPrice) || (Number(this.simplePrice) > 0 ? Number(this.simplePrice) : 89.00),
                    stock: Number(this.bulkStock) || 50,
                    weight_grams: 500,
                    is_active: true
                });
            });

            this.variants = generated;
            this.markDirty();
        },

        applyBulkValues() {
            this.variants.forEach(v => {
                if (Number(this.bulkMrp) > 0) v.mrp = Number(this.bulkMrp);
                if (Number(this.bulkPrice) > 0) v.selling_price = Number(this.bulkPrice);
                if (Number(this.bulkStock) >= 0) v.stock = Number(this.bulkStock);
            });
            this.markDirty();
        }
    };
}
</script>
@endsection
