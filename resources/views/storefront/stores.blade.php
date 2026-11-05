@extends('layouts.storefront')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6"
     x-data="{
        map: null,
        markers: {},
        pins: {{ json_encode($mapPins) }},
        selectedStoreId: null,
        initMap() {
            if (typeof L === 'undefined') return;

            const defaultLat = {{ (float) ($location['lat'] ?? 12.9716) }};
            const defaultLng = {{ (float) ($location['lng'] ?? 77.5946) }};

            this.map = L.map('stores-map').setView([defaultLat, defaultLng], 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(this.map);

            // Add Customer Location Pin
            const userIcon = L.divIcon({
                html: '<div class=\'w-6 h-6 rounded-full bg-indigo-600 border-2 border-white shadow-lg flex items-center justify-center text-white text-xs\'>📍</div>',
                className: '',
                iconSize: [24, 24]
            });
            L.marker([defaultLat, defaultLng], { icon: userIcon })
                .addTo(this.map)
                .bindPopup('<b>Your Current Location</b><br>{{ $location['address_label'] ?? 'Bengaluru' }}');

            // Add Store Pins
            this.pins.forEach(pin => {
                const storeIcon = L.divIcon({
                    html: `<div class='px-2 py-1 rounded-xl bg-slate-900 text-amber-400 font-extrabold text-[11px] shadow-lg border border-amber-300 flex items-center space-x-1 cursor-pointer whitespace-nowrap'><span>⚡</span><span>${pin.name}</span></div>`,
                    className: '',
                    iconSize: [120, 28]
                });

                const m = L.marker([pin.lat, pin.lng], { icon: storeIcon })
                    .addTo(this.map)
                    .bindPopup(`
                        <div class='p-2 space-y-1 text-xs'>
                            <h4 class='font-bold text-slate-900'>${pin.name}</h4>
                            <p class='text-slate-500'>${pin.address}</p>
                            <div class='flex items-center space-x-2 pt-1 font-bold'>
                                <span class='text-amber-500'>★ ${pin.rating}</span>
                                <span class='${pin.is_open ? 'text-emerald-600' : 'text-slate-400'}'>${pin.is_open ? 'Open Now' : 'Closed'}</span>
                            </div>
                            <div class='pt-2'>
                                <a href='${pin.url}' class='px-3 py-1 bg-indigo-600 text-white rounded-lg text-[10px] font-bold block text-center'>Enter Store &rarr;</a>
                            </div>
                        </div>
                    `);

                this.markers[pin.id] = m;
            });
        },
        focusStore(id, lat, lng) {
            this.selectedStoreId = id;
            if (this.map && lat && lng) {
                this.map.flyTo([lat, lng], 15, { duration: 1.2 });
                if (this.markers[id]) {
                    this.markers[id].openPopup();
                }
            }
        }
     }"
     x-init="$nextTick(() => initMap())">

    <!-- Leaflet CSS & JS for OpenStreetMap Interactive Directory -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- Header & Interactive Filter Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
                    ⚡ HYPER-LOCAL RETAIL DIRECTORY
                </span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mt-1">
                Nearby Stores & Outlets
            </h1>
            <p class="text-xs text-slate-500">
                Order directly from physical shops near <strong class="text-indigo-600">{{ $location['address_label'] ?? 'Bengaluru' }}</strong> with instant 15-30m delivery and in-store pickup
            </p>
        </div>

        <button type="button" @click="$store.location.modalOpen = true" class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-indigo-600 hover:bg-indigo-50 shadow-xs flex items-center space-x-1.5 self-start md:self-auto">
            <span>📍 Change Location</span>
        </button>
    </div>

    <!-- Quick Filters Ribbon -->
    <form method="GET" action="{{ route('stores.index') }}" class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex flex-wrap items-center gap-3">
            <!-- Search by Name/City -->
            <div class="relative min-w-[220px]">
                <input type="text" name="q" value="{{ $query }}" placeholder="Search store name or area..." class="w-full text-xs pl-8 pr-3 py-1.5 rounded-xl border border-slate-200 focus:ring-indigo-500">
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <!-- Quick Commerce Only -->
            <label class="flex items-center space-x-2 cursor-pointer font-bold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-xl border border-amber-200">
                <input type="checkbox" name="quick_only" value="1" {{ !empty($filters['quick_only']) ? 'checked' : '' }} onchange="this.form.submit()" class="rounded text-amber-500 focus:ring-amber-400">
                <span>⚡ Quick Commerce Only</span>
            </label>

            <!-- Store Pickup Only -->
            <label class="flex items-center space-x-2 cursor-pointer font-semibold text-slate-700 bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200">
                <input type="checkbox" name="pickup_only" value="1" {{ !empty($filters['pickup_only']) ? 'checked' : '' }} onchange="this.form.submit()" class="rounded text-indigo-600 focus:ring-indigo-500">
                <span>🛍️ In-Store Pickup</span>
            </label>

            <!-- Open Now Only -->
            <label class="flex items-center space-x-2 cursor-pointer font-semibold text-slate-700 bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200">
                <input type="checkbox" name="open_only" value="1" {{ !empty($filters['open_only']) ? 'checked' : '' }} onchange="this.form.submit()" class="rounded text-emerald-600 focus:ring-emerald-500">
                <span>🟢 Open Now</span>
            </label>
        </div>

        <!-- Radius Selector -->
        <div class="flex items-center space-x-2">
            <span class="text-slate-500 font-medium">Radius:</span>
            <select name="radius_km" onchange="this.form.submit()" class="text-xs font-bold text-slate-800 border border-slate-200 rounded-xl py-1 pl-2 pr-6">
                <option value="5" {{ ($filters['radius_km'] ?? '') == 5 ? 'selected' : '' }}>Within 5 km</option>
                <option value="10" {{ ($filters['radius_km'] ?? '') == 10 ? 'selected' : '' }}>Within 10 km</option>
                <option value="25" {{ ($filters['radius_km'] ?? '') == 25 ? 'selected' : '' }}>Within 25 km</option>
                <option value="50" {{ ($filters['radius_km'] ?? '') == 50 ? 'selected' : '' }}>Within 50 km</option>
            </select>
        </div>
    </form>

    <!-- Split Screen: Store Cards on Left, Interactive Map on Right -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- Store Cards Column (7 Cols on Desktop) -->
        <div class="lg:col-span-7 space-y-4">
            <div class="text-xs font-bold text-slate-500 flex items-center justify-between">
                <span>{{ $stores->total() }} Stores Found in Selected Area</span>
                <span>Sorted by Distance & Rating</span>
            </div>

            @forelse($stores as $store)
                @php
                    $loc = $store->location;
                    $lat = $loc ? (float) $loc->lat : null;
                    $lng = $loc ? (float) $loc->lng : null;
                @endphp
                <div class="p-5 rounded-3xl bg-white border border-slate-200/80 hover:border-indigo-400 hover:shadow-lg transition flex flex-col sm:flex-row gap-5 items-start"
                     :class="selectedStoreId === {{ $store->id }} ? 'ring-2 ring-indigo-600' : ''">

                    <!-- Store Logo / Banner Box -->
                    <div class="w-full sm:w-36 h-28 rounded-2xl bg-slate-900 relative overflow-hidden flex-shrink-0">
                        @if($store->banner)
                            <img src="{{ asset('storage/' . $store->banner) }}" alt="{{ $store->name }}" class="w-full h-full object-cover">
                        @elseif($store->logo)
                            <img src="{{ asset('storage/' . $store->logo) }}" alt="{{ $store->name }}" class="w-full h-full object-contain p-2 bg-white">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-white font-black text-2xl">
                                🏪
                            </div>
                        @endif
                        <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-[9px] font-black {{ $store->isOpenNow() ? 'bg-emerald-500 text-white' : 'bg-slate-700 text-slate-300' }}">
                            {{ $store->isOpenNow() ? 'OPEN' : 'CLOSED' }}
                        </span>
                    </div>

                    <!-- Store Content -->
                    <div class="flex-1 min-w-0 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-extrabold text-slate-900 truncate">
                                {{ $store->name }}
                            </h3>
                            <div class="flex items-center text-amber-500 text-xs font-bold space-x-0.5">
                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                <span>{{ number_format($store->rating_avg ?? 4.8, 1) }}</span>
                            </div>
                        </div>

                        <p class="text-xs text-slate-500 line-clamp-1">
                            {{ $store->description ?: 'Authorized neighbourhood store on OneStall Network' }}
                        </p>

                        <div class="text-[11px] text-slate-600 flex items-center space-x-1">
                            <span>📍</span>
                            <span class="truncate">{{ $loc ? "{$loc->address}, {$loc->city} {$loc->pincode}" : 'Indiranagar, Bengaluru' }}</span>
                        </div>

                        <!-- Badges Row -->
                        <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-bold pt-1">
                            @if(isset($store->distance_km))
                                <span class="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700">
                                    {{ number_format((float) $store->distance_km, 1) }} km away
                                </span>
                            @endif

                            @if($store->accepts_quick_commerce)
                                <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 border border-amber-200">
                                    ⚡ 15-30m Quick Delivery
                                </span>
                            @endif

                            @if($store->accepts_pickup)
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700">
                                    🛍️ Store Pickup
                                </span>
                            @endif
                        </div>

                        <!-- Card Buttons -->
                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                            @if($lat && $lng)
                                <button type="button"
                                        @click="focusStore({{ $store->id }}, {{ $lat }}, {{ $lng }})"
                                        class="text-xs font-bold text-slate-600 hover:text-indigo-600 flex items-center space-x-1">
                                    <span>🗺️ Show on Map</span>
                                </button>
                            @else
                                <span></span>
                            @endif

                            <a href="{{ route('store.show', $store->slug) }}" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition">
                                Enter Storefront &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center bg-white rounded-3xl border border-slate-200">
                    <p class="text-sm font-bold text-slate-800">No stores found in this area matching your criteria.</p>
                    <p class="text-xs text-slate-500 mt-1">Try expanding your delivery radius to 25 km or 50 km.</p>
                </div>
            @endforelse

            <div class="pt-4">
                {{ $stores->links() }}
            </div>
        </div>

        <!-- Sticky Interactive Map Column (5 Cols on Desktop) -->
        <div class="lg:col-span-5 sticky top-24">
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-md overflow-hidden">
                <div class="p-3 bg-slate-900 text-white flex items-center justify-between text-xs font-bold">
                    <span class="flex items-center space-x-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Live Hyper-Local Map View</span>
                    </span>
                    <span class="text-slate-400 font-normal text-[10px]">{{ count($mapPins) }} store pins loaded</span>
                </div>
                <div id="stores-map" class="w-full h-[520px] bg-slate-100"></div>
            </div>
        </div>

    </div>

</div>
@endsection
