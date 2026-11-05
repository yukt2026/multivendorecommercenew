@extends('layouts.seller')

@section('title', 'Quick Commerce Live Dispatch Console')

@section('content')
<div class="space-y-6" x-data="{
    audioEnabled: true,
    orders: [],
    loading: false,
    timeoutMax: {{ $timeoutSeconds }},
    playAlert() {
        if (!this.audioEnabled) return;
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.3);
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.3);
        } catch(e) { console.warn(e); }
    },
    fetchOrders() {
        fetch('{{ route('seller.quick.orders_api') }}')
            .then(res => res.json())
            .then(data => {
                const hadNewPlaced = data.orders.some(o => o.status === 'placed');
                if (hadNewPlaced && (!this.orders.length || this.orders.some(o => o.status === 'placed' && !data.orders.find(no => no.id === o.id)))) {
                    this.playAlert();
                }
                this.orders = data.orders;
            })
            .catch(err => console.error(err));
    },
    init() {
        this.fetchOrders();
        setInterval(() => this.fetchOrders(), 4000);
        setInterval(() => {
            this.orders.forEach(o => {
                if (o.remaining_seconds > 0) o.remaining_seconds--;
            });
        }, 1000);
    }
}">
    <!-- Top Bar & KPI Header -->
    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-orange-950 text-white rounded-3xl p-6 sm:p-8 shadow-xl">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="inline-flex items-center gap-2 bg-orange-500/20 text-orange-300 px-3 py-1 rounded-full text-xs font-bold mb-2 border border-orange-500/30">
                    <span class="w-2 h-2 rounded-full bg-orange-400 animate-ping"></span>
                    <span>⚡ Realtime Order Dispatch Console</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                    {{ $store ? $store->name : 'Store Outlet' }}
                </h1>
                <p class="text-xs text-slate-400 mt-1">
                    Accept incoming orders within <strong>{{ $timeoutSeconds }} seconds</strong> to avoid automated customer rerouting.
                </p>
            </div>

            <!-- Controls & Audio Toggle -->
            <div class="flex items-center gap-4 bg-slate-800/80 backdrop-blur-md p-3 rounded-2xl border border-slate-700/80">
                <label class="flex items-center gap-2 text-xs font-bold cursor-pointer text-slate-300 hover:text-white">
                    <input type="checkbox" x-model="audioEnabled" class="rounded text-orange-500 focus:ring-orange-400">
                    <span>🔊 Sound Alert</span>
                </label>
                <div class="h-4 w-px bg-slate-700"></div>
                <button @click="fetchOrders()" class="text-xs bg-orange-500 hover:bg-orange-600 text-white font-bold px-3 py-1.5 rounded-xl transition shadow-sm">
                    Refresh Feed
                </button>
            </div>
        </div>

        <!-- Live Metrics Bar -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-6 border-t border-slate-800">
            <div>
                <span class="text-xs text-slate-400 block">Pending Acceptance</span>
                <span class="text-2xl font-black text-amber-400" x-text="orders.filter(o => o.status === 'placed').length">0</span>
            </div>
            <div>
                <span class="text-xs text-slate-400 block">In Packing</span>
                <span class="text-2xl font-black text-cyan-400" x-text="orders.filter(o => o.status === 'store_accepted' || o.status === 'packing').length">0</span>
            </div>
            <div>
                <span class="text-xs text-slate-400 block">Ready / Dispatched</span>
                <span class="text-2xl font-black text-emerald-400" x-text="orders.filter(o => o.status === 'ready' || o.status === 'rider_assigned').length">0</span>
            </div>
            <div>
                <span class="text-xs text-slate-400 block">Store Capacity</span>
                <span class="text-2xl font-black text-white">{{ $store ? $store->effectiveQuickCapacity() : 15 }} max</span>
            </div>
        </div>
    </div>

    <!-- Incoming Orders Awaiting Acceptance -->
    <div>
        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2 mb-4">
            <span class="w-3 h-3 rounded-full bg-rose-500 animate-pulse"></span>
            <span>New Incoming Orders (Requires Urgent Acceptance)</span>
        </h2>

        <template x-if="orders.filter(o => o.status === 'placed').length === 0">
            <div class="bg-white rounded-2xl border border-dashed border-slate-200 p-8 text-center text-slate-500 text-xs">
                ✨ No new pending orders right now. Listening for new orders...
            </div>
        </template>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <template x-for="order in orders.filter(o => o.status === 'placed')" :key="order.id">
                <div class="bg-white rounded-2xl p-5 border-2 border-rose-400 shadow-lg relative overflow-hidden flex flex-col justify-between">
                    <!-- Timeout Visual Countdown Banner -->
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold text-slate-900 text-sm" x-text="'#' + order.order_number"></span>
                            <span class="text-xs bg-rose-100 text-rose-800 font-bold px-2 py-0.5 rounded">⚡ QUICK</span>
                        </div>

                        <!-- Countdown Clock -->
                        <div class="flex items-center gap-1 font-mono font-black text-sm"
                             :class="order.remaining_seconds < 30 ? 'text-rose-600 animate-pulse' : 'text-amber-600'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span x-text="order.remaining_seconds + 's left'"></span>
                        </div>
                    </div>

                    <!-- Order Items List -->
                    <div class="mb-4">
                        <div class="text-xs text-slate-500 font-bold mb-2 uppercase">Items to Pick:</div>
                        <div class="space-y-1.5">
                            <template x-for="item in order.items" :key="item.title">
                                <div class="flex items-center justify-between bg-slate-50 p-2 rounded-xl text-xs">
                                    <span class="font-bold text-slate-900" x-text="item.qty + 'x ' + item.title"></span>
                                    <span class="text-slate-400 text-[11px]" x-text="item.variant_name || 'Standard'"></span>
                                </div>
                            </template>
                        </div>
                        <div class="flex items-center justify-between mt-3 text-xs font-bold text-slate-900">
                            <span>Total Value:</span>
                            <span x-text="'₹' + Number(order.total).toFixed(2)"></span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="grid grid-cols-2 gap-3 pt-3 border-t border-slate-100">
                        <form :action="'{{ url('/seller/orders') }}/' + order.id + '/accept'" method="POST">
                            @csrf
                            <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-md transition flex items-center justify-center gap-1.5">
                                <span>✓ Accept Order</span>
                            </button>
                        </form>

                        <form :action="'{{ url('/seller/orders') }}/' + order.id + '/reject'" method="POST" onsubmit="return confirm('Reject this order? It will automatically reroute to the nearest alternate store.');">
                            @csrf
                            <button type="submit" class="w-full py-2 bg-slate-100 hover:bg-rose-50 text-slate-700 hover:text-rose-600 rounded-xl font-bold text-xs transition border border-slate-200">
                                <span>✕ Reject (Reroute)</span>
                            </button>
                        </form>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Orders In Progress (Packing / Ready / Dispatched) -->
    <div class="mt-8">
        <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
            <span>📦 Orders In Preparation & Dispatch</span>
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <template x-for="order in orders.filter(o => ['store_accepted', 'packing', 'ready', 'rider_assigned'].includes(o.status))" :key="order.id">
                <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                            <div>
                                <span class="font-mono font-bold text-slate-900 text-sm" x-text="'#' + order.order_number"></span>
                                <p class="text-[11px] text-slate-400" x-text="order.customer_name"></p>
                            </div>
                            <span class="text-xs font-bold px-2.5 py-1 rounded-lg uppercase"
                                  :class="{
                                      'bg-amber-100 text-amber-800': order.status === 'store_accepted',
                                      'bg-blue-100 text-blue-800': order.status === 'packing',
                                      'bg-purple-100 text-purple-800': order.status === 'ready',
                                      'bg-emerald-100 text-emerald-800': order.status === 'rider_assigned'
                                  }"
                                  x-text="order.status.replace('_', ' ')">
                            </span>
                        </div>

                        <!-- Packing Checklist -->
                        <div class="space-y-1.5 mb-4">
                            <template x-for="item in order.items" :key="item.title">
                                <label class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-50 cursor-pointer text-xs">
                                    <input type="checkbox" class="rounded text-orange-500 focus:ring-orange-400">
                                    <span class="font-medium text-slate-800" x-text="item.qty + 'x ' + item.title"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <!-- Flow Actions based on Status -->
                    <div class="pt-3 border-t border-slate-100 space-y-2">
                        <!-- If Accepted -> Advance to Packing -->
                        <template x-if="order.status === 'store_accepted'">
                            <form :action="'{{ url('/seller/orders') }}/' + order.id + '/pack'" method="POST">
                                @csrf
                                <button type="submit" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-xs shadow transition">
                                    Start Packing Items
                                </button>
                            </form>
                        </template>

                        <!-- If Packing -> Advance to Ready -->
                        <template x-if="order.status === 'packing'">
                            <form :action="'{{ url('/seller/orders') }}/' + order.id + '/ready'" method="POST">
                                @csrf
                                <button type="submit" class="w-full py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold text-xs shadow transition">
                                    Mark Ready for Pickup (Auto-Assign Rider)
                                </button>
                            </form>
                        </template>

                        <!-- If Ready -> Allow Manual Rider Selection -->
                        <template x-if="order.status === 'ready'">
                            <form :action="'{{ url('/seller/orders') }}/' + order.id + '/assign-rider'" method="POST" class="flex gap-2">
                                @csrf
                                <select name="delivery_partner_id" class="text-xs rounded-xl border-slate-200 flex-1">
                                    <option value="">-- Auto-Nearest Rider --</option>
                                    @foreach($availableRiders as $rider)
                                        <option value="{{ $rider->id }}">{{ $rider->name }} ({{ $rider->vehicle }})</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="px-3 py-1.5 bg-slate-900 text-white text-xs font-bold rounded-xl">
                                    Dispatch
                                </button>
                            </form>
                        </template>

                        <!-- If Rider Assigned -->
                        <template x-if="order.status === 'rider_assigned'">
                            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-2.5 text-xs text-emerald-800 flex items-center justify-between">
                                <div>
                                    <span class="font-bold block" x-text="'🛵 ' + (order.rider ? order.rider.name : 'Rider Assigned')"></span>
                                    <span class="text-[11px] text-emerald-600" x-text="order.rider ? order.rider.phone : ''"></span>
                                </div>
                                <span class="text-[10px] bg-emerald-200 text-emerald-900 font-bold px-2 py-0.5 rounded">En Route</span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection
