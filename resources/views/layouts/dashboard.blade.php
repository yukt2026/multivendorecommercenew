<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dashboard' }} - {{ config('onestall.name', 'OneStall') }}</title>

    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Dark Mode Initializer (Prevents FOUC) -->
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>

@php
    $portal = $portal ?? (request()->is('seller*') ? 'seller' : 'admin');
    $menuSections = config("menu.{$portal}", []);
    $isSeller = $portal === 'seller';
@endphp

<body 
    class="h-full flex flex-col font-sans antialiased bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 transition-colors duration-200"
    x-data="{
        mobileSidebarOpen: false,
        sidebarCollapsed: localStorage.getItem('sidebar_collapsed') === 'true',
        darkMode: document.documentElement.classList.contains('dark'),
        toggleDarkMode() {
            this.darkMode = !this.darkMode;
            if (this.darkMode) {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            }
        },
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('sidebar_collapsed', this.sidebarCollapsed);
        },
        toasts: [],
        addToast(message, type = 'success') {
            const id = Date.now();
            this.toasts.push({ id, message, type });
            setTimeout(() => { this.removeToast(id); }, 4000);
        },
        removeToast(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        }
    }"
    x-init="
        @if(session('success'))
            addToast(@js(session('success')), 'success');
        @endif
        @if(session('error'))
            addToast(@js(session('error')), 'danger');
        @endif
        window.addEventListener('toast', e => addToast(e.detail.message, e.detail.type || 'success'));
    "
