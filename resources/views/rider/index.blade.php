<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>OneStall Rider - Fleet Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen pb-16 font-sans">
    
    <!-- Mobile App Header -->
    <header class="bg-slate-900 text-white px-4 py-4 sticky top-0 z-30 shadow-md">
        <div class="max-w-md mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-orange-500 text-white flex items-center justify-center font-bold text-lg shadow">
                    🛵
                </div>
                <div>
                    <h1 class="font-extrabold text-sm leading-tight">{{ $rider->name }}</h1>
                    <div class="flex items-center gap-1.5 text-[11px] text-emerald-400 font-semibold">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>{{ ucfirst($rider->status) }} • {{ $rider->vehicle ?? 'Motorcycle' }}</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- Switch Rider dropdown -->
                <form action="{{ route('rider.switch') }}" method="POST" class="flex items-center">
                    @csrf
                    <select name="rider_id" onchange="this.form.submit()" class="text-xs bg-slate-800 text-white border-slate-700 rounded-lg py-1 px-2">
                        @foreach($allRiders as $r)
                            <option value="{{ $r->id }}" {{ $r->id === $rider->id ? 'selected' : '' }}>
                                {{ $r->name }}
                            </option>
                        @endforeach
                    </select>
                </form>

                <form action="{{ route('logout') }}" method="POST" class="flex items-center">
                    @csrf
                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-500/20 text-rose-300 hover:bg-rose-500/30 text-xs font-semibold border border-rose-500/40 transition" title="Log Out">
                        ✕
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-md mx-auto px-4 py-5 space-y-4">
        
        <!-- Alerts -->
        @if(session('success'))
            <div class="bg-emerald-500 text-white text-xs font-bold p-3 rounded-2xl shadow-sm flex items-center gap-2">
                <span>✓</span> <span>{{ session('success') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div class="bg-rose-500 text-white text-xs font-bold p-3 rounded-2xl shadow-sm flex items-center gap-2">
                <span>✕</span> <span>{{ session('error') }}</span>
            </div>
        @endif

        <!-- Quick Telemetry & GPS Simulation Card -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200" x-data="{
            updating: false,
            msg: '',
            pingLocation() {
                this.updating = true;
                const newLat = {{ $rider->current_lat ?? 12.9716 }} + (Math.random() * 0.005 - 0.0025);
                const newLng = {{ $rider->current_lng ?? 77.5946 }} + (Math.random() * 0.005 - 0.0025);
                fetch('{{ route('rider.location_api') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ rider_id: {{ $rider->id }}, lat: newLat, lng: newLng })
                })
                .then(r => r.json())
                .then(d => {
                    this.updating = false;
                    this.msg = 'GPS Ping sent (' + newLat.toFixed(4) + ', ' + newLng.toFixed(4) + ')';
                    setTimeout(() => this.msg = '', 3000);
                })
                .catch(e => { this.updating = false; console.error(e); });
            }
        }">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-500 uppercase">Live GPS Coordinates</span>
                <span class="text-[11px] font-mono text-slate-400">
                    {{ number_format($rider->current_lat ?? 12.9716, 4) }}, {{ number_format($rider->current_lng ?? 77.5946, 4) }}
                </span>
            </div>
            <button @click="pingLocation()" :disabled="updating" class="w-full py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2">
                <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                <span x-text="updating ? 'Pinging GPS...' : 'Send GPS Location Ping'">Send GPS Location Ping</span>
            </button>
            <p x-show="msg" x-text="msg" class="text-[11px] text-emerald-600 font-bold text-center mt-2"></p>
        </div>

        <!-- Active Tasks Header -->
        <div class="flex items-center justify-between pt-2">
            <h2 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                <span>Active Assignments</span>
                <span class="bg-orange-100 text-orange-800 text-xs px-2 py-0.5 rounded-full font-bold">
                    {{ $activeAssignments->count() }}
                </span>
            </h2>
            <span class="text-xs text-slate-500">Delivered today: <strong>{{ $completedToday }}</strong></span>
        </div>

        <!-- Assignments List -->
        @if($activeAssignments->isEmpty())
            <div class="bg-white rounded-3xl p-8 text-center border border-dashed border-slate-300">
                <div class="w-14 h-14 bg-orange-50 text-orange-500 rounded-full flex items-center justify-center mx-auto mb-3 text-2xl">
                    🛵
                </div>
                <h3 class="font-bold text-slate-900 text-sm">No Active Delivery Assignments</h3>
                <p class="text-xs text-slate-500 mt-1">Stay tuned. You will be automatically assigned when nearby store orders are ready for pickup.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($activeAssignments as $assignment)
                    @php
                        $fo = $assignment->fulfilmentOrder;
                        $store = $fo?->store;
                        $order = $fo?->order;
                    @endphp
                    <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-200 space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <span class="text-xs font-mono font-bold text-slate-900">#{{ $fo?->fulfilment_order_number }}</span>
                                <span class="text-[11px] text-slate-400 block">{{ $fo?->items?->count() ?? 1 }} item(s)</span>
                            </div>
                            <span class="px-2.5 py-1 text-xs font-bold rounded-lg uppercase
                                {{ $assignment->status->value === 'assigned' ? 'bg-amber-100 text-amber-800' :
                                  ($assignment->status->value === 'accepted' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800') }}">
                                {{ $assignment->status->label() }}
                            </span>
                        </div>

                        <!-- Pickup & Drop Locations -->
                        <div class="space-y-2 text-xs">
                            <div class="flex items-start gap-2.5">
                                <span class="w-5 h-5 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-[10px] shrink-0 mt-0.5">P</span>
                                <div>
                                    <p class="font-bold text-slate-900">{{ $store?->name ?? 'Store Outlet' }}</p>
                                    <p class="text-[11px] text-slate-500">{{ $store?->location?->address ?? 'Nearby Dark Store Hub' }}</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-2.5">
                                <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-[10px] shrink-0 mt-0.5">D</span>
                                <div>
                                    <p class="font-bold text-slate-900">{{ $order?->customerAddress?->name ?? 'Customer' }}</p>
                                    <p class="text-[11px] text-slate-500">{{ $order?->customerAddress?->line1 }}, {{ $order?->customerAddress?->city }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <a href="{{ route('rider.assignment', $assignment->id) }}" class="block w-full py-2.5 bg-orange-500 hover:bg-orange-600 text-white rounded-2xl font-bold text-xs text-center shadow-md transition">
                            Open Task & Update Status →
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </main>
</body>
</html>
