@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Store: ' . $store->name])

@section('content')
<div class="space-y-6" x-data="{ suspendModalOpen: false, radiusModalOpen: false }">

    <!-- Top Breadcrumb & Page Header -->
    <x-page-header 
        :title="$store->name" 
        :subtitle="'Merchant Storefront: /store/' . $store->slug . ' • Managed by ' . ($store->seller?->business_name ?? 'N/A')"
        :breadcrumbs="['Command Central' => route('admin.dashboard'), 'Stores Directory' => route('admin.stores.index'), $store->name => '#']"
    >
        <x-slot name="badge">
            <div class="flex items-center space-x-2">
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase {{ $store->status->value === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400' }}">
                    {{ $store->status->label() }}
                </span>
                @if($store->accepts_quick_commerce)
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-400 text-slate-950 shadow-xs">
                        ⚡ 10-30m Quick
                    </span>
                @endif
            </div>
        </x-slot>

        <x-slot name="actions">
            <div class="flex flex-wrap items-center gap-2">
                <a 
                    href="{{ route('store.show', $store->slug) }}" 
                    target="_blank" 
                    class="px-3.5 py-2 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold flex items-center space-x-1.5 shadow-xs transition"
                >
                    <span>Live Storefront</span>
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>

                <!-- Quick Commerce Capability Toggle -->
                <form method="POST" action="{{ route('admin.stores.toggle_quick', $store) }}" class="inline">
                    @csrf
                    <button 
                        type="submit" 
                        class="px-3.5 py-2 text-xs font-bold rounded-xl {{ $store->accepts_quick_commerce ? 'bg-amber-100 text-amber-800 hover:bg-amber-200 dark:bg-amber-950/60 dark:text-amber-300' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' }} transition flex items-center space-x-1"
                        title="{{ $store->accepts_quick_commerce ? 'Disable Quick Commerce' : 'Enable Quick Commerce' }}"
                    >
                        <span>{{ $store->accepts_quick_commerce ? '⚡ Disable Quick' : '⚡ Enable Quick' }}</span>
                    </button>
                </form>

                <!-- Update Radius Modal Trigger -->
                <button 
                    type="button" 
                    @click="$dispatch('open-modal', 'update-radius-modal')"
                    class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 border border-indigo-200 dark:border-indigo-800 transition"
                >
                    Adjust Radius ({{ $store->effectiveDeliveryRadius() }} km)
                </button>

                @if($store->status->value === 'suspended')
                    <form method="POST" action="{{ route('admin.stores.approve', $store) }}" class="inline">
                        @csrf
                        <button 
                            type="submit" 
                            onclick="return confirm('Reactivate this store? Public listings will be restored.')"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs transition"
                        >
                            Reactivate Store
                        </button>
                    </form>
                @else
                    <button 
                        type="button" 
                        @click="$dispatch('open-modal', 'suspend-store-modal')"
                        class="px-3.5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-xs transition"
                    >
                        Suspend Store
                    </button>
                @endif
            </div>
        </x-slot>
    </x-page-header>

    <!-- 3-Column Parameter Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- Store Overview -->
        <x-card title="Store Parameters" subtitle="Fulfillment rules and operational settings">
            <div class="text-xs space-y-2.5 text-slate-600 dark:text-slate-300">
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-400">Store Type:</span>
                    <strong>{{ $store->is_onestall_owned ? 'OneStall Direct Hub' : 'Third-Party Merchant' }}</strong>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-400">Quick Commerce:</span>
                    <span class="font-bold {{ $store->accepts_quick_commerce ? 'text-amber-500' : 'text-slate-400' }}">
                        {{ $store->accepts_quick_commerce ? 'Enabled (10-30 min)' : 'Disabled' }}
                    </span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-400">Customer Pickup:</span>
                    <span class="font-semibold {{ $store->accepts_pickup ? 'text-emerald-600' : 'text-slate-400' }}">
                        {{ $store->accepts_pickup ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-400">Delivery Radius:</span>
                    <strong class="font-mono text-indigo-600 dark:text-indigo-400">{{ $store->effectiveDeliveryRadius() }} km</strong>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-400">Buyer Rating:</span>
                    <span class="font-bold text-amber-500">★ {{ number_format((float)$store->rating_avg, 1) }} <span class="text-slate-400 font-normal">({{ $store->rating_count }} reviews)</span></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-400">Contact Phone:</span>
                    <span class="font-mono">{{ $store->phone ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-slate-400">Contact Email:</span>
                    <span>{{ $store->email ?? 'N/A' }}</span>
                </div>
            </div>
        </x-card>

        <!-- Geo Location -->
        <x-card title="Physical Location" subtitle="GPS dispatch coordinates and dispatch address">
            <div class="text-xs space-y-2.5 text-slate-600 dark:text-slate-300">
                @if($store->location)
                    <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-400">Address:</span>
                        <span class="font-medium text-right max-w-xs">{{ $store->location->address }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-400">City / State:</span>
                        <strong>{{ $store->location->city }}, {{ $store->location->state }}</strong>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-400">Pincode:</span>
                        <span class="font-mono font-bold">{{ $store->location->pincode }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-400">GPS Lat / Lng:</span>
                        <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ $store->location->lat }}, {{ $store->location->lng }}</span>
                    </div>
                    <div class="pt-2">
                        <a 
                            href="{{ route('admin.stores.map') }}" 
                            class="inline-flex items-center space-x-1 text-indigo-600 dark:text-indigo-400 hover:underline font-bold text-xs"
                        >
                            <span>Inspect on Coverage Map &rarr;</span>
                        </a>
                    </div>
                @else
                    <div class="text-slate-400 py-6 text-center">
                        <p>No physical location geocoded.</p>
                    </div>
                @endif
            </div>
        </x-card>

        <!-- Merchant / Seller Relation -->
        <x-card title="Merchant Details" subtitle="Primary seller account and taxation credentials">
            <div class="text-xs space-y-2.5 text-slate-600 dark:text-slate-300">
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-400">Business Name:</span>
                    <strong>{{ $store->seller?->business_name ?? 'N/A' }}</strong>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-400">Legal Entity:</span>
                    <span>{{ $store->seller?->legal_name ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-400">Seller ID:</span>
                    <span class="font-mono font-bold">#{{ $store->seller_id }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-400">GSTIN:</span>
                    <span class="font-mono">{{ $store->seller?->gstin ?? 'Unregistered' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-400">PAN:</span>
                    <span class="font-mono">{{ $store->seller?->pan ?? 'N/A' }}</span>
                </div>
                <div class="pt-2">
                    @if($store->seller)
                        <a href="{{ route('admin.sellers.show', $store->seller_id) }}" class="inline-flex items-center space-x-1 text-indigo-600 dark:text-indigo-400 hover:underline font-bold text-xs">
                            <span>Manage Merchant Account &rarr;</span>
                        </a>
                    @endif
                </div>
            </div>
        </x-card>
    </div>

    <!-- Operating Timings Weekly Grid -->
    <x-card title="Operating Timings (Weekly Schedule)" subtitle="Open hours determining instant order acceptance and dispatch routing">
        <div class="grid grid-cols-2 sm:grid-cols-7 gap-3 text-xs">
            @php $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']; @endphp
            @foreach($days as $idx => $dayName)
                @php $timing = $store->timings->firstWhere('day_of_week', $idx); @endphp
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 text-center">
                    <div class="font-bold text-slate-900 dark:text-white">{{ $dayName }}</div>
                    @if($timing && $timing->is_closed)
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold text-rose-600 bg-rose-50 dark:bg-rose-950/60 mt-1.5 uppercase">Closed</span>
                    @elseif($timing && $timing->opens_at && $timing->closes_at)
                        <div class="text-[11px] text-slate-700 dark:text-slate-300 font-mono font-semibold mt-1.5">
                            {{ substr($timing->opens_at, 0, 5) }} - {{ substr($timing->closes_at, 0, 5) }}
                        </div>
                    @else
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold text-emerald-600 bg-emerald-50 dark:bg-emerald-950/60 mt-1.5">Open 24/7</span>
                    @endif
                </div>
            @endforeach
        </div>
    </x-card>

    <!-- Inventory Locations & Stock -->
    @if($store->inventoryLocations->isNotEmpty())
        <x-card title="Connected Stock Locations & Warehouses" subtitle="Inventory nodes mapped to this store outlet">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($store->inventoryLocations as $loc)
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 text-xs space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-900 dark:text-white">{{ $loc->name }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $loc->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                                {{ $loc->type->value ?? 'Location' }}
                            </span>
                        </div>
                        <div class="text-slate-500 font-mono text-[11px]">
                            GPS: {{ $loc->lat ?? 'N/A' }}, {{ $loc->lng ?? 'N/A' }}
                        </div>
                        <div class="flex items-center justify-between pt-1 border-t border-slate-200/60 dark:border-slate-700/60 text-[11px]">
                            <span>SKUs Mapped: <strong class="text-slate-800 dark:text-slate-200">{{ $loc->inventories->count() }}</strong></span>
                            <span>Total Units: <strong class="text-indigo-600 dark:text-indigo-400 font-mono">{{ $loc->inventories->sum('quantity_on_hand') }}</strong></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    <!-- Recent Fulfilment Orders Table -->
    <x-card title="Recent Fulfilment Orders" subtitle="Latest orders routed to this store outlet for packing and delivery">
        @if($recentOrders->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50/70 dark:bg-slate-950/40 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-200/80 dark:border-slate-800 font-semibold">
                        <tr>
                            <th class="py-3 px-4">Order #</th>
                            <th class="py-3 px-4">Customer</th>
                            <th class="py-3 px-4">Type</th>
                            <th class="py-3 px-4 text-right">Subtotal</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Placed At</th>
                            <th class="py-3 px-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @foreach($recentOrders as $fo)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-850/50 transition">
                                <td class="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white">
                                    #{{ $fo->order?->order_number ?? $fo->id }}
                                </td>
                                <td class="py-3 px-4 text-slate-700 dark:text-slate-300">
                                    {{ $fo->order?->customer?->user?->name ?? 'Guest Buyer' }}
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $fo->fulfilment_type->value === 'quick' ? 'bg-amber-100 text-amber-800' : 'bg-indigo-100 text-indigo-800' }}">
                                        {{ $fo->fulfilment_type->value === 'quick' ? '⚡ 10m Quick' : 'Standard' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 dark:text-white">
                                    ₹{{ number_format((float) $fo->subtotal, 2) }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ $fo->status->value }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right text-slate-400 font-mono text-[11px]">
                                    {{ $fo->created_at->format('M d, H:i') }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    @if($fo->order_id)
                                        <a href="{{ route('admin.orders.show', $fo->order_id) }}" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 transition">
                                            View Order &rarr;
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="py-8 text-center text-slate-400 text-xs">
                No orders have been routed to this store outlet yet.
            </div>
        @endif
    </x-card>

    <!-- Activity Log -->
    @if($activityLogs->isNotEmpty())
        <x-card title="Store Activity & Governance Audit" subtitle="Administrative actions performed on this store outlet">
            <div class="space-y-3">
                @foreach($activityLogs as $activity)
                    <div class="p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 text-xs flex items-center justify-between">
                        <div>
                            <span class="font-bold text-slate-900 dark:text-white">{{ $activity->description }}</span>
                            <div class="text-[11px] text-slate-400 mt-0.5">By: {{ $activity->causer?->name ?? 'System' }}</div>
                        </div>
                        <span class="text-slate-400 font-mono text-[11px]">{{ $activity->created_at->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    <!-- MODAL: Update Delivery Radius -->
    <x-modal name="update-radius-modal" title="Adjust Delivery Radius" size="sm">
        <form method="POST" action="{{ route('admin.stores.update_radius', $store) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Coverage Radius (Kilometers) <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <input 
                        type="number" 
                        step="0.5" 
                        min="0.5" 
                        max="100" 
                        name="delivery_radius_km" 
                        value="{{ old('delivery_radius_km', $store->delivery_radius_km ?? 8.0) }}"
                        required
                        class="w-full p-2.5 text-xs font-mono font-bold rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    >
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400 text-xs font-bold">
                        km
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mt-1">Defines maximum distance for radial delivery coverage on the geo map.</p>
            </div>

            <div class="flex items-center justify-end space-x-2 pt-2">
                <button type="button" @click="$dispatch('close-modal', 'update-radius-modal')" class="px-3 py-1.5 text-xs text-slate-600 hover:text-slate-900">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition">
                    Save Radius
                </button>
            </div>
        </form>
    </x-modal>

    <!-- MODAL: Suspend Store -->
    <x-modal name="suspend-store-modal" title="Suspend Store Outlet" size="md">
        <form method="POST" action="{{ route('admin.stores.suspend', $store) }}" class="space-y-4">
            @csrf
            <div>
                <p class="text-xs text-rose-600 dark:text-rose-400 mb-3 font-semibold">
                    Warning: Suspending this store will immediately hide it from nearby store discovery and prevent new order routings.
                </p>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Reason for Suspension
                </label>
                <textarea 
                    name="reason" 
                    rows="3" 
                    placeholder="e.g. Health & Safety audit pending, regulatory compliance, merchant request..."
                    class="w-full p-2.5 text-xs rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                ></textarea>
            </div>

            <div class="flex items-center justify-end space-x-2 pt-2">
                <button type="button" @click="$dispatch('close-modal', 'suspend-store-modal')" class="px-3 py-1.5 text-xs text-slate-600 hover:text-slate-900">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-rose-600 hover:bg-rose-700 text-white shadow-xs transition">
                    Confirm Suspension
                </button>
            </div>
        </form>
    </x-modal>

</div>
@endsection
