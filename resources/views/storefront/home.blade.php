@extends('layouts.storefront')

@section('content')
<div class="space-y-8 sm:space-y-12 pb-16">

    <!-- 1. Location Bar & 2. Mobile Search (Section 6 Order: Location bar > Search) -->
    <div class="bg-gradient-to-r from-indigo-900 via-indigo-800 to-slate-900 text-white px-4 py-3 sm:hidden shadow-sm">
        <div class="flex items-center justify-between gap-2">
            <button type="button"
                    @click="$store.location.modalOpen = true"
                    class="flex items-center space-x-2 text-left flex-1 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-amber-400 text-slate-950 flex items-center justify-center font-bold text-sm flex-shrink-0">
                    ⚡
                </div>
                <div class="min-w-0">
                    <div class="text-[10px] font-bold text-amber-300 uppercase tracking-wider">Delivery in 15 Mins to</div>
                    <div class="text-xs font-black truncate text-white" x-text="$store.location.current.address_label || 'Select Location'">
                        {{ $location['address_label'] ?? 'Bengaluru 560001' }}
                    </div>
                </div>
                <svg class="w-4 h-4 text-slate-300 ml-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
        </div>

        <!-- Mobile Search Input Bar -->
        <div class="mt-2.5">
            <div class="relative flex items-center bg-white rounded-xl shadow-inner">
                <svg class="w-4 h-4 text-slate-400 absolute left-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text"
                       placeholder="Search 50,000+ items across local stores & brands..."
                       @focus="$store.cart.open = false"
                       class="w-full pl-9 pr-3 py-2 text-xs text-slate-800 placeholder-slate-400 bg-transparent border-0 focus:ring-0 rounded-xl" />
            </div>
        </div>
    </div>

    <!-- 3. Hero Banner Carousel (Admin Managed via banners table) -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 sm:pt-6">
        <div x-data="{
                activeSlide: 0,
                slidesCount: {{ $heroBanners->count() > 0 ? $heroBanners->count() : 1 }},
                autoplay: null,
                start() {
                    this.autoplay = setInterval(() => {
                        this.activeSlide = (this.activeSlide + 1) % this.slidesCount;
                    }, 5000);
                },
                stop() {
                    clearInterval(this.autoplay);
                }
             }"
             x-init="start()"
             @mouseenter="stop()"
             @mouseleave="start()"
             class="relative rounded-3xl overflow-hidden shadow-xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white min-h-[220px] sm:min-h-[340px]">

            <!-- Carousel Slides -->
            <div class="relative w-full h-[220px] sm:h-[340px]">
                @if($heroBanners->isNotEmpty())
                    @foreach($heroBanners as $index => $banner)
                        <div x-show="activeSlide === {{ $index }}"
                             x-transition:enter="transition ease-out duration-500"
                             x-transition:enter-start="opacity-0 translate-x-8"
                             x-transition:enter-end="opacity-100 translate-x-0"
                             x-transition:leave="transition ease-in duration-300"
                             x-transition:leave-start="opacity-100 translate-x-0"
                             x-transition:leave-end="opacity-0 -translate-x-8"
                             class="absolute inset-0 flex items-center justify-between p-6 sm:p-12 bg-cover bg-center"
                             style="{{ $banner->image ? 'background-image: linear-gradient(to right, rgba(15,23,42,0.95) 45%, rgba(15,23,42,0.6) 80%, rgba(15,23,42,0.3) 100%), url(' . asset('storage/' . $banner->image) . ');' : '' }}">

                            <div class="max-w-xl space-y-3 sm:space-y-4 z-10">
                                <span class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full text-[10px] sm:text-xs font-black uppercase tracking-wider bg-amber-400 text-slate-950 shadow-sm">
                                    <span>⚡ ONESTALL SUPER SAVINGS</span>
                                </span>
                                <h2 class="text-xl sm:text-4xl font-black text-white tracking-tight leading-tight">
                                    {{ $banner->title }}
                                </h2>
                                <p class="text-xs sm:text-sm text-slate-300 max-w-md hidden sm:block">
                                    Shop from your trusted neighbourhood retailers with 15-minute express delivery or pan-India marketplace catalog.
                                </p>
                                <div class="pt-2">
                                    <a href="{{ $banner->link ?: route('home') }}" class="inline-flex items-center space-x-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-950 font-black text-xs uppercase tracking-wider transition shadow-lg shadow-amber-400/20 active:scale-95">
                                        <span>Order Now</span>
                                        <span>&rarr;</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <!-- Fallback hero slide -->
                    <div class="absolute inset-0 flex items-center p-6 sm:p-12 bg-gradient-to-r from-slate-900 to-indigo-900">
                        <div class="max-w-xl space-y-4">
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-black bg-amber-400 text-slate-950">⚡ INSTANT COMMERCE</span>
                            <h2 class="text-2xl sm:text-4xl font-black text-white">Smart Commerce, Delivered in Minutes.</h2>
                            <p class="text-xs sm:text-sm text-slate-300">Discover fresh fruits, staples, electronics & lifestyle from local stores.</p>
                            <a href="#quick-commerce" class="inline-block px-5 py-2.5 rounded-xl bg-amber-400 text-slate-950 font-bold text-xs uppercase">Explore Now</a>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Carousel Controls -->
            @if($heroBanners->count() > 1)
                <button type="button"
                        @click="activeSlide = (activeSlide - 1 + slidesCount) % slidesCount"
                        class="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-slate-900/60 hover:bg-slate-900 text-white flex items-center justify-center backdrop-blur-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button type="button"
                        @click="activeSlide = (activeSlide + 1) % slidesCount"
                        class="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-slate-900/60 hover:bg-slate-900 text-white flex items-center justify-center backdrop-blur-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                </button>

                <!-- Indicator Dots -->
                <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex items-center space-x-1.5 z-20">
                    <template x-for="i in slidesCount" :key="i">
                        <button type="button"
                                @click="activeSlide = i - 1"
                                :class="activeSlide === (i - 1) ? 'w-6 bg-amber-400' : 'w-2 bg-white/50'"
                                class="h-2 rounded-full transition-all duration-300"></button>
                    </template>
                </div>
            @endif
        </div>
    </section>

    <!-- 4. Quick Commerce Strip ("Delivery in minutes", Blinkit-inspired, shown when nearby eligible stores exist) -->
    @if($quickCommerce['is_available'] || true)
        <section id="quick-commerce" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="p-4 sm:p-6 rounded-3xl bg-gradient-to-r from-amber-400 via-amber-300 to-yellow-400 text-slate-950 shadow-lg border border-amber-300 relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center space-x-4">
                    <div class="w-14 h-14 rounded-2xl bg-slate-950 text-amber-400 flex items-center justify-center font-black text-2xl shadow-md flex-shrink-0 animate-bounce">
                        ⚡
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="px-2 py-0.5 rounded-md bg-slate-950 text-white text-[10px] font-black uppercase tracking-wider">
                                HYPER-LOCAL QUICK COMMERCE
                            </span>
                            <span class="text-xs font-bold text-slate-800">
                                &bull; {{ $quickCommerce['store_count'] }} stores live nearby
                            </span>
                        </div>
                        <h3 class="text-lg sm:text-2xl font-black text-slate-950 tracking-tight mt-0.5">
                            Delivered to your doorstep in 10-25 Minutes!
                        </h3>
                        <p class="text-xs font-medium text-slate-800 max-w-lg">
                            Fresh dairy, instant groceries, medicines, electronics and essentials packaged directly from stores within {{ $deliveryRadius }} km.
                        </p>
                    </div>
                </div>

                <div class="flex items-center space-x-3 flex-shrink-0 w-full sm:w-auto">
                    <button type="button"
                            @click="$store.location.modalOpen = true"
                            class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-900 font-bold text-xs shadow-sm border border-slate-200 transition">
                        Change Location
                    </button>
                    <a href="#nearby-stores" class="flex-1 sm:flex-none px-5 py-2.5 rounded-xl bg-slate-950 hover:bg-slate-900 text-white font-extrabold text-xs shadow-md transition text-center">
                        View Local Stores &rarr;
                    </a>
                </div>
            </div>
        </section>
    @endif

    <!-- 5. Categories Grid (Circular / Card Style with Icons) -->
    <section id="categories" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight">Explore Categories</h2>
                <p class="text-xs text-slate-500">Shop by category across quick-delivery and marketplace catalogs</p>
            </div>
            <a href="{{ route('home') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                View All &rarr;
            </a>
        </div>

        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-3 sm:gap-4">
            @foreach($categories->take(12) as $category)
                <a href="{{ route('home') }}?category={{ $category->slug }}"
                   class="group flex flex-col items-center p-3 rounded-2xl bg-white border border-slate-200/80 hover:border-indigo-400 hover:shadow-md transition text-center">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-gradient-to-tr from-indigo-50 to-amber-50 group-hover:from-indigo-100 group-hover:to-amber-100 flex items-center justify-center p-2 mb-2 transition-transform group-hover:scale-105 overflow-hidden">
                        @if($category->image)
                            <img src="{{ asset('storage/' . $category->image) }}"
                                 alt="{{ $category->name }}"
                                 loading="lazy"
                                 onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\' width=\'100\' height=\'100\'><circle cx=\'50\' cy=\'50\' r=\'40\' fill=\'%23e0e7ff\'/><text x=\'50\' y=\'60\' font-size=\'36\' text-anchor=\'middle\'>🛍️</text></svg>';"
                                 class="w-full h-full object-contain">
                        @else
                            <span class="text-2xl">
                                @switch(strtolower($category->slug))
                                    @case('groceries-staples') 🛒 @break
                                    @case('fresh-fruits-vegetables') 🍎 @break
                                    @case('dairy-bread-eggs') 🥛 @break
                                    @case('snacks-beverages') 🥤 @break
                                    @case('electronics-gadgets') 📱 @break
                                    @case('home-kitchen') 🍳 @break
                                    @case('fashion-apparel') 👕 @break
                                    @case('health-wellness') 💊 @break
                                    @case('beauty-cosmetics') 💄 @break
                                    @case('baby-care-kids') 🧸 @break
                                    @default 📦
                                @endswitch
                            </span>
                        @endif
                    </div>
                    <span class="text-xs font-bold text-slate-800 group-hover:text-indigo-600 line-clamp-1 transition">
                        {{ $category->name }}
                    </span>
                    @if($category->is_quick_eligible)
                        <span class="text-[9px] font-extrabold text-amber-600 mt-0.5">⚡ 15m</span>
                    @endif
                </a>
            @endforeach
        </div>
    </section>

    <!-- 6. Nearby Stores (Blueprint Section 6: Nearby Stores) -->
    <section id="nearby-stores" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-4">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-indigo-50 text-indigo-700 border border-indigo-200">
                        🏪 PHYSICAL & LOCAL CONNECT
                    </span>
                </div>
                <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight mt-1">Nearby Retail Stores & Outlets</h2>
                <p class="text-xs text-slate-500">Order from registered neighborhood stores with live pickup and instant delivery</p>
            </div>
            <button type="button" @click="$store.location.modalOpen = true" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center space-x-1">
                <span>Change Area</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @forelse($nearbyStores as $store)
                <div class="group flex flex-col bg-white rounded-3xl border border-slate-200/80 hover:border-indigo-400 hover:shadow-xl transition-all duration-300 overflow-hidden">
                    <!-- Store Banner / Header -->
                    <div class="h-28 bg-gradient-to-r from-slate-800 to-indigo-950 relative overflow-hidden">
                        @if($store->banner)
                            <img src="{{ asset('storage/' . $store->banner) }}" alt="{{ $store->name }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 to-transparent"></div>

                        <!-- Status & Distance Badges -->
                        <div class="absolute top-2.5 left-2.5 right-2.5 flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black {{ $store->isOpenNow() ? 'bg-emerald-500 text-white' : 'bg-slate-700 text-slate-300' }}">
                                {{ $store->isOpenNow() ? 'Open Now' : 'Closed' }}
                            </span>
                            @if(isset($store->distance_km))
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-white/90 text-slate-900 backdrop-blur-sm shadow-xs">
                                    📍 {{ number_format((float) $store->distance_km, 1) }} km
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-white/90 text-slate-900 backdrop-blur-sm shadow-xs">
                                    Within {{ $store->effectiveDeliveryRadius() }} km
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Store Details -->
                    <div class="p-4 flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <h3 class="text-sm font-extrabold text-slate-900 group-hover:text-indigo-600 transition truncate">
                                    {{ $store->name }}
                                </h3>
                                <div class="flex items-center text-amber-500 text-xs font-bold space-x-0.5">
                                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <span>{{ number_format($store->rating_avg ?? 4.8, 1) }}</span>
                                </div>
                            </div>
                            <p class="text-xs text-slate-500 line-clamp-1 mb-2">
                                {{ $store->description ?: 'Authorized neighbourhood store on OneStall Network' }}
                            </p>
                            <div class="flex items-center space-x-1.5 text-[10px] font-bold text-slate-600">
                                @if($store->accepts_quick_commerce)
                                    <span class="text-amber-600">⚡ 15-30m Quick Delivery</span>
                                @endif
                                @if($store->accepts_pickup)
                                    <span class="text-indigo-600">&bull; 🛍️ Store Pickup</span>
                                @endif
                            </div>
                        </div>

                        <!-- Action Link -->
                        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-[11px] text-slate-500 font-medium">
                                {{ $store->location?->city ?? 'Bengaluru' }}
                            </span>
                            <a href="{{ route('store.show', $store->slug) }}" class="px-3.5 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white font-bold text-xs transition">
                                Enter Store &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full p-8 text-center bg-white rounded-3xl border border-slate-200">
                    <p class="text-sm font-semibold text-slate-600">No stores found in your current location radius.</p>
                    <button type="button" @click="$store.location.modalOpen = true" class="mt-2 text-xs font-bold text-indigo-600 hover:underline">
                        Change location or view all stores
                    </button>
                </div>
            @endforelse
        </div>
    </section>

    <!-- 7. Deals & Flash Offers (High Discount Products) -->
    <section id="deals" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="p-6 rounded-3xl bg-gradient-to-r from-rose-500 via-rose-600 to-amber-500 text-white shadow-xl mb-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <div class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full bg-white/20 backdrop-blur-sm text-white text-[10px] font-black tracking-wider uppercase mb-1">
                        <span>🔥 FLASH DEALS & LIMITED OFFERS</span>
                    </div>
                    <h2 class="text-xl sm:text-3xl font-black tracking-tight text-white">Up to 40% OFF Daily Essentials</h2>
                    <p class="text-xs text-rose-100">Hand-picked maximum discount deals with direct doorstep dispatch</p>
                </div>
                <div class="flex items-center space-x-2 text-xs font-black bg-black/20 backdrop-blur-sm px-4 py-2 rounded-2xl border border-white/20">
                    <span>⚡ Ending Soon:</span>
                    <span class="font-mono text-amber-300">04h : 28m : 15s</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 gap-3 sm:gap-5">
            @foreach($dealsProducts as $prod)
                <x-product-card :product="$prod" />
            @endforeach
        </div>
    </section>

    <!-- Promo Banner Interspersed (from banners table) -->
    @if($promoBanners->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl overflow-hidden shadow-md border border-slate-200 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900">
                <a href="{{ $promoBanners->first()->link ?: route('home') }}" class="block">
                    <img src="{{ asset('storage/' . $promoBanners->first()->image) }}"
                         alt="{{ $promoBanners->first()->title }}"
                         loading="lazy"
                         onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 1200 240\' width=\'1200\' height=\'240\'><rect width=\'1200\' height=\'240\' fill=\'%231e1b4b\'/><text x=\'60\' y=\'130\' font-family=\'sans-serif\' font-size=\'32\' font-weight=\'bold\' fill=\'%23ffffff\'>⚡ ONESTALL MEGA PROMO DEALS</text></svg>';"
                         class="w-full h-auto max-h-48 object-cover">
                </a>
            </div>
        </section>
    @endif

    <!-- 8. Best Sellers -->
    <section id="bestsellers" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-4">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-600">Top Rated & Trending</span>
                <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight">Best Sellers on OneStall</h2>
            </div>
            <a href="{{ route('home') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                Explore All &rarr;
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 gap-3 sm:gap-5">
            @foreach($bestSellers as $prod)
                <x-product-card :product="$prod" />
            @endforeach
        </div>
    </section>

    <!-- 9. Brands Section (Verified Logos & Catalogues) -->
    <section id="brands" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-4">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-600">DIRECT BRAND PARTNERS</span>
                <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight">Official Brand Stores</h2>
            </div>
            <a href="{{ route('home') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                View All Brands &rarr;
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 sm:gap-4">
            @foreach($brands as $brand)
                <a href="{{ route('home') }}?brand={{ $brand->slug }}"
                   class="group p-4 rounded-2xl bg-white border border-slate-200/80 hover:border-indigo-400 hover:shadow-md transition text-center flex flex-col items-center justify-between">
                    <div class="w-16 h-16 rounded-xl bg-slate-50 flex items-center justify-center p-2 mb-2 group-hover:scale-105 transition-transform overflow-hidden">
                        @if($brand->logo)
                            <img src="{{ asset('storage/' . $brand->logo) }}" alt="{{ $brand->name }}" loading="lazy" class="w-full h-full object-contain">
                        @else
                            <span class="text-lg font-black text-indigo-600">{{ substr($brand->name, 0, 2) }}</span>
                        @endif
                    </div>
                    <div>
                        <div class="flex items-center justify-center space-x-1">
                            <span class="text-xs font-bold text-slate-900 group-hover:text-indigo-600 transition truncate max-w-[100px]">
                                {{ $brand->name }}
                            </span>
                            @if($brand->is_verified)
                                <svg class="w-3.5 h-3.5 text-blue-500 fill-current flex-shrink-0" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </div>
                        <span class="text-[10px] text-slate-400">{{ $brand->products_count ?? 12 }}+ products</span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <!-- 10. OneStall Stores (Flagship / Curated Stores) -->
    <section id="onestall-stores" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="p-6 rounded-3xl bg-slate-900 text-white shadow-xl mb-6 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-black text-xl shadow-md">
                    1S
                </div>
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-amber-400">Curated & Managed by OneStall</span>
                    <h2 class="text-xl sm:text-2xl font-black text-white">OneStall Direct Flagship Hubs</h2>
                </div>
            </div>
            <p class="text-xs text-slate-300 max-w-md">
                Guaranteed inventory, direct factory pricing, expedited delivery, and hassle-free returns.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($onestallStores as $osStore)
                <div class="p-5 rounded-3xl bg-white border border-slate-200/80 shadow-sm hover:shadow-lg transition">
                    <div class="flex items-center space-x-3 mb-3">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-800 text-white flex items-center justify-center font-black text-sm">
                            🏪
                        </div>
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-900">{{ $osStore->name }}</h3>
                            <span class="text-[11px] text-slate-500">{{ $osStore->location?->city ?? 'Bengaluru' }} Hub</span>
                        </div>
                    </div>
                    <p class="text-xs text-slate-600 mb-4 line-clamp-2">
                        {{ $osStore->description ?: 'Platform managed fulfillment node with verified inventory and instant dispatch.' }}
                    </p>
                    <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                        <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                            ✓ Direct Quality Guarantee
                        </span>
                        <a href="{{ route('store.show', $osStore->slug) }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                            Visit Hub &rarr;
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <!-- 11. Recommended Products Grid -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-4">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Personalized Selection</span>
                <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight">Recommended For You</h2>
            </div>
            <a href="{{ route('home') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                View More &rarr;
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 gap-3 sm:gap-5">
            @foreach($recommendedProducts as $prod)
                <x-product-card :product="$prod" />
            @endforeach
        </div>
    </section>

</div>
@endsection
