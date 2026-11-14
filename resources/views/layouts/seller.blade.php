<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Seller Studio' }} - {{ config('onestall.name', 'OneStall') }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])


@php
    $user = auth()->user();
    $seller = $user?->seller;
    $sellerStores = $seller ? $seller->stores()->with('location')->get() : collect();
    $activeStoreId = session('seller_active_store_id') ?: $sellerStores->first()?->id;
    $activeStore = $sellerStores->firstWhere('id', $activeStoreId) ?: $sellerStores->first();
    $isStoreOpen = $activeStore ? ($activeStore->status === \App\Enums\StoreStatus::ACTIVE) : true;
    $menuSections = config('menu.seller', []);

    // Dynamic Counts
    $productCount = $seller ? \App\Models\Product::where('seller_id', $seller->id)->count() : 0;
    $pendingOrdersCount = $seller ? \App\Models\FulfilmentOrder::where('seller_id', $seller->id)->whereIn('status', ['placed', 'store_accepted', 'packing'])->count() : 0;
    $quickLiveCount = $seller ? \App\Models\FulfilmentOrder::where('seller_id', $seller->id)->where('fulfilment_type', \App\Enums\FulfilmentType::QUICK)->whereIn('status', ['placed', 'store_accepted', 'packing'])->count() : 0;
    $pendingReturnsCount = $seller ? \App\Models\OrderReturn::forSeller($seller->id)->whereIn('status', ['requested', 'approved', 'in_transit'])->count() : 0;
    $lowStockCount = $seller ? \App\Models\Inventory::whereHas('location', fn($q) => $q->where('seller_id', $seller->id))->whereRaw('(quantity_on_hand - quantity_reserved) <= low_stock_threshold')->count() : 0;
    $storefrontUrl = $activeStore?->slug ? route('store.show', $activeStore->slug) : route('home');
@endphp

<script>
    function sellerStudioLayout(config) {
        return {
            sidebarOpen: false,
            pendingOrdersCount: Number(config.pendingOrdersCount) || 0,
            quickLiveCount: Number(config.quickLiveCount) || 0,
            isStoreOpen: Boolean(config.isStoreOpen),
            isTogglingStore: false,
            audioCtx: null,
            soundMuted: localStorage.getItem('seller_sound_muted') === 'true',

            init() {
                window.addEventListener('click', () => this.initAudio(), { once: true });
                setInterval(() => this.checkPendingOrders(), 20000);
            },

            toggleSound() {
                this.soundMuted = !this.soundMuted;
                localStorage.setItem('seller_sound_muted', this.soundMuted);
            },

            initAudio() {
                if (!this.audioCtx) {
                    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                    if (AudioContextClass) {
                        this.audioCtx = new AudioContextClass();
                    }
                }
                if (this.audioCtx && this.audioCtx.state === 'suspended') {
                    this.audioCtx.resume();
                }
            },

            async toggleStoreStatus(storeId) {
                if (this.isTogglingStore) return;
                this.isTogglingStore = true;
                try {
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    const res = await fetch(config.toggleStoreUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ store_id: storeId })
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.isStoreOpen = data.is_open;
                    }
                } catch (e) {
                    console.error(e);
                } finally {
                    this.isTogglingStore = false;
                }
            },

            async checkPendingOrders() {
                try {
                    const res = await fetch(config.pendingCountUrl, {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (res.ok) {
                        const data = await res.json();
                        if (data.has_fresh && data.pending_count > this.pendingOrdersCount) {
                            this.playOrderChime();
                        }
                        this.pendingOrdersCount = data.pending_count;
                    }
                } catch(e) {}
            },

            playOrderChime() {
                if (this.soundMuted) return;
                try {
                    this.initAudio();
                    if (!this.audioCtx) return;
                    const ctx = this.audioCtx;
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    const now = ctx.currentTime;
                    osc.frequency.setValueAtTime(587.33, now);
                    osc.frequency.setValueAtTime(880, now + 0.15);
                    gain.gain.setValueAtTime(0.25, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
                    osc.start(now);
                    osc.stop(now + 0.45);
                } catch (e) {}
            }
        };
    }
</script>
</head>

<body 
    class="h-full flex antialiased bg-slate-950 text-slate-100 font-sans" 
    x-data="sellerStudioLayout({
        pendingOrdersCount: {{ (int) $pendingOrdersCount }},
        quickLiveCount: {{ (int) $quickLiveCount }},
        isStoreOpen: {{ $isStoreOpen ? 'true' : 'false' }},
        toggleStoreUrl: '{{ route('seller.store.toggle_status') }}',
        pendingCountUrl: '{{ route('seller.orders.pending_count') }}'
    })"
