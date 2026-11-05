@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Stores Geo Map'])

@section('content')
<div class="space-y-6" x-data="storeMapViewer()">

    <!-- Page Header -->
    <x-page-header 
        title="Interactive Store Map &amp; Geo-Coverage" 
        subtitle="Visual geospatial overview of active retail stores, dark store pick-up hubs, and delivery radius circles."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Stores' => route('admin.stores.index'), 'Map View' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                {{ count($mapData) }} Geocoded Stores
            </span>
        </x-slot>

        <x-slot name="actions">
            <a href="{{ route('admin.stores.index') }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                <span>&larr; Back to Directory List</span>
            </a>
        </x-slot>
    </x-page-header>

    <!-- Map & Store Sidebar Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

        <!-- Left: Filter & Store List (Span 1) -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm h-[600px] flex flex-col">
            <div class="pb-3 border-b border-slate-100 dark:border-slate-800 mb-3">
                <input 
                    type="text" 
                    x-model="searchTerm" 
                    placeholder="Filter store or city..." 
                    class="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500"
                >
            </div>

            <div class="flex-1 overflow-y-auto space-y-2 pr-1">
                <template x-for="store in filteredStores" :key="store.id">
                    <div 
                        @click="focusStore(store)" 
                        class="p-3 rounded-xl border transition cursor-pointer"
                        :class="selectedStore?.id === store.id ? 'bg-indigo-50 dark:bg-indigo-950/50 border-indigo-500' : 'bg-slate-50 dark:bg-slate-800/60 border-slate-200 dark:border-slate-700/60 hover:border-slate-300'"
                    >
                        <div class="flex items-center justify-between font-bold text-xs text-slate-900 dark:text-white">
                            <span x-text="store.name"></span>
                            <span x-show="store.is_quick" class="text-[9px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-mono font-bold">10m</span>
                        </div>
                        <div class="text-[11px] text-slate-500 mt-1" x-text="store.seller + ' • ' + store.city"></div>
                        <div class="text-[10px] text-indigo-600 dark:text-indigo-400 mt-1 font-mono" x-text="'Radius: ' + store.radius + ' km'"></div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Right: Leaflet Interactive Map Container (Span 3) -->
        <div class="lg:col-span-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm h-[600px] relative">
            <div id="adminStoresMap" class="w-full h-full z-10"></div>
        </div>
    </div>

</div>

<!-- Leaflet CDN -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
function storeMapViewer() {
    return {
        stores: @json($mapData),
        searchTerm: '',
        selectedStore: null,
        map: null,
        markers: {},
        circles: {},

        get filteredStores() {
            if (!this.searchTerm) return this.stores;
            const term = this.searchTerm.toLowerCase();
            return this.stores.filter(s => 
                s.name.toLowerCase().includes(term) || 
                s.seller.toLowerCase().includes(term) || 
                s.city.toLowerCase().includes(term)
            );
        },

        init() {
            this.$nextTick(() => {
                this.initMap();
            });
        },

        initMap() {
            const defaultLat = this.stores.length > 0 ? this.stores[0].lat : 12.9716;
            const defaultLng = this.stores.length > 0 ? this.stores[0].lng : 77.5946;

            this.map = L.map('adminStoresMap').setView([defaultLat, defaultLng], 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(this.map);

            this.stores.forEach(store => {
                const marker = L.marker([store.lat, store.lng]).addTo(this.map);
                marker.bindPopup(`
                    <div style="font-size: 12px; font-family: sans-serif;">
                        <strong>${store.name}</strong><br>
                        <span style="color: #64748b;">${store.seller}</span><br>
                        Radius: <strong>${store.radius} km</strong><br>
                        <a href="${store.url}" style="color: #4f46e5; text-decoration: underline; font-weight: bold; margin-top: 4px; display: inline-block;">Open Store Console &rarr;</a>
                    </div>
                `);

                const circle = L.circle([store.lat, store.lng], {
                    color: store.is_quick ? '#f59e0b' : '#6366f1',
                    fillColor: store.is_quick ? '#fef3c7' : '#e0e7ff',
                    fillOpacity: 0.15,
                    radius: store.radius * 1000
                }).addTo(this.map);

                this.markers[store.id] = marker;
                this.circles[store.id] = circle;
            });
        },

        focusStore(store) {
            this.selectedStore = store;
            this.map.flyTo([store.lat, store.lng], 14, { duration: 1.2 });
            if (this.markers[store.id]) {
                this.markers[store.id].openPopup();
            }
        }
    };
}
</script>
@endsection
