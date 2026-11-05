@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Store: ' . $store->name])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        :title="$store->name" 
        :subtitle="'Merchant Storefront: /store/' . $store->slug . ' • Managed by ' . ($store->seller?->business_name ?? 'N/A')"
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Stores' => route('admin.stores.index'), $store->name => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase {{ $store->status->value === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400' }}">
                {{ $store->status->label() }}
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <a href="{{ route('stores.index') }}" target="_blank" class="px-3.5 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1 transition">
                    <span>Live Stores Directory</span>
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>

                @if($store->status->value === 'suspended')
                    <form method="POST" action="{{ route('admin.stores.approve', $store) }}" class="inline">
                        @csrf
                        <button type="submit" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                            Reactivate Store
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.stores.suspend', $store) }}" class="inline" onsubmit="return confirm('Suspend this store?');">
                        @csrf
                        <button type="submit" class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                            Suspend Store
                        </button>
                    </form>
                @endif
            </div>
        </x-slot>
    </x-page-header>

    <!-- Details Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- Store Overview -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white">Store Parameters</h3>
            <div class="text-xs space-y-2 text-slate-600 dark:text-slate-300">
                <div><span class="text-slate-400 w-32 inline-block">Store Type:</span> <strong>{{ $store->is_onestall_owned ? 'OneStall Direct Hub' : 'Third-Party Merchant' }}</strong></div>
                <div><span class="text-slate-400 w-32 inline-block">Quick Commerce:</span> <span class="font-bold {{ $store->accepts_quick_commerce ? 'text-amber-500' : 'text-slate-400' }}">{{ $store->accepts_quick_commerce ? 'Enabled (10-30 min)' : 'Disabled' }}</span></div>
                <div><span class="text-slate-400 w-32 inline-block">Customer Pickup:</span> <span>{{ $store->accepts_pickup ? 'Enabled' : 'Disabled' }}</span></div>
                <div><span class="text-slate-400 w-32 inline-block">Delivery Radius:</span> <strong>{{ $store->effectiveDeliveryRadius() }} km</strong></div>
                <div><span class="text-slate-400 w-32 inline-block">Rating:</span> <span>⭐ {{ number_format((float)$store->rating_avg, 1) }} ({{ $store->rating_count }} reviews)</span></div>
                <div><span class="text-slate-400 w-32 inline-block">Contact Phone:</span> <span>{{ $store->phone ?? 'N/A' }}</span></div>
                <div><span class="text-slate-400 w-32 inline-block">Contact Email:</span> <span>{{ $store->email ?? 'N/A' }}</span></div>
            </div>
        </div>

        <!-- Geo Location -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white">Physical Location</h3>
            <div class="text-xs space-y-2 text-slate-600 dark:text-slate-300">
                @if($store->location)
                    <div><span class="text-slate-400 w-24 inline-block">Address:</span> {{ $store->location->address }}</div>
                    <div><span class="text-slate-400 w-24 inline-block">City/State:</span> {{ $store->location->city }}, {{ $store->location->state }}</div>
                    <div><span class="text-slate-400 w-24 inline-block">Pincode:</span> <span class="font-mono">{{ $store->location->pincode }}</span></div>
                    <div><span class="text-slate-400 w-24 inline-block">Coordinates:</span> <span class="font-mono text-indigo-600 dark:text-indigo-400">{{ $store->location->lat }}, {{ $store->location->lng }}</span></div>
                @else
                    <div class="text-slate-400">No physical location geocoded.</div>
                @endif
            </div>
        </div>

        <!-- Merchant / Seller Relation -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white">Merchant Details</h3>
            <div class="text-xs space-y-2 text-slate-600 dark:text-slate-300">
                <div><span class="text-slate-400 w-24 inline-block">Business:</span> <strong>{{ $store->seller?->business_name }}</strong></div>
                <div><span class="text-slate-400 w-24 inline-block">Legal Name:</span> {{ $store->seller?->legal_name }}</div>
                <div><span class="text-slate-400 w-24 inline-block">Seller ID:</span> #{{ $store->seller_id }}</div>
                <div><span class="text-slate-400 w-24 inline-block">GSTIN:</span> <span class="font-mono">{{ $store->seller?->gstin ?? 'Unregistered' }}</span></div>
                <div class="pt-2">
                    <a href="{{ route('admin.sellers.show', $store->seller_id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">
                        View Seller Profile &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Operating Timings -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white mb-4">Operating Timings (Weekly)</h3>
        <div class="grid grid-cols-2 sm:grid-cols-7 gap-3 text-xs">
            @php $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']; @endphp
            @foreach($days as $idx => $dayName)
                @php $timing = $store->timings->firstWhere('day_of_week', $idx); @endphp
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 text-center">
                    <div class="font-bold text-slate-900 dark:text-white">{{ $dayName }}</div>
                    @if($timing && $timing->is_closed)
                        <div class="text-[10px] text-rose-500 font-bold mt-1 uppercase">Closed</div>
                    @elseif($timing)
                        <div class="text-[10px] text-slate-500 font-mono mt-1">{{ substr($timing->opens_at, 0, 5) }} - {{ substr($timing->closes_at, 0, 5) }}</div>
                    @else
                        <div class="text-[10px] text-emerald-500 font-semibold mt-1">Open 24/7</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

</div>
@endsection
