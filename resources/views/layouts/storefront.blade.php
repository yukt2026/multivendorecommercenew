<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- SEO Meta, OpenGraph, Canonical, Webmaster & JSON-LD (Blueprint Section 32) -->
    <x-seo-head 
        :meta="$seoMeta ?? null" 
        :type="$seoType ?? 'website'" 
        :model="$seoModel ?? null" 
        :overrides="$seoOverrides ?? (isset($title) ? ['title' => $title] : [])" 
        :paginator="$seoPaginator ?? null" 
    />

    <!-- Favicons -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    <!-- Preconnect Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full flex flex-col font-sans antialiased text-slate-800 bg-slate-50 pb-16 sm:pb-0"
      x-data="{
        mobileCategoryOpen: false,
        megaMenuOpen: false
      }">

    <!-- Top Announcement & Quick Delivery Ticker -->
    <div class="bg-slate-950 text-slate-200 px-4 py-2 text-xs font-semibold shadow-inner">
        <div class="max-w-7xl mx-auto w-full flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <span class="inline-block w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                <span>
                    ⚡ <strong>Quick Commerce Live:</strong> 10-30 Min Delivery within {{ setting('default_delivery_radius_km', 8) }} km radius
                </span>
            </div>
            <div class="hidden sm:flex items-center space-x-6 text-xs font-medium text-slate-300">
                <span>Free delivery on orders over <strong class="text-amber-400">{{ format_currency(setting('free_delivery_threshold', 499)) }}</strong></span>
                <a href="{{ route('seller.dashboard') }}" class="hover:text-amber-300 transition">Sell on OneStall</a>
                <a href="{{ route('admin.dashboard') }}" class="hover:text-amber-300 transition">Admin Portal</a>
            </div>
        </div>
    </div>

    <!-- Main Sticky Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-sm" x-data="{ searchType: 'all', searchFocused: false, query: '', suggestions: { products: [], stores: [], brands: [] }, loading: false,
        async fetchSuggestions() {
            if (this.query.length < 2) {
                this.suggestions = { products: [], stores: [], brands: [] };
                return;
            }
            this.loading = true;
            try {
                const res = await fetch(`/api/storefront/search/suggestions?q=${encodeURIComponent(this.query)}&type=${this.searchType}`);
                const data = await res.json();
                this.suggestions = data.results || { products: [], stores: [], brands: [] };
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        }
    }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20 gap-4">

                <!-- Logo & Location Group -->
                <div class="flex items-center space-x-4 lg:space-x-6 flex-shrink-0">
                    <a href="{{ route('home') }}" class="flex items-center space-x-2.5 group">
                        <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-indigo-700 via-indigo-600 to-amber-400 flex items-center justify-center text-white font-extrabold text-2xl shadow-md group-hover:scale-105 transition-transform">
                            1S
                        </div>
                        <div>
                            <span class="text-2xl font-black tracking-tight text-slate-900 group-hover:text-indigo-600 transition">OneStall<span class="text-amber-500">.in</span></span>
                            <span class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase -mt-1">Omnichannel Commerce</span>
                        </div>
                    </a>

                    <!-- Location Selector Button -->
                    <button type="button"
                            @click="$store.location.modalOpen = true"
                            class="hidden md:flex items-center space-x-2 px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 hover:border-slate-300 transition text-left">
                        <div class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            </svg>
                        </div>
                        <div class="text-xs">
                            <span class="block text-[10px] font-medium text-slate-400 uppercase tracking-wider">Deliver to</span>
                            <span class="font-bold text-slate-900 flex items-center max-w-[140px] truncate" x-text="$store.location.current.address_label || 'Select Location'">
                                Bengaluru 560001
                            </span>
                        </div>
                        <svg class="w-3.5 h-3.5 text-slate-400 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>

                <!-- Big Omnisearch Bar with Tabs -->
                <div class="flex-1 max-w-2xl relative hidden sm:block" @click.outside="searchFocused = false">
                    <div class="relative flex items-center bg-slate-100 rounded-2xl border-2 border-transparent focus-within:border-indigo-600 focus-within:bg-white focus-within:shadow-lg transition-all duration-200">
                        <!-- Search Type Switcher Pill -->
                        <div class="pl-2">
                            <select x-model="searchType" @change="fetchSuggestions()" class="bg-transparent text-xs font-bold text-slate-600 border-none rounded-lg focus:ring-0 py-1.5 pl-2 pr-6 cursor-pointer hover:text-indigo-600">
                                <option value="all">All</option>
                                <option value="products">Products</option>
                                <option value="stores">Stores</option>
                                <option value="brands">Brands</option>
                            </select>
                        </div>

                        <span class="h-6 w-px bg-slate-300 mx-1"></span>

                        <input type="text"
                               x-model="query"
                               @focus="searchFocused = true"
                               @input.debounce.300ms="fetchSuggestions()"
                               placeholder="Search fresh groceries, local electronics, stores, brands..."
                               class="w-full bg-transparent border-0 focus:ring-0 text-sm py-2.5 px-3 text-slate-800 placeholder-slate-400" />

                        <!-- Clear Button -->
                        <button x-show="query.length > 0" @click="query = ''; suggestions = { products: [], stores: [], brands: [] }" type="button" class="text-slate-400 hover:text-slate-600 pr-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>

                        <!-- Search Submit Button -->
                        <div class="pr-1.5">
                            <button type="button" class="w-10 h-10 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white flex items-center justify-center transition shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Live Autocomplete Suggestions Box -->
                    <div x-show="searchFocused && (suggestions.products.length > 0 || suggestions.stores.length > 0 || suggestions.brands.length > 0 || loading)"
                         x-transition
                         class="absolute top-full left-0 right-0 mt-2 bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden z-50 max-h-[480px] overflow-y-auto divide-y divide-slate-100">

                        <!-- Loading State -->
                        <div x-show="loading" class="p-4 text-center text-xs text-slate-400 flex items-center justify-center space-x-2">
                            <svg class="animate-spin h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>Searching catalog & nearby stores...</span>
                        </div>

                        <!-- Products Match -->
                        <template x-if="suggestions.products.length > 0">
                            <div class="p-3">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 px-2 block mb-1">Products</span>
                                <div class="space-y-1">
                                    <template x-for="item in suggestions.products" :key="item.id">
                                        <a :href="item.url" class="flex items-center justify-between p-2 rounded-xl hover:bg-slate-50 transition">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                                    <template x-if="item.image">
                                                        <img :src="item.image" class="w-full h-full object-contain p-0.5" />
                                                    </template>
                                                    <template x-if="!item.image">
                                                        <span class="text-[10px] text-slate-400 font-bold">1S</span>
                                                    </template>
                                                </div>
                                                <div>
                                                    <div class="text-xs font-semibold text-slate-900 line-clamp-1" x-text="item.title"></div>
                                                    <div class="text-[11px] text-slate-500">
                                                        <span x-text="item.brand || item.category || 'Product'"></span>
                                                        <template x-if="item.is_quick">
                                                            <span class="text-[10px] font-extrabold text-amber-600 ml-1">⚡ 15m</span>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right flex-shrink-0">
                                                <div class="text-xs font-bold text-slate-900" x-text="item.formatted_price"></div>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- Stores Match -->
                        <template x-if="suggestions.stores.length > 0">
                            <div class="p-3 bg-slate-50/50">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 px-2 block mb-1">Stores & Outlets</span>
                                <div class="space-y-1">
                                    <template x-for="store in suggestions.stores" :key="store.id">
                                        <a :href="store.url" class="flex items-center justify-between p-2 rounded-xl hover:bg-white transition shadow-xs">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                                    🏪
                                                </div>
                                                <div>
                                                    <div class="text-xs font-bold text-slate-900" x-text="store.name"></div>
                                                    <div class="text-[10px] text-slate-500">
                                                        <span x-text="store.city"></span> &bull;
                                                        <span :class="store.is_open ? 'text-emerald-600 font-semibold' : 'text-slate-400'" x-text="store.is_open ? 'Open Now' : 'Closed'"></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="text-xs text-indigo-600 font-semibold">Visit &rarr;</span>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- Brands Match -->
                        <template x-if="suggestions.brands.length > 0">
                            <div class="p-3">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 px-2 block mb-1">Brands</span>
                                <div class="flex flex-wrap gap-2 px-2">
                                    <template x-for="brand in suggestions.brands" :key="brand.id">
                                        <a :href="brand.url" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                            <span x-text="brand.name"></span>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Right Action Bar: Wishlist, Cart Drawer, User Dropdown -->
                <div class="flex items-center space-x-2 sm:space-x-3 flex-shrink-0">

                    <!-- Wishlist Button -->
                    <a href="{{ route('wishlist.index') }}"
                       class="relative p-2.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 hover:text-rose-500 transition"
                       title="View Wishlist">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <span x-show="$store.wishlist.count > 0"
                              x-text="$store.wishlist.count"
                              class="absolute -top-1 -right-1 px-1.5 py-0.2 rounded-full text-[10px] font-black bg-rose-500 text-white shadow-sm">
                        </span>
                    </a>

                    <!-- Mini-Cart Drawer Trigger -->
                    <button type="button"
                            @click="$store.cart.toggleDrawer()"
                            class="relative flex items-center space-x-2 px-3.5 py-2 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold text-xs shadow-md shadow-emerald-500/20 active:scale-95 transition">
                        <div class="relative">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            <span x-show="$store.cart.itemCount > 0"
                                  x-text="$store.cart.itemCount"
                                  class="absolute -top-2 -right-2 px-1.5 py-0.2 rounded-full text-[10px] font-black bg-amber-400 text-slate-950 shadow-sm">
                            </span>
                        </div>
                        <div class="hidden md:block text-left pl-1">
                            <div class="text-[10px] text-emerald-100 font-medium">My Cart</div>
                            <div class="text-xs font-black tracking-tight" x-text="$store.cart.formattedSubtotal || '₹0.00'">₹0.00</div>
                        </div>
                    </button>

                    <!-- Account Menu -->
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        @auth
                            <button @click="open = !open" type="button" class="flex items-center space-x-2 p-1.5 rounded-xl border border-slate-200 hover:bg-slate-50 transition">
                                <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white font-bold text-xs flex items-center justify-center">
                                    {{ substr(auth()->user()->name, 0, 1) }}
                                </div>
                                <span class="hidden lg:inline text-xs font-bold text-slate-800 max-w-[100px] truncate">
                                    {{ auth()->user()->name }}
                                </span>
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>

                            <div x-show="open" x-transition class="absolute right-0 mt-2 w-56 rounded-2xl bg-white shadow-2xl border border-slate-200 py-2 z-50 text-xs divide-y divide-slate-100">
                                <div class="px-4 py-2.5">
                                    <p class="font-bold text-slate-900">{{ auth()->user()->name }}</p>
                                    <p class="text-[11px] text-slate-500 truncate">{{ auth()->user()->email ?? auth()->user()->phone }}</p>
                                </div>
                                <div class="py-1">
                                    <a href="{{ route('orders.index') }}" class="block px-4 py-2 text-slate-700 hover:bg-slate-50 font-medium">My Orders</a>
                                    <a href="{{ route('notifications.index') }}" class="block px-4 py-2 text-slate-700 hover:bg-slate-50 font-medium">Notifications &amp; Preferences</a>
                                    <button type="button" @click="$store.location.modalOpen = true; open = false" class="w-full text-left px-4 py-2 text-slate-700 hover:bg-slate-50 font-medium">Saved Addresses</button>
                                </div>
                                @if(auth()->user()->hasRole('seller_owner') || auth()->user()->seller)
                                    <div class="py-1">
                                        <a href="{{ route('seller.dashboard') }}" class="block px-4 py-2 text-amber-600 hover:bg-amber-50 font-semibold">Seller Central &rarr;</a>
                                    </div>
                                @endif
                                @if(auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin'))
                                    <div class="py-1">
                                        <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-indigo-600 hover:bg-indigo-50 font-semibold">Admin Panel &rarr;</a>
                                    </div>
                                @endif
                                <div class="py-1">
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="w-full text-left px-4 py-2 text-rose-600 hover:bg-rose-50 font-medium">Sign Out</button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <a href="{{ route('login') }}" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-800 font-bold text-xs shadow-sm transition flex items-center space-x-1.5">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                <span>Sign In</span>
                            </a>
                        @endauth
                    </div>

                </div>

            </div>
        </div>

        <!-- Mega-Menu Categories Ribbon -->
        <nav class="border-t border-slate-100 bg-white hidden sm:block">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-11 text-xs">
                    <!-- Mega Menu Trigger -->
                    <div class="relative" @mouseleave="megaMenuOpen = false">
                        <button type="button"
                                @mouseenter="megaMenuOpen = true"
                                @click="megaMenuOpen = !megaMenuOpen"
                                class="inline-flex items-center space-x-1.5 font-extrabold text-indigo-600 hover:text-indigo-800 pr-4 border-r border-slate-200 py-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <span>ALL CATEGORIES</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>

                        <!-- Mega Menu Panel -->
                        <div x-show="megaMenuOpen"
                             x-transition
                             class="absolute left-0 top-full w-[850px] bg-white rounded-2xl shadow-2xl border border-slate-200 p-6 z-50 grid grid-cols-3 gap-6">
                            @if(isset($categories))
                                @foreach($categories->take(6) as $cat)
                                    <div class="space-y-2">
                                        <a href="{{ route('home') }}?category={{ $cat->slug }}" class="font-bold text-slate-900 hover:text-indigo-600 flex items-center space-x-1.5 text-xs">
                                            <span>{{ $cat->name }}</span>
                                            @if($cat->is_quick_eligible)
                                                <span class="text-[9px] font-extrabold text-amber-500">⚡</span>
                                            @endif
                                        </a>
                                        @if($cat->children && $cat->children->isNotEmpty())
                                            <ul class="space-y-1.5 pl-2 text-slate-500 text-[11px]">
                                                @foreach($cat->children->take(4) as $sub)
                                                    <li>
                                                        <a href="{{ route('home') }}?category={{ $sub->slug }}" class="hover:text-indigo-600 hover:underline">
                                                            {{ $sub->name }}
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    <!-- Quick Category Pills -->
                    <div class="flex items-center space-x-5 overflow-x-auto no-scrollbar font-semibold text-slate-600">
                        <a href="{{ route('home') }}#quick-commerce" class="text-amber-600 hover:text-amber-700 flex items-center space-x-1 flex-shrink-0">
                            <span>⚡ Quick Commerce (15m)</span>
                        </a>
                        <a href="{{ route('home') }}#nearby-stores" class="hover:text-indigo-600 flex-shrink-0">Nearby Stores</a>
                        <a href="{{ route('home') }}#deals" class="hover:text-rose-600 flex-shrink-0">Top Deals</a>
                        <a href="{{ route('home') }}#bestsellers" class="hover:text-indigo-600 flex-shrink-0">Best Sellers</a>
                        <a href="{{ route('home') }}#brands" class="hover:text-indigo-600 flex-shrink-0">Brands</a>
                        <a href="{{ route('home') }}#onestall-stores" class="hover:text-indigo-600 flex-shrink-0">OneStall Flagship</a>
                    </div>

                    <!-- Customer Support Hotline -->
                    <div class="hidden xl:flex items-center space-x-1 text-slate-400 font-medium">
                        <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        <span>Support: 1800-ONESTALL</span>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content Slot -->
    <main class="flex-1">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <!-- Mini-Cart Slideout Drawer (Alpine.js) -->
    <div x-show="$store.cart.open"
         x-cloak
         class="fixed inset-0 z-50 overflow-hidden"
         aria-labelledby="slide-over-title" role="dialog" aria-modal="true">

        <!-- Background Backdrop -->
        <div x-show="$store.cart.open"
             x-transition:enter="ease-in-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in-out duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="$store.cart.open = false"
             class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity"></div>

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <div x-show="$store.cart.open"
                 x-transition:enter="transform transition ease-in-out duration-300 sm:duration-400"
                 x-transition:enter-start="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transform transition ease-in-out duration-300 sm:duration-400"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full"
                 class="w-screen max-w-md bg-white shadow-2xl flex flex-col">

                <!-- Drawer Header -->
                <div class="p-4 sm:p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-bold">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-extrabold text-slate-900" id="slide-over-title">Your Cart</h2>
                            <p class="text-[11px] text-slate-500"><span x-text="$store.cart.itemCount">0</span> items selected</p>
                        </div>
                    </div>
                    <button type="button" @click="$store.cart.open = false" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-200/50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Free Shipping Progress Indicator -->
                <div class="p-3 bg-emerald-50 border-b border-emerald-100">
                    <div class="flex items-center justify-between text-xs font-semibold text-emerald-900 mb-1.5">
                        <template x-if="$store.cart.freeQualified">
                            <span class="flex items-center space-x-1">
                                <span>🎉</span>
                                <span><strong>Congratulations!</strong> You unlocked FREE Delivery!</span>
                            </span>
                        </template>
                        <template x-if="!$store.cart.freeQualified">
                            <span>Add <strong x-text="$store.cart.freeRemaining ? '₹' + $store.cart.freeRemaining : ''"></strong> more for <strong>FREE Delivery</strong></span>
                        </template>
                        <span class="text-[10px] font-bold" x-text="$store.cart.progressPercent + '%'"></span>
                    </div>
                    <div class="w-full bg-emerald-200/60 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-emerald-600 h-1.5 rounded-full transition-all duration-300" :style="'width: ' + $store.cart.progressPercent + '%'"></div>
                    </div>
                </div>

                <!-- Cart Items List / Scrollable Container -->
                <div class="flex-1 overflow-y-auto p-4 sm:p-5 divide-y divide-slate-100">
                    <!-- Empty State -->
                    <template x-if="$store.cart.items.length === 0">
                        <div class="h-full flex flex-col items-center justify-center text-center py-16">
                            <div class="w-20 h-20 rounded-full bg-slate-100 flex items-center justify-center text-slate-300 mb-4">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            </div>
                            <h3 class="text-base font-bold text-slate-900">Your cart is empty</h3>
                            <p class="text-xs text-slate-500 max-w-xs mt-1">Explore fresh grocery deals, nearby local shops and national products.</p>
                            <button type="button" @click="$store.cart.open = false" class="mt-6 px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md">
                                Start Shopping
                            </button>
                        </div>
                    </template>

                    <!-- Items List -->
                    <template x-for="item in $store.cart.items" :key="item.id">
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div class="w-14 h-14 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center overflow-hidden flex-shrink-0">
                                <template x-if="item.image_url">
                                    <img :src="item.image_url" class="w-full h-full object-contain p-1" />
                                </template>
                                <template x-if="!item.image_url">
                                    <span class="text-xs text-slate-300 font-bold">1S</span>
                                </template>
                            </div>

                            <div class="flex-1 min-w-0">
                                <h4 class="text-xs font-bold text-slate-900 truncate" x-text="item.title"></h4>
                                <div class="text-[11px] text-slate-500" x-text="item.variant_name"></div>
                                <div class="flex items-center space-x-1 mt-0.5">
                                    <span class="text-xs font-black text-slate-900" x-text="item.formatted_price"></span>
                                    <template x-if="item.mrp > item.price">
                                        <span class="text-[10px] text-slate-400 line-through" x-text="item.formatted_mrp"></span>
                                    </template>
                                </div>
                            </div>

                            <!-- Stepper & Delete -->
                            <div class="flex flex-col items-end space-y-1 flex-shrink-0">
                                <div class="inline-flex items-center rounded-xl bg-indigo-50 border border-indigo-200 overflow-hidden text-indigo-700">
                                    <button type="button" @click="$store.cart.updateQuantity(item.id, item.quantity - 1)" class="px-2 py-1 hover:bg-indigo-200 font-black text-xs">-</button>
                                    <span class="px-2 text-xs font-extrabold" x-text="item.quantity"></span>
                                    <button type="button" @click="$store.cart.updateQuantity(item.id, item.quantity + 1)" class="px-2 py-1 hover:bg-indigo-200 font-black text-xs">+</button>
                                </div>
                                <button type="button" @click="$store.cart.removeItem(item.id)" class="text-[10px] text-rose-500 hover:text-rose-700">Remove</button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Drawer Footer Summary -->
                <div x-show="$store.cart.items.length > 0" class="p-4 sm:p-5 border-t border-slate-200 bg-slate-50 space-y-3">
                    <div class="space-y-1.5 text-xs">
                        <div class="flex items-center justify-between text-slate-600">
                            <span>Subtotal</span>
                            <span class="font-bold text-slate-900" x-text="$store.cart.formattedSubtotal">₹0.00</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-600">
                            <span>Delivery Fee</span>
                            <span :class="$store.cart.freeQualified ? 'text-emerald-600 font-bold' : 'font-bold text-slate-900'" x-text="$store.cart.freeQualified ? 'FREE' : '{{ format_currency(setting('base_delivery_fee', 40)) }}'"></span>
                        </div>
                    </div>

                    <a href="{{ route('cart.index') }}" class="w-full py-3.5 px-4 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-extrabold text-sm flex items-center justify-between shadow-lg shadow-emerald-500/20 active:scale-95 transition">
                        <span>View Cart & Checkout</span>
                        <span x-text="$store.cart.formattedSubtotal">₹0.00</span>
                    </a>
                </div>

            </div>
        </div>
    </div>

    <!-- Location Selector Modal (Alpine.js) -->
    <div x-show="$store.location.modalOpen"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         role="dialog" aria-modal="true">

        <div class="flex items-center justify-center min-h-screen p-4 text-center">
            <!-- Modal Backdrop -->
            <div x-show="$store.location.modalOpen"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="$store.location.modalOpen = false"
                 class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity"></div>

            <!-- Modal Content Panel -->
            <div x-show="$store.location.modalOpen"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block w-full max-w-lg p-6 my-8 text-left align-middle bg-white rounded-3xl shadow-2xl border border-slate-200 transform transition-all relative">

                <!-- Header -->
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900">Choose Delivery Location</h3>
                        <p class="text-xs text-slate-500">Find nearby hyper-local stores and instant delivery zones</p>
                    </div>
                    <button type="button" @click="$store.location.modalOpen = false" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- GPS Location Button -->
                <div class="mt-4">
                    <button type="button"
                            @click="$store.location.detectCurrentLocation()"
                            :disabled="$store.location.detecting"
                            class="w-full py-3 px-4 rounded-2xl bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-indigo-700 font-bold text-xs flex items-center justify-between transition">
                        <div class="flex items-center space-x-2.5">
                            <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 004 11m0 0a8 8 0 00.99 7.132"/></svg>
                            </div>
                            <span x-text="$store.location.detecting ? 'Detecting your GPS coordinates...' : 'Detect my current location (GPS)'">Detect my current location</span>
                        </div>
                        <span class="text-xs font-semibold">&rarr;</span>
                    </button>
                    <div x-show="$store.location.errorMessage" x-text="$store.location.errorMessage" class="text-xs text-rose-500 mt-2 font-medium"></div>
                </div>

                <!-- Search Input -->
                <div class="mt-4">
                    <div class="relative">
                        <input type="text"
                               x-model="$store.location.searchQuery"
                               @input.debounce.300ms="$store.location.search()"
                               placeholder="Enter pincode or city name..."
                               class="w-full pl-10 pr-4 py-2.5 text-xs bg-slate-50 border border-slate-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-indigo-500 transition" />
                        <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                </div>

                <!-- Saved Addresses (if logged in) -->
                <template x-if="$store.location.saved.length > 0">
                    <div class="mt-4">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Saved Addresses</span>
                        <div class="space-y-1.5 max-h-40 overflow-y-auto">
                            <template x-for="addr in $store.location.saved" :key="addr.id">
                                <button type="button"
                                        @click="$store.location.setLocation({
                                            city: addr.city,
                                            pincode: addr.pincode,
                                            state: addr.state,
                                            lat: addr.lat,
                                            lng: addr.lng,
                                            address_label: addr.name + ' (' + addr.city + ' ' + addr.pincode + ')'
                                        })"
                                        class="w-full text-left p-2.5 rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/50 transition flex items-center justify-between text-xs">
                                    <div>
                                        <div class="font-bold text-slate-900" x-text="addr.name + ' - ' + addr.type"></div>
                                        <div class="text-[11px] text-slate-500" x-text="addr.line1 + ', ' + addr.city + ' ' + addr.pincode"></div>
                                    </div>
                                    <span class="text-[10px] font-bold text-indigo-600">Select</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <!-- Popular Indian Cities -->
                <div class="mt-4">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Popular Cities</span>
                    <div class="grid grid-cols-2 gap-2 max-h-56 overflow-y-auto">
                        <template x-for="c in $store.location.searchResults" :key="c.pincode + c.city">
                            <button type="button"
                                    @click="$store.location.setLocation({
                                        city: c.city,
                                        pincode: c.pincode,
                                        state: c.state,
                                        lat: c.lat,
                                        lng: c.lng,
                                        address_label: c.city + ' ' + c.pincode
                                    })"
                                    class="p-2.5 rounded-xl border border-slate-100 hover:border-indigo-300 hover:bg-slate-50 text-left transition flex items-center space-x-2">
                                <span class="text-base">📍</span>
                                <div>
                                    <div class="text-xs font-bold text-slate-900" x-text="c.city"></div>
                                    <div class="text-[10px] text-slate-500" x-text="c.pincode"></div>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation Bar (sm:hidden) -->
    <nav class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200 py-1.5 px-3 shadow-lg">
        <div class="grid grid-cols-5 gap-1 text-center">
            <!-- Home -->
            <a href="{{ route('home') }}" class="flex flex-col items-center py-1 text-indigo-600 font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span class="text-[10px] mt-0.5">Home</span>
            </a>

            <!-- Categories -->
            <a href="{{ route('home') }}#categories" class="flex flex-col items-center py-1 text-slate-600 hover:text-indigo-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                <span class="text-[10px] mt-0.5">Categories</span>
            </a>

            <!-- Stores -->
            <a href="{{ route('home') }}#nearby-stores" class="flex flex-col items-center py-1 text-slate-600 hover:text-indigo-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span class="text-[10px] mt-0.5">Stores</span>
            </a>

            <!-- Cart Trigger -->
            <button type="button" @click="$store.cart.toggleDrawer()" class="flex flex-col items-center py-1 text-slate-600 hover:text-indigo-600 relative">
                <div class="relative">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    <span x-show="$store.cart.itemCount > 0"
                          x-text="$store.cart.itemCount"
                          class="absolute -top-1.5 -right-2 px-1 py-0.2 rounded-full text-[9px] font-black bg-emerald-600 text-white">
                    </span>
                </div>
                <span class="text-[10px] mt-0.5">Cart</span>
            </button>

            <!-- Account -->
            @auth
                <a href="{{ route('home') }}" class="flex flex-col items-center py-1 text-slate-600 hover:text-indigo-600">
                    <div class="w-5 h-5 rounded-full bg-indigo-600 text-white text-[10px] font-bold flex items-center justify-center">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <span class="text-[10px] mt-0.5">Account</span>
                </a>
            @else
                <a href="{{ route('login') }}" class="flex flex-col items-center py-1 text-slate-600 hover:text-indigo-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span class="text-[10px] mt-0.5">Sign In</span>
                </a>
            @endauth
        </div>
    </nav>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-400 py-16 border-t border-slate-800 mt-16 text-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Trust Badges Strip -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 pb-12 border-b border-slate-800">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-400/10 text-amber-400 flex items-center justify-center text-lg flex-shrink-0">
                        ⚡
                    </div>
                    <div>
                        <h4 class="font-bold text-white text-xs">10-30 Min Delivery</h4>
                        <p class="text-[11px] text-slate-500">From verified local neighbourhood stores</p>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-lg flex-shrink-0">
                        🛡️
                    </div>
                    <div>
                        <h4 class="font-bold text-white text-xs">100% Genuine</h4>
                        <p class="text-[11px] text-slate-500">Direct from brand authorized sellers</p>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-lg flex-shrink-0">
                        🔒
                    </div>
                    <div>
                        <h4 class="font-bold text-white text-xs">Secure Payments</h4>
                        <p class="text-[11px] text-slate-500">UPI, Cards, NetBanking & COD</p>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center text-lg flex-shrink-0">
                        🔄
                    </div>
                    <div>
                        <h4 class="font-bold text-white text-xs">Easy 7-Day Returns</h4>
                        <p class="text-[11px] text-slate-500">Hassle-free doorstep pickup & refunds</p>
                    </div>
                </div>
            </div>

            <!-- Footer Columns -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-8 py-12">
                <!-- Brand Info -->
                <div class="md:col-span-2 space-y-4">
                    <div class="flex items-center space-x-2 text-white font-black text-xl">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-amber-400 flex items-center justify-center text-white text-base">
                            1S
                        </div>
                        <span>OneStall.in</span>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed max-w-sm">
                        India's pioneering omnichannel retail platform. Unifying national marketplace selections, Blinkit-speed local grocery deliveries, physical store connections, and OneStall Cargo freight logistics.
                    </p>
                    <div class="pt-2">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-300 block mb-2">Subscribe to OneStall Weekly Deals</span>
                        <div class="flex max-w-xs">
                            <input type="email" placeholder="Enter your email" class="w-full bg-slate-800 border-0 rounded-l-xl text-xs text-white placeholder-slate-500 focus:ring-1 focus:ring-indigo-500 py-2 px-3" />
                            <button type="button" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-3 text-xs rounded-r-xl transition">Join</button>
                        </div>
                    </div>
                </div>

                <!-- Platform Pillars -->
                <div>
                    <h5 class="text-xs font-bold uppercase tracking-wider text-white mb-4">Platform Modes</h5>
                    <ul class="space-y-2 text-xs">
                        <li><a href="{{ route('home') }}#quick-commerce" class="hover:text-white transition">Quick Commerce (10-30m)</a></li>
                        <li><a href="{{ route('home') }}#nearby-stores" class="hover:text-white transition">Physical Stores Connect</a></li>
                        <li><a href="{{ route('home') }}#deals" class="hover:text-white transition">Marketplace Deals</a></li>
                        <li><a href="{{ route('home') }}#brands" class="hover:text-white transition">OneStall Verified Brands</a></li>
                        <li><a href="{{ route('seller.dashboard') }}" class="hover:text-white transition">Cargo Freight Logistics</a></li>
                    </ul>
                </div>

                <!-- Sellers & Merchants -->
                <div>
                    <h5 class="text-xs font-bold uppercase tracking-wider text-white mb-4">Sell With Us</h5>
                    <ul class="space-y-2 text-xs">
                        <li><a href="{{ route('seller.dashboard') }}" class="hover:text-white transition">Seller Hub & Portal</a></li>
                        <li><a href="{{ route('seller.store.edit') }}" class="hover:text-white transition">Launch Digital Storefront</a></li>
                        <li><a href="{{ route('seller.inventory.index') }}" class="hover:text-white transition">Omnichannel Inventory</a></li>
                        <li><a href="{{ route('seller.dashboard') }}" class="hover:text-white transition">Fulfilment Priority Network</a></li>
                        <li><a href="{{ route('admin.dashboard') }}" class="hover:text-white transition">Platform Governance</a></li>
                    </ul>
                </div>

                <!-- Customer Care & Security -->
                <div>
                    <h5 class="text-xs font-bold uppercase tracking-wider text-white mb-4">Customer Care</h5>
                    <ul class="space-y-2 text-xs">
                        <li><a href="{{ route('home') }}" class="hover:text-white transition">Help & Support Desk</a></li>
                        <li><a href="{{ route('home') }}" class="hover:text-white transition">Track Your Order</a></li>
                        <li><a href="{{ route('home') }}" class="hover:text-white transition">Returns & Cancellation</a></li>
                        <li><a href="{{ route('home') }}" class="hover:text-white transition">Privacy Policy & Terms</a></li>
                        <li><a href="{{ route('home') }}" class="hover:text-white transition">Security & Trust Center</a></li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Copyright & Payment Badges -->
            <div class="border-t border-slate-800 pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px] text-slate-500">
                <div>
                    &copy; {{ date('Y') }} OneStall Technologies Pvt. Ltd. All rights reserved.
                </div>
                <div class="flex items-center space-x-3 text-slate-400">
                    <span class="px-2 py-0.5 rounded bg-slate-800 text-[10px] font-mono">UPI</span>
                    <span class="px-2 py-0.5 rounded bg-slate-800 text-[10px] font-mono">RuPay</span>
                    <span class="px-2 py-0.5 rounded bg-slate-800 text-[10px] font-mono">Visa</span>
                    <span class="px-2 py-0.5 rounded bg-slate-800 text-[10px] font-mono">Mastercard</span>
                    <span class="px-2 py-0.5 rounded bg-slate-800 text-[10px] font-mono">NetBanking</span>
                    <span class="px-2 py-0.5 rounded bg-slate-800 text-[10px] font-mono">COD</span>
                </div>
            </div>

        </div>
    </footer>

</body>
</html>
