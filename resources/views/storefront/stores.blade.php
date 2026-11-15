@extends('layouts.storefront')

@section('title', 'Hyperlocal Store Directory & Outlets - OneStall.in')

@section('content')
<div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-8 space-y-5 sm:space-y-6"
     x-data="{
        map: null,
        markers: {},
        pins: {{ json_encode($mapPins) }},
        selectedStoreId: null,
        mobileTab: 'list', // 'list' or 'map'
        userLat: {{ (float) ($location['lat'] ?? 12.9716) }},
        userLng: {{ (float) ($location['lng'] ?? 77.5946) }},
        initMap() {
            if (typeof L === 'undefined') {
                setTimeout(() => this.initMap(), 300);
                return;
            }

            const mapEl = document.getElementById('stores-map');
            if (!mapEl) return;

            if (this.map) {
                this.map.remove();
                this.markers = {};
            }

            this.map = L.map('stores-map', {
                zoomControl: true,
                scrollWheelZoom: false
            }).setView([this.userLat, this.userLng], 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href=\'https://www.openstreetmap.org/copyright\'>OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(this.map);

            // Add Customer / Current Location Pin
            const userIcon = L.divIcon({
                html: `<div class='w-7 h-7 rounded-full bg-indigo-600 border-2 border-white shadow-xl flex items-center justify-center text-white text-xs animate-pulse ring-4 ring-indigo-500/20'>📍</div>`,
                className: '',
                iconSize: [28, 28],
                iconAnchor: [14, 14]
            });
            L.marker([this.userLat, this.userLng], { icon: userIcon })
                .addTo(this.map)
                .bindPopup(`<div class='p-1.5 text-xs font-sans'><b>Your Selected Location</b><br><span class='text-slate-500'>{{ $location['address_label'] ?? 'Bengaluru' }}</span></div>`);

            // Add Store Pins
            const group = [];
            this.pins.forEach(pin => {
                const isQuick = pin.is_quick;
                const storeIcon = L.divIcon({
                    html: `
                        <div class='px-2.5 py-1 rounded-xl bg-slate-950 text-white font-extrabold text-[11px] shadow-xl border ${isQuick ? 'border-amber-400 text-amber-300' : 'border-indigo-400 text-indigo-200'} flex items-center space-x-1.5 cursor-pointer whitespace-nowrap hover:scale-110 transition-transform'>
                            <span>${isQuick ? '⚡' : '🏪'}</span>
                            <span class='truncate max-w-[110px] text-white font-bold'>${pin.name}</span>
                        </div>
                    `,
                    className: '',
                    iconSize: [140, 28],
                    iconAnchor: [70, 14]
                });

                const m = L.marker([pin.lat, pin.lng], { icon: storeIcon })
                    .addTo(this.map)
                    .bindPopup(`
                        <div class='p-2 space-y-1.5 text-xs font-sans max-w-[210px]'>
                            <div class='flex items-center space-x-2'>
                                <div class='w-7 h-7 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center text-sm shrink-0 overflow-hidden'>
                                    ${pin.logo_url ? `<img src='${pin.logo_url}' class='w-full h-full object-cover'>` : '🏪'}
                                </div>
                                <div class='min-w-0'>
                                    <h4 class='font-bold text-slate-900 truncate'>${pin.name}</h4>
                                    <div class='text-[10px] text-slate-500 truncate'>${pin.city}</div>
                                </div>
                            </div>
                            <div class='flex items-center justify-between text-[11px] pt-1 border-t border-slate-100'>
                                <span class='text-amber-600 font-bold'>★ ${Number(pin.rating).toFixed(1)}</span>
                                <span class='${pin.is_open ? 'text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded font-bold' : 'text-slate-400'}'>${pin.is_open ? 'Open Now' : 'Closed'}</span>
                            </div>
                            <div class='pt-1.5'>
                                <a href='${pin.url}' class='px-3 py-1.5 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 text-white rounded-xl text-[11px] font-bold block text-center shadow-xs'>Visit Storefront &rarr;</a>
                            </div>
                        </div>
                    `);

                m.on('click', () => {
                    this.selectedStoreId = pin.id;
                });

                this.markers[pin.id] = m;
                group.push([pin.lat, pin.lng]);
            });

            // Auto-fit bounds so all pins are visible
            if (group.length > 0) {
                const bounds = L.latLngBounds(group);
                bounds.extend([this.userLat, this.userLng]);
                this.map.fitBounds(bounds, { padding: [35, 35], maxZoom: 14 });
            }
        },
        focusStore(id, lat, lng) {
            this.selectedStoreId = id;
            if (this.map && lat && lng) {
                this.map.flyTo([lat, lng], 15, { duration: 1 });
                if (this.markers[id]) {
                    this.markers[id].openPopup();
                }
            }
            if (window.innerWidth < 1024) {
                this.mobileTab = 'map';
                this.$nextTick(() => {
                    if (this.map) {
                        this.map.invalidateSize();
                        this.map.flyTo([lat, lng], 15);
                        if (this.markers[id]) this.markers[id].openPopup();
                    }
                });
            }
        },
        switchToMap() {
            this.mobileTab = 'map';
            this.$nextTick(() => {
                if (this.map) this.map.invalidateSize();
            });
        }
     }"
     x-init="$nextTick(() => initMap())">

    <!-- Leaflet CSS & JS for OpenStreetMap Interactive Directory -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- 1. Hero Header Banner -->
    <div class="p-5 sm:p-7 rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white relative overflow-hidden shadow-xl border border-slate-800">
        <div class="absolute inset-0 opacity-15 bg-[radial-gradient(#818cf8_1px,transparent_1px)] [background-size:16px_16px]"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-5">
            <div class="space-y-1.5 max-w-2xl">
                <div class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950 shadow-xs">
                    <span>⚡ HYPER-LOCAL RETAIL DIRECTORY</span>
                </div>
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight text-white leading-tight">
                    Nearby Stores &amp; Neighbourhood Outlets
                </h1>
                <p class="text-xs sm:text-sm text-slate-300 font-medium leading-relaxed">
                    Shop directly from certified merchant outlets near 
                    <span class="text-amber-300 font-bold underline cursor-pointer" @click="$store.location.modalOpen = true">{{ $location['address_label'] ?? 'Bengaluru' }}</span> 
                    with rapid 15–30m doorstep delivery or instant store counter pickup.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <button type="button" @click="$store.location.modalOpen = true"
                        class="px-4 py-2.5 rounded-2xl bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/20 text-xs font-bold text-white shadow-xs flex items-center space-x-2 transition cursor-pointer active:scale-95">
                    <span>📍</span>
                    <span>Change Pin Location</span>
                </button>
                <a href="{{ route('stores.index', ['all_stores' => 1]) }}"
                   class="px-4 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-xs font-bold text-white shadow-md shadow-indigo-600/30 flex items-center space-x-1.5 transition cursor-pointer active:scale-95">
                    <span>🌐</span>
                    <span>View All Outlets ({{ $totalActiveStores }})</span>
                </a>
            </div>
        </div>
    </div>

    <!-- 2. City Quick Selector Strip -->
    <div class="flex items-center space-x-2 overflow-x-auto no-scrollbar scrollbar-none py-1">
        <span class="text-xs font-bold text-slate-500 shrink-0 mr-1">City Hubs:</span>
        <a href="{{ route('stores.index', array_merge(request()->except(['city', 'page']))) }}"
           class="px-3.5 py-1.5 rounded-xl text-xs font-bold shrink-0 transition {{ empty($city) ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-600/20' : 'bg-white hover:bg-slate-100 text-slate-700 border border-slate-200/90' }}">
            All Cities ({{ $totalActiveStores }})
        </a>
        @foreach($cityCounts as $cName => $cCount)
            <a href="{{ route('stores.index', array_merge(request()->except('page'), ['city' => $cName])) }}"
               class="px-3.5 py-1.5 rounded-xl text-xs font-bold shrink-0 transition flex items-center space-x-1 {{ $city === $cName ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-600/20' : 'bg-white hover:bg-slate-100 text-slate-700 border border-slate-200/90' }}">
                <span>📍 {{ $cName }}</span>
                <span class="text-[10px] {{ $city === $cName ? 'text-indigo-200' : 'text-slate-400' }}">({{ $cCount }})</span>
            </a>
        @endforeach
    </div>

    <!-- 3. Search & Interactive Filters Bar -->
    <form method="GET" action="{{ route('stores.index') }}" class="p-3.5 sm:p-4 bg-white rounded-3xl border border-slate-200/90 shadow-xs space-y-3">
        @if(!empty($city))
            <input type="hidden" name="city" value="{{ $city }}">
        @endif
        @if(!empty($filters['all_stores']))
            <input type="hidden" name="all_stores" value="1">
        @endif

        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            
            <!-- Left: Search Box -->
            <div class="relative flex-1 min-w-[240px]">
                <input type="text" name="q" value="{{ $query }}" placeholder="Search store name, locality, or category..."
                       class="w-full text-xs sm:text-sm pl-9 pr-4 py-2.5 rounded-2xl border border-slate-200/90 bg-slate-50/50 hover:bg-slate-50 focus:bg-white focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 font-medium text-slate-900 transition outline-none">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <!-- Right: Filter Toggles & Radius -->
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <!-- Quick Commerce -->
                <label class="flex items-center space-x-1.5 px-3 py-2 rounded-xl border cursor-pointer font-bold transition select-none {{ !empty($filters['quick_only']) ? 'bg-amber-50 border-amber-300 text-amber-900 shadow-2xs' : 'bg-slate-50 hover:bg-slate-100 border-slate-200 text-slate-700' }}">
                    <input type="checkbox" name="quick_only" value="1" {{ !empty($filters['quick_only']) ? 'checked' : '' }} onchange="this.form.submit()" class="rounded text-amber-500 focus:ring-amber-400 border-slate-300 w-3.5 h-3.5">
                    <span>⚡ Quick Delivery</span>
                </label>

                <!-- In-Store Pickup -->
                <label class="flex items-center space-x-1.5 px-3 py-2 rounded-xl border cursor-pointer font-semibold transition select-none {{ !empty($filters['pickup_only']) ? 'bg-indigo-50 border-indigo-300 text-indigo-900 shadow-2xs' : 'bg-slate-50 hover:bg-slate-100 border-slate-200 text-slate-700' }}">
                    <input type="checkbox" name="pickup_only" value="1" {{ !empty($filters['pickup_only']) ? 'checked' : '' }} onchange="this.form.submit()" class="rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 w-3.5 h-3.5">
                    <span>🛍️ Pickup</span>
                </label>

                <!-- Open Now -->
                <label class="flex items-center space-x-1.5 px-3 py-2 rounded-xl border cursor-pointer font-semibold transition select-none {{ !empty($filters['open_only']) ? 'bg-emerald-50 border-emerald-300 text-emerald-900 shadow-2xs' : 'bg-slate-50 hover:bg-slate-100 border-slate-200 text-slate-700' }}">
                    <input type="checkbox" name="open_only" value="1" {{ !empty($filters['open_only']) ? 'checked' : '' }} onchange="this.form.submit()" class="rounded text-emerald-600 focus:ring-emerald-500 border-slate-300 w-3.5 h-3.5">
                    <span>🟢 Open Now</span>
                </label>

                <!-- Radius Selector -->
                <div class="flex items-center space-x-1 bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5">
                    <span class="text-slate-400 text-[11px] font-bold">Radius:</span>
                    <select name="radius_km" onchange="this.form.submit()" class="bg-transparent text-xs font-bold text-slate-800 border-0 p-0 pr-4 focus:ring-0 cursor-pointer">
                        <option value="5" {{ ($filters['radius_km'] ?? '') == 5 ? 'selected' : '' }}>5 km</option>
                        <option value="10" {{ ($filters['radius_km'] ?? '') == 10 ? 'selected' : '' }}>10 km</option>
                        <option value="25" {{ ($filters['radius_km'] ?? '') == 25 ? 'selected' : '' }}>25 km</option>
                        <option value="50" {{ ($filters['radius_km'] ?? '') == 50 ? 'selected' : '' }}>50 km</option>
                        <option value="all" {{ ($filters['radius_km'] ?? '') === 'all' || !empty($filters['all_stores']) ? 'selected' : '' }}>All Network</option>
                    </select>
                </div>

                <!-- Sort By -->
                <div class="flex items-center space-x-1 bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5">
                    <span class="text-slate-400 text-[11px] font-bold">Sort:</span>
                    <select name="sort" onchange="this.form.submit()" class="bg-transparent text-xs font-bold text-slate-800 border-0 p-0 pr-4 focus:ring-0 cursor-pointer">
                        <option value="distance" {{ $sortBy === 'distance' ? 'selected' : '' }}>Nearest First</option>
                        <option value="rating" {{ $sortBy === 'rating' ? 'selected' : '' }}>Top Rated</option>
                        <option value="newest" {{ $sortBy === 'newest' ? 'selected' : '' }}>Newest Listed</option>
                        <option value="name" {{ $sortBy === 'name' ? 'selected' : '' }}>Store Name</option>
                    </select>
                </div>
            </div>

        </div>
    </form>

    <!-- 4. Mobile View Mode Segmented Switcher (sm:hidden) -->
    <div class="lg:hidden flex items-center rounded-2xl bg-slate-200/80 p-1">
        <button type="button" @click="mobileTab = 'list'"
                :class="mobileTab === 'list' ? 'bg-white text-slate-900 font-extrabold shadow-sm' : 'text-slate-600 font-semibold'"
                class="flex-1 py-2 rounded-xl text-xs flex items-center justify-center space-x-1.5 transition cursor-pointer">
            <span>📋 Stores ({{ $stores->total() }})</span>
        </button>
        <button type="button" @click="switchToMap()"
                :class="mobileTab === 'map' ? 'bg-white text-slate-900 font-extrabold shadow-sm' : 'text-slate-600 font-semibold'"
                class="flex-1 py-2 rounded-xl text-xs flex items-center justify-center space-x-1.5 transition cursor-pointer">
            <span>🗺️ Interactive Map ({{ count($mapPins) }})</span>
        </button>
    </div>

    <!-- 5. Main Split Screen Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- Store Cards Column (7 Columns) -->
        <div class="lg:col-span-7 space-y-4" x-show="mobileTab === 'list' || window.innerWidth >= 1024">
            
            <div class="text-xs font-bold text-slate-500 flex items-center justify-between px-1">
                <span>
                    @if(!empty($city))
                        Showing stores in <strong class="text-indigo-600">{{ $city }}</strong> ({{ $stores->total() }})
                    @elseif(!empty($filters['all_stores']))
                        Showing all stores across OneStall Network ({{ $stores->total() }})
                    @else
                        {{ $stores->total() }} Stores Found Near {{ $location['address_label'] ?? 'Your Location' }}
                    @endif
                </span>
                <span class="text-slate-400 font-medium">Auto-updated</span>
            </div>

            <!-- Stores Loop -->
            @forelse($stores as $store)
                @php
                    $loc = $store->location;
                    $lat = $loc ? (float) $loc->lat : null;
                    $lng = $loc ? (float) $loc->lng : null;
                    $prodCount = $store->products()->count();
                @endphp
                <div class="p-4 sm:p-5 rounded-3xl bg-white border border-slate-200/90 hover:border-indigo-300 hover:shadow-lg transition-all duration-300 flex flex-col sm:flex-row gap-4 sm:gap-5 items-start"
                     :class="selectedStoreId === {{ $store->id }} ? 'ring-2 ring-indigo-600 border-indigo-600 bg-indigo-50/20' : ''">

                    <!-- Store Logo / Banner Box -->
                    <div class="w-full sm:w-36 h-32 sm:h-28 rounded-2xl bg-slate-900 relative overflow-hidden shrink-0 flex items-center justify-center shadow-xs">
                        @if($store->banner_url)
                            <img src="{{ $store->banner_url }}" alt="{{ $store->name }}" class="w-full h-full object-cover">
                        @elseif($store->logo_url)
                            <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="w-full h-full object-contain p-2 bg-white">
                        @else
                            <div class="w-full h-full bg-gradient-to-br from-indigo-600 to-indigo-900 flex flex-col items-center justify-center text-white p-2 text-center">
                                <span class="text-2xl mb-0.5">🏪</span>
                                <span class="text-[10px] font-bold text-white/80 uppercase tracking-wider truncate max-w-[110px]">{{ $store->name }}</span>
                            </div>
                        @endif

                        <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-[9px] font-black {{ $store->isOpenNow() ? 'bg-emerald-500 text-white shadow-xs' : 'bg-slate-800 text-slate-300' }}">
                            {{ $store->isOpenNow() ? 'OPEN' : 'CLOSED' }}
                        </span>
                    </div>

                    <!-- Store Content -->
                    <div class="flex-1 min-w-0 space-y-2 w-full">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="flex items-center space-x-1.5">
                                    <h3 class="text-sm sm:text-base font-bold text-slate-900 truncate">
                                        <a href="{{ route('store.show', $store->slug) }}" class="hover:text-indigo-600 transition">
                                            {{ $store->name }}
                                        </a>
                                    </h3>
                                    <span class="w-4 h-4 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-[10px]" title="Verified Merchant">✓</span>
                                </div>
                                <div class="text-[11px] text-slate-500 flex items-center space-x-1 mt-0.5">
                                    <span>📍</span>
                                    <span class="truncate">{{ $loc ? "{$loc->address}, {$loc->city} {$loc->pincode}" : 'Verified Neighbourhood Hub' }}</span>
                                </div>
                            </div>

                            <!-- Rating -->
                            <div class="flex items-center bg-amber-50 border border-amber-200/80 px-2 py-0.5 rounded-lg text-amber-700 font-extrabold text-xs shrink-0">
                                <span>★ {{ number_format($store->rating_avg ?: 4.8, 1) }}</span>
                            </div>
                        </div>

                        @if($store->description)
                            <p class="text-xs text-slate-500 line-clamp-1 font-normal">
                                {{ $store->description }}
                            </p>
                        @endif

                        <!-- Badges Row -->
                        <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-bold pt-1">
                            @if(isset($store->distance_km) && is_numeric($store->distance_km))
                                <span class="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-100">
                                    {{ number_format((float) $store->distance_km, 1) }} km away
                                </span>
                            @elseif($loc?->city)
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700">
                                    {{ $loc->city }}
                                </span>
                            @endif

                            @if($store->accepts_quick_commerce)
                                <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200/80">
                                    ⚡ 15–30m Quick Delivery
                                </span>
                            @endif

                            @if($store->accepts_pickup)
                                <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200/80">
                                    🛍️ Counter Pickup
                                </span>
                            @endif

                            @if($prodCount > 0)
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600">
                                    {{ $prodCount }} Products
                                </span>
                            @endif
                        </div>

                        <!-- Card Action Buttons -->
                        <div class="pt-2.5 border-t border-slate-100 flex items-center justify-between gap-2">
                            @if($lat && $lng)
                                <button type="button"
                                        @click="focusStore({{ $store->id }}, {{ $lat }}, {{ $lng }})"
                                        class="text-xs font-bold text-slate-600 hover:text-indigo-600 flex items-center space-x-1 cursor-pointer transition">
                                    <span>🗺️ Show on Map</span>
                                </button>
                            @else
                                <span></span>
                            @endif

                            <a href="{{ route('store.show', $store->slug) }}"
                               class="px-4 py-2 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 text-white font-bold text-xs shadow-xs active:scale-95 transition flex items-center space-x-1">
                                <span>Enter Storefront</span>
                                <span>&rarr;</span>
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <!-- Fallback when no stores in direct GPS radius -->
                <div class="p-6 sm:p-8 text-center bg-white rounded-3xl border border-slate-200/90 shadow-xs space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl mx-auto">
                        📍
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-base font-bold text-slate-900">No stores found within {{ $filters['radius_km'] }} km of {{ $location['address_label'] ?? 'your location' }}</h3>
                        <p class="text-xs text-slate-500 max-w-md mx-auto">
                            Don't worry! You can expand your delivery radius or explore certified stores across other cities in the OneStall Network.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center justify-center gap-2 pt-2">
                        <a href="{{ route('stores.index', ['all_stores' => 1]) }}"
                           class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition">
                            Explore All {{ $totalActiveStores }} Stores
                        </a>
                        <button type="button" @click="$store.location.modalOpen = true"
                                class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition">
                            Select Another City
                        </button>
                    </div>
                </div>

                @if(!empty($fallbackStores) && $fallbackStores->isNotEmpty())
                    <div class="pt-4 space-y-3">
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider px-1">Top Verified Stores on OneStall Network</h4>
                        @foreach($fallbackStores as $store)
                            @php
                                $loc = $store->location;
                                $lat = $loc ? (float) $loc->lat : null;
                                $lng = $loc ? (float) $loc->lng : null;
                            @endphp
                            <div class="p-4 rounded-3xl bg-white border border-slate-200/80 hover:border-indigo-300 hover:shadow-md transition flex flex-col sm:flex-row gap-4 items-start">
                                <div class="w-full sm:w-28 h-20 rounded-2xl bg-slate-900 relative overflow-hidden shrink-0 flex items-center justify-center">
                                    @if($store->logo_url)
                                        <img src="{{ $store->logo_url }}" class="w-full h-full object-contain p-2 bg-white">
                                    @else
                                        <span class="text-2xl">🏪</span>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0 space-y-1.5 w-full">
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-bold text-slate-900 text-sm truncate">{{ $store->name }}</h4>
                                        <span class="text-amber-600 font-bold text-xs">★ {{ number_format($store->rating_avg ?: 4.8, 1) }}</span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 truncate">📍 {{ $loc ? "{$loc->city}, {$loc->state}" : 'Verified Outlet' }}</div>
                                    <div class="flex items-center justify-between pt-1">
                                        <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">⚡ Quick Active</span>
                                        <a href="{{ route('store.show', $store->slug) }}" class="text-xs font-bold text-indigo-600 hover:underline">Visit Store &rarr;</a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endforelse

            <div class="pt-4">
                {{ $stores->links() }}
            </div>
        </div>

        <!-- Sticky Interactive Map Column (5 Columns on Desktop) -->
        <div class="lg:col-span-5 lg:sticky lg:top-24 space-y-3" x-show="mobileTab === 'map' || window.innerWidth >= 1024">
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-md overflow-hidden">
                <div class="p-3.5 bg-slate-900 text-white flex items-center justify-between text-xs font-bold">
                    <span class="flex items-center space-x-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Live Hyper-Local Map</span>
                    </span>
                    <span class="text-slate-400 font-medium text-[11px]">{{ count($mapPins) }} store pins loaded</span>
                </div>
                <div id="stores-map" class="w-full h-[380px] sm:h-[480px] lg:h-[560px] bg-slate-100"></div>
            </div>

            <div class="p-3 bg-indigo-50/60 rounded-2xl border border-indigo-100 text-xs text-indigo-900 flex items-center space-x-2">
                <span class="text-base">💡</span>
                <span>Click any map pin to view store details and navigate directly to its storefront.</span>
            </div>
        </div>

    </div>

</div>
@endsection
