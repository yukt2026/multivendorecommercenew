@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Quick Commerce Operations & Mission Control'])

@section('content')
<div class="space-y-6" x-data="quickCommerceOps(@js($ops))">

    <!-- Page Header -->
    <x-page-header 
        title="Quick Commerce Mission Control" 
        subtitle="Realtime store load balancing, SLA breach alerts, and hyperlocal dispatch telemetry."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Quick Commerce Ops' => '#']"
    >
        <x-slot name="badge">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 space-x-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                <span>Hyperlocal Telemetry Live</span>
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <form method="POST" action="{{ route('admin.quick.scan_timeouts') }}" class="inline">
                    @csrf
                    <button 
                        type="submit" 
                        class="px-3.5 py-2 bg-amber-600 hover:bg-amber-500 text-white text-xs font-semibold rounded-lg shadow-sm flex items-center space-x-1.5 transition"
                        title="Scan pending orders and auto-reroute any that exceeded store timeout"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Scan Timeouts</span>
                    </button>
                </form>

                <a 
                    href="{{ route('admin.delivery_partners.index') }}" 
                    class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm flex items-center space-x-1.5 transition"
                >
                    <span>🛵 Manage Rider Fleet</span>
                </a>

                <button 
                    type="button"
                    @click="fetchLiveFeed()" 
                    :class="loading ? 'animate-spin' : ''"
                    class="p-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold transition"
                    title="Refresh live telemetry now"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </button>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Realtime KPIs Strip -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <!-- Active Quick Orders -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Active Quick Orders</div>
            <div class="text-3xl font-black text-amber-500 dark:text-amber-400 mt-1" x-text="ops.active_orders_count">
                {{ $ops['active_orders_count'] }}
            </div>
            <div class="text-[11px] text-slate-400 mt-1">In transit &amp; packing</div>
        </div>

        <!-- SLA Breaches -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">SLA Breaches</div>
            <div 
                class="text-3xl font-black mt-1" 
                :class="ops.breached_count > 0 ? 'text-rose-600 dark:text-rose-400 animate-pulse' : 'text-emerald-600 dark:text-emerald-400'"
                x-text="ops.breached_count"
            >
                {{ $ops['breached_count'] }}
            </div>
            <div class="text-[11px] text-slate-400 mt-1" x-text="ops.breached_count > 0 ? 'Urgent dispatch escalation needed' : 'All orders within SLA'">
                {{ $ops['breached_count'] > 0 ? 'Urgent dispatch escalation needed' : 'All orders within SLA' }}
            </div>
        </div>

        <!-- Delivered Today -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Delivered Today</div>
            <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-1" x-text="ops.today_delivered">
                {{ $ops['today_delivered'] }}
            </div>
            <div class="text-[11px] text-slate-400 mt-1">Hyperlocal completed</div>
        </div>

        <!-- Riders Fleet (Avail / Total) -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Riders (Available / Total)</div>
            <div class="text-3xl font-black text-slate-900 dark:text-white mt-1">
                <span x-text="ops.riders_summary.available">{{ $ops['riders_summary']['available'] }}</span>
                <span class="text-slate-400 font-normal text-xl">/ <span x-text="ops.riders_summary.total">{{ $ops['riders_summary']['total'] }}</span></span>
            </div>
            <div class="text-[11px] text-indigo-600 dark:text-indigo-400 mt-1">
                <span x-text="ops.riders_summary.busy">{{ $ops['riders_summary']['busy'] }}</span> currently on deliveries
            </div>
        </div>
    </div>

    <!-- SLA Breach Warning Alert Banner -->
    <div x-show="ops.breached_count > 0" class="bg-rose-50 dark:bg-rose-950/40 border-2 border-rose-300 dark:border-rose-800 rounded-2xl p-5 shadow-sm space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2 text-rose-800 dark:text-rose-300">
                <span class="w-3 h-3 rounded-full bg-rose-600 animate-ping"></span>
                <h3 class="font-extrabold text-sm uppercase tracking-wider">
                    Urgent SLA Breaches (<span x-text="ops.breached_count">{{ $ops['breached_count'] }}</span>)
                </h3>
            </div>
            <form method="POST" action="{{ route('admin.quick.scan_timeouts') }}">
                @csrf
                <button type="submit" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold transition shadow-xs">
                    Reroute Breached Orders
                </button>
            </form>
        </div>
        <p class="text-xs text-rose-700 dark:text-rose-400">
            The following quick-commerce packages have exceeded store acceptance timeouts or estimated delivery windows. Store reassignment or dispatch escalation recommended.
        </p>

        <div class="divide-y divide-rose-200 dark:divide-rose-900/60 text-xs">
            @foreach($ops['breached_orders'] as $breached)
                <div class="py-2.5 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <a href="{{ route('admin.orders.show', $breached->order_id) }}" class="font-mono font-bold text-indigo-700 dark:text-indigo-400 hover:underline">
                            #{{ $breached->fulfilment_order_number }}
                        </a>
                        <span class="text-slate-600 dark:text-slate-400">Store: <strong>{{ $breached->store?->name ?? 'N/A' }}</strong></span>
                        <span class="text-rose-600 dark:text-rose-400 font-bold uppercase text-[10px] px-2 py-0.5 rounded bg-rose-100 dark:bg-rose-900/50">
                            {{ strtoupper($breached->status) }}
                        </span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="font-bold text-rose-700 dark:text-rose-300 bg-rose-100 dark:bg-rose-900/50 px-2.5 py-1 rounded-md font-mono text-[11px]">
                            Elapsed: {{ $breached->minutesSincePlaced() }}m
                        </span>
                        <form method="POST" action="{{ route('admin.quick.reroute', $breached) }}">
                            @csrf
                            <button type="submit" class="px-2.5 py-1 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 rounded-md text-[11px] font-semibold hover:bg-slate-50 transition" title="Attempt auto-reroute to alternative store">
                                Re-route
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Healthy Status Banner when No Breaches -->
    <div x-show="ops.breached_count === 0" class="bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/60 rounded-2xl p-4 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                ✓
            </div>
            <div>
                <div class="text-xs font-bold text-emerald-900 dark:text-emerald-200">All Hyperlocal SLAs Healthy &amp; On Track</div>
                <div class="text-[11px] text-emerald-700 dark:text-emerald-400">Store acceptance times and rider transit windows are operating within optimal 10-30 minute thresholds.</div>
            </div>
        </div>
        <span class="text-[10px] font-mono px-2 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/80 text-emerald-800 dark:text-emerald-300 font-bold">
            0 SLA VIOLATIONS
        </span>
    </div>

    <!-- Two-Column Operations Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Store Live Capacities & Load Balancing (1 Column) -->
        <div class="lg:col-span-1 bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <h3 class="font-extrabold text-slate-900 dark:text-white text-sm flex items-center space-x-2">
                    <span>🏬 Store Live Capacities</span>
                </h3>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                    <span x-text="ops.stores_load.length">{{ count($ops['stores_load']) }}</span> outlets
                </span>
            </div>

            <div class="space-y-3.5 max-h-[600px] overflow-y-auto pr-1">
                @forelse($ops['stores_load'] as $storeLoad)
                    <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-900 dark:text-white truncate max-w-[180px]">{{ $storeLoad['name'] }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                                {{ $storeLoad['status'] === 'overloaded' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800' :
                                  ($storeLoad['status'] === 'busy' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800') }}">
                                {{ $storeLoad['status'] }}
                            </span>
                        </div>

                        <!-- Progress Bar -->
                        <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2 overflow-hidden">
                            <div class="h-2 rounded-full transition-all duration-500
                                {{ $storeLoad['load_percentage'] >= 90 ? 'bg-rose-500' :
                                  ($storeLoad['load_percentage'] >= 60 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                 style="width: {{ min(100, $storeLoad['load_percentage']) }}%">
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                            <span>Active: <strong class="text-slate-800 dark:text-slate-200">{{ $storeLoad['active_orders'] }}</strong> orders</span>
                            <span>Limit: {{ $storeLoad['capacity'] }} ({{ $storeLoad['load_percentage'] }}%)</span>
                        </div>

                        @if(!empty($storeLoad['address']) && $storeLoad['address'] !== 'N/A')
                            <div class="text-[10px] text-slate-400 truncate">
                                📍 {{ $storeLoad['address'] }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center py-8 text-slate-400 text-xs">
                        No active quick commerce stores configured.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Live Active Quick Orders Feed (2 Columns) -->
        <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center space-x-2">
                    <h3 class="font-extrabold text-slate-900 dark:text-white text-sm">
                        <span>⚡ Live Active Quick Orders</span>
                    </h3>
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                </div>
                <div class="text-xs text-slate-400 flex items-center space-x-2">
                    <span>Auto-updating (6s)</span>
                    <button type="button" @click="fetchLiveFeed()" class="text-indigo-600 dark:text-indigo-400 hover:underline">Sync Now</button>
                </div>
            </div>

            @if($ops['active_orders']->isEmpty())
                <div class="text-center py-16 text-slate-400 text-xs space-y-2">
                    <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <div class="font-semibold text-slate-700 dark:text-slate-300">No Active Quick Orders in Transit</div>
                    <p class="text-[11px] text-slate-400">New orders placed with 10-30 min delivery SLA will stream into this feed live.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 uppercase tracking-wider text-[10px] font-semibold">
                                <th class="pb-3">Order / Package</th>
                                <th class="pb-3">Store</th>
                                <th class="pb-3">Live Status</th>
                                <th class="pb-3">Rider Partner</th>
                                <th class="pb-3">Elapsed</th>
                                <th class="pb-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($ops['active_orders'] as $fo)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-3 font-mono font-bold text-slate-900 dark:text-white">
                                        <a href="{{ route('admin.orders.show', $fo->order_id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                            #{{ $fo->fulfilment_order_number }}
                                        </a>
                                        <span class="block text-[10px] text-slate-400 font-sans font-normal">
                                            {{ $fo->items->sum('quantity') ?: $fo->items->sum('qty') }} items • {{ format_currency((float)$fo->total_amount) }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-slate-700 dark:text-slate-300">
                                        <div class="font-medium truncate max-w-[140px]">{{ $fo->store?->name ?? 'Hub Outlet' }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $fo->store?->location?->city ?? 'Local Store' }}</div>
                                    </td>
                                    <td class="py-3">
                                        @php
                                            $st = $fo->status;
                                            $isReady = in_array($st, ['ready', 'rider_assigned', 'picked_up']);
                                            $isPlaced = $st === 'placed';
                                        @endphp
                                        <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider border
                                            {{ $isReady ? 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/60 dark:text-sky-400 dark:border-sky-800' :
                                              ($isPlaced ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-400 dark:border-amber-800' : 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700') }}">
                                            {{ str_replace('_', ' ', $st) }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-slate-700 dark:text-slate-300">
                                        @if($fo->deliveryAssignment?->deliveryPartner)
                                            <div class="font-medium">{{ $fo->deliveryAssignment->deliveryPartner->name }}</div>
                                            <div class="text-[10px] text-slate-400">{{ $fo->deliveryAssignment->deliveryPartner->phone ?? 'Assigned' }}</div>
                                        @else
                                            <span class="text-amber-600 dark:text-amber-400 font-semibold text-[11px] flex items-center space-x-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                <span>Awaiting Rider</span>
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 font-mono {{ $fo->isSlaBreached() ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-slate-600 dark:text-slate-400' }}">
                                        {{ $fo->minutesSincePlaced() }}m
                                        @if($fo->isSlaBreached())
                                            <span class="text-[9px] block text-rose-500 font-sans font-bold">BREACHED</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-right space-x-1.5">
                                        <a href="{{ route('admin.orders.show', $fo->order_id) }}" class="p-1.5 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition" title="Inspect Order Details">
                                            <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        @if($fo->isSlaBreached())
                                            <form method="POST" action="{{ route('admin.quick.reroute', $fo) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="p-1.5 text-rose-500 hover:text-rose-700 transition" title="Auto-reroute to alternative store">
                                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                </button>
                                            </form>
                                        @endif
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

<script>
function quickCommerceOps(initialOps) {
    return {
        ops: initialOps,
        loading: false,
        pollInterval: null,
        init() {
            this.pollInterval = setInterval(() => {
                this.fetchLiveFeed();
            }, 6000);
        },
        fetchLiveFeed() {
            this.loading = true;
            fetch('{{ route("admin.quick.api_feed") }}', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.status === 'success' && data.data) {
                    this.ops = data.data;
                }
            })
            .catch(err => console.error('Ops feed poll error:', err))
            .finally(() => {
                this.loading = false;
            });
        }
    };
}
</script>
@endsection
