@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Add New Product'])

@section('content')
<div class="space-y-6" x-data="sellerProductForm({
    isEdit: false,
    productType: 'simple',
    title: '',
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
        action="{{ route('seller.products.store') }}" 
        enctype="multipart/form-data"
        @submit="isSubmitting = true"
        class="space-y-6 pb-24"
    >
        @csrf
        <input type="hidden" name="submit_action" x-model="submitAction" value="save_draft" />

        <!-- Sticky Save Bar -->
        <div class="sticky top-16 z-30 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-lg flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center space-x-2">
                <span class="w-2.5 h-2.5 rounded-full" :class="isDirty ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500'"></span>
                <span class="text-xs font-bold text-slate-800 dark:text-slate-200" x-text="isDirty ? 'Unsaved changes in form' : 'All changes saved'"></span>
            </div>
            <div class="flex items-center space-x-2.5">
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
                    class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-600/20 transition flex items-center space-x-1.5"
                >
                    <span>Submit for Review</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </div>
        </div>

        <!-- 1. Basic Details -->
        <x-card title="1. Basic Information" subtitle="Provide the fundamental title, type, and rich description for this item">
            <div class="space-y-4">
                <!-- Product Title -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Product Title <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="title" 
                        x-model="title"
                        @input="markDirty(); autoSuggestSku()"
                        value="{{ old('title') }}" 
                        placeholder="e.g. Organic Pure Desi Cow Ghee (500ml)"
                        required
                        class="w-full p-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-medium" 
                    />
                </div>

                <!-- Product Type Selector -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                        Product Structure <span class="text-rose-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="p-4 rounded-xl border cursor-pointer transition flex items-start space-x-3" :class="productType === 'simple' ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/30 ring-2 ring-indigo-500/20' : 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50'">
                            <input type="radio" name="product_type" value="simple" x-model="productType" @change="markDirty()" class="mt-0.5 text-indigo-600" />
                            <div>
                                <span class="text-xs font-bold text-slate-900 dark:text-white block">Simple Product</span>
                                <span class="text-[11px] text-slate-500">A standalone item with a single SKU, price, and inventory level (e.g. 500ml Ghee).</span>
                            </div>
                        </label>
                        <label class="p-4 rounded-xl border cursor-pointer transition flex items-start space-x-3" :class="productType === 'variable' ? 'border-purple-600 bg-purple-50/40 dark:bg-purple-950/30 ring-2 ring-purple-500/20' : 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50'">
                            <input type="radio" name="product_type" value="variable" x-model="productType" @change="markDirty()" class="mt-0.5 text-purple-600" />
                            <div>
                                <span class="text-xs font-bold text-slate-900 dark:text-white block">Variable Product (Multi-Option)</span>
                                <span class="text-[11px] text-slate-500">Item with variations like Size, Color, or Weight (e.g. T-Shirt with S, M, L).</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Short Description -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Short Summary / Tagline
                    </label>
                    <input 
                        type="text" 
                        name="short_desc" 
                        @input="markDirty()"
                        value="{{ old('short_desc') }}" 
                        placeholder="Brief 1-sentence value proposition for storefront listings"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white" 
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
                        placeholder="Detailed product features, highlights, ingredients, usage directions..."
                        class="w-full p-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
                    >{{ old('description') }}</textarea>
                    <p class="text-[10px] text-slate-400 mt-1">HTML formatting tags are sanitized automatically on save.</p>
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
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-medium"
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
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-medium"
                    >
                        <option value="">No Brand / Generic</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>
                                {{ $brand->name }} {{ $brand->is_verified ? '✓' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-card>

        <!-- 3. Media & Visuals -->
        <x-card title="3. Product Media & Gallery" subtitle="Upload high-res packaging photos, product angles, and demonstration videos">
            <div class="space-y-4">
                <div class="p-6 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/40 text-center">
                    <svg class="w-10 h-10 mx-auto text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
                        Upload Product Photos &amp; Video Clips
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">JPG, PNG, WebP or MP4 up to 10MB each. The first uploaded image becomes the primary thumbnail.</p>
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
                                <button type="button" @click="autoSuggestSku()" class="text-[10px] text-indigo-600 hover:underline">
                                    Suggest
                                </button>
                            </div>
                            <input 
                                type="text" 
                                name="simple_sku" 
                                x-model="simpleSku"
                                @input="markDirty(); checkSkuLive()"
                                value="{{ old('simple_sku') }}" 
                                placeholder="e.g. MILK-500ML" 
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                            <p class="text-[10px] mt-1 font-semibold" :class="skuAvailable ? 'text-emerald-500' : 'text-rose-500'" x-text="skuMessage"></p>
                        </div>

                        <!-- Barcode -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Barcode / EAN
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
                                value="{{ old('simple_mrp') }}" 
                                placeholder="99.00" 
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                        </div>

                        <!-- Selling Price -->
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
                                value="{{ old('simple_selling_price') }}" 
                                placeholder="85.00" 
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                            <template x-if="Number(simplePrice) > Number(simpleMrp) && Number(simpleMrp) > 0">
                                <p class="text-[10px] text-rose-500 font-bold mt-1">Selling price cannot exceed MRP.</p>
                            </template>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <!-- Cost Price -->
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
                                value="{{ old('simple_cost_price') }}" 
                                placeholder="60.00" 
                                class="w-full p-2.5 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                        </div>

                        <!-- Initial Stock Units -->
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
                                Weight (grams)
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
                            Shipping Dimensions (cm) - Required for Cargo Logistics
                        </label>
                        <div class="grid grid-cols-3 gap-3">
                            <input type="number" step="0.1" min="0" name="simple_length" placeholder="Length (cm)" value="{{ old('simple_length', 10) }}" @input="markDirty()" class="p-2 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700" />
                            <input type="number" step="0.1" min="0" name="simple_width" placeholder="Width (cm)" value="{{ old('simple_width', 10) }}" @input="markDirty()" class="p-2 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700" />
                            <input type="number" step="0.1" min="0" name="simple_height" placeholder="Height (cm)" value="{{ old('simple_height', 15) }}" @input="markDirty()" class="p-2 text-xs rounded-xl font-mono bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700" />
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
                            <button type="button" @click="addOption()" class="px-3 py-1 rounded-lg bg-purple-50 text-purple-600 dark:bg-purple-950 dark:text-purple-300 text-xs font-bold hover:bg-purple-100">
                                + Add Option (e.g. Color)
                            </button>
                        </div>

                        <template x-for="(opt, optIdx) in options" :key="optIdx">
                            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50 flex flex-col sm:flex-row sm:items-center gap-3">
                                <div class="w-full sm:w-1/3">
                                    <input type="text" x-model="opt.name" placeholder="Option Name (e.g. Size)" @input="markDirty()" class="w-full p-2 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 font-bold" />
                                </div>
                                <div class="w-full sm:w-2/3 flex items-center space-x-2">
                                    <input type="text" x-model="opt.values" placeholder="Comma-separated values (e.g. S, M, L, XL)" @input="markDirty()" class="w-full p-2 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
                                    <button type="button" @click="removeOption(optIdx)" class="text-rose-500 hover:text-rose-700 text-sm font-bold px-2">&times;</button>
                                </div>
                            </div>
                        </template>

                        <div class="flex justify-end">
                            <button 
                                type="button" 
                                @click="generateVariantsMatrix()"
                                class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-md transition"
                            >
                                Generate Variants Combinations Matrix &darr;
                            </button>
                        </div>
                    </div>

                    <!-- Generated Variants Matrix Table -->
                    <template x-if="variants.length > 0">
                        <div class="space-y-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Generated Variants (<span x-text="variants.length"></span>)</span>
                                <!-- Bulk Apply -->
                                <div class="flex items-center space-x-2 text-xs">
                                    <input type="number" step="0.01" placeholder="Bulk MRP" x-model="bulkMrp" class="w-20 p-1 text-xs border rounded-lg" />
                                    <input type="number" step="0.01" placeholder="Bulk Price" x-model="bulkPrice" class="w-20 p-1 text-xs border rounded-lg" />
                                    <input type="number" step="1" placeholder="Bulk Stock" x-model="bulkStock" class="w-20 p-1 text-xs border rounded-lg" />
                                    <button type="button" @click="applyBulkValues()" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 rounded-lg font-bold text-[11px]">Apply to All</button>
                                </div>
                            </div>

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
                                            <th class="p-2.5 text-right">&times;</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <template x-for="(v, vIdx) in variants" :key="vIdx">
                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-900">
                                                <td class="p-2.5 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                                    <span x-text="v.name"></span>
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
                                                <td class="p-2.5 text-right">
                                                    <button type="button" @click="variants.splice(vIdx, 1); markDirty()" class="text-rose-500 hover:text-rose-700 font-bold">&times;</button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
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
                            <option value="5.00" {{ old('tax_percent', '5.00') == '5.00' ? 'selected' : '' }}>5% (Essentials)</option>
                            <option value="12.00" {{ old('tax_percent') == '12.00' ? 'selected' : '' }}>12% (Standard Low)</option>
                            <option value="18.00" {{ old('tax_percent') == '18.00' ? 'selected' : '' }}>18% (Standard High)</option>
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
                    <label class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex items-start space-x-3 cursor-pointer">
                        <input type="checkbox" name="is_quick_capable" value="1" {{ old('is_quick_capable', true) ? 'checked' : '' }} @change="markDirty()" class="mt-0.5 rounded text-amber-500 focus:ring-amber-500" />
                        <div>
                            <span class="text-xs font-bold text-slate-900 dark:text-white block">⚡ 10-30m Quick Delivery Candidate</span>
                            <span class="text-[11px] text-slate-500">Eligible for instant hyper-local dark store rider dispatch.</span>
                        </div>
                    </label>

                    <label class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex items-start space-x-3 cursor-pointer">
                        <input type="checkbox" name="is_returnable" value="1" {{ old('is_returnable', true) ? 'checked' : '' }} @change="markDirty()" class="mt-0.5 rounded text-indigo-600 focus:ring-indigo-500" />
                        <div>
                            <span class="text-xs font-bold text-slate-900 dark:text-white block">Customer Returns Allowed</span>
                            <span class="text-[11px] text-slate-500">Permit buyers to raise a return pickup within window.</span>
                        </div>
                    </label>

                    <label class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex items-start space-x-3 cursor-pointer">
                        <input type="checkbox" name="is_cod_allowed" value="1" {{ old('is_cod_allowed', true) ? 'checked' : '' }} @change="markDirty()" class="mt-0.5 rounded text-emerald-600 focus:ring-emerald-500" />
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
            <div class="space-y-4" x-data="{ metaTitle: '', metaDesc: '' }">
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
                            value="{{ old('meta_title') }}" 
                            placeholder="Buy Fresh Ghee Online - Best Price in India" 
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
                            placeholder="Order pure cow ghee with lightning-fast 15-30 minute delivery." 
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        >{{ old('meta_description') }}</textarea>
                    </div>
                </div>

                <!-- Google SERP Snippet Preview -->
                <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-1 font-sans">
                    <div class="text-[11px] text-emerald-700 dark:text-emerald-400 font-mono">https://onestall.in &rsaquo; product &rsaquo; ...</div>
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
        submitAction: 'save_draft',
        simpleSku: '',
        simpleMrp: 0,
        simplePrice: 0,
        skuAvailable: true,
        skuMessage: '',

        // Options for variable product
        options: [
            { name: 'Size', values: 'Small, Medium, Large' },
            { name: 'Color', values: 'Red, Blue' }
        ],
        variants: [],
        bulkMrp: 0,
        bulkPrice: 0,
        bulkStock: 50,

        // Specifications
        specifications: [
            { group: 'General', key: 'Brand Origin', value: 'India' }
        ],

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
        },

        autoSuggestSku() {
            if (!this.title) return;
            const clean = this.title.replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 4);
            const rand = Math.random().toString(36).substring(2, 6).toUpperCase();
            this.simpleSku = `${clean}-${rand}`;
            this.checkSkuLive();
        },

        async checkSkuLive() {
            if (!this.simpleSku) return;
            try {
                const res = await fetch(config.checkSkuUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ sku: this.simpleSku })
                });
                const data = await res.json();
                this.skuAvailable = data.available;
                this.skuMessage = data.message;
            } catch (e) {
                console.error(e);
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
            const validOptions = this.options.filter(o => o.name.trim() && o.values.trim());
            if (validOptions.length === 0) {
                alert('Please provide at least one option name and its comma-separated values.');
                return;
            }

            const optionSets = validOptions.map(o => {
                return o.values.split(',').map(v => ({ option: o.name.trim(), value: v.trim() })).filter(v => v.value);
            });

            // Cartesian product helper
            const cartesian = (arr) => arr.reduce((a, b) => a.flatMap(d => b.map(e => [d, e].flat())));
            const combinations = optionSets.length === 1 ? optionSets[0].map(item => [item]) : cartesian(optionSets);

            const generated = [];
            const prefix = this.title ? this.title.replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 4) : 'VAR';

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
                    mrp: this.bulkMrp || 99.00,
                    selling_price: this.bulkPrice || 89.00,
                    stock: this.bulkStock || 50,
                    weight_grams: 500,
                    is_active: true
                });
            });

            this.variants = generated;
            this.markDirty();
        },

        applyBulkValues() {
            this.variants.forEach(v => {
                if (this.bulkMrp > 0) v.mrp = this.bulkMrp;
                if (this.bulkPrice > 0) v.selling_price = this.bulkPrice;
                if (this.bulkStock >= 0) v.stock = this.bulkStock;
            });
            this.markDirty();
        }
    };
}
</script>
@endsection
