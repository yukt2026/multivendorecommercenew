@extends('layouts.storefront')

@section('content')
<div class="space-y-6 sm:space-y-12 pb-16">

    <!-- 3. Hero Banner Carousel (Admin Managed via banners table) -->
    <section class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 pt-2 sm:pt-6">
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
             class="relative rounded-2xl sm:rounded-3xl overflow-hidden shadow-xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white min-h-[190px] sm:min-h-[340px]">

            <!-- Carousel Slides -->
            <div class="relative w-full h-[190px] sm:h-[340px]">
                @if($heroBanners->isNotEmpty())
                    @foreach($heroBanners as $index => $banner)
                        @php
                            $imageUrl = $banner->image_url;
                            $targetUrl = $banner->link ? (str_starts_with($banner->link, 'http') ? $banner->link : url($banner->link)) : route('home');
                        @endphp
                        <div x-show="activeSlide === {{ $index }}"
                             x-cloak
                             x-transition:enter="transition ease-out duration-500"
                             x-transition:enter-start="opacity-0 translate-x-8"
                             x-transition:enter-end="opacity-100 translate-x-0"
                             x-transition:leave="transition ease-in duration-300"
                             x-transition:leave-start="opacity-100 translate-x-0"
                             x-transition:leave-end="opacity-0 -translate-x-8"
                             class="absolute inset-0 flex items-center justify-between p-4 sm:p-12 overflow-hidden bg-slate-950">

                            @if($imageUrl)
                                <!-- Background Image with responsive cover & smooth hover zoom -->
                                <img src="{{ $imageUrl }}" 
                                     alt="{{ $banner->title }}" 
                                     class="absolute inset-0 w-full h-full object-cover object-center z-0 transition-transform duration-700 hover:scale-105"
                                     loading="{{ $index === 0 ? 'eager' : 'lazy' }}">
                                <!-- Subtle gentle scrim for contrast without obscuring the artwork -->
                                <div class="absolute inset-0 bg-gradient-to-r from-slate-950/40 via-slate-950/10 to-transparent z-[1] pointer-events-none"></div>
                            @else
                                <div class="absolute inset-0 z-0 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900"></div>
                            @endif

                            <!-- Clickable Slide Link Overlay -->
                            <a href="{{ $targetUrl }}" class="absolute inset-0 z-[5]" aria-label="{{ $banner->title }}"></a>

                            <!-- Frosted Glass Content Card (Ensures readability on light or dark images) -->
                            <div class="max-w-xl space-y-2 sm:space-y-4 relative z-10 bg-slate-950/50 backdrop-blur-md p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-white/10 shadow-2xl">
                                <span class="inline-flex items-center space-x-1 px-2.5 py-0.5 rounded-full text-[9px] sm:text-xs font-black uppercase tracking-wider bg-amber-400 text-slate-950 shadow-sm">
                                    <span>⚡ ONESTALL SPECIAL</span>
                                </span>
                                <h2 class="text-base sm:text-3xl font-black text-white tracking-tight leading-tight line-clamp-2 drop-shadow-md">
                                    {{ $banner->title }}
                                </h2>
                                <p class="text-xs sm:text-sm text-slate-200 max-w-md hidden sm:block drop-shadow-sm">
                                    Shop from your trusted neighbourhood retailers with 15-minute express delivery or pan-India marketplace catalog.
                                </p>
                                <div class="pt-1 sm:pt-2">
                                    <span class="inline-flex items-center space-x-1.5 px-4 py-2 sm:px-5 sm:py-2.5 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-950 font-black text-[11px] sm:text-xs uppercase tracking-wider transition shadow-lg shadow-amber-400/20 active:scale-95 pointer-events-none">
                                        <span>Order Now</span>
                                        <span>&rarr;</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <!-- Fallback hero slide -->
                    <div class="absolute inset-0 flex items-center p-4 sm:p-12 bg-gradient-to-r from-slate-900 to-indigo-900">
                        <div class="max-w-xl space-y-3 sm:space-y-4">
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] sm:text-xs font-black bg-amber-400 text-slate-950">⚡ INSTANT COMMERCE</span>
                            <h2 class="text-lg sm:text-4xl font-black text-white">Smart Commerce, Delivered in Minutes.</h2>
                            <p class="text-xs sm:text-sm text-slate-300 hidden sm:block">Discover fresh fruits, staples, electronics & lifestyle from local stores.</p>
                            <a href="{{ route('quick.index') }}" class="inline-block px-4 py-2 sm:px-5 sm:py-2.5 rounded-xl bg-amber-400 text-slate-950 font-bold text-xs uppercase">Explore Now</a>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Carousel Controls (Hidden on Mobile) -->
            @if($heroBanners->count() > 1)
                <button type="button"
                        @click="activeSlide = (activeSlide - 1 + slidesCount) % slidesCount"
                        class="hidden sm:flex absolute left-2 sm:left-3 top-1/2 -translate-y-1/2 z-30 w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-slate-900/70 hover:bg-slate-900 text-white items-center justify-center backdrop-blur-md transition shadow-lg cursor-pointer">
                    <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button type="button"
                        @click="activeSlide = (activeSlide + 1) % slidesCount"
                        class="hidden sm:flex absolute right-2 sm:right-3 top-1/2 -translate-y-1/2 z-30 w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-slate-900/70 hover:bg-slate-900 text-white items-center justify-center backdrop-blur-md transition shadow-lg cursor-pointer">
                    <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                </button>

                <!-- Indicator Dots -->
                <div class="absolute bottom-2.5 sm:bottom-3 left-1/2 -translate-x-1/2 flex items-center space-x-1.5 z-30">
                    <template x-for="i in slidesCount" :key="i">
                        <button type="button"
                                @click="activeSlide = i - 1"
                                :class="activeSlide === (i - 1) ? 'w-5 sm:w-6 bg-amber-400' : 'w-2 bg-white/50'"
                                class="h-1.5 sm:h-2 rounded-full transition-all duration-300"></button>
                    </template>
                </div>
            @endif
        </div>
    </section>

    <!-- 4. Quick Commerce Strip ("Delivery in minutes", Blinkit-inspired, shown when nearby eligible stores exist) -->
    @if($quickCommerce['is_available'] || true)
        <section id="quick-commerce" class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
            <div class="p-3.5 sm:p-6 rounded-2xl sm:rounded-3xl bg-gradient-to-r from-amber-400 via-amber-300 to-yellow-400 text-slate-950 shadow-lg border border-amber-300 relative overflow-hidden flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 sm:gap-4">
                <div class="flex items-start sm:items-center space-x-3 sm:space-x-4">
                    <div class="w-10 h-10 sm:w-14 sm:h-14 rounded-xl sm:rounded-2xl bg-slate-950 text-amber-400 flex items-center justify-center font-black text-lg sm:text-2xl shadow-md flex-shrink-0 animate-bounce">
                        ⚡
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 mb-1">
                            <span class="px-2 py-0.5 rounded-md bg-slate-950 text-white text-[9px] sm:text-[10px] font-black uppercase tracking-wider inline-block">
                                HYPER-LOCAL QUICK COMMERCE
                            </span>
                            <span class="text-[11px] sm:text-xs font-bold text-slate-800">
                                &bull; {{ $quickCommerce['store_count'] }} stores live nearby
                            </span>
                        </div>
                        <h3 class="text-base sm:text-2xl font-black text-slate-950 tracking-tight leading-snug">
                            Delivered to your doorstep in 10-25 Minutes!
                        </h3>
                        <p class="text-[11px] sm:text-xs font-medium text-slate-800 max-w-lg mt-0.5 line-clamp-2 sm:line-clamp-none">
                            Fresh dairy, instant groceries, medicines, electronics and essentials packaged directly from stores within {{ $deliveryRadius }} km.
                        </p>
                    </div>
                </div>

                <div class="flex items-center space-x-2 sm:space-x-3 flex-shrink-0 w-full md:w-auto pt-1 sm:pt-0">
                    <button type="button"
                            @click="$store.location.modalOpen = true"
                            class="flex-1 md:flex-none px-3 sm:px-4 py-2 sm:py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-900 font-bold text-xs shadow-sm border border-slate-200 transition text-center">
                        Change Location
                    </button>
                    <a href="#nearby-stores" class="flex-1 md:flex-none px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl bg-slate-950 hover:bg-slate-900 text-white font-extrabold text-xs shadow-md transition text-center">
                        View Local Stores &rarr;
                    </a>
                </div>
            </div>
        </section>
    @endif

    <!-- Available Platform Coupons Showcase Bar -->
    @if(isset($availableCoupons) && $availableCoupons->isNotEmpty())
        <section id="available-coupons" class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8" x-data="{ copiedCode: null }">
            <div class="p-3.5 sm:p-4 rounded-2xl bg-indigo-50/70 border border-indigo-100 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 shadow-2xs">
                <div class="flex items-center space-x-2.5">
                    <span class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shadow-xs shrink-0">
                        🎟️
                    </span>
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs font-black text-indigo-950 uppercase tracking-wider">Storewide Promo Discounts</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-indigo-100 text-indigo-800">Verified Active</span>
                        </div>
                        <p class="text-[11px] text-slate-500">Apply these exclusive vouchers at cart checkout for instant bill deductions</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 overflow-x-auto w-full md:w-auto no-scrollbar pb-1 md:pb-0">
                    @foreach($availableCoupons as $coup)
                        <div class="p-2 sm:px-3 sm:py-1.5 rounded-xl bg-white border border-indigo-200/90 shadow-2xs flex items-center space-x-2 shrink-0">
                            <div>
                                <span class="font-mono font-black text-xs text-indigo-700 tracking-wider">{{ $coup->code }}</span>
                                <span class="text-[11px] font-bold text-slate-800 ml-1">({{ $coup->formatted_discount }} OFF)</span>
                            </div>
                            <button 
                                type="button" 
                                @click="navigator.clipboard.writeText('{{ $coup->code }}'); copiedCode = '{{ $coup->code }}'; setTimeout(() => copiedCode = null, 2000)"
                                class="px-2 py-0.5 text-[10px] font-bold rounded-md transition cursor-pointer"
                                :class="copiedCode === '{{ $coup->code }}' ? 'bg-emerald-600 text-white' : 'bg-indigo-50 hover:bg-indigo-100 text-indigo-700'"
                            >
                                <span x-text="copiedCode === '{{ $coup->code }}' ? 'Copied!' : 'Copy'"></span>
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <!-- Featured Marketing & Festival Campaigns -->
    @if(isset($featuredCampaigns) && $featuredCampaigns->isNotEmpty())
        <section id="featured-campaigns" class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-3 sm:mb-4">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-rose-600">Featured Seasonal Events</span>
                    <h2 class="text-base sm:text-2xl font-black text-slate-900 tracking-tight">Active Mega Sale Campaigns</h2>
                </div>
                <a href="{{ route('campaigns.index') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center">
                    <span>View All Festivals</span>
                    <span class="ml-0.5">&rarr;</span>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($featuredCampaigns as $camp)
                    <a href="{{ route('campaign.show', $camp->slug) }}" class="block p-4 sm:p-5 rounded-2xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white border border-slate-800 shadow-md hover:shadow-xl hover:-translate-y-0.5 transition duration-200 group">
                        <div class="flex items-center justify-between mb-2.5">
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
                                {{ $camp->type_label }}
                            </span>
                            <span class="text-[10px] text-emerald-400 flex items-center space-x-1 font-mono font-semibold">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                <span>Live Now</span>
                            </span>
                        </div>

                        <h3 class="text-base sm:text-lg font-black text-white group-hover:text-amber-300 transition line-clamp-1">
                            {{ $camp->name }}
                        </h3>

                        <p class="text-xs text-slate-300 mt-1 line-clamp-2 leading-relaxed">
                            {{ $camp->description ?: 'Explore curated flash deals, bundle offers and seasonal marketplace specials.' }}
                        </p>

                        <div class="mt-3.5 pt-2.5 border-t border-slate-800/80 flex items-center justify-between text-[11px]">
                            <span class="text-slate-400 font-mono text-[10px]">
                                Ends: {{ $camp->ends_at ? $camp->ends_at->format('M d, Y') : 'Limited Time' }}
                            </span>
                            <span class="font-bold text-amber-400 group-hover:translate-x-1 transition flex items-center space-x-1">
                                <span>Shop Deals</span>
                                <span>&rarr;</span>
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <!-- 5. Categories Grid (Circular / Card Style with Icons) -->
    <section id="categories" class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-3 sm:mb-4">
            <div>
                <h2 class="text-base sm:text-2xl font-black text-slate-900 tracking-tight">Explore Categories</h2>
                <p class="text-[11px] sm:text-xs text-slate-500">Shop by category across quick-delivery and marketplace catalogs</p>
            </div>
            <a href="{{ route('search') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center">
                <span>View All</span>
                <span class="ml-0.5">&rarr;</span>
            </a>
        </div>

        <div class="grid grid-cols-4 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2 sm:gap-4">
            @foreach($categories->take(12) as $category)
                <a href="{{ route('category.show', $category->slug) }}"
                   class="group flex flex-col items-center p-1.5 sm:p-3 rounded-2xl bg-white border border-slate-200/80 hover:border-indigo-400 hover:shadow-md transition text-center">
                    <div class="w-12 h-12 sm:w-16 sm:h-16 rounded-xl sm:rounded-2xl bg-gradient-to-tr from-indigo-50 to-amber-50 group-hover:from-indigo-100 group-hover:to-amber-100 flex items-center justify-center p-1.5 sm:p-2 mb-1 transition-transform group-hover:scale-105 overflow-hidden flex-shrink-0">
                        @if($category->image && file_exists(public_path('storage/' . $category->image)))
                            <img src="{{ asset('storage/' . $category->image) }}"
                                 alt="{{ $category->name }}"
                                 loading="lazy"
                                 class="w-full h-full object-contain rounded-xl">
                        @else
                            <span class="text-xl sm:text-2xl">
                                @switch(strtolower($category->slug))
                                    @case('groceries-staples') 🛒 @break
                                    @case('fresh-fruits-vegetables') 🍎 @break
                                    @case('dairy-bread-eggs') 🥛 @break
                                    @case('snacks-beverages') 🥤 @break
                                    @case('personal-care-hygiene') 🧼 @break
                                    @case('electronics-gadgets') 📱 @break
                                    @case('home-kitchen') 🍳 @break
                                    @case('fashion-apparel') 👕 @break
                                    @case('health-wellness') 💊 @break
                                    @case('beauty-cosmetics') 💄 @break
                                    @case('baby-care-kids') 🧸 @break
                                    @case('stationery-office-supplies') 📚 @break
                                    @default 🛍️
                                @endswitch
                            </span>
                        @endif
                    </div>
                    <span class="text-[10px] sm:text-xs font-bold text-slate-800 group-hover:text-indigo-600 line-clamp-2 leading-tight h-7 flex items-center justify-center transition">
                        {{ $category->name }}
                    </span>
                    @if($category->is_quick_eligible)
                        <span class="text-[8px] sm:text-[9px] font-extrabold text-amber-600 mt-0.5">⚡ 15m</span>
                    @endif
                </a>
            @endforeach
        </div>
    </section>

    <!-- 6. Nearby Stores (Blueprint Section 6: Nearby Stores) -->
    <section id="nearby-stores" class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-3 sm:mb-4">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="px-2 py-0.5 rounded-full text-[9px] sm:text-[10px] font-extrabold bg-indigo-50 text-indigo-700 border border-indigo-200">
                        🏪 PHYSICAL & LOCAL CONNECT
                    </span>
                </div>
                <h2 class="text-base sm:text-2xl font-black text-slate-900 tracking-tight mt-1">Nearby Retail Stores & Outlets</h2>
                <p class="text-[11px] sm:text-xs text-slate-500">Order from registered neighborhood stores with live pickup and instant delivery</p>
            </div>
            <button type="button" @click="$store.location.modalOpen = true" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center space-x-1">
                <span>Change Area</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
        </div>

        <!-- Mobile Horizontal Carousel (sm:hidden) / Desktop Grid (sm:grid) -->
        <div class="flex sm:grid overflow-x-auto sm:overflow-visible sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 pb-2 sm:pb-0 no-scrollbar snap-x">
            @forelse($nearbyStores as $store)
                <div class="flex-shrink-0 w-[260px] sm:w-auto snap-start group flex flex-col bg-white rounded-2xl sm:rounded-3xl border border-slate-200/80 hover:border-indigo-400 hover:shadow-xl transition-all duration-300 overflow-hidden">
                    <!-- Store Banner / Header -->
                    <div class="h-24 sm:h-28 bg-gradient-to-r from-slate-800 to-indigo-950 relative overflow-hidden flex-shrink-0">
                        @if($store->banner)
                            <img src="{{ asset('storage/' . $store->banner) }}" alt="{{ $store->name }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 to-transparent"></div>

                        <!-- Status & Distance Badges -->
                        <div class="absolute top-2 left-2 right-2 flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] sm:text-[10px] font-black {{ $store->isOpenNow() ? 'bg-emerald-500 text-white' : 'bg-slate-700 text-slate-300' }}">
                                {{ $store->isOpenNow() ? 'Open Now' : 'Closed' }}
                            </span>
                            @if(isset($store->distance_km))
                                <span class="px-2 py-0.5 rounded-full text-[9px] sm:text-[10px] font-black bg-white/90 text-slate-900 backdrop-blur-sm shadow-xs">
                                    📍 {{ number_format((float) $store->distance_km, 1) }} km
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[9px] sm:text-[10px] font-black bg-white/90 text-slate-900 backdrop-blur-sm shadow-xs">
                                    Within {{ $store->effectiveDeliveryRadius() }} km
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Store Details -->
                    <div class="p-3 sm:p-4 flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <h3 class="text-xs sm:text-sm font-extrabold text-slate-900 group-hover:text-indigo-600 transition truncate">
                                    {{ $store->name }}
                                </h3>
                                <div class="flex items-center text-amber-500 text-xs font-bold space-x-0.5 flex-shrink-0 ml-1">
                                    <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <span class="text-[11px] sm:text-xs">{{ number_format($store->rating_avg ?? 4.8, 1) }}</span>
                                </div>
                            </div>
                            <p class="text-[11px] sm:text-xs text-slate-500 line-clamp-1 mb-1.5">
                                {{ $store->description ?: 'Authorized neighbourhood store on OneStall Network' }}
                            </p>
                            <div class="flex items-center space-x-1.5 text-[9px] sm:text-[10px] font-bold text-slate-600">
                                @if($store->accepts_quick_commerce)
                                    <span class="text-amber-600">⚡ 15-30m Delivery</span>
                                @endif
                                @if($store->accepts_pickup)
                                    <span class="text-indigo-600">&bull; 🛍️ Pickup</span>
                                @endif
                            </div>
                        </div>

                        <!-- Action Link -->
                        <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-[10px] sm:text-[11px] text-slate-500 font-medium truncate max-w-[120px]">
                                {{ $store->location?->city ?? 'Bengaluru' }}
                            </span>
                            <a href="{{ route('store.show', $store->slug) }}" class="px-3 py-1 sm:px-3.5 sm:py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white font-bold text-xs transition">
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
                <div x-data="{
                    timeLeft: '',
                    initTimer() {
                        const update = () => {
                            const now = new Date();
                            const end = new Date();
                            end.setHours(23, 59, 59, 999);
                            const diff = Math.max(0, end - now);
                            const h = String(Math.floor(diff / 3600000)).padStart(2, '0');
                            const m = String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0');
                            const s = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
                            this.timeLeft = `${h}h : ${m}m : ${s}s`;
                        };
                        update();
                        setInterval(update, 1000);
                    }
                }" x-init="initTimer()" class="flex items-center space-x-2 text-xs font-black bg-black/20 backdrop-blur-sm px-4 py-2 rounded-2xl border border-white/20">
                    <span>⚡ Ending Soon:</span>
                    <span class="font-mono text-amber-300" x-text="timeLeft">04h : 28m : 15s</span>
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
            <div class="grid grid-cols-1 {{ $promoBanners->count() > 1 ? 'md:grid-cols-2' : '' }} gap-4">
                @foreach($promoBanners->take(2) as $promo)
                    <div class="rounded-3xl overflow-hidden shadow-md border border-slate-200 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 group">
                        <a href="{{ $promo->link ? (str_starts_with($promo->link, 'http') ? $promo->link : url($promo->link)) : route('home') }}" class="block">
                            <img src="{{ $promo->image_url }}"
                                 alt="{{ $promo->title }}"
                                 loading="lazy"
                                 onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 1200 240\' width=\'1200\' height=\'240\'><rect width=\'1200\' height=\'240\' fill=\'%231e1b4b\'/><text x=\'60\' y=\'130\' font-family=\'sans-serif\' font-size=\'32\' font-weight=\'bold\' fill=\'%23ffffff\'>⚡ ONESTALL MEGA PROMO DEALS</text></svg>';"
                                 class="w-full h-auto max-h-48 object-cover group-hover:scale-101 transition duration-300">
                        </a>
                    </div>
                @endforeach
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
            <a href="{{ route('search', ['sort' => 'rating_desc']) }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
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
            <a href="{{ route('search', ['tab' => 'brands']) }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                View All Brands &rarr;
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 sm:gap-4">
            @foreach($brands as $brand)
                <a href="{{ route('brand.show', $brand->slug) }}"
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
                        <span class="text-[10px] text-slate-400">{{ $brand->products_count ?? $brand->products()->count() }} products</span>
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
                            <span class="text-[11px] text-slate-500">{{ $osStore->location?->city ?? setting('default_city', 'Bengaluru') }} Hub</span>
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
            <a href="{{ route('search') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
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
