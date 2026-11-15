@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Delivery Partners Fleet Management'])

@section('content')
<div class="space-y-6" x-data="deliveryPartnerManager()">

    <!-- Page Header & Action Bar -->
    <x-page-header 
        title="Delivery Partners Fleet" 
        subtitle="Manage hyperlocal quick-commerce riders, realtime GPS coordinates, duty availability, and vehicle fleet."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Delivery Fleet' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                ⚡ {{ number_format($metrics['total_riders']) }} Fleet Riders
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <a 
                    href="{{ route('admin.quick.index') }}" 
                    class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl transition flex items-center space-x-1"
                >
                    <span>&larr; Mission Control</span>
                </a>

                <a 
                    href="{{ route('admin.delivery_partners.export', request()->query()) }}" 
                    class="px-3.5 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:text-indigo-600 text-xs font-bold transition shadow-xs flex items-center space-x-1.5"
                    title="Export fleet to CSV"
                >
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Export CSV</span>
                </a>

                <button 
                    type="button" 
                    @click="openCreateModal()" 
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition shadow-xs flex items-center space-x-1.5 cursor-pointer"
                >
                    <span>+</span>
                    <span>Register Delivery Rider</span>
                </button>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Metrics Cards Strip -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-5 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Fleet</div>
            <div class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">
                {{ number_format($metrics['total_riders']) }}
            </div>
            <div class="text-[10px] text-slate-400">Registered delivery partners</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-5 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Available &amp; Online</div>
            <div class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400 flex items-center space-x-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>{{ number_format($metrics['available']) }}</span>
            </div>
            <div class="text-[10px] text-slate-400">Ready for instant dispatch</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-5 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Busy / On Delivery</div>
            <div class="text-xl sm:text-2xl font-black text-amber-500 flex items-center space-x-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                <span>{{ number_format($metrics['busy']) }}</span>
            </div>
            <div class="text-[10px] text-slate-400">Active package en route</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-5 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Offline / Off-Duty</div>
            <div class="text-xl sm:text-2xl font-black text-slate-500">
                {{ number_format($metrics['offline']) }}
            </div>
            <div class="text-[10px] text-slate-400">Not accepting orders</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-5 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Deliveries</div>
            <div class="text-xl sm:text-2xl font-black text-indigo-600 dark:text-indigo-400">
                {{ number_format($metrics['completed_deliveries']) }}
            </div>
            <div class="text-[10px] text-slate-400">Completed order drop-offs</div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-xs">
        <form method="GET" action="{{ route('admin.delivery_partners.index') }}" class="flex flex-wrap items-center gap-2.5">
            <!-- Search -->
            <div class="relative flex-1 min-w-[200px]">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ $search }}" 
                    placeholder="Search rider name, phone, vehicle reg..." 
                    class="w-full text-xs font-medium pl-9 pr-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <!-- Status Filter -->
            <select name="status" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                <option value="all">All Duty Statuses</option>
                <option value="available" {{ $status === 'available' ? 'selected' : '' }}>🟢 Available &amp; Online</option>
                <option value="busy" {{ $status === 'busy' ? 'selected' : '' }}>🟡 Busy / On Job</option>
                <option value="offline" {{ $status === 'offline' ? 'selected' : '' }}>⚪ Offline / Off-Duty</option>
            </select>

            <!-- Vehicle Type Filter -->
            <select name="vehicle" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                <option value="all">All Vehicle Types</option>
                <option value="Electric Scooter" {{ $vehicle === 'Electric Scooter' ? 'selected' : '' }}>⚡ Electric Scooter</option>
                <option value="Motorcycle" {{ $vehicle === 'Motorcycle' ? 'selected' : '' }}>🏍️ Motorcycle</option>
                <option value="Bicycle" {{ $vehicle === 'Bicycle' ? 'selected' : '' }}>🚲 Bicycle</option>
                <option value="Van" {{ $vehicle === 'Van' ? 'selected' : '' }}>🚐 Van</option>
            </select>

            <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shrink-0">
                Filter
            </button>

            @if($search || $status !== 'all' || $vehicle !== 'all')
                <a href="{{ route('admin.delivery_partners.index') }}" class="text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 self-center">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Fleet Table Card -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
        @if($riders->isEmpty())
            <div class="p-12 text-center space-y-3">
                <div class="w-16 h-16 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center text-3xl">
                    🚴
                </div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">No delivery partners found</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">Try clearing your filters or register a new delivery rider.</p>
                <button 
                    type="button" 
                    @click="openCreateModal()" 
                    class="inline-flex items-center space-x-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-xs"
                >
                    <span>+ Register First Rider</span>
                </button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-[11px] font-bold uppercase text-slate-400 tracking-wider">
                        <tr>
                            <th class="p-4">Rider Profile</th>
                            <th class="p-4">Phone / Contact</th>
                            <th class="p-4">Vehicle Fleet</th>
                            <th class="p-4">Duty Status</th>
                            <th class="p-4">Live GPS Position</th>
                            <th class="p-4 text-center">Active Jobs</th>
                            <th class="p-4 text-center">Deliveries</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        @foreach($riders as $rider)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                                <td class="p-4">
                                    <div class="font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                                        <div class="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-400 font-black text-xs flex items-center justify-center shrink-0">
                                            {{ strtoupper(substr($rider->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <span>{{ $rider->name }}</span>
                                            <div class="text-[10px] text-slate-400 font-mono">ID: #{{ $rider->id }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="p-4">
                                    <div class="font-mono font-bold text-slate-800 dark:text-slate-200">
                                        <a href="tel:{{ $rider->phone }}" class="hover:text-indigo-600 transition">{{ $rider->phone }}</a>
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-sans">SMS / OTP Verified</span>
                                </td>

                                <td class="p-4">
                                    <div class="font-bold text-slate-800 dark:text-slate-200">
                                        {{ $rider->vehicle }}
                                    </div>
                                    <div class="text-[10px] font-mono text-slate-400">
                                        {{ $rider->vehicle_number ?: 'No Plate Tagged' }}
                                    </div>
                                </td>

                                <td class="p-4">
                                    <form method="POST" action="{{ route('admin.delivery_partners.toggle', $rider) }}" class="inline">
                                        @csrf
                                        <button 
                                            type="submit" 
                                            class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold border transition hover:opacity-80
                                                {{ $rider->status === 'available' ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800' :
                                                  ($rider->status === 'busy' ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-400 dark:border-amber-800' :
                                                   'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700') }}"
                                            title="Click to toggle Online/Offline"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full mr-1.5 
                                                {{ $rider->status === 'available' ? 'bg-emerald-500' : ($rider->status === 'busy' ? 'bg-amber-500' : 'bg-slate-400') }}"></span>
                                            <span class="capitalize">{{ $rider->status }}</span>
                                        </button>
                                    </form>
                                </td>

                                <td class="p-4 font-mono text-[11px] text-slate-600 dark:text-slate-300">
                                    @if($rider->current_lat && $rider->current_lng)
                                        <a 
                                            href="https://www.google.com/maps?q={{ $rider->current_lat }},{{ $rider->current_lng }}" 
                                            target="_blank" 
                                            class="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center space-x-1"
                                            title="View GPS position on Google Maps"
                                        >
                                            <span>{{ number_format($rider->current_lat, 4) }}, {{ number_format($rider->current_lng, 4) }}</span>
                                            <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                        @if($rider->last_location_updated_at)
                                            <div class="text-[9px] text-slate-400 font-sans mt-0.5">Updated {{ $rider->last_location_updated_at->diffForHumans() }}</div>
                                        @endif
                                    @else
                                        <span class="text-slate-400">GPS Offline</span>
                                    @endif
                                </td>

                                <td class="p-4 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $rider->active_orders_count > 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">
                                        {{ $rider->active_orders_count }} job(s)
                                    </span>
                                </td>

                                <td class="p-4 text-center font-bold text-slate-900 dark:text-white">
                                    {{ $rider->assignments_count }}
                                </td>

                                <td class="p-4 text-right">
                                    <div class="inline-flex items-center space-x-1">
                                        <button 
                                            type="button" 
                                            @click="openEditModal(@js($rider))" 
                                            class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition"
                                            title="Edit Rider Profile"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>

                                        <form method="POST" action="{{ route('admin.delivery_partners.destroy', $rider) }}" class="inline" onsubmit="return confirm('Remove rider {{ $rider->name }} from delivery fleet?');">
                                            @csrf
                                            @method('DELETE')
                                            <button 
                                                type="submit" 
                                                class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition" 
                                                title="Remove Rider"
                                                @if($rider->active_orders_count > 0) disabled title="Cannot remove rider with active orders" @endif
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $riders->links() }}
            </div>
        @endif
    </div>

    <!-- Create / Edit Rider Modal -->
    <x-modal name="rider-modal" size="lg">
        <x-slot name="header">
            <div class="flex items-center space-x-2">
                <span class="text-indigo-600 dark:text-indigo-400 text-lg">🚴</span>
                <h3 class="text-base font-bold text-slate-900 dark:text-white" x-text="modalMode === 'create' ? 'Register Delivery Partner' : 'Edit Delivery Partner Details'"></h3>
            </div>
        </x-slot>

        <form id="riderForm" :action="modalAction" method="POST" class="space-y-4">
            @csrf
            <template x-if="modalMode === 'edit'">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <!-- Row 1: Name & Phone -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Full Name <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="name" 
                        x-model="form.name" 
                        required 
                        placeholder="e.g. Rahul Sharma"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Phone Number <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="phone" 
                        x-model="form.phone" 
                        required 
                        placeholder="+919876543210"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
            </div>

            <!-- Row 2: Vehicle Type & Plate Number -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Vehicle Fleet Type <span class="text-rose-500">*</span>
                    </label>
                    <select 
                        name="vehicle" 
                        x-model="form.vehicle" 
                        required 
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    >
                        <option value="Electric Scooter">⚡ Electric Scooter (EV)</option>
                        <option value="Motorcycle">🏍️ Motorcycle / Bike</option>
                        <option value="Bicycle">🚲 Bicycle</option>
                        <option value="Van">🚐 Delivery Van / Cargo</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Vehicle Reg Number (Plate)
                    </label>
                    <input 
                        type="text" 
                        name="vehicle_number" 
                        x-model="form.vehicle_number" 
                        placeholder="e.g. KA-01-EQ-1234"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono uppercase"
                    />
                </div>
            </div>

            <!-- Row 3: Duty Status & GPS Coordinates -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Duty Status
                    </label>
                    <select 
                        name="status" 
                        x-model="form.status" 
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    >
                        <option value="available">🟢 Available &amp; Online</option>
                        <option value="busy">🟡 Busy / On Job</option>
                        <option value="offline">⚪ Offline / Off-Duty</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Initial Latitude
                    </label>
                    <input 
                        type="number" 
                        step="0.000001" 
                        name="current_lat" 
                        x-model="form.current_lat" 
                        placeholder="12.9716"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Initial Longitude
                    </label>
                    <input 
                        type="number" 
                        step="0.000001" 
                        name="current_lng" 
                        x-model="form.current_lng" 
                        placeholder="77.5946"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
                    />
                </div>
            </div>

            <!-- Active Checkbox -->
            <div class="flex items-center space-x-2.5 pt-1">
                <input 
                    type="checkbox" 
                    id="rider_active_box" 
                    name="is_active" 
                    value="1" 
                    x-model="form.is_active" 
                    class="rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                >
                <label for="rider_active_box" class="text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                    Rider account is verified &amp; active in quick commerce dispatcher pool
                </label>
            </div>
        </form>

        <x-slot name="footer">
            <button 
                type="button" 
                @click="$dispatch('close-modal', 'rider-modal')" 
                class="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 transition"
            >
                Cancel
            </button>
            <button 
                form="riderForm"
                type="submit" 
                class="px-5 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition flex items-center space-x-1.5 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span x-text="modalMode === 'create' ? 'Register Rider' : 'Save Changes'"></span>
            </button>
        </x-slot>
    </x-modal>

</div>

<script>
    function deliveryPartnerManager() {
        return {
            modalMode: 'create',
            modalAction: '{{ route("admin.delivery_partners.store") }}',
            form: {
                id: null,
                name: '',
                phone: '',
                vehicle: 'Electric Scooter',
                vehicle_number: '',
                status: 'available',
                current_lat: 12.9716,
                current_lng: 77.5946,
                is_active: true
            },

            openCreateModal() {
                this.modalMode = 'create';
                this.modalAction = '{{ route("admin.delivery_partners.store") }}';
                this.form = {
                    id: null,
                    name: '',
                    phone: '',
                    vehicle: 'Electric Scooter',
                    vehicle_number: '',
                    status: 'available',
                    current_lat: 12.9716,
                    current_lng: 77.5946,
                    is_active: true
                };
                this.$dispatch('open-modal', 'rider-modal');
            },

            openEditModal(rider) {
                this.modalMode = 'edit';
                this.modalAction = '{{ url("/admin/delivery-partners") }}/' + rider.id;
                this.form = {
                    id: rider.id,
                    name: rider.name,
                    phone: rider.phone,
                    vehicle: rider.vehicle || 'Electric Scooter',
                    vehicle_number: rider.vehicle_number || '',
                    status: rider.status || 'available',
                    current_lat: rider.current_lat || 12.9716,
                    current_lng: rider.current_lng || 77.5946,
                    is_active: !!rider.is_active
                };
                this.$dispatch('open-modal', 'rider-modal');
            }
        };
    }
</script>
@endsection
