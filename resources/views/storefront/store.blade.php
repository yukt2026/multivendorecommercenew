@extends('layouts.storefront', [
    'title' => $store->meta_title ?: $store->name . ' - Storefront on OneStall.in',
    'metaDescription' => $store->meta_description ?: ($store->description ?: 'Shop fresh groceries, daily essentials, and local goods from ' . $store->name),
])

@section('content')
<!-- JSON-LD Structured Data for Google Indexing -->
<script type="application/ld+json">
{!! json_encode($schemaJson, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>

<div class="space-y-6 pb-12" x-data="publicStorefront({
    slug: @js($store->slug),
    followUrl: '{{ route("store.follow", $store->slug) }}',
    isFollowing: @js($isFollowing),
    activeTab: '{{ $activeTab }}',
})">
    <!-- HERO STORE BANNER & PROFILE CARD -->
    <div class="relative bg-slate-100 border-b border-slate-200/80">
        <!-- Banner Image Container with Rich Visual Depth -->
        <div class="w-full h-44 sm:h-60 lg:h-72 overflow-hidden bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 relative">
            @if($store->banner_url)
                <img src="{{ $store->banner_url }}" alt="{{ $store->name }} Banner" class="w-full h-full object-cover" />
            @else
                <div class="w-full h-full flex flex-col items-center justify-center relative overflow-hidden">
                    <div class="absolute inset-0 opacity-15 bg-[radial-gradient(#818cf8_1px,transparent_1px)] [background-size:16px_16px]"></div>
                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center text-white/40 mb-2">
                        <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <span class="text-xs sm:text-sm font-semibold text-white/50 tracking-wide uppercase">{{ $store->name }} Storefront</span>
                </div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-950/30 to-transparent"></div>
        </div>

        <!-- Store Profile Floating Header Card -->
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 -mt-14 sm:-mt-20 relative z-10 pb-6">
            <div class="bg-white/95 backdrop-blur-md rounded-2xl sm:rounded-3xl border border-slate-200/90 shadow-xl p-4 sm:p-6 transition-all">
                
                <!-- Main Header Flex: Mobile Stacks Gracefully, Desktop Side-by-Side -->
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-5 sm:gap-6">
                    
                    <!-- Left Column: Logo & Store Identity -->
                    <div class="flex items-start gap-3 sm:gap-5 flex-1 min-w-0">
                        <!-- Store Logo Frame -->
                        <div class="w-16 h-16 sm:w-22 sm:h-22 rounded-2xl bg-white border-2 border-white shadow-md ring-1 ring-slate-200/80 overflow-hidden shrink-0 flex items-center justify-center self-start">
                            @if($store->logo_url)
                                <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="w-full h-full object-cover" />
                            @else
                                <div class="w-full h-full bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center text-white text-xl sm:text-2xl font-black">
                                    {{ substr($store->name, 0, 2) }}
                                </div>
                            @endif
                        </div>

                        <!-- Store Title, Verification & Quick Meta -->
                        <div class="space-y-2 flex-1 min-w-0">
                            <!-- Store Name & Verification Badges -->
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-lg sm:text-2xl font-black tracking-tight text-slate-900 leading-tight">
                                    {{ $store->name }}
                                </h1>
                                <span class="inline-flex items-center space-x-1 px-2.5 py-0.5 rounded-full text-[10px] sm:text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200/80 shadow-2xs">
                                    <svg class="w-3 h-3 text-indigo-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <span>Verified Merchant</span>
                                </span>
                            </div>

                            <!-- Rating, Open Status & Timing Chips (Organized Responsive Row) -->
                            <div class="flex flex-wrap items-center gap-2 pt-0.5 text-xs">
                                <!-- Star Rating Badge -->
                                @if($store->rating_count > 0)
                                    <span class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-lg font-bold bg-amber-50 text-amber-900 border border-amber-200/80 text-[11px]">
                                        <span class="text-amber-500">★</span>
                                        <span>{{ number_format($store->rating_avg, 1) }}</span>
                                        <span class="text-slate-400 font-normal">({{ $store->rating_count }} {{ Str::plural('review', $store->rating_count) }})</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-lg font-bold bg-slate-100 text-slate-700 border border-slate-200 text-[11px]">
                                        <span class="text-amber-400">★</span>
                                        <span>New Store</span>
                                        <span class="text-slate-400 font-normal">(No ratings yet)</span>
                                    </span>
                                @endif

                                <!-- Open / Closed Status Pill -->
                                @if($store->status !== \App\Enums\StoreStatus::ACTIVE)
                                    <span class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-lg font-bold bg-slate-100 text-slate-700 border border-slate-300 text-[11px]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        <span>Store Offline</span>
                                    </span>
                                @elseif($isOpenNow)
                                    <span class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-lg font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span>Open Now</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-lg font-bold bg-rose-50 text-rose-700 border border-rose-200 text-[11px]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        <span>Closed Now</span>
                                    </span>
                                @endif

                                <!-- Today's Hours & Weekly Popover Chip -->
                                @php
                                    $todayTiming = $timings->firstWhere('day_of_week', now()->dayOfWeek);
                                @endphp
                                @if($todayTiming || $timings->isNotEmpty())
                                    <div class="relative inline-flex items-center" x-data="{ openTimings: false }">
                                        <button 
                                            type="button" 
                                            @click="openTimings = !openTimings" 
                                            class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-lg font-bold text-[11px] bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition cursor-pointer"
                                            title="View 7 days operating schedule"
                                        >
                                            @if($todayTiming)
                                                @if($todayTiming->is_closed)
                                                    <span class="text-rose-600 font-bold">Closed Today</span>
                                                @elseif($todayTiming->is24Hours())
                                                    <span class="text-emerald-700 font-bold">⚡ 24x7 Open</span>
                                                @elseif($todayTiming->opens_at && $todayTiming->closes_at)
                                                    <span>{{ \Carbon\Carbon::parse($todayTiming->opens_at)->format('h:i A') }} - {{ \Carbon\Carbon::parse($todayTiming->closes_at)->format('h:i A') }}</span>
                                                @endif
                                            @else
                                                <span>Weekly Hours</span>
                                            @endif
                                            <span class="text-indigo-600 font-semibold text-[10px] ml-1">Schedule ▾</span>
                                        </button>

                                        <!-- Weekly Schedule Dropdown Popover -->
                                        <div 
                                            x-show="openTimings" 
                                            @click.away="openTimings = false" 
                                            x-cloak
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-100"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-95"
                                            class="absolute left-0 top-full mt-1.5 z-40 w-72 p-4 bg-white rounded-2xl shadow-2xl border border-slate-200 text-xs space-y-2"
                                        >
                                            <div class="font-extrabold text-slate-800 pb-2 border-b border-slate-100 flex items-center justify-between">
                                                <span class="flex items-center space-x-1.5">
                                                    <span>🗓️</span>
                                                    <span>Weekly Schedule</span>
                                                </span>
                                                <span class="text-[10px] font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">7 Days</span>
                                            </div>
                                            @php
                                                $dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'];
                                            @endphp
                                            <div class="space-y-1.5">
                                                @foreach($dayNames as $dNum => $dName)
                                                    @php
                                                        $t = $timings->firstWhere('day_of_week', $dNum);
                                                        $isToday = now()->dayOfWeek === $dNum;
                                                    @endphp
                                                    <div class="flex items-center justify-between text-[11px] py-0.5 px-2 rounded-lg {{ $isToday ? 'bg-indigo-50 font-bold text-indigo-700' : 'text-slate-600' }}">
                                                        <span>{{ $dName }}{{ $isToday ? ' (Today)' : '' }}</span>
                                                        <span>
                                                            @if(!$t || $t->is_closed)
                                                                <span class="text-rose-500 font-medium">Closed</span>
                                                            @elseif($t->is24Hours())
                                                                <span class="text-emerald-600 font-bold">24 Hours</span>
                                                            @elseif($t->opens_at && $t->closes_at)
                                                                {{ \Carbon\Carbon::parse($t->opens_at)->format('h:i A') }} - {{ \Carbon\Carbon::parse($t->closes_at)->format('h:i A') }}
                                                            @else
                                                                Open
                                                            @endif
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <!-- Location Pill -->
                                @if($location)
                                    <span class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-lg text-[11px] font-medium bg-slate-50 text-slate-600 border border-slate-200" title="{{ $location->address }}">
                                        <span>📍</span>
                                        <span>{{ $location->city }} ({{ $location->pincode }})</span>
                                    </span>
                                @endif
                            </div>

                            <!-- Fulfillment Capabilities (Quick Commerce & Pickup Badges) -->
                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                @if($store->accepts_quick_commerce)
                                    <span class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-amber-50 text-amber-900 border border-amber-200/90 shadow-2xs">
                                        <span>⚡</span>
                                        <span>10-30 Min Delivery</span>
                                        <span class="text-amber-700 font-normal">(Within {{ $store->effectiveDeliveryRadius() }} km)</span>
                                    </span>
                                @endif

                                @if($store->accepts_pickup)
                                    <span class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-50 text-slate-700 border border-slate-200 shadow-2xs">
                                        <span>🏬</span>
                                        <span>Counter Pickup Available</span>
                                    </span>
                                @endif
                            </div>

                            @if($store->description)
                                <p class="text-xs text-slate-600 pt-1 leading-relaxed max-w-2xl line-clamp-2">
                                    {{ $store->description }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <!-- Right Column: Follow & Share Action Buttons -->
                    <div class="flex items-center gap-2.5 sm:self-center lg:self-start shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                        <button 
                            type="button" 
                            @click="toggleFollow()" 
                            class="flex-1 sm:flex-none px-5 py-2.5 rounded-xl font-bold text-xs transition-all flex items-center justify-center space-x-2 shadow-sm cursor-pointer"
                            :class="isFollowing ? 'bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100' : 'bg-slate-900 text-white border border-slate-900 hover:bg-slate-800 active:scale-95'"
                        >
                            <span class="text-sm" x-text="isFollowing ? '❤️' : '🤍'"></span>
                            <span x-text="isFollowing ? 'Following Store' : 'Follow Store'"></span>
                        </button>

                        <button 
                            type="button" 
                            @click="shareStore()"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 active:scale-95 text-slate-700 transition flex items-center justify-center space-x-1.5 cursor-pointer shadow-2xs"
                            title="Share Store Link"
                        >
                            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                            <span class="text-xs font-bold hidden sm:inline">Share</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN STORE NAVIGATION TABS -->
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <div class="border-b border-slate-200/80 bg-white rounded-2xl p-1.5 shadow-2xs">
            <nav class="flex space-x-1 sm:space-x-2 overflow-x-auto text-xs font-bold no-scrollbar scroll-smooth" aria-label="Store Tabs">
                <button 
                    type="button" 
                    @click="activeTab = 'featured'" 
                    class="py-2.5 px-3.5 rounded-xl transition-all whitespace-nowrap flex items-center space-x-1.5 cursor-pointer"
                    :class="activeTab === 'featured' ? 'bg-indigo-600 text-white shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                >
                    <span>⚡ Featured &amp; Quick</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold" :class="activeTab === 'featured' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">{{ $featuredProducts->count() }}</span>
                </button>

                <button 
                    type="button" 
                    @click="activeTab = 'new_arrivals'" 
                    class="py-2.5 px-3.5 rounded-xl transition-all whitespace-nowrap flex items-center space-x-1.5 cursor-pointer"
                    :class="activeTab === 'new_arrivals' ? 'bg-indigo-600 text-white shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                >
                    <span>✨ New Arrivals</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold" :class="activeTab === 'new_arrivals' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">{{ $newArrivals->count() }}</span>
                </button>

                <button 
                    type="button" 
                    @click="activeTab = 'all'" 
                    class="py-2.5 px-3.5 rounded-xl transition-all whitespace-nowrap flex items-center space-x-1.5 cursor-pointer"
                    :class="activeTab === 'all' ? 'bg-indigo-600 text-white shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                >
                    <span>📦 All Products</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold" :class="activeTab === 'all' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">{{ $allProducts->total() }}</span>
                </button>

                <button 
                    type="button" 
                    @click="activeTab = 'coupons'" 
                    class="py-2.5 px-3.5 rounded-xl transition-all whitespace-nowrap flex items-center space-x-1.5 cursor-pointer"
                    :class="activeTab === 'coupons' ? 'bg-indigo-600 text-white shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                >
                    <span>🏷️ Coupons</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold" :class="activeTab === 'coupons' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">{{ $coupons->count() }}</span>
                </button>

                <button 
                    type="button" 
                    @click="activeTab = 'reviews'" 
                    class="py-2.5 px-3.5 rounded-xl transition-all whitespace-nowrap flex items-center space-x-1.5 cursor-pointer"
                    :class="activeTab === 'reviews' ? 'bg-indigo-600 text-white shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                >
                    <span>★ Reviews</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold" :class="activeTab === 'reviews' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">{{ $reviews->total() }}</span>
                </button>
            </nav>
        </div>

        <!-- TAB CONTENT 1: FEATURED -->
        <div x-show="activeTab === 'featured'" class="py-6 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-extrabold text-slate-900">Featured &amp; Quick Delivery Essentials</h2>
                    <p class="text-xs text-slate-500">Curated instant-delivery items available from this local store.</p>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                @forelse($featuredProducts as $product)
                    <x-product-card :product="$product" />
                @empty
                    <div class="col-span-full py-12 text-center text-xs text-slate-400">
                        No featured products published yet.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- TAB CONTENT 2: NEW ARRIVALS -->
        <div x-show="activeTab === 'new_arrivals'" class="py-6 space-y-4">
            <div>
                <h2 class="text-base font-extrabold text-slate-900">Latest Store Additions</h2>
                <p class="text-xs text-slate-500">Newly listed items in stock this week.</p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                @forelse($newArrivals as $product)
                    <x-product-card :product="$product" />
                @empty
                    <div class="col-span-full py-12 text-center text-xs text-slate-400">
                        No new arrivals available.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- TAB CONTENT 3: ALL PRODUCTS WITH CATEGORY FILTER & SORT -->
        <div x-show="activeTab === 'all'" class="py-6 space-y-6">
            <!-- Filter & Sort Bar -->
            <form method="GET" action="{{ route('store.show', $store->slug) }}" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 rounded-2xl bg-white border border-slate-200 shadow-xs">
                <input type="hidden" name="tab" value="all">

                <!-- Category Filter -->
                <div class="flex items-center space-x-2">
                    <label class="text-xs font-bold text-slate-600">Category:</label>
                    <select 
                        name="category" 
                        onchange="this.form.submit()" 
                        class="p-2 text-xs rounded-xl border border-slate-200 bg-slate-50 text-slate-900"
                    >
                        <option value="">All Categories</option>
                        @foreach($availableCategories as $cat)
                            <option value="{{ $cat->slug }}" {{ $selectedCategory === $cat->slug ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Sort Filter -->
                <div class="flex items-center space-x-2">
                    <label class="text-xs font-bold text-slate-600">Sort By:</label>
                    <select 
                        name="sort" 
                        onchange="this.form.submit()" 
                        class="p-2 text-xs rounded-xl border border-slate-200 bg-slate-50 text-slate-900"
                    >
                        <option value="newest" {{ $selectedSort === 'newest' ? 'selected' : '' }}>Newest Listed</option>
                        <option value="price_asc" {{ $selectedSort === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                        <option value="price_desc" {{ $selectedSort === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                        <option value="name_asc" {{ $selectedSort === 'name_asc' ? 'selected' : '' }}>Product Name (A-Z)</option>
                    </select>
                </div>
            </form>

            <!-- Products Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                @forelse($allProducts as $product)
                    <x-product-card :product="$product" />
                @empty
                    <div class="col-span-full py-12 text-center text-xs text-slate-400">
                        No products match your filter criteria.
                    </div>
                @endforelse
            </div>

            <!-- Pagination -->
            <div class="pt-4">
                {{ $allProducts->links() }}
            </div>
        </div>

        <!-- TAB CONTENT 4: OFFERS & COUPONS -->
        <div x-show="activeTab === 'coupons'" class="py-6 space-y-4">
            <div>
                <h2 class="text-base font-extrabold text-slate-900">Active Deals &amp; Store Discounts</h2>
                <p class="text-xs text-slate-500">Apply these promotional codes at checkout for instant order savings.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($coupons as $coupon)
                    <div class="p-5 rounded-2xl bg-white border border-dashed border-indigo-200 shadow-xs space-y-3 relative overflow-hidden" x-data="{ copied: false }">
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-extrabold uppercase bg-amber-100 text-amber-800">
                                    {{ $coupon->type->value === 'percent' ? $coupon->value . '% OFF' : '₹' . $coupon->value . ' FLAT OFF' }}
                                </span>
                                <h3 class="text-sm font-extrabold text-slate-900 mt-2 font-mono">
                                    {{ $coupon->code }}
                                </h3>
                            </div>
                            <button 
                                type="button" 
                                @click="navigator.clipboard.writeText('{{ $coupon->code }}'); copied = true; setTimeout(() => copied = false, 2500)"
                                class="px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-600 font-bold text-xs transition"
                            >
                                <span x-text="copied ? 'Copied! ✓' : 'Copy Code'"></span>
                            </button>
                        </div>

                        <p class="text-xs text-slate-500">
                            Min Order: ₹{{ number_format((float) $coupon->min_order, 2) }}
                            @if($coupon->max_discount)
                                &bull; Max Discount: ₹{{ number_format((float) $coupon->max_discount, 2) }}
                            @endif
                        </p>
                    </div>
                @empty
                    <div class="col-span-full py-12 text-center text-xs text-slate-400">
                        No promotional discount coupons active right now.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- TAB CONTENT 5: REVIEWS & RATINGS -->
        <div x-show="activeTab === 'reviews'" class="py-6 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-6 rounded-2xl bg-white border border-slate-200 shadow-xs">
                <div>
                    <h2 class="text-lg font-black text-slate-900">Customer Ratings &amp; Verification</h2>
                    <p class="text-xs text-slate-500">Authentic feedback from verified purchasers from this merchant.</p>
                </div>
                @if($reviews->total() > 0)
                    <div class="flex items-center space-x-3">
                        <span class="text-3xl font-black text-slate-900">{{ number_format($store->rating_avg, 1) }}</span>
                        <div>
                            <div class="text-amber-500 text-sm">
                                @for($i = 1; $i <= 5; $i++)
                                    {{ $i <= round($store->rating_avg) ? '★' : '☆' }}
                                @endfor
                            </div>
                            <span class="text-[11px] text-slate-400 font-medium">{{ $reviews->total() }} verified {{ Str::plural('review', $reviews->total()) }}</span>
                        </div>
                    </div>
                @else
                    <div class="text-right">
                        <span class="text-xs font-bold text-slate-600 block">No reviews yet</span>
                        <span class="text-[11px] text-slate-400">Order from this store to leave the first review!</span>
                    </div>
                @endif
            </div>

            <div class="space-y-3">
                @forelse($reviews as $review)
                    <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="font-bold text-slate-900">{{ $review->user?->name ?? 'Verified Customer' }}</span>
                                @if($review->is_verified_purchase)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700">✓ Verified Purchase</span>
                                @endif
                            </div>
                            <span class="text-[10px] text-slate-400">{{ $review->created_at->diffForHumans() }}</span>
                        </div>

                        <div class="text-amber-500 font-bold text-xs">
                            @for($i = 0; $i < $review->rating; $i++)
                                ★
                            @endfor
                        </div>

                        @if($review->title)
                            <h4 class="font-bold text-slate-800">{{ $review->title }}</h4>
                        @endif

                        <p class="text-slate-600">{{ $review->body }}</p>

                        @if($review->product)
                            <div class="pt-1 text-[11px] text-indigo-600 font-medium">
                                Item: {{ $review->product->title }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="py-12 text-center text-xs text-slate-400">
                        No customer reviews received yet.
                    </div>
                @endforelse

                <div class="pt-2">
                    {{ $reviews->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function publicStorefront(config) {
    return {
        activeTab: config.activeTab || 'featured',
        isFollowing: config.isFollowing,

        async toggleFollow() {
            try {
                const res = await fetch(config.followUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                if (res.status === 401) {
                    window.location.href = '{{ route("login") }}';
                    return;
                }

                const data = await res.json();
                if (data.success) {
                    this.isFollowing = data.following;
                    alert(data.message);
                }
            } catch (e) {
                console.error(e);
            }
        },

        async shareStore() {
            if (navigator.share) {
                try {
                    await navigator.share({
                        title: @js($store->name),
                        text: 'Shop from ' + @js($store->name) + ' on OneStall.in',
                        url: window.location.href,
                    });
                } catch (e) {
                    // User canceled share
                }
            } else if (navigator.clipboard) {
                try {
                    await navigator.clipboard.writeText(window.location.href);
                    alert('Store link copied to clipboard!');
                } catch (e) {
                    prompt('Copy store link:', window.location.href);
                }
            } else {
                prompt('Copy store link:', window.location.href);
            }
        }
    };
}
</script>
@endsection
