@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Merchant Orders'])

@section('content')
<div class="space-y-6" x-data="{
    filterOpen: false,
    soundEnabled: true,
    lastCount: {{ $counts['action_needed'] }},
    playAlertChime() {
        if (!this.soundEnabled) return;
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc.frequency.setValueAtTime(880, ctx.currentTime + 0.12); // A5
            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
            osc.start();
            osc.stop(ctx.currentTime + 0.5);
        } catch (e) {
            console.log('Audio alert error:', e);
        }
    },
    pollOrders() {
        fetch('{{ route('seller.orders.pending_count') }}')
            .then(res => res.json())
            .then(data => {
                if (data.has_fresh && data.pending_count > this.lastCount) {
                    this.playAlertChime();
                    $dispatch('toast', { message: '⚡ New incoming order received! Please review and pack.', type: 'warning' });
                }
                this.lastCount = data.pending_count;
            })
            .catch(() => {});
    }
}" x-init="setInterval(() => pollOrders(), 20000)">

    <!-- Page Header & New Order Audio Chime Controls -->
    <x-page-header 
        title="Orders &amp; Dispatch Console" 
        subtitle="Manage 10-30m instant store pickups, standard parcel dispatch, and order fulfillment SLAs."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Orders' => '#']"
    >
        <x-slot name="badge">
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black bg-amber-500/10 text-amber-500 border border-amber-500/20">
                    <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse mr-1.5"></span>
                    <span x-text="lastCount + ' Need Action'">{{ $counts['action_needed'] }} Need Action</span>
                </span>

                <button 
                    @click="soundEnabled = !soundEnabled" 
                    type="button" 
                    class="px-2.5 py-1 rounded-full text-[11px] font-bold border transition flex items-center space-x-1"
                    :class="soundEnabled ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' : 'bg-slate-100 dark:bg-slate-800 text-slate-400 border-slate-300 dark:border-slate-700'"
                    :title="soundEnabled ? 'Audio Chime Enabled' : 'Audio Chime Muted'"
                >
                    <span x-text="soundEnabled ? '🔔 Sound On' : '🔕 Muted'">🔔 Sound On</span>
                </button>
            </div>
        </x-slot>

        <x-slot name="actions">
            <x-button variant="outline" size="sm" @click="playAlertChime()">
                Test Audio Ping
            </x-button>
        </x-slot>
    </x-page-header>

    <!-- Navigation Tabs: All / Quick / Online / Pickup -->
    <div class="flex items-center space-x-2 border-b border-slate-200 dark:border-slate-800 overflow-x-auto text-xs font-bold scrollbar-none pb-px">
        <a 
            href="{{ route('seller.orders.index', ['tab' => 'all', 'status' => $status, 'store_id' => $storeId, 'search' => $search]) }}"
            class="px-4 py-3 border-b-2 transition whitespace-nowrap flex items-center space-x-2 {{ $tab === 'all' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400 font-black' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' }}"
        >
            <span>All Packages</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] {{ $tab === 'all' ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-500' }}">
                {{ $counts['all'] }}
            </span>
        </a>

        <a 
            href="{{ route('seller.orders.index', ['tab' => 'quick', 'status' => $status, 'store_id' => $storeId, 'search' => $search]) }}"
            class="px-4 py-3 border-b-2 transition whitespace-nowrap flex items-center space-x-2 {{ $tab === 'quick' ? 'border-amber-500 text-amber-600 dark:text-amber-400 font-black' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' }}"
        >
            <span class="flex items-center space-x-1">
                <span>⚡ Quick Commerce</span>
                <span class="text-[10px] text-amber-500 font-normal">(10-30m)</span>
            </span>
            <span class="px-2 py-0.5 rounded-full text-[10px] {{ $tab === 'quick' ? 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 font-black' : 'bg-slate-100 dark:bg-slate-800 text-slate-500' }}">
                {{ $counts['quick'] }}
            </span>
        </a>

        <a 
            href="{{ route('seller.orders.index', ['tab' => 'online', 'status' => $status, 'store_id' => $storeId, 'search' => $search]) }}"
            class="px-4 py-3 border-b-2 transition whitespace-nowrap flex items-center space-x-2 {{ $tab === 'online' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-black' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' }}"
        >
            <span>📦 Standard Online</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] {{ $tab === 'online' ? 'bg-indigo-100 dark:bg-indigo-950 text-indigo-800 dark:text-indigo-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-500' }}">
                {{ $counts['online'] }}
            </span>
        </a>

        <a 
            href="{{ route('seller.orders.index', ['tab' => 'pickup', 'status' => $status, 'store_id' => $storeId, 'search' => $search]) }}"
            class="px-4 py-3 border-b-2 transition whitespace-nowrap flex items-center space-x-2 {{ $tab === 'pickup' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400 font-black' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' }}"
        >
            <span>🏬 Store Pickup</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] {{ $tab === 'pickup' ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-500' }}">
                {{ $counts['pickup'] }}
            </span>
        </a>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <form method="GET" action="{{ route('seller.orders.index') }}" class="flex flex-wrap items-center gap-3 w-full">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <!-- Search box -->
            <div class="relative flex-1 min-w-[200px]">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ $search }}" 
                    placeholder="Search package #, order #, or customer..." 
                    class="w-full text-xs font-medium pl-9 pr-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                >
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <!-- Status Dropdown -->
            <select 
                name="status" 
                class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
            >
                <option value="all">All Statuses</option>
                <option value="placed" {{ $status === 'placed' ? 'selected' : '' }}>Placed (New)</option>
                <option value="store_accepted" {{ $status === 'store_accepted' ? 'selected' : '' }}>Store Accepted</option>
                <option value="packing" {{ $status === 'packing' ? 'selected' : '' }}>Packing</option>
                <option value="confirmed" {{ $status === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                <option value="packed" {{ $status === 'packed' ? 'selected' : '' }}>Packed</option>
                <option value="ready" {{ $status === 'ready' ? 'selected' : '' }}>Ready for Pickup</option>
                <option value="ready_to_ship" {{ $status === 'ready_to_ship' ? 'selected' : '' }}>Ready to Ship</option>
                <option value="rider_assigned" {{ $status === 'rider_assigned' ? 'selected' : '' }}>Rider Assigned</option>
                <option value="out_for_delivery" {{ $status === 'out_for_delivery' ? 'selected' : '' }}>Out for Delivery</option>
                <option value="delivered" {{ $status === 'delivered' ? 'selected' : '' }}>Delivered</option>
                <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>

            <!-- Store Dropdown -->
            @if($stores->count() > 1)
                <select 
                    name="store_id" 
                    class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                >
                    <option value="">All Outlets</option>
                    @foreach($stores as $st)
                        <option value="{{ $st->id }}" {{ (string) $storeId === (string) $st->id ? 'selected' : '' }}>
                            {{ $st->name }}
                        </option>
                    @endforeach
                </select>
            @endif

            <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 dark:bg-white text-white dark:text-slate-900 text-xs font-bold transition shrink-0">
                Filter
            </button>

            @if($search || ($status && $status !== 'all') || $storeId)
                <a href="{{ route('seller.orders.index', ['tab' => $tab]) }}" class="text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 self-center">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Orders Table -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
        @if($orders->isEmpty())
            <div class="p-12 text-center space-y-3">
                <div class="w-16 h-16 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center text-3xl">
                    📦
                </div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">No fulfillment orders found</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">There are no orders matching your current filter in this tab.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-[11px] font-bold uppercase text-slate-400 tracking-wider">
                        <tr>
                            <th class="p-4">Package &amp; Order #</th>
                            <th class="p-4">Customer &amp; Location</th>
                            <th class="p-4">Channel</th>
                            <th class="p-4">Items</th>
                            <th class="p-4">Net Payout</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        @foreach($orders as $fo)
                            @php
                                $isQuick = $fo->fulfilment_type === \App\Enums\FulfilmentType::QUICK;
                            @endphp
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                                <td class="p-4">
                                    <div class="font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                                        <span>#{{ $fo->fulfilment_order_number }}</span>
                                        @if($fo->status === 'placed')
                                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse" title="New order needing accept"></span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                        Parent: <a href="{{ route('seller.orders.show', $fo) }}" class="hover:underline">#{{ $fo->order?->order_number }}</a>
                                    </div>
                                    <div class="text-[10px] text-slate-400">{{ $fo->created_at->format('d M, h:i A') }}</div>
                                </td>

                                <td class="p-4">
                                    <div class="font-bold text-slate-900 dark:text-white">
                                        {{ $fo->order?->address_snapshot['name'] ?? 'Shopper' }}
                                    </div>
                                    <div class="text-[11px] text-slate-400 truncate max-w-[180px]">
                                        {{ $fo->order?->address_snapshot['city'] ?? '' }} ({{ $fo->order?->address_snapshot['pincode'] ?? '' }})
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        Outlet: <strong class="text-slate-600 dark:text-slate-300">{{ $fo->store?->name ?? 'Main Hub' }}</strong>
                                    </div>
                                </td>

                                <td class="p-4">
                                    @if($isQuick)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500/10 text-amber-500 border border-amber-500/20">
                                            ⚡ Quick (10-30m)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                            📦 Standard
                                        </span>
                                    @endif
                                </td>

                                <td class="p-4">
                                    <div class="text-slate-800 dark:text-slate-200 font-bold">
                                        {{ $fo->items->sum('qty') }} unit(s)
                                    </div>
                                    <div class="text-[11px] text-slate-400 truncate max-w-[160px]" title="{{ $fo->items->first()?->title }}">
                                        {{ $fo->items->first()?->title }}
                                        @if($fo->items->count() > 1)
                                            +{{ $fo->items->count() - 1 }} more
                                        @endif
                                    </div>
                                </td>

                                <td class="p-4">
                                    <div class="font-black text-emerald-600 dark:text-emerald-400 text-sm">
                                        {{ format_currency((float) $fo->seller_payable) }}
                                    </div>
                                    <div class="text-[10px] text-slate-400">Total: {{ format_currency((float) $fo->total) }}</div>
                                </td>

                                <td class="p-4">
                                    @php
                                        $badgeBg = match(strtolower($fo->status)) {
                                            'placed' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-300',
                                            'store_accepted', 'confirmed' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-400 border border-sky-300',
                                            'packing', 'packed' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-300',
                                            'ready', 'ready_to_ship' => 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-400 border border-purple-300',
                                            'rider_assigned', 'in_transit', 'out_for_delivery' => 'bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-400 border border-orange-300',
                                            'delivered' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-300',
                                            default => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300',
                                        };
                                    @endphp
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold {{ $badgeBg }}">
                                        {{ ucwords(str_replace('_', ' ', $fo->status)) }}
                                    </span>
                                </td>

                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end space-x-2">
                                        <a 
                                            href="{{ route('seller.orders.show', $fo) }}" 
                                            class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition"
                                        >
                                            Process &rarr;
                                        </a>

                                        <!-- Quick transition shortcuts -->
                                        @if($fo->status === 'placed')
                                            <form method="POST" action="{{ route('seller.orders.transition', $fo) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="target_status" value="{{ $isQuick ? 'store_accepted' : 'confirmed' }}">
                                                <button type="submit" class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition">
                                                    Accept
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
