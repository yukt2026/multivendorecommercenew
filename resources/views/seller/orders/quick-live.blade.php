@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Quick 15m Dispatch Console'])

@section('content')
<div class="space-y-6" x-data="{
    audioEnabled: true,
    orders: @js($initialOrders ?? []),
    loading: false,
    filterStatus: 'all',
    timeoutMax: {{ $timeoutSeconds }},
    initAudio() {
        if (!window.audioCtx) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                window.audioCtx = new AudioContextClass();
            }
        }
        if (window.audioCtx && window.audioCtx.state === 'suspended') {
            window.audioCtx.resume();
        }
    },
    playAlert() {
        if (!this.audioEnabled) return;
        try {
            this.initAudio();
            const ctx = window.audioCtx || new (window.AudioContext || window.webkitAudioContext)();
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
        } catch(e) { console.warn('Audio alert error:', e); }
    },
    fetchOrders() {
        this.loading = true;
        const url = '{{ route('seller.quick.orders_api', ['store_id' => $store?->id]) }}';
        fetch(url)
            .then(res => res.json())
            .then(data => {
                const hadNewPlaced = data.orders && data.orders.some(o => o.status === 'placed');
                if (hadNewPlaced && (!this.orders.length || this.orders.some(o => o.status === 'placed' && !data.orders.find(no => no.id === o.id)))) {
                    this.playAlert();
                }
                this.orders = data.orders || [];
                this.loading = false;
            })
            .catch(err => {
                console.error(err);
                this.loading = false;
            });
    },
    init() {
        window.addEventListener('click', () => this.initAudio(), { once: true });
        this.fetchOrders();
        setInterval(() => this.fetchOrders(), 4000);
        setInterval(() => {
            this.orders.forEach(o => {
                if (o.remaining_seconds > 0) o.remaining_seconds--;
            });
        }, 1000);
    }
}">
    <!-- Clean Page Header with Breadcrumbs & Sound Toggle -->
    <x-page-header 
        title="Quick 15m Dispatch Console" 
        subtitle="Real-time order intake, pick-and-pack workflow, automated rider dispatch, and SLA countdowns."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Orders' => route('seller.orders.index'), 'Quick Dispatch' => '#']"
    >
        <x-slot name="badge">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                <span>⚡ Live Feed</span>
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center gap-2">
                <!-- Sound Alert Toggle -->
                <button 
                    @click="audioEnabled = !audioEnabled; if(audioEnabled) playAlert();" 
                    type="button" 
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold border transition flex items-center space-x-1.5 shadow-sm"
                    :class="audioEnabled ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-400 border-slate-200 dark:border-slate-700'"
                    :title="audioEnabled ? 'Sound alerts active (Click to mute)' : 'Sound alerts muted (Click to enable)'"
                >
                    <span x-text="audioEnabled ? '🔊 Sound On' : '🔇 Muted'">🔊 Sound On</span>
                </button>

                <!-- Refresh Button -->
                <x-button variant="outline" size="sm" @click="fetchOrders()" class="flex items-center space-x-1.5">
                    <svg class="w-3.5 h-3.5" :class="loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Refresh</span>
                </x-button>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Feedback Alerts -->
    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-bold flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('notice'))
        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 text-xs font-bold flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>{{ session('notice') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-bold flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Outlet Operations & SLA Status Banner -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center space-x-3.5">
            <div class="w-11 h-11 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-500 flex items-center justify-center text-xl shrink-0">
                ⚡
            </div>
            <div>
                <div class="flex items-center space-x-2">
                    <h2 class="text-base sm:text-lg font-black text-slate-900 dark:text-white capitalize">
                        {{ $store ? $store->name : 'No Active Store' }}
                    </h2>
                    @if($store && $store->quick_delivery_enabled)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse mr-1"></span>
                            Quick Hub Active
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-500 border border-slate-200 dark:border-slate-700">
                            Standard Dispatch
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Orders must be accepted within <strong class="text-amber-600 dark:text-amber-400 font-bold">{{ $timeoutSeconds }} seconds</strong> before automated fallback rerouting to neighboring hubs.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 shrink-0 text-xs">
            <div class="px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 text-slate-600 dark:text-slate-300 font-medium">
                <span>Radius: </span>
                <strong class="text-slate-900 dark:text-white font-bold">{{ $store ? $store->effectiveQuickRadius() : 5 }} km</strong>
            </div>
            <div class="px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 text-slate-600 dark:text-slate-300 font-medium">
                <span>Max Capacity: </span>
                <strong class="text-slate-900 dark:text-white font-bold">{{ $store ? $store->effectiveQuickCapacity() : 15 }} orders</strong>
            </div>
        </div>
    </div>

    <!-- 5 Clean Metric Stat Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
        <!-- 1. Pending Acceptance -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm transition hover:shadow-md flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Needs Action</span>
                <span class="w-2 h-2 rounded-full bg-rose-500" :class="orders.filter(o => o.status === 'placed').length > 0 ? 'animate-ping' : ''"></span>
            </div>
            <div class="flex items-baseline justify-between">
                <div class="text-2xl sm:text-3xl font-black text-rose-600 dark:text-rose-400" x-text="orders.filter(o => o.status === 'placed').length">0</div>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-300">Pending</span>
            </div>
            <span class="text-[10px] text-slate-400 mt-2 block">&lt; {{ $timeoutSeconds }}s SLA Target</span>
        </div>

        <!-- 2. In Preparation -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm transition hover:shadow-md flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">In Preparation</span>
                <span class="text-xs">📦</span>
            </div>
            <div class="flex items-baseline justify-between">
                <div class="text-2xl sm:text-3xl font-black text-amber-600 dark:text-amber-400" x-text="orders.filter(o => ['store_accepted', 'packing'].includes(o.status)).length">0</div>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-300">Packing</span>
            </div>
            <span class="text-[10px] text-slate-400 mt-2 block">Accepted &amp; Picking</span>
        </div>

        <!-- 3. Ready for Pickup -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm transition hover:shadow-md flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Ready / Dispatch</span>
                <span class="text-xs">🛵</span>
            </div>
            <div class="flex items-baseline justify-between">
                <div class="text-2xl sm:text-3xl font-black text-purple-600 dark:text-purple-400" x-text="orders.filter(o => ['ready', 'rider_assigned'].includes(o.status)).length">0</div>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-300">Awaiting</span>
            </div>
            <span class="text-[10px] text-slate-400 mt-2 block">Courier at Store</span>
        </div>

        <!-- 4. En Route -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm transition hover:shadow-md flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">En Route</span>
                <span class="text-xs">🚀</span>
            </div>
            <div class="flex items-baseline justify-between">
                <div class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-400" x-text="orders.filter(o => ['picked_up', 'out_for_delivery'].includes(o.status)).length">0</div>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-300">Live</span>
            </div>
            <span class="text-[10px] text-slate-400 mt-2 block">Doorstep Delivery</span>
        </div>

        <!-- 5. Active Store Capacity Load -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm transition hover:shadow-md flex flex-col justify-between col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Store Load</span>
                <span class="text-xs">🏬</span>
            </div>
            <div class="flex items-baseline justify-between">
                <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
                    <span x-text="orders.length">0</span><span class="text-xs text-slate-400 font-normal">/{{ $store ? $store->effectiveQuickCapacity() : 15 }}</span>
                </div>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">Capacity</span>
            </div>
            <span class="text-[10px] text-slate-400 mt-2 block">Active Dispatch Slot</span>
        </div>
    </div>

    <!-- 1. Urgent Action: Incoming Orders Requiring Store Acceptance -->
    <div class="space-y-3.5 pt-2">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-black text-slate-900 dark:text-slate-100 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                <span>Incoming Orders Requiring Acceptance</span>
            </h3>
            <span class="text-xs font-black text-rose-500 px-2.5 py-0.5 rounded-full bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-900/60" x-show="orders.filter(o => o.status === 'placed').length > 0" x-text="orders.filter(o => o.status === 'placed').length + ' Urgent'"></span>
        </div>

        <!-- Radar / Active Listening Empty State -->
        <template x-if="orders.filter(o => o.status === 'placed').length === 0">
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 p-8 text-center text-slate-500 dark:text-slate-400 shadow-sm relative overflow-hidden">
                <div class="relative w-14 h-14 mx-auto mb-3 flex items-center justify-center">
                    <div class="absolute inset-0 rounded-full bg-amber-500/10 dark:bg-amber-500/20 animate-ping"></div>
                    <div class="relative w-10 h-10 rounded-full bg-amber-500/15 text-amber-500 flex items-center justify-center text-lg font-bold">
                        ⚡
                    </div>
                </div>
                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm">All Incoming Orders Accepted</h4>
                <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                    Console is actively listening for new 15-minute quick delivery orders. Audio chime will ring immediately when a new order arrives.
                </p>
                <div class="mt-3 inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-50 dark:bg-slate-800 text-[10px] text-slate-500 font-medium border border-slate-200/60 dark:border-slate-700/60">
                    <span>Protection Active:</span>
                    <strong class="text-slate-700 dark:text-slate-300">{{ $timeoutSeconds }}s Auto-Reroute Window</strong>
                </div>
            </div>
        </template>

        <!-- Orders Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <template x-for="order in orders.filter(o => o.status === 'placed')" :key="order.id">
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border-2 border-rose-400/80 dark:border-rose-500/70 shadow-lg relative overflow-hidden flex flex-col justify-between transition hover:shadow-xl">
                    <div>
                        <!-- Header with Order Link & Countdown Timer -->
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800 mb-3">
                            <div class="flex items-center gap-2">
                                <a :href="'{{ url('/seller/orders') }}/' + order.id" class="font-mono font-black text-slate-900 dark:text-white text-sm hover:text-amber-500 transition" x-text="'#' + order.order_number" title="View Full Order Details"></a>
                                <span class="text-[10px] bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 font-black px-2 py-0.5 rounded-full">
                                    ⚡ 15m QUICK
                                </span>
                            </div>

                            <!-- Live Countdown SLA Clock -->
                            <div class="flex items-center gap-1 font-mono font-black text-xs px-2.5 py-1 rounded-full"
                                 :class="order.remaining_seconds < 30 ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 animate-pulse' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span x-text="order.remaining_seconds + 's left to accept'"></span>
                            </div>
                        </div>

                        <!-- Customer & Destination Details -->
                        <div class="mb-3 text-xs bg-slate-50 dark:bg-slate-800/60 p-2.5 rounded-xl">
                            <div class="font-bold text-slate-800 dark:text-slate-200" x-text="'Deliver to: ' + order.customer_name"></div>
                            <div class="text-slate-400 text-[11px] truncate mt-0.5" x-text="order.customer_address || 'Customer doorstep'"></div>
                        </div>

                        <!-- Item Picking Checklist -->
                        <div class="mb-4">
                            <div class="text-[11px] text-slate-400 font-bold mb-2 uppercase tracking-wider flex items-center justify-between">
                                <span>Items to Pick:</span>
                                <span class="font-mono text-slate-600 dark:text-slate-300" x-text="order.items_count + ' total units'"></span>
                            </div>
                            <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                                <template x-for="item in order.items" :key="item.title">
                                    <div class="flex items-center justify-between bg-slate-50 dark:bg-slate-800/80 p-2 rounded-xl text-xs">
                                        <div class="flex items-center space-x-2">
                                            <span class="w-5 h-5 rounded-md bg-amber-500/10 text-amber-600 dark:text-amber-400 font-mono font-bold flex items-center justify-center text-[10px]" x-text="item.qty + 'x'"></span>
                                            <span class="font-bold text-slate-800 dark:text-slate-200" x-text="item.title"></span>
                                        </div>
                                        <span class="text-slate-400 text-[11px]" x-text="item.variant_name || 'Standard'"></span>
                                    </div>
                                </template>
                            </div>
                            <div class="flex items-center justify-between mt-3 text-xs font-black text-slate-900 dark:text-slate-100">
                                <span>Order Total:</span>
                                <span class="text-emerald-600 dark:text-emerald-400 text-sm" x-text="'₹' + Number(order.total).toFixed(2)"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="grid grid-cols-2 gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <form :action="'{{ url('/seller/orders') }}/' + order.id + '/accept'" method="POST">
                            @csrf
                            <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-md transition flex items-center justify-center gap-1.5">
                                <span>✓ Accept Order</span>
                            </button>
                        </form>

                        <form :action="'{{ url('/seller/orders') }}/' + order.id + '/reject'" method="POST" onsubmit="return confirm('Reject this order? It will automatically reroute to the nearest alternate store.');">
                            @csrf
                            <button type="submit" class="w-full py-2.5 bg-slate-100 hover:bg-rose-50 text-slate-700 hover:text-rose-600 dark:bg-slate-800 dark:hover:bg-rose-950/40 dark:text-slate-300 dark:hover:text-rose-400 rounded-xl font-bold text-xs transition border border-slate-200 dark:border-slate-700">
                                <span>✕ Reject (Reroute)</span>
                            </button>
                        </form>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- 2. Preparation, Packing & Active Rider Dispatch Pipeline -->
    <div class="space-y-4 pt-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <h3 class="text-sm font-black text-slate-900 dark:text-slate-100 flex items-center gap-2">
                <span>📦 Store Preparation &amp; Rider Dispatch</span>
            </h3>

            <!-- Stage Filter Pills -->
            <div class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl text-xs font-bold self-start">
                <button 
                    @click="filterStatus = 'all'" 
                    class="px-3 py-1 rounded-lg transition"
                    :class="filterStatus === 'all' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                >
                    All Active (<span x-text="orders.filter(o => o.status !== 'placed').length"></span>)
                </button>
                <button 
                    @click="filterStatus = 'packing'" 
                    class="px-3 py-1 rounded-lg transition"
                    :class="filterStatus === 'packing' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                >
                    In Packing
                </button>
                <button 
                    @click="filterStatus = 'ready'" 
                    class="px-3 py-1 rounded-lg transition"
                    :class="filterStatus === 'ready' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                >
                    Ready for Pickup
                </button>
                <button 
                    @click="filterStatus = 'en_route'" 
                    class="px-3 py-1 rounded-lg transition"
                    :class="filterStatus === 'en_route' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                >
                    En Route
                </button>
            </div>
        </div>

        <!-- Empty State -->
        <template x-if="orders.filter(o => o.status !== 'placed').length === 0">
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-8 text-center text-slate-500 dark:text-slate-400 text-xs shadow-sm">
                <div class="w-10 h-10 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-2 text-lg">
                    📦
                </div>
                <p class="font-bold text-slate-700 dark:text-slate-300">No Orders in Preparation</p>
                <p class="text-[11px] text-slate-400 mt-1">Accepted orders will appear here automatically for picking, packing, and rider handover.</p>
            </div>
        </template>

        <!-- Pipeline Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <template x-for="order in orders.filter(o => {
                if (o.status === 'placed') return false;
                if (filterStatus === 'packing') return ['store_accepted', 'packing'].includes(o.status);
                if (filterStatus === 'ready') return ['ready', 'rider_assigned'].includes(o.status);
                if (filterStatus === 'en_route') return ['picked_up', 'out_for_delivery'].includes(o.status);
                return true;
            })" :key="order.id">
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between transition hover:shadow-md">
                    <div>
                        <!-- Header & Status Tag -->
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800 mb-3">
                            <div>
                                <a :href="'{{ url('/seller/orders') }}/' + order.id" class="font-mono font-black text-slate-900 dark:text-white text-sm hover:text-amber-500 transition" x-text="'#' + order.order_number" title="View Order Details"></a>
                                <p class="text-[11px] text-slate-400 truncate" x-text="order.customer_name"></p>
                            </div>
                            <span class="text-[10px] font-black px-2.5 py-1 rounded-xl uppercase tracking-wider"
                                  :class="{
                                      'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': order.status === 'store_accepted',
                                      'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300': order.status === 'packing',
                                      'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300': order.status === 'ready',
                                      'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300': order.status === 'rider_assigned',
                                      'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': ['picked_up', 'out_for_delivery'].includes(order.status)
                                  }"
                                  x-text="order.status_label || order.status.replace('_', ' ')">
                            </span>
                        </div>

                        <!-- Customer Address -->
                        <div class="text-[11px] text-slate-400 bg-slate-50 dark:bg-slate-800/40 p-2 rounded-xl mb-3 truncate" x-text="'📍 ' + (order.customer_address || 'Delivery destination')"></div>

                        <!-- Packing Checklist -->
                        <div class="space-y-1.5 mb-4">
                            <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1">Pick &amp; Pack Checklist:</div>
                            <template x-for="item in order.items" :key="item.title">
                                <label class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer text-xs transition">
                                    <input type="checkbox" class="rounded text-amber-500 focus:ring-amber-400 border-slate-300 dark:border-slate-700">
                                    <span class="font-medium text-slate-800 dark:text-slate-200" x-text="item.qty + 'x ' + item.title"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <!-- Flow Actions based on Pipeline Stage -->
                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2">
                        <!-- 1. Store Accepted -> Move to Packing -->
                        <template x-if="order.status === 'store_accepted'">
                            <form :action="'{{ url('/seller/orders') }}/' + order.id + '/pack'" method="POST">
                                @csrf
                                <button type="submit" class="w-full py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-xl font-bold text-xs shadow transition flex items-center justify-center gap-1.5">
                                    <span>📦 Start Packing Items</span>
                                </button>
                            </form>
                        </template>

                        <!-- 2. Packing -> Mark Ready for Pickup -->
                        <template x-if="order.status === 'packing'">
                            <form :action="'{{ url('/seller/orders') }}/' + order.id + '/ready'" method="POST">
                                @csrf
                                <button type="submit" class="w-full py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-bold text-xs shadow transition flex items-center justify-center gap-1.5">
                                    <span>✓ Mark Ready for Pickup</span>
                                </button>
                            </form>
                        </template>

                        <!-- 3. Ready -> Assign Delivery Rider -->
                        <template x-if="order.status === 'ready'">
                            <div class="space-y-2">
                                <form :action="'{{ url('/seller/orders') }}/' + order.id + '/assign-rider'" method="POST" class="flex gap-2">
                                    @csrf
                                    <select name="delivery_partner_id" class="text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 flex-1 focus:ring-amber-500">
                                        <option value="">-- Nearest Available Rider --</option>
                                        @foreach($availableRiders as $rider)
                                            <option value="{{ $rider->id }}">{{ $rider->name }} ({{ $rider->vehicle }})</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-bold rounded-xl transition">
                                        Dispatch
                                    </button>
                                </form>
                            </div>
                        </template>

                        <!-- 4. Rider Assigned -> Handover to Rider & Dispatch -->
                        <template x-if="order.status === 'rider_assigned'">
                            <div class="space-y-2">
                                <div class="bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-900/60 rounded-xl p-2.5 text-xs text-indigo-900 dark:text-indigo-200 flex items-center justify-between">
                                    <div>
                                        <span class="font-bold block" x-text="'🛵 ' + (order.rider ? order.rider.name : 'Rider Assigned')"></span>
                                        <span class="text-[11px] text-indigo-600 dark:text-indigo-400" x-text="order.rider ? (order.rider.phone + ' • ' + (order.rider.vehicle || 'Bike')) : 'Rider arriving at store'"></span>
                                    </div>
                                    <span class="text-[10px] bg-indigo-200 dark:bg-indigo-900 text-indigo-900 dark:text-indigo-200 font-bold px-2 py-0.5 rounded-full">At Store</span>
                                </div>

                                <form :action="'{{ url('/seller/orders') }}/' + order.id + '/handover'" method="POST">
                                    @csrf
                                    <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow transition flex items-center justify-center gap-1.5">
                                        <span>Hand Over Bag &amp; Mark En Route &rarr;</span>
                                    </button>
                                </form>
                            </div>
                        </template>

                        <!-- 5. Picked Up / Out for Delivery -> Live En Route Status -->
                        <template x-if="['picked_up', 'out_for_delivery'].includes(order.status)">
                            <div class="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900/60 rounded-xl p-3 text-xs text-emerald-900 dark:text-emerald-200 flex flex-col gap-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="font-black text-emerald-700 dark:text-emerald-400 flex items-center gap-1">
                                        <span>🛵 Rider En Route to Doorstep</span>
                                    </span>
                                    <span class="text-[10px] bg-emerald-200 dark:bg-emerald-900 text-emerald-900 dark:text-emerald-200 font-black px-2 py-0.5 rounded-full">Active</span>
                                </div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center justify-between">
                                    <span x-text="order.rider ? order.rider.name : 'Delivery Partner'"></span>
                                    <span class="font-mono" x-text="order.rider ? order.rider.phone : ''"></span>
                                </div>
                                <template x-if="order.delivery_otp">
                                    <div class="mt-1 pt-1.5 border-t border-emerald-200/60 dark:border-emerald-900/40 flex items-center justify-between text-[11px]">
                                        <span class="text-slate-400">Delivery OTP:</span>
                                        <span class="font-mono font-black text-slate-800 dark:text-slate-200" x-text="order.delivery_otp"></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection
