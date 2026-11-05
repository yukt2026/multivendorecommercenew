@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Store Outlets & Locations'])

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <x-page-header 
        title="Store Outlets & Fulfillment Hubs" 
        subtitle="Manage multiple physical stores, dark stores, geo-coordinates, and operating hours."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Stores' => route('seller.stores.index')]"
    >
        <x-slot name="actions">
            <x-button variant="primary" size="sm" href="{{ route('seller.stores.create') }}" icon="plus">
                Add Physical Outlet
            </x-button>
        </x-slot>
    </x-page-header>

    <!-- Metrics Strip -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <x-stat-card 
            label="Total Store Outlets" 
            :value="$totalStores" 
            icon="building-storefront" 
            color="indigo"
            helpText="Physical premises & dark stores"
        />
        <x-stat-card 
            label="Active & Open Now" 
            :value="$activeCount" 
            icon="check-circle" 
            color="emerald"
            helpText="Receiving quick commerce orders"
        />
        <x-stat-card 
            label="Quick Commerce Hubs" 
            :value="$quickCount" 
            icon="bolt" 
            color="amber"
            helpText="10-30m instant delivery enabled"
        />
    </div>

    <!-- Stores Table -->
    <x-card :padded="false">
        <div class="p-4 sm:p-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">All Physical Premises & Fulfillment Hubs</h3>
            <span class="text-xs text-slate-500 font-mono">{{ $stores->count() }} registered</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="py-3 px-4">Store / Outlet</th>
                        <th class="py-3 px-4">Physical Location</th>
                        <th class="py-3 px-4">Coverage Radius</th>
                        <th class="py-3 px-4">Operating Hours</th>
                        <th class="py-3 px-4">Fulfillment Mode</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse($stores as $st)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition {{ $activeStore && $activeStore->id === $st->id ? 'bg-indigo-50/30 dark:bg-indigo-950/20' : '' }}">
                            <td class="py-3 px-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center font-bold text-slate-700 dark:text-slate-300">
                                        {{ substr($st->name, 0, 2) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900 dark:text-white flex items-center space-x-1.5">
                                            <span>{{ $st->name }}</span>
                                            @if($activeStore && $activeStore->id === $st->id)
                                                <span class="px-1.5 py-0.2 text-[9px] bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-400 font-bold rounded">Active Context</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-slate-400 font-mono">/store/{{ $st->slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-slate-800 dark:text-slate-200">{{ $st->location?->address ?? 'No physical address' }}</div>
                                <div class="text-slate-500 text-[11px]">{{ $st->location?->city }}, {{ $st->location?->state }} - {{ $st->location?->pincode }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-bold text-slate-900 dark:text-white">{{ $st->delivery_radius_km ?? 8 }} km</span>
                                <span class="text-[10px] text-slate-500 block">radial zone</span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-slate-700 dark:text-slate-300">{{ $st->timings->first()?->opens_at ? substr($st->timings->first()->opens_at, 0, 5) . ' - ' . substr($st->timings->first()->closes_at, 0, 5) : '09:00 - 22:00' }}</span>
                                <span class="text-[10px] {{ $st->isOpenNow() ? 'text-emerald-500 font-bold' : 'text-slate-400' }} block">
                                    {{ $st->isOpenNow() ? '● Open Now' : '○ Closed Now' }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center space-x-1.5">
                                    @if($st->accepts_quick_commerce)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">⚡ 10-30m Quick</span>
                                    @endif
                                    @if($st->accepts_pickup)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">Pickup</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <x-badge :variant="$st->status->value === 'active' ? 'success' : 'danger'" size="sm" dot>
                                    {{ $st->status->label() }}
                                </x-badge>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    <form method="POST" action="{{ route('seller.switch_store') }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="store_id" value="{{ $st->id }}">
                                        <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                                            Switch To
                                        </button>
                                    </form>
                                    <a href="{{ route('seller.stores.reports', ['store_id' => $st->id]) }}" class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition" title="Store Reports">
                                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500">
                                No store outlets found. Add your first physical store outlet!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
@endsection
