@extends('layouts.admin')

@section('title', 'Quick Commerce Operations & Mission Control')

@section('content')
<div class="space-y-6" x-data="{
    pollData: null,
    fetchLiveFeed() {
        fetch('{{ route('admin.quick.api_feed') }}')
            .then(res => res.json())
            .then(d => { this.pollData = d.data; })
            .catch(e => console.error(e));
    },
    init() {
        setInterval(() => this.fetchLiveFeed(), 6000);
    }
}">
    <!-- Mission Control Header -->
    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 text-white rounded-3xl p-6 sm:p-8 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="inline-flex items-center gap-2 bg-indigo-500/20 text-indigo-300 px-3 py-1 rounded-full text-xs font-bold mb-2 border border-indigo-500/30">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span>Hyperlocal Ops & SLA Telemetry</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                    Quick Commerce Mission Control
                </h1>
                <p class="text-xs text-slate-400 mt-1">
                    Realtime store load balancing, SLA breach alerts, and hyperlocal dispatch monitoring.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.delivery_partners.index') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition shadow">
                    🛵 Manage Rider Fleet
                </a>
                <button @click="fetchLiveFeed()" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl transition border border-slate-700">
                    ↻ Refresh
                </button>
            </div>
        </div>

        <!-- Realtime KPIs -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-6 border-t border-slate-800">
            <div>
                <span class="text-xs text-slate-400 block">Active Quick Orders</span>
                <span class="text-2xl font-black text-amber-400">
                    {{ $ops['active_orders_count'] }}
                </span>
            </div>
            <div>
                <span class="text-xs text-slate-400 block">SLA Breaches</span>
                <span class="text-2xl font-black {{ $ops['breached_count'] > 0 ? 'text-rose-400 animate-pulse' : 'text-emerald-400' }}">
                    {{ $ops['breached_count'] }}
                </span>
            </div>
            <div>
                <span class="text-xs text-slate-400 block">Delivered Today</span>
                <span class="text-2xl font-black text-emerald-400">
                    {{ $ops['today_delivered'] }}
                </span>
            </div>
            <div>
                <span class="text-xs text-slate-400 block">Riders (Avail / Total)</span>
                <span class="text-2xl font-black text-white">
                    {{ $ops['riders_summary']['available'] }} / {{ $ops['riders_summary']['total'] }}
                </span>
            </div>
        </div>
    </div>

    <!-- SLA Breach Warning Alert -->
    @if($ops['breached_count'] > 0)
        <div class="bg-rose-50 border-2 border-rose-300 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center gap-3 text-rose-800 mb-2">
                <span class="w-3 h-3 rounded-full bg-rose-600 animate-ping"></span>
                <h3 class="font-extrabold text-sm uppercase tracking-wider">Urgent SLA Breaches ({{ $ops['breached_count'] }})</h3>
            </div>
            <p class="text-xs text-rose-700 mb-3">
                The following packages have exceeded store acceptance timeouts or estimated delivery windows. Store reassignment or dispatch escalation recommended.
            </p>
            <div class="divide-y divide-rose-200 text-xs">
                @foreach($ops['breached_orders'] as $breached)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <span class="font-mono font-bold text-slate-900">#{{ $breached->fulfilment_order_number }}</span>
                            <span class="text-slate-500 ml-2">Store: {{ $breached->store?->name ?? 'N/A' }}</span>
                            <span class="text-rose-600 font-bold ml-2">Status: {{ strtoupper($breached->status) }}</span>
                        </div>
                        <span class="font-bold text-rose-700 bg-rose-100 px-2.5 py-1 rounded-md">
                            Elapsed: {{ $breached->minutesSincePlaced() }} mins
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Store Load Balancing & Capacity Gauges -->
        <div class="lg:col-span-1 bg-white rounded-3xl p-6 border border-slate-200 shadow-sm space-y-4">
            <h3 class="font-extrabold text-slate-900 text-sm flex items-center justify-between">
                <span>🏬 Store Live Capacities</span>
                <span class="text-xs text-slate-400">{{ count($ops['stores_load']) }} stores</span>
            </h3>

            <div class="space-y-4">
                @foreach($ops['stores_load'] as $storeLoad)
                    <div class="p-3.5 rounded-2xl border border-slate-100 bg-slate-50/50">
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <span class="font-bold text-slate-900">{{ $storeLoad['name'] }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                {{ $storeLoad['status'] === 'overloaded' ? 'bg-rose-100 text-rose-800' :
                                  ($storeLoad['status'] === 'busy' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') }}">
                                {{ $storeLoad['status'] }}
                            </span>
                        </div>
                        
                        <!-- Progress bar -->
                        <div class="w-full bg-slate-200 rounded-full h-2 mb-2 overflow-hidden">
                            <div class="h-2 rounded-full transition-all duration-500
                                {{ $storeLoad['load_percentage'] >= 90 ? 'bg-rose-500' :
                                  ($storeLoad['load_percentage'] >= 60 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                 style="width: {{ min(100, $storeLoad['load_percentage']) }}%">
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-[11px] text-slate-500">
                            <span>Active: <strong>{{ $storeLoad['active_orders'] }}</strong> orders</span>
                            <span>Limit: {{ $storeLoad['capacity'] }} ({{ $storeLoad['load_percentage'] }}%)</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Live Orders Feed -->
        <div class="lg:col-span-2 bg-white rounded-3xl p-6 border border-slate-200 shadow-sm space-y-4">
            <h3 class="font-extrabold text-slate-900 text-sm flex items-center justify-between">
                <span>⚡ Live Active Quick Orders</span>
                <span class="text-xs text-slate-400">Auto-updating</span>
            </h3>

            @if($ops['active_orders']->isEmpty())
                <div class="text-center py-12 text-slate-400 text-xs">
                    No active quick commerce orders in transit right now.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-400 uppercase tracking-wider text-[10px]">
                                <th class="pb-3 font-semibold">Order / Package</th>
                                <th class="pb-3 font-semibold">Store</th>
                                <th class="pb-3 font-semibold">Status</th>
                                <th class="pb-3 font-semibold">Rider</th>
                                <th class="pb-3 font-semibold">Elapsed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($ops['active_orders'] as $fo)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="py-3 font-mono font-bold text-slate-900">
                                        #{{ $fo->fulfilment_order_number }}
                                        <span class="block text-[10px] text-slate-400 font-sans">
                                            {{ $fo->items->sum('qty') }} items • ₹{{ number_format($fo->total_amount, 2) }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-slate-700">
                                        {{ $fo->store?->name ?? 'Hub' }}
                                    </td>
                                    <td class="py-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                            {{ in_array($fo->status, ['ready', 'rider_assigned', 'picked_up']) ? 'bg-blue-100 text-blue-800' :
                                              ($fo->status === 'placed' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">
                                            {{ str_replace('_', ' ', $fo->status) }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-slate-700">
                                        {{ $fo->deliveryAssignment?->deliveryPartner?->name ?? 'Unassigned' }}
                                    </td>
                                    <td class="py-3 font-mono {{ $fo->isSlaBreached() ? 'text-rose-600 font-bold' : 'text-slate-500' }}">
                                        {{ $fo->minutesSincePlaced() }}m
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
