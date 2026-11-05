<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Seller Portal' }} - {{ setting('name', 'OneStall') }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full flex antialiased bg-slate-950 text-slate-100 font-sans" x-data="{ sidebarOpen: false }">
    <!-- Sidebar -->
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 border-r border-slate-800 transition-transform duration-200 ease-in-out lg:translate-x-0 lg:static lg:inset-auto flex flex-col">
        <!-- Brand Header -->
        <div class="h-16 flex items-center justify-between px-6 border-b border-slate-800">
            <a href="{{ route('seller.dashboard') }}" class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-emerald-600 flex items-center justify-center text-white font-bold text-lg shadow-lg shadow-emerald-600/30">
                    OS
                </div>
                <div>
                    <span class="text-base font-bold text-white tracking-tight">Seller Studio</span>
                    <span class="text-[10px] uppercase font-bold tracking-widest text-emerald-400 block -mt-1">OneStall Merchant</span>
                </div>
            </a>
            <button @click="sidebarOpen = false" class="lg:hidden text-slate-400 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Store Switcher & Badge -->
        <div class="p-4 mx-3 my-3 rounded-xl bg-slate-800/80 border border-slate-700/60">
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Active Store</div>
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-100">Apex Retail - Indiranagar</span>
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" title="Store Live"></span>
            </div>
            <div class="mt-2 text-[10px] text-emerald-400 flex items-center space-x-1">
                <span>⚡ Store Connect Active</span>
                <span>(Blinkit mode)</span>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 px-4 py-2 space-y-1 overflow-y-auto text-sm font-medium">
            <a href="{{ route('seller.dashboard') }}" class="flex items-center px-3 py-2.5 rounded-lg text-emerald-400 bg-emerald-600/10 border border-emerald-500/20 font-semibold transition">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Overview
            </a>

            <div class="pt-4 px-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400">Inventory & Orders</div>

            <div class="px-3 py-2 text-xs text-slate-400 rounded flex items-center justify-between">
                <span>Digital Catalog</span>
                <span class="px-2 py-0.5 text-[10px] bg-slate-800 text-slate-300 rounded-full font-mono">148 SKUs</span>
            </div>

            <div class="px-3 py-2 text-xs text-slate-400 rounded flex items-center justify-between">
                <span>Quick Commerce Orders</span>
                <span class="px-2 py-0.5 text-[10px] bg-amber-500/20 text-amber-300 rounded-full font-mono font-bold">8 New</span>
            </div>

            <div class="px-3 py-2 text-xs text-slate-400 rounded flex items-center justify-between">
                <span>OneStall Cargo Shipments</span>
                <span class="px-2 py-0.5 text-[10px] bg-slate-800 text-slate-300 rounded-full font-mono">3 In-Transit</span>
            </div>

            <div class="pt-4 px-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400">Financials</div>

            <div class="px-3 py-2 text-xs text-slate-400 rounded flex items-center justify-between">
                <span>Commission Rate</span>
                <span class="text-xs font-mono font-bold text-slate-200">{{ setting('default_commission_pct', 10) }}%</span>
            </div>
        </nav>

        <!-- Isolation Notice & User Profile -->
        <div class="p-4 border-t border-slate-800 bg-slate-900/60 text-[11px] text-slate-400">
            <div class="flex items-center justify-between mb-2">
                <span class="font-semibold text-slate-300">Seller Session</span>
                <form method="POST" action="{{ route('seller.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-[11px] text-rose-400 hover:text-rose-300 font-semibold transition flex items-center space-x-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
            <div class="text-slate-400 font-mono text-[10px]">
                {{ auth()->user()->email ?? 'seller@onestall.in' }}
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 bg-slate-950">
        <!-- Top Navigation -->
        <header class="h-16 bg-slate-900/50 backdrop-blur border-b border-slate-800 flex items-center justify-between px-6 sticky top-0 z-30">
            <div class="flex items-center space-x-4">
                <button @click="sidebarOpen = true" class="lg:hidden text-slate-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="text-sm font-semibold text-slate-200">
                    Seller Portal
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <a href="{{ route('home') }}" class="text-xs px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 transition">
                    Live Storefront
                </a>
                @if(auth()->user() && (auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin')))
                    <a href="{{ route('admin.dashboard') }}" class="text-xs px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 transition">
                        Admin
                    </a>
                @endif
                <div class="h-4 w-px bg-slate-800 mx-1"></div>
                <div class="flex items-center space-x-2 text-xs text-slate-300">
                    <span class="w-7 h-7 rounded-full bg-amber-500/20 text-amber-400 border border-amber-500/30 font-bold flex items-center justify-center text-[11px]">
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
