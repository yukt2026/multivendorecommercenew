@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Edit on Behalf: ' . $product->title])

@section('content')
<div class="space-y-6 max-w-4xl">
    <x-page-header 
        title="Edit Listing on Behalf of Merchant" 
        subtitle="Correct statutory tax codes, title formatting, category taxonomy, or descriptions before approving into live catalogue."
        :breadcrumbs="['Command Central' => route('admin.dashboard'), 'Moderation' => route('admin.catalogue.moderation.index'), $product->title => route('admin.catalogue.moderation.show', $product), 'Edit on Behalf' => '#']"
    >
        <x-slot name="actions">
            <a 
                href="{{ route('admin.catalogue.moderation.show', $product) }}" 
                class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition"
            >
                &larr; Back to Inspection
            </a>
        </x-slot>
    </x-page-header>

    <x-card padding="p-6">
        <form method="POST" action="{{ route('admin.catalogue.moderation.update', $product) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Title -->
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Product Title <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="title" 
                        value="{{ old('title', $product->title) }}" 
                        required 
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    />
                </div>

                <!-- Category -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Catalogue Category <span class="text-rose-500">*</span>
                    </label>
                    <select name="category_id" required class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('category_id', $product->category_id) == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Brand -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Brand Affiliation
                    </label>
                    <select name="brand_id" class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                        <option value="">No Brand / Generic</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" {{ old('brand_id', $product->brand_id) == $brand->id ? 'selected' : '' }}>
                                {{ $brand->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- HSN Code -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        HSN / SAC Code
                    </label>
                    <input 
                        type="text" 
                        name="hsn_code" 
                        value="{{ old('hsn_code', $product->hsn_code) }}" 
                        placeholder="e.g. 04012000"
                        class="w-full p-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    />
                </div>

                <!-- Tax Percent -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Applicable GST / Tax Slab (%)
                    </label>
                    <input 
                        type="number" 
                        step="0.01" 
                        min="0" 
                        max="100" 
                        name="tax_percent" 
                        value="{{ old('tax_percent', $product->tax_percent) }}" 
                        placeholder="e.g. 18.00"
                        class="w-full p-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    />
                </div>

                @php
                    $defVar = $product->defaultVariant ?: $product->variants->first();
                @endphp
                <!-- MRP -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Default SKU MRP (₹)
                    </label>
                    <input 
                        type="number" 
                        step="0.01" 
                        min="0" 
                        name="mrp" 
                        value="{{ old('mrp', $defVar?->mrp) }}" 
                        class="w-full p-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    />
                </div>

                <!-- Selling Price -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Default SKU Selling Price (₹)
                    </label>
                    <input 
                        type="number" 
                        step="0.01" 
                        min="0" 
                        name="selling_price" 
                        value="{{ old('selling_price', $defVar?->selling_price) }}" 
                        class="w-full p-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    />
                </div>
            </div>

            <!-- Short Desc -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Short Description
                </label>
                <textarea 
                    name="short_desc" 
                    rows="2" 
                    class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                >{{ old('short_desc', $product->short_desc) }}</textarea>
            </div>

            <!-- Full Description -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Detailed Product Description
                </label>
                <textarea 
                    name="description" 
                    rows="5" 
                    class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                >{{ old('description', $product->description) }}</textarea>
            </div>

            <!-- Toggles -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-100 dark:border-slate-800">
                <label class="flex items-center space-x-2 text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_quick_capable" value="1" {{ old('is_quick_capable', $product->is_quick_capable) ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500">
                    <span>Quick Commerce Capable (10-30m)</span>
                </label>

                <label class="flex items-center space-x-2 text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_cod_allowed" value="1" {{ old('is_cod_allowed', $product->is_cod_allowed) ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500">
                    <span>Cash on Delivery (COD) Allowed</span>
                </label>

                <label class="flex items-center space-x-2 text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_returnable" value="1" {{ old('is_returnable', $product->is_returnable) ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500">
                    <span>Return Policy Allowed (7 Days)</span>
                </label>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                <a href="{{ route('admin.catalogue.moderation.show', $product) }}" class="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition">
                    Save Changes & Return to Preview
                </button>
            </div>
        </form>
    </x-card>
</div>
@endsection
