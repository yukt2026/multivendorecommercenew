<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Sign In' }} - {{ config('onestall.name', 'OneStall') }}</title>

    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Dark Mode Initializer -->
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body 
    class="h-full font-sans antialiased bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100"
    x-data="{
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
        }
    }"
>
    <div class="min-h-full flex flex-col lg:flex-row">
        <!-- Left Side: Premium Brand Showcase (Hidden on small screens, 50% on lg) -->
        <div class="hidden lg:flex lg:w-1/2 relative bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 p-12 flex-col justify-between overflow-hidden border-r border-indigo-900/40">
            <!-- Background Radial Glow -->
            <div class="absolute -top-32 -left-32 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <!-- Top Brand -->
            <div class="relative z-10 flex items-center space-x-3">
                <a href="{{ route('home') }}" class="flex items-center space-x-2">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-extrabold text-xl shadow-lg shadow-indigo-600/30">
                        1S
                    </div>
                    <div>
                        <span class="text-xl font-bold tracking-tight text-white">OneStall<span class="text-indigo-400">.in</span></span>
                        <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-widest -mt-1">Smart Commerce</span>
                    </div>
                </a>
            </div>

            <!-- Central Pitch -->
            <div class="relative z-10 my-auto py-12 max-w-lg space-y-6">
                <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-amber-400/20 border border-amber-400/30 text-amber-300 text-xs font-bold">
                    <span>⚡ HYPER-LOCAL SPEED & NATIONAL REACH</span>
                </div>

                <h1 class="text-4xl sm:text-5xl font-extrabold text-white tracking-tight leading-tight">
                    One Store, <br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 via-indigo-300 to-indigo-400">All You Need.</span>
                </h1>

                <p class="text-sm text-slate-300 leading-relaxed">
                    Access India's unified smart commerce engine: instant local deliveries from physical store connect, dark store dispatch, national marketplace catalogue, and heavy OneStall Cargo logistics.
                </p>

                <!-- Value Proposition Badges -->
                <div class="grid grid-cols-2 gap-4 pt-4">
                    <div class="p-4 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-md">
                        <div class="text-amber-400 font-extrabold text-xl">10-30m</div>
                        <div class="text-xs font-semibold text-white mt-0.5">Quick Commerce</div>
                        <p class="text-[11px] text-slate-400 mt-1">Under {{ setting('default_delivery_radius_km', 8) }} km radius connect</p>
                    </div>

                    <div class="p-4 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-md">
                        <div class="text-indigo-400 font-extrabold text-xl">Cargo Fleet</div>
                        <div class="text-xs font-semibold text-white mt-0.5">OneStall Logistics</div>
                        <p class="text-[11px] text-slate-400 mt-1">Inter-city linehaul & heavy freight</p>
                    </div>
                </div>
            </div>

            <!-- Footer Quote -->
            <div class="relative z-10 text-xs text-slate-400 border-t border-slate-800 pt-6 flex items-center justify-between">
                <span>&copy; {{ date('Y') }} OneStall Technologies Inc.</span>
                <span class="text-slate-400">Enterprise SLA Active</span>
            </div>
        </div>

        <!-- Right Side: Clean Form Container -->
        <div class="flex-1 flex flex-col justify-between p-6 sm:p-10 lg:p-12 {{ ($wideLayout ?? false) ? 'max-w-4xl' : 'max-w-xl' }} mx-auto w-full">
            <!-- Mobile Brand Header & Theme Toggle -->
            <div class="flex items-center justify-between pb-6">
                <a href="{{ route('home') }}" class="flex items-center space-x-2 lg:hidden">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold text-sm">1S</div>
                    <span class="text-lg font-bold text-slate-900 dark:text-white">OneStall</span>
                </a>

                <div class="ml-auto flex items-center space-x-3">
                    <a href="{{ route('home') }}" class="text-xs text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition">
                        &larr; Storefront
                    </a>
                    <button 
                        @click="toggleDarkMode()" 
                        class="p-2 rounded-xl text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                        title="Toggle Dark Mode"
                    >
                        <template x-if="!darkMode">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                        </template>
                        <template x-if="darkMode">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </template>
                    </button>
                </div>
            </div>

            <!-- Form Content Slot -->
            <div class="my-auto py-8">
                {{ $slot ?? '' }}
                @yield('content')
            </div>

            <!-- Bottom Disclaimer -->
            <div class="pt-6 border-t border-slate-200/80 dark:border-slate-800 text-center text-xs text-slate-500 dark:text-slate-400">
                Protected by OneStall Enterprise Security and Multi-Tenant Isolation.
            </div>
        </div>
    </div>
</body>
</html>
