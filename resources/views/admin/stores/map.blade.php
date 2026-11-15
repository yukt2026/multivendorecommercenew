@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Interactive Stores Geo-Coverage Map'])

@section('content')
<div class="space-y-6" x-data="storeMapViewer()">

    <!-- Page Header Component -->
    <x-page-header 
        title="Interactive Store Map & Geo-Coverage" 
        subtitle="Visual geospatial command center for retail stores, dark store fulfillment hubs, and hyper-local delivery radius coverage."
        :breadcrumbs="['Command Central' => route('admin.dashboard'), 'Stores Directory' => route('admin.stores.index'), 'Geo Map' => '#']"
    >
        <x-slot name="badge">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/70 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                <span class="w-2 h-2 rounded-full bg-emerald-500 mr-2 animate-pulse"></span>
                {{ $metrics['total_geocoded'] }} Geocoded Store Outlets
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <button 
                    type="button" 
                    @click="fitAllMarkers()" 
                    class="px-3 py-2 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold flex items-center space-x-1.5 shadow-xs transition"
                    title="Fit all store markers in viewport"
                >
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    <span>Fit All in View</span>
                </button>
                <a 
                    href="{{ route('admin.stores.index') }}" 
                    class="px-3.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-semibold flex items-center space-x-1.5 transition"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Store Directory</span>
                </a>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Top 5 Geospatial Metrics Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Total Mapped Hubs</span>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-2xl font-black text-slate-900 dark:text-white font-mono">{{ $metrics['total_geocoded'] }}</span>
                <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">{{ $metrics['active'] }} Active</span>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Quick Commerce (10-30m)</span>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-2xl font-black text-amber-500 font-mono">{{ $metrics['quick_commerce'] }}</span>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">Express Delivery</span>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Standard Outlets</span>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-2xl font-black text-indigo-600 dark:text-indigo-400 font-mono">{{ $metrics['standard'] }}</span>
                <span class="text-[11px] font-semibold text-slate-500">Scheduled / Cargo</span>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Avg Delivery Radius</span>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-2xl font-black text-slate-900 dark:text-white font-mono">{{ $metrics['avg_radius'] }} <span class="text-sm font-normal text-slate-400">km</span></span>
                <span class="text-[11px] font-semibold text-slate-500">Radial Bounds</span>
            </div>
        </div>

        <div class="col-span-2 sm:col-span-1 p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">City Coverage Reach</span>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-2xl font-black text-sky-600 dark:text-sky-400 font-mono">{{ $metrics['cities_count'] }}</span>
                <span class="text-[11px] font-semibold text-slate-500">Active Cities</span>
            </div>
        </div>
    </div>

    <!-- Alert for unmapped stores missing GPS coordinates -->
    @if($metrics['unmapped'] > 0)
        <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/80 text-xs text-amber-900 dark:text-amber-200 flex items-center justify-between gap-4">
            <div class="flex items-center space-x-3">
                <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>
                    <strong>Geocoding Notice:</strong> {{ $metrics['unmapped'] }} store outlet(s) are registered without GPS coordinates and cannot be plotted on the map.
                </span>
            </div>
            <a href="{{ route('admin.stores.index') }}" class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shrink-0 transition">
                Resolve in Directory &rarr;
            </a>
        </div>
    @endif

    <!-- Interactive Map & Controls Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

        <!-- Left: Filters & Store Directory List (Span 1) -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-3xl p-4 shadow-sm flex flex-col h-[700px]">
            
            <!-- Filters Header & Search -->
            <div class="space-y-3 pb-3 border-b border-slate-100 dark:border-slate-800 shrink-0">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input 
                        type="text" 
                        x-model="searchTerm" 
                        placeholder="Search store, seller, city..." 
                        class="w-full pl-9 pr-8 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                    >
                    <button 
                        type="button" 
                        x-show="searchTerm" 
                        @click="searchTerm = ''" 
                        class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs font-bold"
                    >
                        &times;
                    </button>
                </div>

                <!-- Quick Filters Bar -->
                <div class="flex items-center space-x-1.5 overflow-x-auto pb-1 text-[11px] scrollbar-none">
                    <button 
                        type="button" 
                        @click="filterType = 'all'" 
                        :class="filterType === 'all' ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'"
                        class="px-2.5 py-1 rounded-lg transition shrink-0"
                    >
                        All (<span x-text="stores.length"></span>)
                    </button>
                    <button 
                        type="button" 
                        @click="filterType = 'quick'" 
                        :class="filterType === 'quick' ? 'bg-amber-500 text-white font-bold shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'"
                        class="px-2.5 py-1 rounded-lg transition shrink-0 flex items-center space-x-1"
                    >
                        <span>âš¡ Quick</span>
                    </button>
                    <button 
                        type="button" 
                        @click="filterType = 'active'" 
                        :class="filterType === 'active' ? 'bg-emerald-600 text-white font-bold' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'"
                        class="px-2.5 py-1 rounded-lg transition shrink-0"
                    >
                        Active
                    </button>
                </div>

                <!-- Radius Circles Toggle -->
                <div class="flex items-center justify-between text-xs pt-1 px-1 text-slate-600 dark:text-slate-400">
                    <label class="flex items-center space-x-2 cursor-pointer select-none">
                        <input 
                            type="checkbox" 
                            x-model="showCircles" 
                            @change="toggleCircles()"
                            class="rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700"
                        >
                        <span class="text-[11px] font-medium">Show Delivery Radii</span>
                    </label>
                    <span class="text-[10px] text-slate-400 font-mono" x-text="filteredStores.length + ' visible'"></span>
                </div>
            </div>

            <!-- Scrollable Store Outlets List -->
            <div class="flex-1 overflow-y-auto space-y-2.5 pr-1 mt-3 scrollbar-thin scrollbar-thumb-slate-200 dark:scrollbar-thumb-slate-700">
                <template x-for="store in filteredStores" :key="store.id">
                    <div 
                        @click="focusStore(store)" 
                        class="p-3 rounded-2xl border transition-all cursor-pointer text-xs space-y-1.5"
                        :class="selectedStore?.id === store.id 
                            ? 'bg-indigo-50/90 dark:bg-indigo-950/60 border-indigo-500 ring-2 ring-indigo-500/20 shadow-xs' 
                            : 'bg-slate-50/80 dark:bg-slate-800/40 border-slate-200/80 dark:border-slate-800 hover:bg-slate-100/70 dark:hover:bg-slate-800/70'"
                    >
                        <div class="flex items-start justify-between gap-1.5">
                            <span class="font-bold text-slate-900 dark:text-white leading-tight" x-text="store.name"></span>
                            <div class="flex items-center space-x-1 shrink-0">
                                <span 
                                    x-show="store.is_quick" 
                                    class="text-[9px] px-1.5 py-0.5 rounded-full bg-amber-400 text-slate-950 font-black tracking-wide"
                                    title="10-30 Min Quick Commerce"
                                >
                                    âš¡ 10m
                                </span>
                                <span 
                                    class="text-[9px] px-1.5 py-0.5 rounded-full font-bold"
                                    :class="store.is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300' : 'bg-slate-200 text-slate-700'"
                                    x-text="store.status_label"
                                ></span>
                            </div>
                        </div>

                        <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                            <span class="font-medium text-slate-700 dark:text-slate-300" x-text="store.seller"></span>
                            <span class="text-slate-400 dark:text-slate-600"> &bull; </span>
                            <span x-text="store.city"></span>
                        </div>

                        <div class="flex items-center justify-between text-[11px] pt-0.5">
                            <span class="font-mono text-indigo-600 dark:text-indigo-400 font-semibold" x-text="'Coverage: ' + store.radius + ' km'"></span>
                            <span class="text-[10px] text-slate-400 font-mono" x-text="'#' + store.id"></span>
                        </div>
                    </div>
                </template>

                <!-- Empty State -->
                <div x-show="filteredStores.length === 0" class="py-12 text-center text-slate-400 text-xs space-y-2">
                    <svg class="w-8 h-8 mx-auto text-slate-300 dark:text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <p class="font-semibold text-slate-600 dark:text-slate-400">No stores match filter</p>
                    <p class="text-[11px]">Try clearing search or switching category tabs.</p>
                    <button 
                        type="button" 
                        @click="searchTerm = ''; filterType = 'all'" 
                        class="mt-2 px-3 py-1 text-xs font-semibold rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100"
                    >
                        Reset Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Right: Leaflet Interactive Map Canvas (Span 3) -->
        <div class="lg:col-span-3 bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm h-[700px] relative flex flex-col">
            
            <!-- Map Overlay Quick Badges & Controls -->
            <div class="absolute top-4 right-4 z-20 flex flex-wrap items-center gap-2 pointer-events-auto">
                <div class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-md text-xs font-medium text-slate-700 dark:text-slate-300 flex items-center space-x-3">
                    <span class="flex items-center space-x-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block"></span>
                        <span class="text-[11px]">Quick Hub</span>
                    </span>
                    <span class="flex items-center space-x-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-600 inline-block"></span>
                        <span class="text-[11px]">Standard Store</span>
                    </span>
                </div>

                <button 
                    type="button" 
                    @click="fitAllMarkers()" 
                    class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md p-2 rounded-xl border border-slate-200 dark:border-slate-800 shadow-md text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-800 transition"
                    title="Fit all store markers in viewport"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                </button>
            </div>

            <!-- Leaflet Target Div Container -->
            <div id="adminStoresMap" class="w-full h-full z-10"></div>
        </div>
    </div>

