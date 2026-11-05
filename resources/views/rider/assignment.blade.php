<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Task #{{ $fulfilmentOrder?->fulfilment_order_number }} - OneStall Rider</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen pb-20 font-sans" x-data="{ showOtpModal: false, otp: '' }">
    
    <!-- Top Nav -->
    <header class="bg-slate-900 text-white px-4 py-3 sticky top-0 z-30 shadow flex items-center justify-between">
        <a href="{{ route('rider.dashboard') }}" class="text-xs text-orange-400 font-bold flex items-center gap-1">
            ← Dashboard
        </a>
        <span class="text-xs font-mono font-bold">#{{ $fulfilmentOrder?->fulfilment_order_number }}</span>
        <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-slate-800 text-slate-300">
            {{ $assignment->status->label() }}
        </span>
    </header>

    <main class="max-w-md mx-auto px-4 py-5 space-y-4">
        
        <!-- Alerts -->
        @if(session('success'))
            <div class="bg-emerald-500 text-white text-xs font-bold p-3 rounded-2xl shadow-sm">
                ✓ {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-rose-500 text-white text-xs font-bold p-3 rounded-2xl shadow-sm">
                ✕ {{ session('error') }}
            </div>
        @endif

        <!-- Step 1: Pickup Location Card -->
        <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-orange-600 uppercase flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-orange-500"></span> 1. Store Pickup
                </span>
                @if($assignment->status->value === 'picked_up')
                    <span class="text-[11px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded">✓ Picked Up</span>
                @endif
            </div>

            <h3 class="font-extrabold text-base text-slate-900">{{ $store?->name ?? 'Store Outlet' }}</h3>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                {{ $store?->location?->address ?? 'Nearby Dark Store Hub' }}
            </p>
            <div class="mt-4 flex items-center gap-2">
                <a href="tel:{{ $store?->phone ?? '9876543210' }}" class="flex-1 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl font-bold text-xs text-center transition">
                    📞 Call Store
                </a>
            </div>
        </div>

        <!-- Step 2: Customer Drop-off Location Card -->
        <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-emerald-700 uppercase flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> 2. Customer Drop-off
                </span>
            </div>

            <h3 class="font-extrabold text-base text-slate-900">{{ $customerAddress?->name ?? 'Customer' }}</h3>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                {{ $customerAddress?->line1 }}, {{ $customerAddress?->city }} {{ $customerAddress?->pincode }}
            </p>
            <div class="mt-4 flex items-center gap-2">
                <a href="tel:{{ $customerAddress?->phone ?? '9876543210' }}" class="flex-1 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl font-bold text-xs text-center transition">
                    📞 Call Customer
                </a>
            </div>
        </div>

        <!-- Package Items Summary -->
        <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-200">
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Items in Package</h4>
            <div class="space-y-2">
                @foreach($fulfilmentOrder?->items ?? [] as $item)
                    <div class="flex items-center justify-between text-xs py-1 border-b border-slate-100 last:border-0">
                        <span class="font-bold text-slate-900">{{ $item->qty }}x {{ $item->title }}</span>
                        <span class="text-slate-400 font-mono">{{ $item->variant_name ?? 'Standard' }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Action Buttons based on status -->
        <div class="pt-2 space-y-3">
            @if($assignment->status->value === 'assigned')
                <form action="{{ route('rider.accept', $assignment->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full py-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-extrabold text-sm shadow-lg transition">
                        ✓ Accept Assignment
                    </button>
                </form>
            @elseif($assignment->status->value === 'accepted')
                <form action="{{ route('rider.pickup', $assignment->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full py-3.5 bg-orange-500 hover:bg-orange-600 text-white rounded-2xl font-extrabold text-sm shadow-lg transition">
                        📦 Confirm Picked Up from Store
                    </button>
                </form>
            @elseif($assignment->status->value === 'picked_up')
                <button @click="showOtpModal = true" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl font-extrabold text-sm shadow-lg transition flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Complete Delivery (Enter OTP)</span>
                </button>
            @else
                <div class="bg-emerald-100 text-emerald-800 p-4 rounded-2xl text-center font-bold text-sm">
                    ✓ Order has been delivered successfully!
                </div>
            @endif
        </div>
    </main>

    <!-- OTP Handshake Modal -->
    <div x-show="showOtpModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-3xl p-6 w-full max-w-sm shadow-2xl space-y-4">
            <div class="text-center">
                <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-2 text-xl font-bold">
                    🔑
                </div>
                <h3 class="font-extrabold text-lg text-slate-900">Enter Customer OTP</h3>
                <p class="text-xs text-slate-500 mt-1">
                    Ask the customer for the 6-digit confirmation code shown on their live order tracking screen.
                </p>
            </div>

            <form action="{{ route('rider.deliver', $assignment->id) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <input type="text" name="otp" required maxlength="6" pattern="[0-9]{6}" placeholder="••••••" autofocus
                           class="w-full text-center text-3xl font-mono tracking-widest font-black py-3 rounded-2xl border-2 border-slate-300 focus:border-emerald-500 focus:ring-0">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="showOtpModal = false" class="py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow">
                        Verify & Finish
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
