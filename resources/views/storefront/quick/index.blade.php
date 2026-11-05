@extends('layouts.storefront')

@section('title', 'Quick Commerce - 10-30 Minute Delivery | OneStall')

@section('content')
<div class="bg-slate-50 min-h-screen py-8" x-data="{
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
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Quick Commerce Hero Banner -->
        <div class="relative rounded-3xl overflow-hidden bg-gradient-to-r from-amber-500 via-orange-600 to-rose-600 text-white shadow-xl mb-8 p-6 sm:p-10">
            <div class="relative z-10 max-w-2xl">
                <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur-md px-3 py-1.5 rounded-full text-xs font-semibold mb-4 text-white">
                    <span class="animate-pulse flex h-2 w-2 rounded-full bg-amber-300"></span>
                    ⚡ Real-time Hyperlocal Delivery
                </div>
                <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight mb-3">
                    Superfast Groceries & Essentials
                </h1>
                <p class="text-amber-100 text-sm sm:text-base mb-6 leading-relaxed">
                    Fresh produce, dairy, snacks, and daily necessities delivered from nearby partner dark stores directly to your doorstep in minutes.
                </p>

                <!-- Location Selector Pill -->
                <div class="inline-flex flex-wrap items-center gap-3 bg-white/10 backdrop-blur-md p-2 rounded-2xl border border-white/20 text-xs sm:text-sm">
                    <div class="flex items-center gap-2 px-3 py-1 bg-white text-slate-900 rounded-xl font-medium shadow-sm">
                        <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                        <span>Lat: {{ number_format($lat, 4) }}, Lng: {{ number_format($lng, 4) }}</span>
                    </div>
                    <button @click="requestGeo()" type="button" class="px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white font-medium rounded-xl transition text-xs flex items-center gap-1.5 shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Use GPS Location
                    </button>
                </div>
            </div>
        </div>

        <!-- Nearby Stores Section -->
        <div class="mb-10">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                        <span>🏬 Stores Delivering Near You</span>
                        <span class="text-xs bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full font-semibold">{{ $nearbyStores->count() }} active</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Showing verified hyperlocal outlets with live inventory within radius</p>
                </div>
            </div>

            @if($nearbyStores->isEmpty())
                <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-8 text-center">
                    <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="font-semibold text-slate-900 text-sm">No quick commerce stores currently in service radius</h3>
                    <p class="text-xs text-slate-500 max-w-md mx-auto mt-1">Try switching coordinates or use standard national delivery to shop from our full catalog.</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($nearbyStores as $store)
                        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 hover:shadow-md transition group">
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-xl bg-orange-50 border border-orange-100 flex items-center justify-center font-bold text-orange-600 text-lg">
                                        {{ substr($store->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-900 text-sm group-hover:text-orange-600 transition">{{ $store->name }}</h3>
                                        <div class="flex items-center gap-2 text-xs text-slate-500 mt-0.5">
                                            <span>📍 {{ $store->distance_km }} km away</span>
                                            <span>•</span>
                                            <span class="text-emerald-600 font-medium">★ {{ number_format($store->rating_avg ?? 4.8, 1) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 text-xs font-bold rounded-lg {{ $store->is_overloaded ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-800' }}">
                                    {{ $store->is_overloaded ? 'High Load' : 'Open' }}
                                </span>
                            </div>

                            <!-- Dynamic ETA Banner -->
                            <div class="bg-amber-50/80 border border-amber-100/80 rounded-xl px-3 py-2 flex items-center justify-between text-xs mb-3">
                                <div class="flex items-center gap-1.5 text-amber-900 font-semibold">
                                    <svg class="w-4 h-4 text-amber-600 animate-spin-slow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>Realistic ETA:</span>
                                </div>
                                <span class="font-extrabold text-amber-900 text-sm">{{ $store->eta['formatted_eta'] }}</span>
                            </div>

                            <div class="flex items-center justify-between text-[11px] text-slate-500 pt-2 border-t border-slate-100">
                                <span>Prep time: {{ $store->eta['prep_time_minutes'] }}m</span>
                                <span>Store load: {{ $store->eta['active_orders'] }}/{{ $store->eta['capacity'] }} orders</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Quick Eligible Products Grid -->
        <div>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                        <span>⚡ Quick Delivery Products</span>
                        <span class="text-xs bg-orange-100 text-orange-800 px-2.5 py-0.5 rounded-full font-bold">10-30 min arrival</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Dispatched immediately from nearest dark store hub</p>
                </div>

                <!-- Search form -->
                <form action="{{ route('quick.index') }}" method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="lat" value="{{ $lat }}">
                    <input type="hidden" name="lng" value="{{ $lng }}">
                    <div class="relative">
                        <input type="text" name="q" value="{{ $searchQuery }}" placeholder="Search milk, bread, chips..." class="pl-9 pr-4 py-1.5 text-xs rounded-xl border border-slate-200 focus:ring-2 focus:ring-orange-500 w-52 sm:w-64">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <button type="submit" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold rounded-xl">Search</button>
                </form>
            </div>

            @if($products->isEmpty())
                <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
                    <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    <h3 class="font-bold text-slate-900 text-base">No Quick-Delivery Products Found</h3>
                    <p class="text-xs text-slate-500 max-w-md mx-auto mt-1">There are no quick-commerce eligible products matching your filter or delivery location right now.</p>
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    @foreach($products as $product)
                        @php
                            $variant = $product->variants->first();
                            $price = $variant?->selling_price ?? $variant?->price ?? 99.00;
                            $mrp = $variant?->mrp ?? ($price * 1.2);
                            $discountPct = $mrp > $price ? round((($mrp - $price) / $mrp) * 100) : 0;
                            $image = $product->media->first()?->path ?? 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=400&q=80';
                        @endphp
                        <div class="bg-white rounded-2xl p-3 border border-slate-200/80 hover:shadow-lg transition flex flex-col justify-between group">
                            <div>
                                <!-- Image & Quick Badge -->
                                <div class="relative aspect-square rounded-xl overflow-hidden bg-slate-100 mb-2.5">
                                    <img src="{{ $image }}" alt="{{ $product->title }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                    <div class="absolute top-2 left-2 bg-gradient-to-r from-amber-500 to-orange-600 text-white font-extrabold text-[10px] px-2 py-0.5 rounded-md shadow-sm flex items-center gap-1">
                                        <span>⚡</span> {{ $product->dynamic_eta }}
                                    </div>
                                    @if($discountPct > 0)
                                        <div class="absolute top-2 right-2 bg-rose-500 text-white font-bold text-[10px] px-1.5 py-0.5 rounded">
                                            {{ $discountPct }}% OFF
                                        </div>
                                    @endif
                                </div>

                                <!-- Title & Seller -->
                                <h4 class="font-bold text-slate-900 text-xs line-clamp-2 mb-1 group-hover:text-orange-600 transition">
                                    <a href="{{ route('product.show', $product->slug) }}">{{ $product->title }}</a>
                                </h4>
                                <p class="text-[11px] text-slate-400 mb-2 truncate">From {{ $product->nearest_store?->name ?? 'Nearby Hub' }}</p>
                            </div>

                            <div>
                                <!-- Price Row -->
                                <div class="flex items-baseline gap-1.5 mb-2.5">
                                    <span class="font-extrabold text-slate-900 text-sm">₹{{ number_format($price, 2) }}</span>
                                    @if($mrp > $price)
                                        <span class="text-[11px] text-slate-400 line-through">₹{{ number_format($mrp, 2) }}</span>
                                    @endif
                                </div>

                                <!-- Add to Cart Button -->
                                <form action="{{ route('cart.add') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="variant_id" value="{{ $variant?->id }}">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="w-full py-1.5 bg-orange-50 hover:bg-orange-500 text-orange-600 hover:text-white border border-orange-200 hover:border-orange-500 rounded-xl font-bold text-xs transition flex items-center justify-center gap-1.5 shadow-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                        <span>ADD</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
