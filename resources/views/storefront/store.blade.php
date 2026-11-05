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
    <div class="relative bg-white border-b border-slate-200">
        <!-- Banner Image Container -->
        <div class="w-full h-48 sm:h-64 lg:h-72 overflow-hidden bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 relative">
            @if($store->banner)
                <img src="{{ $store->banner }}" alt="{{ $store->name }} Banner" class="w-full h-full object-cover" />
            @else
                <div class="w-full h-full flex items-center justify-center opacity-30">
                    <svg class="w-24 h-24 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/70 via-transparent to-transparent"></div>
        </div>

        <!-- Store Profile Floating Header -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-16 sm:-mt-20 relative z-10 pb-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xl p-5 sm:p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                
                <!-- Left: Logo & Store Metadata -->
                <div class="flex items-start space-x-4">
                    <!-- Store Logo Frame -->
                    <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl bg-white border-2 border-slate-100 shadow-md overflow-hidden shrink-0 flex items-center justify-center">
                        @if($store->logo)
                            <img src="{{ $store->logo }}" alt="{{ $store->name }}" class="w-full h-full object-cover" />
                        @else
                            <div class="w-full h-full bg-indigo-600 flex items-center justify-center text-white text-2xl font-black">
                                {{ substr($store->name, 0, 2) }}
                            </div>
                        @endif
                    </div>

                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900">
                                {{ $store->name }}
                            </h1>
                            <span class="inline-flex items-center space-x-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                <svg class="w-3 h-3 text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span>Verified Merchant</span>
                            </span>
                        </div>

                        <!-- Rating & Timings Info -->
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600">
                            <!-- Star Rating -->
                            <div class="flex items-center space-x-1 font-bold text-slate-900">
                                <span class="text-amber-500">★</span>
                                <span>{{ number_format($store->rating_avg ?: 4.8, 1) }}</span>
                                <span class="text-slate-400 font-normal">({{ $store->rating_count ?: 48 }} ratings)</span>
                            </div>

                            <!-- Open Now Badge -->
                            <div class="flex items-center space-x-1.5">
                                @if($isOpenNow)
                                    <span class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span>Open Now</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        <span>Closed Today</span>
                                    </span>
                                @endif
                            </div>

                            <!-- Location -->
                            @if($location)
                                <div class="text-slate-500 truncate max-w-xs">
                                    📍 {{ $location->city }} ({{ $location->pincode }})
                                </div>
                            @endif
                        </div>

                        <!-- Delivery Radius & Mode Badges -->
                        <div class="flex flex-wrap items-center gap-2 pt-1 text-[11px]">
                            @if($store->accepts_quick_commerce)
                                <span class="px-2 py-0.5 rounded-md font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                    ⚡ 10-30 Min Delivery (Within {{ $store->effectiveDeliveryRadius() }} km)
                                </span>
                            @endif

                            @if($store->accepts_pickup)
                                <span class="px-2 py-0.5 rounded-md font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                    🏬 Counter Pickup Available
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Right: Follow / Favorite Action -->
                <div class="flex items-center space-x-3 shrink-0">
                    <button 
                        type="button" 
                        @click="toggleFollow()" 
                        class="px-5 py-2.5 rounded-xl font-bold text-xs transition flex items-center space-x-2 border shadow-xs"
                        :class="isFollowing ? 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100' : 'bg-slate-900 text-white border-slate-900 hover:bg-slate-800'"
                    >
                        <span x-text="isFollowing ? '♥ Following' : '♡ Follow Store'"></span>
                    </button>

                    <button 
                        type="button" 
                        @click="navigator.share ? navigator.share({ title: @js($store->name), url: window.location.href }) : alert('URL copied to clipboard!')"
                        class="p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 transition"
                        title="Share Store"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN STORE NAVIGATION TABS -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="border-b border-slate-200">
            <nav class="flex space-x-6 overflow-x-auto text-xs font-bold" aria-label="Store Tabs">
                <button 
                    type="button" 
                    @click="activeTab = 'featured'" 
                    class="py-3 px-1 border-b-2 transition whitespace-nowrap"
                    :class="activeTab === 'featured' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800'"
                >
                    ⚡ Featured &amp; Quick ({{ $featuredProducts->count() }})
                </button>

                <button 
                    type="button" 
                    @click="activeTab = 'new_arrivals'" 
                    class="py-3 px-1 border-b-2 transition whitespace-nowrap"
                    :class="activeTab === 'new_arrivals' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800'"
                >
                    ✨ New Arrivals ({{ $newArrivals->count() }})
                </button>

                <button 
                    type="button" 
                    @click="activeTab = 'all'" 
                    class="py-3 px-1 border-b-2 transition whitespace-nowrap"
                    :class="activeTab === 'all' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800'"
                >
                    📦 All Products ({{ $allProducts->total() }})
                </button>

                <button 
                    type="button" 
                    @click="activeTab = 'coupons'" 
                    class="py-3 px-1 border-b-2 transition whitespace-nowrap"
                    :class="activeTab === 'coupons' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800'"
                >
                    🏷️ Offers &amp; Coupons ({{ $coupons->count() }})
                </button>

                <button 
                    type="button" 
                    @click="activeTab = 'reviews'" 
                    class="py-3 px-1 border-b-2 transition whitespace-nowrap"
                    :class="activeTab === 'reviews' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800'"
                >
                    ★ Reviews &amp; Ratings ({{ $reviews->total() }})
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
                    @include('storefront.partials.product-card', ['product' => $product])
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
                    @include('storefront.partials.product-card', ['product' => $product])
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
                    @include('storefront.partials.product-card', ['product' => $product])
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
                <div class="flex items-center space-x-3">
                    <span class="text-3xl font-black text-slate-900">{{ number_format($store->rating_avg ?: 4.8, 1) }}</span>
                    <div>
                        <div class="text-amber-500 text-sm">★★★★★</div>
                        <span class="text-[11px] text-slate-400 font-medium">{{ max($store->rating_count, 1) }} verified reviews</span>
                    </div>
                </div>
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
        }
    };
}
</script>
@endsection
