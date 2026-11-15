@extends('layouts.rider', ['title' => 'Rider Dashboard - OneStall'])

@section('content')
<div class="space-y-4" x-data="riderDashboard()">

    <!-- KYC Action Alert for Non-Approved Riders -->
    @if($rider->approval_status !== 'approved')
        <div class="rounded-2xl p-4 flex items-start space-x-3 shadow-xs border {{ $rider->approval_status === 'rejected' ? 'bg-rose-50 border-rose-200 text-rose-900' : 'bg-amber-50 border-amber-200 text-amber-900' }}">
            <div class="text-xl">⚠️</div>
            <div class="flex-1 text-xs">
                <div class="font-extrabold text-xs">
                    {{ $rider->approval_status === 'rejected' ? 'KYC Verification Needs Correction' : 'KYC Verification Underway' }}
                </div>
                <div class="text-[11px] opacity-90 mt-0.5">
                    {{ $rider->approval_status === 'rejected' ? ($rider->rejection_reason ?: 'One or more documents were rejected. Please review and re-upload.') : 'Upload your Driving License, RC, and ID proof to get activated for order assignments.' }}
                </div>
                <a href="{{ route('rider.kyc') }}" class="inline-flex items-center space-x-1 mt-2 px-3 py-1.5 {{ $rider->approval_status === 'rejected' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-amber-600 hover:bg-amber-700' }} text-white rounded-xl font-bold text-[11px] shadow-xs transition">
                    <span>Manage KYC Documents</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>
    @endif

    <!-- Metric Strip Cards (2x2 Grid) -->
    <div class="grid grid-cols-2 gap-3">
        <!-- Duty Status Card -->
        <div class="bg-white rounded-2xl p-3.5 border border-slate-200 shadow-xs flex flex-col justify-between">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Duty Availability</div>
            <div class="flex items-center space-x-2 my-1">
                <span class="w-2.5 h-2.5 rounded-full {{ $rider->status === 'available' ? 'bg-emerald-500 animate-pulse' : ($rider->status === 'busy' ? 'bg-amber-500' : 'bg-slate-400') }}"></span>
                <span class="text-base font-black capitalize text-slate-900">
                    {{ $rider->status === 'available' ? 'Online' : ucfirst($rider->status) }}
                </span>
            </div>
            <form method="POST" action="{{ route('rider.toggle_duty') }}">
                @csrf
                <button type="submit" class="text-[11px] font-bold text-orange-600 hover:text-orange-700 underline cursor-pointer">
                    {{ $rider->status === 'available' ? 'Go Offline' : 'Go Online' }}
                </button>
            </form>
        </div>

        <!-- Today Completed Deliveries -->
        <div class="bg-white rounded-2xl p-3.5 border border-slate-200 shadow-xs flex flex-col justify-between">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Today Delivered</div>
            <div class="text-2xl font-black text-indigo-600 my-0.5">
                {{ number_format($completedToday) }}
            </div>
            <div class="text-[10px] text-slate-400">Completed drop-offs</div>
        </div>

        <!-- Today Earnings -->
        <div class="bg-white rounded-2xl p-3.5 border border-slate-200 shadow-xs flex flex-col justify-between">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Today's Earnings</div>
            <div class="text-xl font-black text-emerald-600 my-0.5 font-mono">
                ₹{{ number_format($todayEarnings, 2) }}
            </div>
            <a href="{{ route('rider.wallet') }}" class="text-[10px] font-bold text-emerald-700 hover:underline">
                View Wallet &rarr;
            </a>
        </div>

        <!-- Cash in Hand (COD) -->
        <div class="bg-white rounded-2xl p-3.5 border border-slate-200 shadow-xs flex flex-col justify-between">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Cash in Hand</div>
            <div class="text-xl font-black font-mono my-0.5 {{ (float)$rider->current_cash_in_hand >= (float)$rider->cash_in_hand_limit ? 'text-rose-600' : 'text-amber-600' }}">
                ₹{{ number_format((float) $rider->current_cash_in_hand, 2) }}
            </div>
            <div class="text-[10px] text-slate-400">
                Limit: ₹{{ number_format((float) ($rider->cash_in_hand_limit ?? 5000), 0) }}
            </div>
        </div>
    </div>

    <!-- Live Telemetry Card -->
    <div class="bg-slate-900 text-white rounded-2xl p-4 shadow-sm border border-slate-800 space-y-2">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <span class="w-2 h-2 rounded-full bg-orange-400 animate-ping"></span>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-300">Live GPS Location</span>
            </div>
            <span class="text-[11px] font-mono text-slate-400">
                {{ number_format($rider->current_lat ?? 12.9716, 4) }}, {{ number_format($rider->current_lng ?? 77.5946, 4) }}
            </span>
        </div>
        
        <button 
            type="button" 
            @click="sendLocationPing()" 
            :disabled="isPinging" 
            class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center space-x-2 border border-slate-700 cursor-pointer"
        >
            <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
            <span x-text="isPinging ? 'Pinging GPS Telemetry...' : 'Send GPS Location Ping'">Send GPS Location Ping</span>
        </button>

        <p x-show="pingMsg" x-text="pingMsg" class="text-[11px] text-emerald-400 font-bold text-center"></p>
    </div>

    <!-- Active Tasks Section Header -->
    <div class="flex items-center justify-between pt-1">
        <h2 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center space-x-2">
            <span>Assigned Tasks</span>
            <span class="bg-orange-100 text-orange-800 text-[10px] px-2 py-0.5 rounded-full font-black">
                {{ $activeAssignments->count() }}
            </span>
        </h2>
        @if($rider->status === 'offline')
            <span class="text-[10px] font-bold text-rose-500 bg-rose-50 px-2 py-0.5 rounded-full">
                ⚠️ You are Offline
            </span>
        @endif
    </div>

    <!-- Assignments List -->
    @if($activeAssignments->isEmpty())
        <div class="bg-white rounded-3xl p-8 text-center border border-dashed border-slate-300 space-y-3">
            <div class="w-14 h-14 bg-orange-50 text-orange-500 rounded-full flex items-center justify-center mx-auto text-3xl">
                🛵
            </div>
            <h3 class="font-bold text-slate-900 text-sm">No Active Delivery Assignments</h3>
            <p class="text-xs text-slate-500 max-w-xs mx-auto">
                {{ $rider->status === 'available' ? 'You are Online! You will be automatically dispatched when nearby store orders are packed and ready.' : 'You are currently Offline. Turn your duty Online to start receiving delivery orders.' }}
            </p>
        </div>
    @else
        <div class="space-y-3.5">
            @foreach($activeAssignments as $assignment)
                @php
                    $fo = $assignment->fulfilmentOrder;
                    $store = $fo?->store;
                    $order = $fo?->order;
                    $isCod = $order?->payment_method === \App\Enums\PaymentMethod::COD;
                @endphp
                <div class="bg-white rounded-3xl p-4 sm:p-5 shadow-xs border border-slate-200 space-y-3.5">
                    <!-- Task Header -->
                    <div class="flex items-center justify-between pb-2.5 border-b border-slate-100">
                        <div>
                            <span class="text-xs font-mono font-bold text-slate-900">
                                #{{ $fo?->fulfilment_order_number }}
                            </span>
                            <div class="text-[10px] text-slate-400">
                                {{ $fo?->items?->count() ?? 1 }} item(s) &bull; {{ $isCod ? 'Cash on Delivery (COD)' : 'Prepaid Online' }}
                            </div>
                        </div>
                        <span class="px-2.5 py-1 text-[10px] font-black rounded-lg uppercase
                            {{ $assignment->status->value === 'assigned' ? 'bg-amber-100 text-amber-800' :
                              ($assignment->status->value === 'accepted' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800') }}">
                            {{ $assignment->status->label() }}
                        </span>
                    </div>

                    <!-- Pickup & Drop Locations -->
                    <div class="space-y-2 text-xs">
                        <div class="flex items-start space-x-2.5">
                            <span class="w-5 h-5 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-[10px] shrink-0 mt-0.5">P</span>
                            <div>
                                <p class="font-bold text-slate-900">{{ $store?->name ?? 'Store Outlet' }}</p>
                                <p class="text-[11px] text-slate-500 line-clamp-1">{{ $store?->location?->address ?? 'Nearby Dark Store Hub' }}</p>
                            </div>
                        </div>

                        @php
                            $dropName = $order?->customerAddress?->name ?? data_get($order?->address_snapshot, 'name', 'Customer');
                            $dropLine = $order?->customerAddress?->line1 ?? (data_get($order?->address_snapshot, 'line1') ?: data_get($order?->address_snapshot, 'address_line_1', ''));
                            $dropCity = $order?->customerAddress?->city ?? data_get($order?->address_snapshot, 'city', '');
                            $dropAddress = trim("{$dropLine}, {$dropCity}", " ,") ?: 'Customer Delivery Location';
                        @endphp
                        <div class="flex items-start space-x-2.5">
                            <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-[10px] shrink-0 mt-0.5">D</span>
                            <div>
                                <p class="font-bold text-slate-900">{{ $dropName }}</p>
                                <p class="text-[11px] text-slate-500 line-clamp-1">{{ $dropAddress }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="space-y-2 pt-1">
                        @if($assignment->status->value === 'assigned')
                            <div class="grid grid-cols-2 gap-2">
                                <button 
                                    type="button" 
                                    @click="openRejectModal({{ $assignment->id }})" 
                                    class="py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs text-center transition cursor-pointer"
                                >
                                    ✕ Decline Job
                                </button>
                                <form action="{{ route('rider.accept', $assignment->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-xs text-center shadow-xs transition cursor-pointer">
                                        ✓ Accept Job
                                    </button>
                                </form>
                            </div>
                        @endif

                        <a 
                            href="{{ route('rider.assignment', $assignment->id) }}" 
                            class="block w-full py-2.5 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white rounded-xl font-bold text-xs text-center shadow-xs transition"
                        >
                            Open Task &amp; Manage Steps &rarr;
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Reject Task Modal -->
    <div x-show="showRejectModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
        <div class="bg-white rounded-3xl p-5 w-full max-w-sm shadow-xl space-y-3" @click.away="showRejectModal = false">
            <div class="text-center">
                <span class="text-rose-500 text-2xl">⚠️</span>
                <h3 class="font-extrabold text-base text-slate-900 mt-1">Decline Delivery Task</h3>
                <p class="text-xs text-slate-500">
                    Order will be immediately re-routed to the next nearest available delivery rider.
                </p>
            </div>

            <form :action="rejectActionUrl" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Reason for Declining <span class="text-rose-500">*</span></label>
                    <select name="reason" required class="w-full text-xs p-2.5 rounded-xl border border-slate-200 bg-slate-50">
                        <option value="distance_too_far">Store or customer is too far</option>
                        <option value="vehicle_breakdown">Vehicle breakdown / puncture</option>
                        <option value="personal_emergency">Personal emergency</option>
                        <option value="heavy_rain_traffic">Severe weather / traffic jam</option>
                        <option value="other">Other reason</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2 pt-1">
                    <button type="button" @click="showRejectModal = false" class="py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold text-xs shadow-xs">
                        Confirm Decline
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
    function riderDashboard() {
        return {
            isPinging: false,
            pingMsg: '',
            showRejectModal: false,
            rejectActionUrl: '',

            openRejectModal(assignmentId) {
                this.rejectActionUrl = '{{ url("/rider/orders") }}/' + assignmentId + '/reject';
                this.showRejectModal = true;
            },

            sendLocationPing() {
                this.isPinging = true;
                const baseLat = {{ (float) ($rider->current_lat ?? 12.9716) }};
                const baseLng = {{ (float) ($rider->current_lng ?? 77.5946) }};
                const newLat = +(baseLat + (Math.random() * 0.003 - 0.0015)).toFixed(6);
                const newLng = +(baseLng + (Math.random() * 0.003 - 0.0015)).toFixed(6);

                fetch('{{ route("rider.location_api") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        rider_id: {{ $rider->id }},
                        lat: newLat,
                        lng: newLng,
                        accuracy: 10.0,
                        speed: 18.5
                    })
                })
                .then(r => r.json())
                .then(data => {
                    this.isPinging = false;
                    this.pingMsg = '✓ Location ping recorded: ' + newLat + ', ' + newLng;
                    setTimeout(() => this.pingMsg = '', 3500);
                })
                .catch(err => {
                    this.isPinging = false;
                    console.error('Telemetry error:', err);
                });
            }
        };
    }
</script>
@endsection
