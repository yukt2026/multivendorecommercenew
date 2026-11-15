<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'OneStall Rider Portal' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-900 min-h-full font-sans antialiased flex flex-col selection:bg-orange-500 selection:text-white pb-20">

    @php
        $rider = $rider ?? (auth()->check() ? auth()->user()->deliveryPartner : null);
        if (!$rider && session('active_rider_id')) {
            $rider = \App\Models\DeliveryPartner::find(session('active_rider_id'));
        }
        $activeCount = $rider ? \App\Models\DeliveryAssignment::where('delivery_partner_id', $rider->id)->whereIn('status', [\App\Enums\DeliveryAssignmentStatus::ASSIGNED, \App\Enums\DeliveryAssignmentStatus::ACCEPTED, \App\Enums\DeliveryAssignmentStatus::PICKED_UP])->count() : 0;
    @endphp

    <!-- Mobile Header -->
    <header class="bg-slate-900 text-white px-4 py-3 sticky top-0 z-40 shadow-md border-b border-slate-800">
        <div class="max-w-md mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('rider.profile') }}" class="w-9 h-9 rounded-xl bg-orange-500 text-white flex items-center justify-center font-black text-base shadow-sm shrink-0 overflow-hidden" title="View Profile">
                    @if($rider && $rider->avatar_url)
                        <img src="{{ $rider->avatar_url }}" alt="{{ $rider->name }}" class="w-full h-full object-cover">
                    @else
                        <span>🛵</span>
                    @endif
                </a>
                <div>
                    <a href="{{ route('rider.profile') }}" class="font-extrabold text-xs text-white leading-tight truncate max-w-[130px] block hover:text-orange-400 transition">
                        {{ $rider?->name ?? 'Delivery Partner' }}
                    </a>
                    <div class="flex items-center space-x-1.5 text-[10px] text-slate-400 font-medium">
                        <span class="truncate max-w-[110px]">{{ $rider?->vehicle ?? 'Motorcycle' }}</span>
                        @if($rider?->vehicle_number)
                            <span>&bull; {{ $rider->vehicle_number }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Header Right: Online/Offline Toggle & Actions -->
            <div class="flex items-center space-x-2">
                @if($rider)
                    <form method="POST" action="{{ route('rider.toggle_duty') }}" class="inline">
                        @csrf
                        <button 
                            type="submit" 
                            class="px-2.5 py-1 rounded-full text-[11px] font-bold border transition flex items-center space-x-1.5 cursor-pointer
                                {{ $rider->status === 'available' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40 hover:bg-emerald-500/30' : 
                                  ($rider->status === 'busy' ? 'bg-amber-500/20 text-amber-400 border-amber-500/40' : 
                                   'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700') }}"
                            title="Toggle Duty Status"
                        >
                            <span class="w-2 h-2 rounded-full 
                                {{ $rider->status === 'available' ? 'bg-emerald-400 animate-pulse' : 
                                  ($rider->status === 'busy' ? 'bg-amber-400' : 'bg-slate-500') }}"></span>
                            <span class="capitalize">{{ $rider->status === 'available' ? 'Online' : ucfirst($rider->status) }}</span>
                        </button>
                    </form>
                @endif

                <a href="{{ route('rider.support') }}" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center text-xs transition" title="Emergency & Support">
                    🆘
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-md w-full mx-auto px-4 py-4 space-y-4">
        
        <!-- Alerts -->
        @if(session('success'))
            <div class="bg-emerald-600 text-white text-xs font-bold p-3.5 rounded-2xl shadow-sm flex items-center space-x-2 animate-fade-in">
                <span class="text-base">✓</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-rose-600 text-white text-xs font-bold p-3.5 rounded-2xl shadow-sm flex items-center space-x-2 animate-fade-in">
                <span class="text-base">✕</span>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if(session('warning'))
            <div class="bg-amber-500 text-slate-900 text-xs font-bold p-3.5 rounded-2xl shadow-sm flex items-center space-x-2 animate-fade-in">
                <span class="text-base">⚠️</span>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Bottom Navigation Bar (Mobile Native App Style) -->
    <nav class="fixed bottom-0 inset-x-0 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 z-40 shadow-lg">
        <div class="max-w-md mx-auto grid grid-cols-5 h-16">
            <!-- 1. Tasks / Dashboard -->
            <a 
                href="{{ route('rider.dashboard') }}" 
                class="flex flex-col items-center justify-center space-y-1 relative transition {{ request()->routeIs('rider.dashboard') || request()->routeIs('rider.assignment') ? 'text-orange-600 font-extrabold' : 'text-slate-400 hover:text-slate-600 font-medium' }}"
            >
                <div class="relative">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    @if($activeCount > 0)
                        <span class="absolute -top-1 -right-2 bg-orange-600 text-white text-[9px] font-black w-4 h-4 rounded-full flex items-center justify-center">
                            {{ $activeCount }}
                        </span>
                    @endif
                </div>
                <span class="text-[10px]">Tasks</span>
            </a>

            <!-- 2. History -->
            <a 
                href="{{ route('rider.history') }}" 
                class="flex flex-col items-center justify-center space-y-1 transition {{ request()->routeIs('rider.history') ? 'text-orange-600 font-extrabold' : 'text-slate-400 hover:text-slate-600 font-medium' }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-[10px]">History</span>
            </a>

            <!-- 3. Wallet / Earnings -->
            <a 
                href="{{ route('rider.wallet') }}" 
                class="flex flex-col items-center justify-center space-y-1 transition {{ request()->routeIs('rider.wallet') ? 'text-orange-600 font-extrabold' : 'text-slate-400 hover:text-slate-600 font-medium' }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span class="text-[10px]">Wallet</span>
            </a>

            <!-- 4. Profile -->
            <a 
                href="{{ route('rider.profile') }}" 
                class="flex flex-col items-center justify-center space-y-1 transition {{ request()->routeIs('rider.profile') ? 'text-orange-600 font-extrabold' : 'text-slate-400 hover:text-slate-600 font-medium' }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span class="text-[10px]">Profile</span>
            </a>

            <!-- 5. Support -->
            <a 
                href="{{ route('rider.support') }}" 
                class="flex flex-col items-center justify-center space-y-1 transition {{ request()->routeIs('rider.support') ? 'text-orange-600 font-extrabold' : 'text-slate-400 hover:text-slate-600 font-medium' }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span class="text-[10px]">Support</span>
            </a>
        </div>
    </nav>

    @stack('scripts')
</body>
</html>
