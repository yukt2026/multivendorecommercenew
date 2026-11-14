@extends('layouts.storefront')

@section('title', 'Quick Commerce - 10-30 Minute Delivery | OneStall')

@section('content')
<div class="bg-slate-50 min-h-screen py-4 sm:py-8" x-data="{
    userLat: {{ $lat }},
    userLng: {{ $lng }},
    requestGeo() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition((pos) => {
                window.location.href = '{{ route('quick.index') }}?lat=' + pos.coords.latitude + '&lng=' + pos.coords.longitude;
            });
        }
    }
}">
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 space-y-6 sm:space-y-8">
        
        <!-- Quick Commerce Hero Banner -->
        <div class="relative rounded-3xl overflow-hidden bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 text-white shadow-2xl p-5 sm:p-8 lg:p-10">
            <!-- Background glow accents & graphics -->
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute right-6 top-6 text-7xl sm:text-9xl opacity-15 pointer-events-none select-none font-black hidden sm:block">
                ⚡
            </div>

            <div class="relative z-10 max-w-2xl space-y-3">
                <div class="inline-flex items-center space-x-2 bg-black/20 backdrop-blur-md px-3 py-1 rounded-full text-[11px] sm:text-xs font-black text-amber-200 border border-white/20 shadow-inner">
                    <span class="w-2 h-2 rounded-full bg-amber-300 animate-ping"></span>
                    <span>⚡ HYPER-LOCAL 10-25 MIN DELIVERY</span>
                </div>

                <h1 class="text-2xl sm:text-4xl lg:text-5xl font-black text-white tracking-tight leading-tight">
                    Superfast Groceries &amp; Essentials
                </h1>

                <p class="text-amber-100 text-xs sm:text-sm sm:leading-relaxed max-w-xl font-medium">
                    Fresh produce, dairy, snacks, and daily necessities delivered from nearby partner dark stores directly to your doorstep in minutes.
                </p>

                <!-- Location Selector Bar -->
                <div class="pt-2 flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 max-w-xl">
                    <button type="button"
                            @click="$store.location.modalOpen = true"
                            class="flex items-center justify-between px-3.5 py-2.5 rounded-2xl bg-white text-slate-900 shadow-md hover:bg-slate-50 transition text-left min-w-0 flex-1 active:scale-[0.99]">
                        <div class="flex items-center space-x-2.5 min-w-0">
                            <div class="w-7 h-7 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-[9px] uppercase font-bold text-slate-400 tracking-wider">Delivering to</span>
                                <span class="block text-xs font-black text-slate-900 truncate" x-text="$store.location.current.address_label || 'Select Delivery Location'">
                                    {{ $location['address_label'] ?? 'Bengaluru 560001' }}
                                </span>
                            </div>
                        </div>
                        <span class="text-[11px] font-extrabold text-orange-600 uppercase flex-shrink-0 ml-2">Change &rarr;</span>
                    </button>

                    <button @click="requestGeo()"
                            type="button"
                            class="px-4 py-2.5 rounded-2xl bg-slate-950/80 hover:bg-slate-950 text-white font-bold text-xs backdrop-blur-md border border-white/20 hover:border-white/40 transition flex items-center justify-center space-x-2 shadow-md active:scale-95 flex-shrink-0">
                        <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Use GPS Location</span>
                    </button>
                </div>

                <!-- Trust Strip -->
                <div class="pt-4 border-t border-white/15 grid grid-cols-3 gap-2 text-white/95 text-xs">
                    <div class="flex items-center space-x-2">
                        <div class="w-6 h-6 rounded-lg bg-white/20 flex items-center justify-center text-xs flex-shrink-0">⚡</div>
                        <div>
                            <div class="font-extrabold text-[11px] sm:text-xs">10-25 Mins</div>
                            <div class="text-[9px] text-amber-100 hidden sm:block">Average dispatch</div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-6 h-6 rounded-lg bg-white/20 flex items-center justify-center text-xs flex-shrink-0">🏪</div>
                        <div>
                            <div class="font-extrabold text-[11px] sm:text-xs">{{ $nearbyStores->count() }} Live Stores</div>
                            <div class="text-[9px] text-amber-100 hidden sm:block">In service radius</div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-6 h-6 rounded-lg bg-white/20 flex items-center justify-center text-xs flex-shrink-0">📦</div>
                        <div>
                            <div class="font-extrabold text-[11px] sm:text-xs">Free &gt; {{ format_currency(setting('free_delivery_threshold', 499)) }}</div>
                            <div class="text-[9px] text-amber-100 hidden sm:block">No delivery fee</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nearby Stores Section -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg sm:text-xl font-black text-slate-900 flex items-center space-x-2">
                        <span>🏬 Stores Delivering Near You</span>
                        <span class="text-xs bg-amber-100 text-amber-800 px-2.5 py-0.5 rounded-full font-bold">{{ $nearbyStores->count() }} active</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Verified local outlets with live inventory synchronization within {{ setting('default_delivery_radius_km', 8) }} km</p>
                </div>
            </div>

            @if($nearbyStores->isEmpty())
                <div class="bg-white rounded-3xl border border-dashed border-slate-300 p-8 text-center shadow-xs">
                    <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center mx-auto mb-3 text-xl">
                        🏪
                    </div>
                    <h3 class="font-bold text-slate-900 text-sm">No quick commerce stores currently in service radius</h3>
                    <p class="text-xs text-slate-500 max-w-md mx-auto mt-1">Try switching coordinates or use standard national delivery to shop from our full catalog.</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 sm:gap-4">
                    @foreach($nearbyStores as $store)
                        <div class="bg-white rounded-3xl p-4 sm:p-5 border border-slate-200/90 hover:border-orange-300 hover:shadow-xl transition-all duration-300 flex flex-col justify-between group">
                            <div>
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="flex items-center space-x-3 min-w-0">
                                        <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-amber-500 to-orange-600 text-white font-black text-base flex items-center justify-center shadow-md flex-shrink-0 group-hover:scale-105 transition-transform">
                                            {{ substr($store->name, 0, 1) }}
                                        </div>
                                        <div class="min-w-0">
                                            <h3 class="font-extrabold text-sm text-slate-900 group-hover:text-orange-600 transition truncate">
                                                {{ $store->name }}
                                            </h3>
                                            <div class="flex items-center space-x-2 text-xs text-slate-500 mt-0.5">
                                                <span class="flex items-center space-x-1 font-semibold text-slate-700">
                                                    <span>📍</span>
                                                    <span>{{ $store->distance_km }} km</span>
                                                </span>
                                                <span>&bull;</span>
                                                <span class="flex items-center space-x-0.5 text-amber-500 font-bold">
                                                    <span>★</span>
                                                    <span>{{ number_format($store->rating_avg ?? 4.8, 1) }}</span>
                                                </span>
                                                <span>&bull;</span>
                                                <span class="text-[10px] font-bold px-1.5 py-0.2 rounded-md {{ $store->is_overloaded ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-800' }}">
                                                    {{ $store->is_overloaded ? 'Busy' : 'Open' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ETA Badge -->
                                <div class="bg-amber-50/80 border border-amber-200/60 rounded-2xl px-3.5 py-2 flex items-center justify-between text-xs mb-3">
                                    <div class="flex items-center space-x-1.5 text-amber-900 font-bold">
                                        <span>⚡ Realistic ETA:</span>
                                    </div>
                                    <span class="font-black text-amber-950 text-xs sm:text-sm">{{ $store->eta['formatted_eta'] }}</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-[11px] text-slate-400 pt-2 border-t border-slate-100">
                                <span>Prep time: ~{{ $store->eta['prep_time_minutes'] }}m</span>
                                <a href="{{ route('store.show', $store->slug ?? $store->id) }}" class="inline-flex items-center space-x-1 text-xs font-bold text-orange-600 hover:text-orange-700 group-hover:underline">
                                    <span>Visit Store &rarr;</span>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Quick Eligible Products Section -->
        <div>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-lg sm:text-xl font-black text-slate-900 flex items-center space-x-2">
                        <span>⚡ Quick Delivery Products</span>
                        <span class="text-xs bg-orange-100 text-orange-800 px-2.5 py-0.5 rounded-full font-bold">10-30 min arrival</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Dispatched immediately from nearest dark store hub or local outlet</p>
                </div>

                <!-- Search form -->
                <form action="{{ route('quick.index') }}" method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="lat" value="{{ $lat }}">
                    <input type="hidden" name="lng" value="{{ $lng }}">
                    @if($selectedCategory)
                        <input type="hidden" name="category" value="{{ $selectedCategory }}">
                    @endif
                    <div class="relative flex-1 sm:flex-none">
                        <input type="text"
                               name="q"
                               value="{{ $searchQuery }}"
                               placeholder="Search milk, bread, chips..."
                               class="pl-9 pr-4 py-2 text-xs rounded-xl border border-slate-200 focus:ring-2 focus:ring-orange-500 w-full sm:w-64 bg-white shadow-2xs">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <button type="submit" class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-xs transition">
                        Search
                    </button>
                </form>
            </div>

            <!-- Category Pills Ribbon -->
            @if(isset($categories) && $categories->isNotEmpty())
                <div class="flex items-center space-x-2 overflow-x-auto no-scrollbar pb-2 mb-4 text-xs font-semibold">
                    <a href="{{ route('quick.index', ['lat' => $lat, 'lng' => $lng]) }}"
                       class="px-3.5 py-1.5 rounded-xl whitespace-nowrap transition shadow-2xs {{ empty($selectedCategory) ? 'bg-orange-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200' }}">
                        All Items
                    </a>
                    @foreach($categories as $c)
                        <a href="{{ route('quick.index', ['lat' => $lat, 'lng' => $lng, 'category' => $c->slug]) }}"
                           class="px-3.5 py-1.5 rounded-xl whitespace-nowrap transition shadow-2xs {{ $selectedCategory === $c->slug ? 'bg-orange-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200' }}">
                            {{ $c->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            @if($products->isEmpty())
                <div class="bg-white rounded-3xl border border-slate-200 p-12 text-center shadow-xs">
                    <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-3 text-2xl">
                        📦
                    </div>
                    <h3 class="font-extrabold text-slate-900 text-base">No Quick-Delivery Products Found</h3>
                    <p class="text-xs text-slate-500 max-w-md mx-auto mt-1">There are no quick-commerce eligible products matching your filter or delivery location right now.</p>
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2.5 sm:gap-4">
                    @foreach($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
