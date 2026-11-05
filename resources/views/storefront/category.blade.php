@extends('layouts.storefront')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <!-- Breadcrumb Navigation -->
    <nav class="flex items-center space-x-2 text-xs text-slate-500 overflow-x-auto no-scrollbar">
        @foreach($breadcrumbs as $crumb)
            @if(!$loop->last)
                <a href="{{ $crumb['url'] }}" class="hover:text-indigo-600 font-medium whitespace-nowrap">{{ $crumb['name'] }}</a>
                <span class="text-slate-300">/</span>
            @else
                <span class="font-bold text-slate-800 whitespace-nowrap">{{ $crumb['name'] }}</span>
            @endif
        @endforeach
    </nav>

    <!-- SEO Category Header Banner Block -->
    <div class="p-6 rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white shadow-xl relative overflow-hidden">
        <div class="max-w-3xl space-y-2 relative z-10">
            <div class="flex items-center space-x-2">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-white/20 text-white backdrop-blur-md">
                    CATEGORY SHOWCASE
                </span>
                @if($category->is_quick_eligible)
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950 shadow-sm">
                        ⚡ 10-30 MIN DELIVERY ELIGIBLE
                    </span>
                @endif
            </div>

            <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight">{{ $category->name }}</h1>
            <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                {{ $category->meta_description ?: $category->description ?: ('Browse our full selection of ' . $category->name . ' sourced directly from verified local stores and national brands with guaranteed quality and lightning fast delivery.') }}
            </p>

            <!-- Subcategories Horizontal Pills -->
            @if($category->children && $category->children->isNotEmpty())
                <div class="pt-3 flex flex-wrap gap-2">
                    @foreach($category->children as $child)
                        <a href="{{ route('category.show', $child->slug) }}" class="inline-flex items-center px-3 py-1 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-semibold backdrop-blur-sm transition">
                            <span>{{ $child->name }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Products & Filter Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 items-start" x-data="{ mobileFilterOpen: false }">

        <!-- Mobile Filter Button Trigger -->
        <div class="lg:hidden flex items-center justify-between">
            <button type="button"
                    @click="mobileFilterOpen = !mobileFilterOpen"
                    class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-800 flex items-center space-x-2 shadow-sm">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                <span>Filters</span>
            </button>

            <!-- Sorting Dropdown (Mobile) -->
            <form method="GET" action="{{ url()->current() }}">
                <select name="sort" onchange="this.form.submit()" class="text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl py-2 pl-3 pr-8 focus:ring-indigo-500">
                    <option value="relevance" {{ $sort === 'relevance' ? 'selected' : '' }}>Relevance & Nearby</option>
                    <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                    <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                    <option value="discount_desc" {{ $sort === 'discount_desc' ? 'selected' : '' }}>Top Discount</option>
                    <option value="rating_desc" {{ $sort === 'rating_desc' ? 'selected' : '' }}>Rating</option>
                    <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Newest</option>
                </select>
            </form>
        </div>

        <!-- Filter Sidebar -->
        <aside :class="mobileFilterOpen ? 'fixed inset-0 z-50 bg-white p-6 overflow-y-auto' : 'hidden lg:block'"
               class="bg-white rounded-3xl border border-slate-200/80 p-5 space-y-6 shadow-sm">

            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Filters</h3>
                <button type="button" @click="mobileFilterOpen = false" class="lg:hidden p-1 text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="GET" action="{{ url()->current() }}" class="space-y-6 text-xs">
                <input type="hidden" name="sort" value="{{ $sort }}">

                <!-- Delivery Speed -->
                <div class="space-y-2">
                    <h4 class="font-bold text-slate-900 text-xs">Delivery Speed</h4>
                    <label class="flex items-center space-x-2.5 cursor-pointer">
                        <input type="radio" name="delivery_type" value="" {{ empty($filters['delivery_type']) ? 'checked' : '' }} onchange="this.form.submit()" class="text-indigo-600 focus:ring-0">
                        <span class="text-slate-600">All Delivery Speeds</span>
                    </label>
                    <label class="flex items-center space-x-2.5 cursor-pointer">
                        <input type="radio" name="delivery_type" value="quick" {{ $filters['delivery_type'] === 'quick' ? 'checked' : '' }} onchange="this.form.submit()" class="text-indigo-600 focus:ring-0">
                        <span class="font-extrabold text-amber-600 flex items-center">
                            <span>⚡ 15-30 Min Quick</span>
                        </span>
                    </label>
                    <label class="flex items-center space-x-2.5 cursor-pointer">
                        <input type="radio" name="delivery_type" value="pickup" {{ $filters['delivery_type'] === 'pickup' ? 'checked' : '' }} onchange="this.form.submit()" class="text-indigo-600 focus:ring-0">
                        <span class="text-slate-700">🛍️ Store Pickup</span>
                    </label>
                </div>

                <!-- Availability Toggle -->
                <div class="pt-3 border-t border-slate-100">
                    <label class="flex items-center justify-between cursor-pointer">
                        <span class="font-bold text-slate-900">In Stock Only</span>
                        <input type="checkbox" name="in_stock" value="1" {{ !empty($filters['in_stock']) ? 'checked' : '' }} onchange="this.form.submit()" class="rounded text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                    </label>
                </div>

                <!-- Price Range -->
                <div class="pt-3 border-t border-slate-100 space-y-2">
                    <h4 class="font-bold text-slate-900 text-xs">Price Range (₹)</h4>
                    <div class="flex items-center space-x-2">
                        <input type="number" name="price_min" value="{{ $filters['price_min'] ?? '' }}" placeholder="Min" class="w-full text-xs p-2 rounded-xl border border-slate-200">
                        <span class="text-slate-400">-</span>
                        <input type="number" name="price_max" value="{{ $filters['price_max'] ?? '' }}" placeholder="Max" class="w-full text-xs p-2 rounded-xl border border-slate-200">
                        <button type="submit" class="px-3 py-2 bg-slate-900 text-white rounded-xl font-bold">Go</button>
                    </div>
                </div>

                <!-- Brands Filter -->
                @if(isset($facets['brands']) && $facets['brands']->isNotEmpty())
                    <div class="pt-3 border-t border-slate-100 space-y-2">
                        <h4 class="font-bold text-slate-900 text-xs">Brands</h4>
                        <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                            @foreach($facets['brands'] as $b)
                                <label class="flex items-center justify-between text-slate-600 hover:text-slate-900 cursor-pointer">
                                    <div class="flex items-center space-x-2">
                                        <input type="checkbox" name="brands[]" value="{{ $b->slug }}"
                                               {{ in_array($b->slug, (array) ($filters['brands'] ?? []), true) ? 'checked' : '' }}
                                               onchange="this.form.submit()"
                                               class="rounded text-indigo-600 focus:ring-indigo-500 h-3.5 w-3.5">
                                        <span class="truncate max-w-[140px]">{{ $b->name }}</span>
                                    </div>
                                    <span class="text-[10px] text-slate-400">{{ $b->products_count }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition">
                    Apply Filters
                </button>
            </form>
        </aside>

        <!-- Product Grid -->
        <main class="lg:col-span-3 space-y-5">
            <!-- Desktop Header: Product Count & Sorting -->
            <div class="hidden sm:flex items-center justify-between pb-3 border-b border-slate-200/80">
                <span class="text-xs font-bold text-slate-700">{{ $products->total() }} Products Found</span>

                <div class="flex items-center space-x-2">
                    <span class="text-xs font-medium text-slate-500">Sort:</span>
                    <form method="GET" action="{{ url()->current() }}">
                        @foreach(request()->except(['sort', 'page']) as $k => $v)
                            @if(is_array($v))
                                @foreach($v as $subV)
                                    <input type="hidden" name="{{ $k }}[]" value="{{ $subV }}">
                                @endforeach
                            @else
                                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                            @endif
                        @endforeach
                        <select name="sort" onchange="this.form.submit()" class="text-xs font-bold text-slate-800 bg-white border border-slate-200 rounded-xl py-1.5 pl-3 pr-8 focus:ring-indigo-500 cursor-pointer">
                            <option value="relevance" {{ $sort === 'relevance' ? 'selected' : '' }}>Relevance & Nearby</option>
                            <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                            <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                            <option value="discount_desc" {{ $sort === 'discount_desc' ? 'selected' : '' }}>Top Discount</option>
                            <option value="rating_desc" {{ $sort === 'rating_desc' ? 'selected' : '' }}>Rating</option>
                            <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Newest</option>
                        </select>
                    </form>
                </div>
            </div>

            @if($products->isNotEmpty())
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    @foreach($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>

                <div class="pt-6">
                    {{ $products->links() }}
                </div>
            @else
                <div class="p-12 text-center bg-white rounded-3xl border border-slate-200">
                    <p class="text-sm font-bold text-slate-800">No products found matching these filters in this category.</p>
                    <a href="{{ route('category.show', $category->slug) }}" class="inline-block mt-3 text-xs font-bold text-indigo-600 hover:underline">
                        Clear all filters
                    </a>
                </div>
            @endif
        </main>
    </div>

</div>
@endsection