>
    <!-- Toast Notification Overlay (Top Right) -->
    <div class="fixed top-4 right-4 z-50 flex flex-col space-y-2 pointer-events-none max-w-sm w-full px-4 sm:px-0">
        <template x-for="t in toasts" :key="t.id">
            <div 
                class="pointer-events-auto p-4 rounded-2xl shadow-2xl border text-xs font-semibold flex items-center justify-between space-x-3 transition-all duration-300 animate-slide-in-right"
                :class="{
                    'bg-emerald-500 text-white border-emerald-400': t.type === 'success',
                    'bg-rose-500 text-white border-rose-400': t.type === 'danger',
                    'bg-indigo-600 text-white border-indigo-500': t.type === 'info',
                    'bg-amber-500 text-slate-950 border-amber-400 font-bold': t.type === 'warning'
                }"
            >
                <div class="flex items-center space-x-2.5">
                    <template x-if="t.type === 'success'">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    </template>
                    <template x-if="t.type === 'danger'">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    </template>
                    <span x-text="t.message"></span>
                </div>
                <button @click="removeToast(t.id)" class="opacity-70 hover:opacity-100 transition p-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
    </div>

    <!-- Mobile Drawer Sidebar Backdrop -->
    <div 
        x-show="mobileSidebarOpen" 
        x-cloak 
        @click="mobileSidebarOpen = false" 
        class="fixed inset-0 z-40 bg-slate-950/75 backdrop-blur-xs lg:hidden transition-opacity"
    ></div>

    <div class="flex h-full w-full overflow-hidden">
        <!-- Sidebar Shell (Responsive Drawer on Mobile + Collapsible on Desktop) -->
        <aside 
            :class="{
                'translate-x-0': mobileSidebarOpen,
                '-translate-x-full': !mobileSidebarOpen,
                'lg:w-20': sidebarCollapsed,
                'lg:w-64': !sidebarCollapsed
            }"
            class="fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 transition-all duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-auto flex flex-col shrink-0 select-none shadow-sm dark:shadow-none"
        >
            <!-- Sidebar Header / Logo -->
            <div class="h-16 flex items-center justify-between px-4 border-b border-slate-200/80 dark:border-slate-800">
                <a href="{{ $isSeller ? route('seller.dashboard') : route('admin.dashboard') }}" class="flex items-center space-x-3 overflow-hidden">
                    <div class="w-9 h-9 rounded-xl {{ $isSeller ? 'bg-emerald-600' : 'bg-indigo-600' }} flex items-center justify-center text-white font-extrabold text-lg shadow-md shrink-0">
                        1S
                    </div>
                    <div x-show="!sidebarCollapsed" x-transition class="whitespace-nowrap">
                        <span class="text-base font-bold text-slate-900 dark:text-white tracking-tight">OneStall</span>
                        <span class="block text-[10px] font-bold uppercase tracking-widest {{ $isSeller ? 'text-emerald-500' : 'text-indigo-500' }} -mt-1">
                            {{ $isSeller ? 'Seller Portal' : 'Admin Central' }}
                        </span>
                    </div>
                </a>

                <!-- Desktop Collapse Button -->
                <button 
                    @click="toggleSidebar()" 
                    class="hidden lg:flex p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    title="Toggle Sidebar Collapse"
                >
                    <svg class="w-4 h-4 transform transition-transform" :class="sidebarCollapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                    </svg>
                </button>

                <!-- Mobile Close Button -->
                <button @click="mobileSidebarOpen = false" class="lg:hidden text-slate-400 hover:text-slate-600 dark:hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Portal Mode Indicator -->
            <div x-show="!sidebarCollapsed" class="p-3 mx-3 my-2.5 rounded-xl bg-slate-100/70 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 flex items-center justify-between text-xs">
                <div class="flex items-center space-x-2">
                    <span class="w-2 h-2 rounded-full {{ $isSeller ? 'bg-emerald-400' : 'bg-indigo-400' }} animate-pulse"></span>
                    <span class="font-semibold text-slate-700 dark:text-slate-300">
                        {{ $isSeller ? 'Physical Store Connected' : 'Super Admin Active' }}
                    </span>
                </div>
                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                    INR
                </span>
            </div>

            <!-- Dynamic Navigation Menu -->
            <nav class="flex-1 px-3 py-3 space-y-6 overflow-y-auto overflow-x-hidden text-sm">
                @foreach($menuSections as $section)
                    <div class="space-y-1">
                        @if(!empty($section['section']))
                            <div 
                                x-show="!sidebarCollapsed" 
                                class="px-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500"
                            >
                                {{ $section['section'] }}
                            </div>
                        @endif

                        @foreach($section['items'] as $item)
                            @php
                                $hasPermission = true;
                                if (!empty($item['can'])) {
                                    $hasPermission = auth()->check() && auth()->user()->can($item['can']);
                                }
                                if (!empty($item['store_permission']) && auth()->check()) {
                                    $hasPermission = $hasPermission && auth()->user()->hasStorePermission($item['store_permission']);
                                }
                                $isActive = request()->routeIs($item['active'] ?? '');
                                $badgeColor = $item['badge_color'] ?? 'indigo';
                                $badgeClass = match($badgeColor) {
                                    'emerald' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
                                    'amber' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
                                    'sky' => 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20',
                                    'slate' => 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border-transparent',
                                    default => 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20',
                                };
                            @endphp

                            @if($hasPermission)
                                <a 
                                    href="{{ \Illuminate\Support\Facades\Route::has($item['route']) ? route($item['route']) : '#' }}"
                                    class="flex items-center px-3 py-2.5 rounded-xl font-medium transition-all duration-150 group {{ $isActive ? ($isSeller ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 font-bold shadow-xs' : 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-400 font-bold shadow-xs') : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800/80' }}"
                                    title="{{ $item['title'] }}"
                                >
                                    <!-- Menu Icon -->
                                    <div class="shrink-0 mr-3">
                                        @if($item['icon'] === 'home')
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                        @elseif($item['icon'] === 'puzzle')
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/></svg>
                                        @elseif($item['icon'] === 'users')
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                        @elseif($item['icon'] === 'store')
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        @elseif($item['icon'] === 'bolt')
                                            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        @elseif($item['icon'] === 'truck')
                                            <svg class="w-5 h-5 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                                        @elseif($item['icon'] === 'cube')
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                        @elseif($item['icon'] === 'cog')
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        @else
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                                        @endif
                                    </div>

                                    <!-- Menu Label & Badge -->
                                    <div x-show="!sidebarCollapsed" class="flex-1 flex items-center justify-between overflow-hidden">
                                        <span class="truncate">{{ $item['title'] }}</span>
                                        @if(!empty($item['badge']))
                                            <span class="ml-2 px-2 py-0.5 text-[10px] font-bold rounded-full border {{ $badgeClass }}">
                                                {{ $item['badge'] }}
                                            </span>
                                        @endif
                                    </div>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </nav>

            <!-- Bottom User & Platform Meta -->
            <div class="p-3 border-t border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/60">
                <div class="flex items-center space-x-3">
                    <x-avatar name="{{ auth()->check() ? auth()->user()->name : 'Admin' }}" status="online" size="sm" />
                    <div x-show="!sidebarCollapsed" class="flex-1 min-w-0">
                        <p class="text-xs font-bold text-slate-900 dark:text-white truncate">
                            {{ auth()->check() ? auth()->user()->name : 'OneStall Master' }}
                        </p>
                        <p class="text-[10px] text-slate-400 truncate">
                            {{ $isSeller ? (auth()->user()?->seller?->business_name ?? 'Merchant Portal') : 'super_admin@onestall.in' }}
                        </p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content Column -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50 dark:bg-slate-950">
            <!-- Sticky Topbar -->
            <header class="h-16 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-slate-200/80 dark:border-slate-800 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6">
                <!-- Left: Mobile Trigger & Search Bar -->
                <div class="flex items-center space-x-3 sm:space-x-4 flex-1 max-w-lg">
                    <button 
                        @click="mobileSidebarOpen = true" 
                        class="lg:hidden p-2 rounded-xl text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>

                    <!-- Global Quick Search Input with Keyboard Badge -->
                    <div class="relative w-full hidden sm:block">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <input 
                            type="text" 
                            placeholder="Search orders, sellers, stores, cargo waybills..." 
                            class="w-full pl-9 pr-14 py-2 text-xs bg-slate-100 dark:bg-slate-800/80 border border-transparent dark:border-slate-700/60 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 text-slate-900 dark:text-slate-100 placeholder-slate-400 transition"
                        />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <kbd class="hidden md:inline-block px-1.5 py-0.5 text-[10px] font-mono font-semibold text-slate-400 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded">Ctrl+K</kbd>
                        </div>
                    </div>
                </div>

                <!-- Right Topbar Controls -->
                <div class="flex items-center space-x-2 sm:space-x-3">
                    @if($isSeller && auth()->check() && (auth()->user()->seller || auth()->user()->storeStaff()->exists()))
                        @php
                            $sellerStores = auth()->user()->accessibleStores();
                            $activeStoreSessionId = session('seller_active_store_id');
                            $currentActiveStore = $activeStoreSessionId ? $sellerStores->firstWhere('id', $activeStoreSessionId) : null;
                        @endphp

                        <!-- Multi-Store Switcher -->
                        <x-dropdown align="right" width="64">
                            <x-slot name="trigger">
                                <button class="flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700/80 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-800 dark:text-slate-200 transition">
                                    <span class="w-2 h-2 rounded-full {{ ($currentActiveStore?->status?->value === 'active' || (!$currentActiveStore && $sellerStores->first()?->status?->value === 'active')) ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    <span class="max-w-[120px] sm:max-w-[160px] truncate">
                                        {{ $currentActiveStore ? $currentActiveStore->name : ($sellerStores->count() > 1 ? 'All Outlets (' . $sellerStores->count() . ')' : ($sellerStores->first()?->name ?? 'Default Store')) }}
                                    </span>
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <div class="px-3.5 py-2.5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                    <div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Merchant Store Switcher</span>
                                        <p class="text-[11px] text-slate-500">Scope metrics & inventory to outlet.</p>
                                    </div>
                                    <span class="px-1.5 py-0.5 text-[10px] font-mono rounded bg-slate-100 dark:bg-slate-800 text-slate-500 font-bold">
                                        {{ $sellerStores->count() }} {{ Str::plural('Outlet', $sellerStores->count()) }}
                                    </span>
                                </div>
                                <div class="py-1 text-xs max-h-60 overflow-y-auto">
                                    @if($sellerStores->count() > 1)
                                        <form method="POST" action="{{ route('seller.switch_store') }}">
                                            @csrf
                                            <input type="hidden" name="store_id" value="all">
                                            <button type="submit" class="w-full text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center justify-between {{ !$currentActiveStore ? 'font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50/50 dark:bg-indigo-950/30' : 'text-slate-700 dark:text-slate-300' }}">
                                                <span>All Outlets (Aggregated)</span>
                                                @if(!$currentActiveStore)
                                                    <span class="text-xs">✓</span>
                                                @endif
                                            </button>
                                        </form>
                                    @endif

                                    @foreach($sellerStores as $s)
                                        <form method="POST" action="{{ route('seller.switch_store') }}">
                                            @csrf
                                            <input type="hidden" name="store_id" value="{{ $s->id }}">
                                            <button type="submit" class="w-full text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center justify-between {{ ($currentActiveStore && $currentActiveStore->id === $s->id) ? 'font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50/50 dark:bg-indigo-950/30' : 'text-slate-700 dark:text-slate-300' }}">
                                                <div class="flex items-center space-x-2">
                                                    <span class="w-2 h-2 rounded-full {{ $s->status->value === 'active' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                                    <span class="truncate max-w-[180px]">{{ $s->name }}</span>
                                                </div>
                                                @if($currentActiveStore && $currentActiveStore->id === $s->id)
                                                    <span class="text-xs">✓</span>
                                                @endif
                                            </button>
                                        </form>
                                    @endforeach
                                </div>

                                <div class="pt-2 pb-1 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 text-xs">
                                    <a href="{{ route('seller.stores.create') }}" class="px-4 py-1.5 flex items-center space-x-2 text-indigo-600 dark:text-indigo-400 hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        <span>Add Physical Outlet</span>
                                    </a>
                                    <a href="{{ route('seller.connections.index') }}" class="px-4 py-1.5 flex items-center space-x-2 text-sky-600 dark:text-sky-400 hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        <span>Connect My Shop Wizard</span>
                                    </a>
                                    <a href="{{ route('seller.stores.reports') }}" class="px-4 py-1.5 flex items-center space-x-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                        <span>Store Analytics</span>
                                    </a>
                                </div>
                            </x-slot>
                        </x-dropdown>
                    @endif

                    <!-- Dark/Light Mode Switcher -->
                    <button 
                        type="button" 
                        @click="toggleDarkMode()" 
                        class="p-2 rounded-xl text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                        title="Toggle Light/Dark Theme"
                    >
                        <template x-if="!darkMode">
                            <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                        </template>
                        <template x-if="darkMode">
                            <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </template>
                    </button>

                    <!-- Notifications Dropdown -->
                    @php
                        $unreadNotificationsCount = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
                        $recentNotifications = auth()->check() ? auth()->user()->unreadNotifications()->latest()->limit(4)->get() : collect();
                    @endphp
                    <x-dropdown align="right" width="80">
                        <x-slot name="trigger">
                            <button class="relative p-2 rounded-xl text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                @if($unreadNotificationsCount > 0)
                                    <span class="absolute top-1.5 right-1.5 flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                                    </span>
                                @endif
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200">Alerts & Notifications</span>
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $unreadNotificationsCount > 0 ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $unreadNotificationsCount }} New
                                </span>
                            </div>
                            <div class="divide-y divide-slate-100 dark:divide-slate-800 max-h-64 overflow-y-auto">
                                @forelse($recentNotifications as $rn)
                                    <a href="{{ $rn->data['action_url'] ?? route('notifications.index') }}" class="p-3.5 block hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                                        <div class="flex items-center justify-between text-xs font-bold text-slate-900 dark:text-white">
                                            <span>{{ $rn->data['title'] ?? 'Notification' }}</span>
                                            <span class="text-[10px] text-slate-400">{{ $rn->created_at->diffForHumans() }}</span>
                                        </div>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">{{ $rn->data['message'] ?? '' }}</p>
                                    </a>
                                @empty
                                    <div class="p-4 text-center text-xs text-slate-400">
                                        No unread notifications.
                                    </div>
                                @endforelse
                            </div>
                            <div class="p-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                                @if($unreadNotificationsCount > 0)
                                    <form method="POST" action="{{ route('notifications.mark_all_read') }}" class="inline">
                                        @csrf
                                        <button type="submit" class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Mark all read</button>
                                    </form>
                                @else
                                    <span></span>
                                @endif
                                <a href="{{ route('notifications.index') }}" class="font-semibold text-slate-600 dark:text-slate-300 hover:underline">View All</a>
                            </div>
                        </x-slot>
                    </x-dropdown>

                    <!-- Quick Actions Menu -->
                    <x-dropdown align="right" width="56">
                        <x-slot name="trigger">
                            <button class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-sm transition flex items-center space-x-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                <span class="hidden sm:inline">Create</span>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="py-1 text-xs font-medium">
                                <a href="#" class="flex items-center px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                                    <span class="w-2 h-2 rounded-full bg-amber-400 mr-2"></span>
                                    New Quick Dispatch
                                </a>
                                <a href="#" class="flex items-center px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                                    <span class="w-2 h-2 rounded-full bg-sky-400 mr-2"></span>
                                    Book Cargo Waybill
                                </a>
                                <a href="#" class="flex items-center px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                                    <span class="w-2 h-2 rounded-full bg-emerald-400 mr-2"></span>
                                    Connect Store POS
                                </a>
                            </div>
                        </x-slot>
                    </x-dropdown>

                    <!-- User Profile Dropdown -->
                    <x-dropdown align="right" width="56">
                        <x-slot name="trigger">
                            <button class="flex items-center space-x-2 p-1 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                                <x-avatar name="{{ auth()->check() ? auth()->user()->name : 'OneStall Admin' }}" size="sm" />
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800">
                                <p class="text-xs font-bold text-slate-900 dark:text-white truncate">
                                    {{ auth()->check() ? auth()->user()->name : 'Master Administrator' }}
                                </p>
                                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60">
                                    {{ $isSeller ? 'Seller Role' : 'Super Admin' }}
                                </span>
                            </div>
                            <div class="py-1 text-xs">
                                <a href="{{ route('home') }}" target="_blank" class="flex items-center px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 transition">
                                    Customer Storefront
                                </a>
                                <a href="{{ route('admin.uikit') }}" class="flex items-center px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 transition">
                                    UI Kit Showcase
                                </a>
                                @if($isSeller)
                                    <a href="{{ route('admin.dashboard') }}" class="flex items-center px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 text-indigo-600 dark:text-indigo-400 font-bold transition">
                                        Switch to Admin
                                    </a>
                                @else
                                    <a href="{{ route('seller.dashboard') }}" class="flex items-center px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 text-emerald-600 dark:text-emerald-400 font-bold transition">
                                        Switch to Seller
                                    </a>
                                @endif
                            </div>
                        </x-slot>
                    </x-dropdown>
                </div>
            </header>

            <!-- Main Body View Container -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto">
                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
