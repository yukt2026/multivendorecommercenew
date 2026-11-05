@extends('layouts.storefront')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <!-- Top Search Header & Typo Tolerance -->
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                    @if($query)
                        Search results for "<span class="text-indigo-600">{{ $query }}</span>"
                    @else
                        Explore OneStall Catalog
                    @endif
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Showing {{ $results->total() }} results across {{ setting('name', 'OneStall') }} network
                </p>
            </div>

            <!-- Tabs: Products / Stores / Brands -->
            <div class="flex items-center p-1 bg-slate-100 rounded-2xl border border-slate-200 self-start sm:self-auto text-xs font-bold">
                <a href="{{ request()->fullUrlWithQuery(['tab' => 'products', 'page' => 1]) }}"
                   class="px-4 py-2 rounded-xl transition {{ $tab === 'products' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    Products ({{ $tab === 'products' ? $results->total() : '•' }})
                </a>
                <a href="{{ request()->fullUrlWithQuery(['tab' => 'stores', 'page' => 1]) }}"
                   class="px-4 py-2 rounded-xl transition {{ $tab === 'stores' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    Stores ({{ $tab === 'stores' ? $results->total() : '•' }})
                </a>
                <a href="{{ request()->fullUrlWithQuery(['tab' => 'brands', 'page' => 1]) }}"
                   class="px-4 py-2 rounded-xl transition {{ $tab === 'brands' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    Brands ({{ $tab === 'brands' ? $results->total() : '•' }})
                </a>
            </div>
        </div>

        <!-- Typo Correction Suggestion ("Did you mean?") -->
        @if(!empty($corrections['suggested_query']) && strtolower($corrections['suggested_query']) !== strtolower($query))
            <div class="p-3 bg-amber-50 rounded-2xl border border-amber-200 text-xs text-slate-800 flex items-center space-x-2">
                <span class="text-amber-600 font-bold">Did you mean:</span>
                <a href="{{ request()->fullUrlWithQuery(['q' => $corrections['suggested_query']]) }}" class="font-bold text-indigo-600 hover:underline">
                    {{ $corrections['suggested_query'] }}
                </a>
            </div>
        @endif
    </div>

    <!-- Main Layout with Filters Sidebar & Product/Store/Brand Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 items-start" x-data="{ mobileFilterOpen: false }">

        <!-- Mobile Filter Button Trigger -->
        <div class="lg:hidden flex items-center justify-between">
            <button type="button"
                    @click="mobileFilterOpen = !mobileFilterOpen"
                    class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-800 flex items-center space-x-2 shadow-sm">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                <span>Filter & Refine</span>
                @if(count($appliedChips) > 0)
                    <span class="w-5 h-5 rounded-full bg-indigo-600 text-white text-[10px] flex items-center justify-center font-bold">
                        {{ count($appliedChips) }}
                    </span>
                @endif
            </button>

            <!-- Sorting Dropdown (Mobile) -->
            <form method="GET" action="{{ url()->current() }}" class="flex items-center space-x-2">
                @foreach(request()->except(['sort', 'page']) as $k => $v)
                    @if(is_array($v))
                        @foreach($v as $subV)
                            <input type="hidden" name="{{ $k }}[]" value="{{ $subV }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endif
                @endforeach
                <select name="sort" onchange="this.form.submit()" class="text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl py-2 pl-3 pr-8 focus:ring-indigo-500">
                    <option value="relevance" {{ $sort === 'relevance' ? 'selected' : '' }}>Relevance</option>
                    <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                    <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                    <option value="discount_desc" {{ $sort === 'discount_desc' ? 'selected' : '' }}>Top Discount</option>
                    <option value="rating_desc" {{ $sort === 'rating_desc' ? 'selected' : '' }}>Customer Rating</option>
                    <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Newest</option>
                </select>
            </form>
        </div>

        <!-- Filter Sidebar -->
        <aside :class="mobileFilterOpen ? 'fixed inset-0 z-50 bg-white p-6 overflow-y-auto' : 'hidden lg:block'"
               class="bg-white rounded-3xl border border-slate-200/80 p-5 space-y-6 shadow-sm">

            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center space-x-1.5">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span>Filters</span>
                </h3>

                <div class="flex items-center space-x-2">
                    @if(count($appliedChips) > 0)
                        <a href="{{ request()->url() }}?q={{ urlencode($query) }}&tab={{ $tab }}" class="text-[11px] font-bold text-rose-500 hover:underline">
                            Clear All
                        </a>
                    @endif
                    <button type="button" @click="mobileFilterOpen = false" class="lg:hidden p-1 text-slate-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <form method="GET" action="{{ url()->current() }}" class="space-y-6 text-xs">
                <input type="hidden" name="q" value="{{ $query }}">
                <input type="hidden" name="tab" value="{{ $tab }}">
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
                    <label class="flex items-center space-x-2.5 cursor-pointer">
                        <input type="radio" name="delivery_type" value="standard" {{ $filters['delivery_type'] === 'standard' ? 'checked' : '' }} onchange="this.form.submit()" class="text-indigo-600 focus:ring-0">
                        <span class="text-slate-700">📦 Standard Shipping</span>
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
                        <input type="number" name="price_min" value="{{ $filters['price_min'] ?? '' }}" placeholder="Min" class="w-full text-xs p-2 rounded-xl border border-slate-200 focus:ring-indigo-500">
                        <span class="text-slate-400">-</span>
                        <input type="number" name="price_max" value="{{ $filters['price_max'] ?? '' }}" placeholder="Max" class="w-full text-xs p-2 rounded-xl border border-slate-200 focus:ring-indigo-500">
                        <button type="submit" class="px-3 py-2 bg-slate-900 text-white rounded-xl font-bold hover:bg-slate-800 transition">Go</button>
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

                <!-- Categories Filter -->
                @if(isset($facets['categories']) && $facets['categories']->isNotEmpty())
                    <div class="pt-3 border-t border-slate-100 space-y-2">
                        <h4 class="font-bold text-slate-900 text-xs">Categories</h4>
                        <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                            @foreach($facets['categories'] as $c)
                                <label class="flex items-center justify-between text-slate-600 hover:text-slate-900 cursor-pointer">
                                    <div class="flex items-center space-x-2">
                                        <input type="radio" name="category" value="{{ $c->slug }}"
                                               {{ ($filters['category'] ?? '') === $c->slug ? 'checked' : '' }}
                                               onchange="this.form.submit()"
                                               class="text-indigo-600 focus:ring-0 h-3.5 w-3.5">
                                        <span class="truncate max-w-[140px]">{{ $c->name }}</span>
                                    </div>
                                    <span class="text-[10px] text-slate-400">{{ $c->products_count }}</span>
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

        <!-- Main Results Grid -->
        <main class="lg:col-span-3 space-y-5">

            <!-- Applied Filter Chips & Sorting Header (Desktop) -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <!-- Applied Filter Chips -->
                <div class="flex flex-wrap items-center gap-1.5 flex-1">
                    @forelse($appliedChips as $chip)
                        <span class="inline-flex items-center space-x-1 px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 shadow-2xs">
                            <span>{{ $chip['label'] }}</span>
                            <a href="{{ $chip['remove_url'] }}" class="text-indigo-400 hover:text-indigo-900 ml-1 font-black">&times;</a>
                        </span>
                    @empty
                        <span class="text-xs text-slate-400 font-medium">All active filters applied</span>
                    @endforelse

                    @if(count($appliedChips) > 0)
                        <a href="{{ request()->url() }}?q={{ urlencode($query) }}&tab={{ $tab }}" class="text-xs font-bold text-rose-500 hover:underline ml-2">
                            Reset All
                        </a>
                    @endif
                </div>

                <!-- Desktop Sort Selector -->
                <div class="hidden sm:flex items-center space-x-2 flex-shrink-0">
                    <span class="text-xs font-medium text-slate-500">Sort by:</span>
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
                        <select name="sort" onchange="this.form.submit()" class="text-xs font-bold text-slate-800 bg-white border border-slate-200 rounded-xl py-1.5 pl-3 pr-8 focus:ring-indigo-500 cursor-pointer shadow-2xs">
                            <option value="relevance" {{ $sort === 'relevance' ? 'selected' : '' }}>Relevance & Nearby</option>
                            <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                            <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                            <option value="discount_desc" {{ $sort === 'discount_desc' ? 'selected' : '' }}>Top Discount</option>
                            <option value="rating_desc" {{ $sort === 'rating_desc' ? 'selected' : '' }}>Customer Rating</option>
                            <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Newest</option>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Tab 1: Products Grid -->
            @if($tab === 'products')
                @if($results->isNotEmpty())
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                        @foreach($results as $product)
                            <x-product-card :product="$product" />
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    <div class="pt-6">
                        {{ $results->links() }}
                    </div>
                @else
                    <div class="p-12 text-center bg-white rounded-3xl border border-slate-200 space-y-3">
                        <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mx-auto text-2xl">
                            🔍
                        </div>
                        <h3 class="text-base font-bold text-slate-900">No products match your search</h3>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto">Try checking your spelling, removing filters, or browsing our popular categories.</p>
                        <a href="{{ route('home') }}" class="inline-block px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold text-xs shadow-md">
                            Browse All Categories
                        </a>
                    </div>
                @endif
            @endif

            <!-- Tab 2: Stores Directory -->
            @if($tab === 'stores')
                @if($results->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($results as $store)
                            <div class="group bg-white rounded-3xl border border-slate-200/80 hover:border-indigo-400 hover:shadow-lg transition overflow-hidden flex flex-col justify-between">
                                <div class="h-28 bg-gradient-to-r from-slate-800 to-indigo-950 relative overflow-hidden">
                                    @if($store->banner)
                                        <img src="{{ asset('storage/' . $store->banner) }}" alt="{{ $store->name }}" loading="lazy" class="w-full h-full object-cover">
                                    @endif
                                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 to-transparent"></div>
                                    <div class="absolute top-2.5 left-2.5 right-2.5 flex items-center justify-between">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $store->isOpenNow() ? 'bg-emerald-500 text-white' : 'bg-slate-700 text-slate-300' }}">
                                            {{ $store->isOpenNow() ? 'Open Now' : 'Closed' }}
                                        </span>
                                        @if(isset($store->distance_km))
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-white/90 text-slate-900">
                                                📍 {{ number_format((float) $store->distance_km, 1) }} km
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="p-4 flex-1 flex flex-col justify-between space-y-3">
                                    <div>
                                        <h4 class="text-sm font-extrabold text-slate-900 group-hover:text-indigo-600 transition truncate">{{ $store->name }}</h4>
                                        <p class="text-xs text-slate-500 line-clamp-2 mt-0.5">{{ $store->description ?: 'Authorized neighbourhood store on OneStall Network' }}</p>
                                        <div class="flex items-center space-x-1.5 text-[10px] font-bold text-slate-600 mt-2">
                                            @if($store->accepts_quick_commerce)
                                                <span class="text-amber-600">⚡ 15-30m Delivery</span>
                                            @endif
                                            @if($store->accepts_pickup)
                                                <span class="text-indigo-600">&bull; 🛍️ Store Pickup</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                                        <span class="text-[11px] text-slate-400">{{ $store->location?->city ?? 'Bengaluru' }}</span>
                                        <a href="{{ route('store.show', $store->slug) }}" class="px-3.5 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white font-bold text-xs transition">
                                            Visit Store &rarr;
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="pt-6">
                        {{ $results->links() }}
                    </div>
                @else
                    <div class="p-12 text-center bg-white rounded-3xl border border-slate-200">
                        <p class="text-sm font-bold text-slate-800">No stores found matching your search</p>
                    </div>
                @endif
            @endif

            <!-- Tab 3: Brands Grid -->
            @if($tab === 'brands')
                @if($results->isNotEmpty())
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                        @foreach($results as $brand)
                            <a href="{{ route('brand.show', $brand->slug) }}" class="p-4 rounded-3xl bg-white border border-slate-200 hover:border-indigo-400 hover:shadow-md transition text-center flex flex-col items-center justify-between">
                                <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center p-2 mb-2 overflow-hidden">
                                    @if($brand->logo)
                                        <img src="{{ asset('storage/' . $brand->logo) }}" alt="{{ $brand->name }}" class="w-full h-full object-contain">
                                    @else
                                        <span class="text-xl font-black text-indigo-600">{{ substr($brand->name, 0, 2) }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center space-x-1">
                                    <span class="text-xs font-bold text-slate-900 truncate">{{ $brand->name }}</span>
                                    @if($brand->is_verified)
                                        <span class="text-blue-500">✓</span>
                                    @endif
                                </div>
                                <span class="text-[10px] text-slate-400 mt-1">{{ $brand->products_count }} Products</span>
                            </a>
                        @endforeach
                    </div>
                    <div class="pt-6">
                        {{ $results->links() }}
                    </div>
                @else
                    <div class="p-12 text-center bg-white rounded-3xl border border-slate-200">
                        <p class="text-sm font-bold text-slate-800">No brands found matching your search</p>
                    </div>
                @endif
            @endif

        </main>
    </div>

</div>
@endsection