>
    <!-- Sidebar -->
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 border-r border-slate-800 transition-transform duration-200 ease-in-out lg:translate-x-0 lg:static lg:inset-auto flex flex-col">
        <!-- Brand Header -->
        <div class="h-16 flex items-center justify-between px-6 border-b border-slate-800">
            <a href="{{ route('seller.dashboard') }}" class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center text-white font-black text-lg shadow-lg shadow-emerald-600/30">
                    1S
                </div>
                <div>
                    <span class="text-base font-bold text-white tracking-tight">Seller Studio</span>
                    <span class="text-[10px] uppercase font-bold tracking-widest text-emerald-400 block -mt-1 truncate max-w-[140px]">
                        {{ $seller?->business_name ?? 'OneStall Merchant' }}
                    </span>
                </div>
            </a>
            <button @click="sidebarOpen = false" class="lg:hidden text-slate-400 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Store Switcher & Badge -->
        <div class="p-3 mx-3 my-3 rounded-xl bg-slate-800/80 border border-slate-700/60">
            <div class="flex items-center justify-between text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
                <span>Active Store Outlet</span>
                @if($sellerStores->count() > 1)
                    <a href="{{ route('seller.stores.index') }}" class="text-emerald-400 hover:underline lowercase font-normal">switch ({{ $sellerStores->count() }})</a>
                @endif
            </div>
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-100 truncate pr-2">
                    {{ $activeStore?->name ?? 'Default Store' }}
                </span>
                <span 
                    class="w-2 h-2 rounded-full" 
                    :class="isStoreOpen ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'" 
                    :title="isStoreOpen ? 'Store Open & Accepting Orders' : 'Store Closed'"
                ></span>
            </div>
            <div class="mt-1.5 text-[10px] text-emerald-400 flex items-center justify-between">
                <span>⚡ {{ $activeStore?->accepts_quick_commerce ? 'Quick 15m Dispatch' : 'Standard Delivery' }}</span>
                <span class="text-slate-400 font-mono">{{ $activeStore?->location?->city ?? 'IN' }}</span>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 px-3 py-2 space-y-4 overflow-y-auto text-sm font-medium">
            @foreach($menuSections as $section)
                <div class="space-y-1">
                    @if(!empty($section['section']))
                        <div class="px-3 pt-2 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            {{ $section['section'] }}
                        </div>
                    @endif

                    @foreach($section['items'] as $item)
                        @php
                            $isActive = request()->routeIs($item['active'] ?? '');
                            $itemRoute = \Illuminate\Support\Facades\Route::has($item['route']) ? route($item['route']) : '#';
                            $badge = $item['badge'] ?? null;
                            $alpineBadge = null;

                            if ($item['route'] === 'seller.orders.index') {
                                $badge = $pendingOrdersCount > 0 ? "{$pendingOrdersCount} New" : null;
                                $alpineBadge = 'pendingOrdersCount > 0 ? (pendingOrdersCount + " New") : null';
                            } elseif ($item['route'] === 'seller.quick.live') {
                                $badge = $quickLiveCount > 0 ? "⚡ {$quickLiveCount}" : '⚡ Live';
                                $alpineBadge = 'quickLiveCount > 0 ? ("⚡ " + quickLiveCount) : "⚡ Live"';
                            } elseif ($item['route'] === 'seller.products.index' && $productCount > 0) {
                                $badge = "{$productCount}";
                            } elseif ($item['route'] === 'seller.returns.index' && $pendingReturnsCount > 0) {
                                $badge = "{$pendingReturnsCount} Req";
                            } elseif ($item['route'] === 'seller.inventory.index' && $lowStockCount > 0) {
                                $badge = "{$lowStockCount} Low";
                            } elseif ($item['route'] === 'seller.stores.index' && $sellerStores->count() > 0) {
                                $badge = "{$sellerStores->count()} Outlets";
                            } elseif ($item['route'] === 'seller.store.edit') {
                                $badge = $isStoreOpen ? 'Open' : 'Closed';
                                $alpineBadge = 'isStoreOpen ? "Open" : "Closed"';
                            }
                        @endphp

                        <a href="{{ $itemRoute }}"
                           class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition {{ $isActive ? 'bg-emerald-600/20 text-emerald-400 border border-emerald-500/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60' }}">
                            <div class="flex items-center space-x-2.5 truncate">
                                @if($item['icon'] === 'home')
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                @elseif($item['icon'] === 'plus')
                                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                @elseif($item['icon'] === 'bolt')
                                    <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                @elseif($item['icon'] === 'truck')
                                    <svg class="w-4 h-4 text-sky-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                                @elseif($item['icon'] === 'cube')
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                @elseif($item['icon'] === 'store' || $item['icon'] === 'building-storefront')
                                    <svg class="w-4 h-4 text-teal-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                @elseif($item['icon'] === 'users')
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                @elseif($item['icon'] === 'refresh')
                                    <svg class="w-4 h-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                @elseif($item['icon'] === 'chart-bar')
                                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                @elseif($item['icon'] === 'currency-rupee')
                                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6m-5 0a3 3 0 110 6H9l5 6M9 4h6"/></svg>
                                @else
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                                @endif
                                <span class="truncate">{{ $item['title'] }}</span>
                            </div>

                            @if($alpineBadge)
                                <span 
                                    x-show="{{ $alpineBadge }} !== null"
                                    x-text="{{ $alpineBadge }}"
                                    class="px-2 py-0.5 text-[10px] rounded-full font-bold bg-slate-800 text-slate-300 border border-slate-700"
                                >
                                    {{ $badge }}
                                </span>
                            @elseif($badge)
                                <span class="px-2 py-0.5 text-[10px] rounded-full font-bold bg-slate-800 text-slate-300 border border-slate-700">
                                    {{ $badge }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>

        <!-- Isolation Notice & User Profile -->
        <div class="p-3 border-t border-slate-800 bg-slate-900/60 text-[11px] text-slate-400">
            <div class="flex items-center justify-between mb-1.5">
                <span class="font-semibold text-slate-300 truncate max-w-[130px]">
                    {{ auth()->user()->name ?? 'Merchant' }}
                </span>
                <form method="POST" action="{{ route('seller.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-[11px] text-rose-400 hover:text-rose-300 font-semibold transition flex items-center space-x-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
            <div class="text-slate-500 font-mono text-[10px] truncate">
                {{ auth()->user()->email ?? 'seller@onestall.in' }}
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 bg-slate-950 overflow-hidden">
        <!-- Top Navigation -->
        <header class="h-16 bg-slate-900/70 backdrop-blur border-b border-slate-800 flex items-center justify-between px-6 sticky top-0 z-30">
            <div class="flex items-center space-x-4">
                <button @click="sidebarOpen = true" class="lg:hidden text-slate-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="text-sm font-semibold text-slate-200 flex items-center space-x-2">
                    <span>Seller Studio</span>
                    <span class="text-slate-500">&bull;</span>
                    <span class="text-xs text-emerald-400 font-mono">{{ $activeStore?->name ?? 'Merchant Portal' }}</span>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <a href="{{ $storefrontUrl }}" target="_blank" class="text-xs px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 transition flex items-center space-x-1">
                    <span>Live Storefront</span>
                    <span>&rarr;</span>
                </a>
                @if(auth()->user() && (auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin')))
                    <a href="{{ route('admin.dashboard') }}" class="text-xs px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 transition">
                        Admin Central
                    </a>
                @endif
                <div class="h-4 w-px bg-slate-800 mx-1"></div>
                <div class="flex items-center space-x-2 text-xs text-slate-300">
                    <span class="w-7 h-7 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 font-bold flex items-center justify-center text-[11px]">
                        {{ strtoupper(substr(auth()->user()->name ?? 'S', 0, 1)) }}
                    </span>
                    <span class="font-medium hidden md:inline-block text-slate-200">{{ auth()->user()->name ?? 'Merchant' }}</span>
                </div>
                <form method="POST" action="{{ route('seller.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 border border-rose-500/30 text-xs font-semibold transition" title="Log Out">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </header>

        <main class="flex-1 p-6 lg:p-8 overflow-y-auto">
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>
</body>
</html>