</div>

<!-- Leaflet CDN (Styling & Engine permitted via CSP) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
/* Leaflet popup styling override to match dashboard theme */
.leaflet-popup-content-wrapper {
    border-radius: 1rem !important;
    padding: 0 !important;
    overflow: hidden !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
    border: 1px solid rgba(226, 232, 240, 0.8) !important;
}
.leaflet-popup-content {
    margin: 0 !important;
    line-height: 1.4 !important;
}
.leaflet-popup-tip-container {
    margin-top: -1px !important;
}
.store-pin-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    color: white;
    font-weight: 800;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    border: 2.5px solid white;
    transition: transform 0.2s ease;
}
.store-pin-badge:hover {
    transform: scale(1.15);
}
</style>

<script>
function storeMapViewer() {
    return {
        stores: @json($mapData),
        searchTerm: '',
        filterType: 'all',
        showCircles: true,
        selectedStore: null,
        map: null,
        markersLayer: null,
        circlesLayer: null,
        markers: {},
        circles: {},

        get filteredStores() {
            let list = this.stores;

            // Type filter
            if (this.filterType === 'quick') {
                list = list.filter(s => s.is_quick);
            } else if (this.filterType === 'active') {
                list = list.filter(s => s.is_active);
            }

            // Search filter
            if (this.searchTerm.trim()) {
                const term = this.searchTerm.toLowerCase().trim();
                list = list.filter(s => 
                    s.name.toLowerCase().includes(term) || 
                    s.seller.toLowerCase().includes(term) || 
                    s.city.toLowerCase().includes(term) ||
                    (s.address && s.address.toLowerCase().includes(term)) ||
                    (s.pincode && s.pincode.toLowerCase().includes(term))
                );
            }

            return list;
        },

        init() {
            this.$nextTick(() => {
                this.initMap();
            });

            // Watch filter changes to update map markers visibility
            this.$watch('searchTerm', () => this.syncMarkers());
            this.$watch('filterType', () => this.syncMarkers());
        },

        initMap() {
            if (typeof L === 'undefined') {
                console.error('Leaflet library failed to load.');
                return;
            }

            // Default center: India or first store
            const defaultLat = this.stores.length > 0 ? this.stores[0].lat : 12.9716;
            const defaultLng = this.stores.length > 0 ? this.stores[0].lng : 77.5946;

            this.map = L.map('adminStoresMap', {
                zoomControl: true,
                attributionControl: true
            }).setView([defaultLat, defaultLng], 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(this.map);

            this.markersLayer = L.layerGroup().addTo(this.map);
            this.circlesLayer = L.layerGroup().addTo(this.map);

            this.createAllLayers();
            this.fitAllMarkers();
        },

        createAllLayers() {
            this.stores.forEach(store => {
                // Pin color based on status and quick commerce
                const pinBg = store.is_quick ? '#f59e0b' : '#4f46e5';
                const pinIcon = store.is_quick ? '⚡' : '🏪';

                const customIcon = L.divIcon({
                    className: 'custom-store-pin',
                    html: `<div class="store-pin-badge" style="background-color: ${pinBg};">${pinIcon}</div>`,
                    iconSize: [34, 34],
                    iconAnchor: [17, 34],
                    popupAnchor: [0, -32]
                });

                const marker = L.marker([store.lat, store.lng], { icon: customIcon });

                // Rich HTML Popup Content
                const popupHtml = `
                    <div style="font-family: inherit; width: 280px; padding: 14px; background: #ffffff;">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;">
                            <div>
                                <h4 style="margin: 0; font-size: 14px; font-weight: 800; color: #0f172a; line-height: 1.2;">
                                    ${this.escapeHtml(store.name)}
                                </h4>
                                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                    ${this.escapeHtml(store.seller)}
                                </div>
                            </div>
                            <span style="font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 9999px; background: ${store.is_quick ? '#fef3c7; color: #92400e;' : '#e0e7ff; color: #3730a3;'}">
                                ${store.is_quick ? '⚡ 10m Quick' : 'Standard'}
                            </span>
                        </div>

                        <div style="margin-top: 10px; padding: 8px; border-radius: 8px; background: #f8fafc; font-size: 11px; color: #334155;">
                            <div><strong>Address:</strong> ${this.escapeHtml(store.address || 'N/A')}</div>
                            <div style="margin-top: 2px;"><strong>City:</strong> ${this.escapeHtml(store.city)} ${store.pincode ? '(' + this.escapeHtml(store.pincode) + ')' : ''}</div>
                            <div style="margin-top: 2px; color: #4f46e5; font-family: monospace;"><strong>Coverage Radius:</strong> ${store.radius} km</div>
                        </div>

                        <div style="margin-top: 12px; display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                            <a href="${store.url}" style="flex: 1; text-align: center; font-size: 11px; font-weight: 700; padding: 6px 10px; border-radius: 8px; background: #4f46e5; color: #ffffff; text-decoration: none;">
                                Store Console &rarr;
                            </a>
                            <a href="${store.storefront_url}" target="_blank" style="font-size: 11px; font-weight: 600; padding: 6px 10px; border-radius: 8px; background: #f1f5f9; color: #334155; text-decoration: none;">
                                Storefront ↗
                            </a>
                        </div>
                    </div>
                `;

                marker.bindPopup(popupHtml);

                marker.on('click', () => {
                    this.selectedStore = store;
                });

                // Radial Coverage Circle
                const circle = L.circle([store.lat, store.lng], {
                    color: store.is_quick ? '#f59e0b' : '#6366f1',
                    fillColor: store.is_quick ? '#fef3c7' : '#e0e7ff',
                    fillOpacity: 0.18,
                    weight: 1.5,
                    radius: store.radius * 1000
                });

                this.markers[store.id] = marker;
                this.circles[store.id] = circle;

                this.markersLayer.addLayer(marker);
                if (this.showCircles) {
                    this.circlesLayer.addLayer(circle);
                }
            });
        },

        syncMarkers() {
            if (!this.markersLayer) return;

            const visibleIds = new Set(this.filteredStores.map(s => s.id));

            this.stores.forEach(store => {
                const marker = this.markers[store.id];
                const circle = this.circles[store.id];

                if (visibleIds.has(store.id)) {
                    if (!this.markersLayer.hasLayer(marker)) {
                        this.markersLayer.addLayer(marker);
                    }
                    if (this.showCircles && circle && !this.circlesLayer.hasLayer(circle)) {
                        this.circlesLayer.addLayer(circle);
                    }
                } else {
                    if (this.markersLayer.hasLayer(marker)) {
                        this.markersLayer.removeLayer(marker);
                    }
                    if (circle && this.circlesLayer.hasLayer(circle)) {
                        this.circlesLayer.removeLayer(circle);
                    }
                }
            });
        },

        toggleCircles() {
            if (!this.circlesLayer) return;

            if (this.showCircles) {
                const visibleIds = new Set(this.filteredStores.map(s => s.id));
                this.stores.forEach(store => {
                    if (visibleIds.has(store.id) && this.circles[store.id]) {
                        this.circlesLayer.addLayer(this.circles[store.id]);
                    }
                });
            } else {
                this.circlesLayer.clearLayers();
            }
        },

        focusStore(store) {
            this.selectedStore = store;
            this.map.flyTo([store.lat, store.lng], 14, {
                duration: 1.2
            });

            if (this.markers[store.id]) {
                this.markers[store.id].openPopup();
            }
        },

        fitAllMarkers() {
            if (!this.map || this.stores.length === 0) return;

            const bounds = L.latLngBounds(this.stores.map(s => [s.lat, s.lng]));
            this.map.fitBounds(bounds, {
                padding: [50, 50],
                maxZoom: 14
            });
        },

        escapeHtml(str) {
            if (!str) return '';
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };
}
</script>
@endsection
