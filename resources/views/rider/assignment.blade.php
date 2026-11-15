@extends('layouts.rider', ['title' => 'Task #' . ($fulfilmentOrder?->fulfilment_order_number ?? 'Order') . ' - OneStall Rider'])

@section('content')
@php
    $custName = data_get($customerAddress, 'name', 'Customer');
    $custPhone = data_get($customerAddress, 'phone');
    $custLine = data_get($customerAddress, 'line1') ?: data_get($customerAddress, 'address_line_1', '');
    $custCity = data_get($customerAddress, 'city', '');
    $custState = data_get($customerAddress, 'state', '');
    $custPincode = data_get($customerAddress, 'pincode', '');
    $custLat = data_get($customerAddress, 'lat') ?: data_get($customerAddress, 'latitude');
    $custLng = data_get($customerAddress, 'lng') ?: data_get($customerAddress, 'longitude');

    $fullCustAddress = trim("{$custLine}, {$custCity} {$custState} {$custPincode}", " ,");

    $custNavUrl = null;
    if (!empty($custLat) && !empty($custLng)) {
        $custNavUrl = "https://www.google.com/maps/dir/?api=1&destination={$custLat},{$custLng}";
    } elseif (!empty($fullCustAddress)) {
        $custNavUrl = "https://www.google.com/maps/dir/?api=1&destination=" . urlencode($fullCustAddress);
    }

    $storeLat = $store?->location?->lat ?? $store?->latitude;
    $storeLng = $store?->location?->lng ?? $store?->longitude;
    $storeAddress = $store?->location?->address ?? ($store?->address ?? 'Nearby Store Hub');
    $storeNavUrl = null;
    if (!empty($storeLat) && !empty($storeLng)) {
        $storeNavUrl = "https://www.google.com/maps/dir/?api=1&destination={$storeLat},{$storeLng}";
    } elseif (!empty($storeAddress) && $storeAddress !== 'Nearby Store Hub') {
        $storeNavUrl = "https://www.google.com/maps/dir/?api=1&destination=" . urlencode($storeAddress);
    }
@endphp
<div class="space-y-4" x-data="assignmentManager()">

    <!-- Top Navigation Bar -->
    <div class="flex items-center justify-between">
        <a href="{{ route('rider.dashboard') }}" class="text-xs text-orange-600 font-bold flex items-center space-x-1 hover:underline">
            <span>&larr;</span>
            <span>Back to Tasks</span>
        </a>
        <div class="flex items-center space-x-2">
            <span class="text-xs font-mono font-bold text-slate-800">
                #{{ $fulfilmentOrder?->fulfilment_order_number }}
            </span>
            <span class="px-2.5 py-0.5 text-[10px] font-black rounded-full uppercase
                {{ $assignment->status->value === 'assigned' ? 'bg-amber-100 text-amber-800' :
                  ($assignment->status->value === 'accepted' ? 'bg-blue-100 text-blue-800' : 
                  ($assignment->status->value === 'picked_up' ? 'bg-orange-100 text-orange-800' : 
                  ($assignment->status->value === 'delivered' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'))) }}">
                {{ $assignment->status->label() }}
            </span>
        </div>
    </div>

    <!-- Step 1: Store Pickup Card -->
    <div class="bg-white rounded-3xl p-4 sm:p-5 shadow-xs border border-slate-200 space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-black text-orange-600 uppercase flex items-center space-x-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-orange-500"></span>
                <span>1. Store Pickup</span>
            </span>
            @if(in_array($assignment->status->value, ['picked_up', 'delivered']))
                <span class="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-md">
                    ✓ Picked Up
                </span>
            @endif
        </div>

        <div>
            <h3 class="font-extrabold text-sm text-slate-900">{{ $store?->name ?? 'Merchant Dark Store' }}</h3>
            <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">
                {{ $storeAddress }}
            </p>
        </div>

        <div class="grid grid-cols-2 gap-2 pt-1">
            @if(!empty($store?->phone))
                <a href="tel:{{ $store->phone }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl font-bold text-xs text-center transition flex items-center justify-center space-x-1">
                    <span>📞</span>
                    <span>Call Store</span>
                </a>
            @endif

            @if($storeNavUrl)
                <a 
                    href="{{ $storeNavUrl }}" 
                    target="_blank" 
                    class="py-2 px-3 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-xl font-bold text-xs text-center transition flex items-center justify-center space-x-1"
                >
                    <span>🧭</span>
                    <span>Directions</span>
                </a>
            @endif
        </div>

        <!-- Store Pickup Form / Confirmation -->
        @if($assignment->status->value === 'accepted')
            <form action="{{ route('rider.pickup', $assignment->id) }}" method="POST" class="pt-2 border-t border-slate-100 space-y-2">
                @csrf
                @if($assignment->pickup_otp)
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">
                            Store Pickup OTP (Ask Dark Store Manager)
                        </label>
                        <input 
                            type="text" 
                            name="pickup_otp" 
                            placeholder="e.g. {{ $assignment->pickup_otp }}" 
                            class="w-full text-center tracking-widest font-mono font-bold text-base py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-orange-500"
                        />
                    </div>
                @endif

                <button 
                    type="submit" 
                    class="w-full py-3 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white rounded-2xl font-extrabold text-xs shadow-md transition cursor-pointer flex items-center justify-center space-x-1.5"
                >
                    <span>📦</span>
                    <span>Confirm Picked Up from Store</span>
                </button>
            </form>
        @endif
    </div>

    <!-- Step 2: Customer Drop-off Card -->
    <div class="bg-white rounded-3xl p-4 sm:p-5 shadow-xs border border-slate-200 space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-black text-emerald-700 uppercase flex items-center space-x-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                <span>2. Customer Drop-off</span>
            </span>
            @if($isCod)
                <span class="text-[10px] bg-amber-100 text-amber-900 font-extrabold px-2 py-0.5 rounded-md">
                    💵 Collect COD: ₹{{ number_format($codAmount, 2) }}
                </span>
            @else
                <span class="text-[10px] bg-emerald-100 text-emerald-800 font-extrabold px-2 py-0.5 rounded-md">
                    ✓ Paid Online
                </span>
            @endif
        </div>

        <div>
            <h3 class="font-extrabold text-sm text-slate-900">{{ $custName }}</h3>
            <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">
                {{ $fullCustAddress ?: 'Customer Address' }}
            </p>
        </div>

        <div class="grid grid-cols-2 gap-2 pt-1">
            @if(!empty($custPhone))
                <a href="tel:{{ $custPhone }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl font-bold text-xs text-center transition flex items-center justify-center space-x-1">
                    <span>📞</span>
                    <span>Call Customer</span>
                </a>
            @endif

            @if($custNavUrl)
                <a 
                    href="{{ $custNavUrl }}" 
                    target="_blank" 
                    class="py-2 px-3 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-xl font-bold text-xs text-center transition flex items-center justify-center space-x-1"
                >
                    <span>🧭</span>
                    <span>Navigate</span>
                </a>
            @endif
        </div>
    </div>

    <!-- Items in Package Summary -->
    <div class="bg-white rounded-3xl p-4 sm:p-5 shadow-xs border border-slate-200 space-y-2.5">
        <div class="flex items-center justify-between text-xs pb-1 border-b border-slate-100">
            <span class="font-extrabold text-slate-700 uppercase tracking-wider text-[10px]">Package Items (Checklist)</span>
            <span class="font-mono text-slate-400 text-[10px]">{{ $fulfilmentOrder?->items?->count() ?? 0 }} items</span>
        </div>

        <div class="space-y-2">
            @foreach($fulfilmentOrder?->items ?? [] as $item)
                <label class="flex items-center space-x-2.5 text-xs py-1 cursor-pointer">
                    <input type="checkbox" checked class="rounded border-slate-300 text-orange-600 focus:ring-orange-500 h-4 w-4">
                    <div class="flex-1 flex justify-between">
                        <span class="font-bold text-slate-800">{{ $item->qty }}x {{ $item->title }}</span>
                        <span class="text-slate-400 font-mono text-[11px]">{{ $item->variant_name ?? 'Standard' }}</span>
                    </div>
                </label>
            @endforeach
        </div>
    </div>

    <!-- Earnings & Trip Compensation Preview (When Delivered) -->
    @if($assignment->status->value === 'delivered' && $assignment->earnings)
        @php $earn = $assignment->earnings; @endphp
        <div class="bg-emerald-50 border border-emerald-200 rounded-3xl p-4 sm:p-5 space-y-2.5 text-emerald-950">
            <div class="flex items-center justify-between">
                <span class="text-xs font-black uppercase tracking-wider text-emerald-800">🎉 Trip Earnings Credited</span>
                <span class="text-base font-black text-emerald-700 font-mono">₹{{ number_format($earn->total_payout, 2) }}</span>
            </div>
            <div class="text-[11px] space-y-1 border-t border-emerald-200/70 pt-2 font-medium">
                <div class="flex justify-between">
                    <span class="text-emerald-700">Base Pick &amp; Drop:</span>
                    <span>₹{{ number_format($earn->base_payout, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-emerald-700">Distance Traveled ({{ number_format($earn->distance_km, 1) }} km):</span>
                    <span>₹{{ number_format($earn->distance_payout, 2) }}</span>
                </div>
                @if($earn->surge_bonus > 0)
                    <div class="flex justify-between">
                        <span class="text-emerald-700">Surge / Incentive:</span>
                        <span>₹{{ number_format($earn->surge_bonus, 2) }}</span>
                    </div>
                @endif
                @if($earn->customer_tip > 0)
                    <div class="flex justify-between">
                        <span class="text-emerald-700">Customer Tip:</span>
                        <span>₹{{ number_format($earn->customer_tip, 2) }}</span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Lifecycle Action Buttons -->
    <div class="space-y-2.5 pt-1">
        @if($assignment->status->value === 'assigned')
            <div class="grid grid-cols-2 gap-2">
                <button 
                    type="button" 
                    @click="showRejectModal = true" 
                    class="py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-2xl font-bold text-xs transition cursor-pointer"
                >
                    ✕ Decline Job
                </button>
                <form action="{{ route('rider.accept', $assignment->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-bold text-xs shadow-md transition cursor-pointer">
                        ✓ Accept Job
                    </button>
                </form>
            </div>

        @elseif($assignment->status->value === 'picked_up')
            <button 
                type="button" 
                @click="showOtpModal = true" 
                class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl font-black text-xs shadow-md transition flex items-center justify-center space-x-2 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Complete Delivery (Enter Customer OTP)</span>
            </button>

            <button 
                type="button" 
                @click="showFailModal = true" 
                class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-rose-600 rounded-xl font-bold text-xs transition cursor-pointer"
            >
                ⚠️ Report Delivery Issue / Failed Drop-off
            </button>

        @elseif($assignment->status->value === 'cancelled')
            <div class="p-3.5 bg-amber-50 rounded-2xl border border-amber-200 text-center space-y-2">
                <p class="text-xs font-bold text-amber-900">
                    Delivery was marked as failed: <span class="capitalize">{{ str_replace('_', ' ', $assignment->failure_reason ?? 'Issue reported') }}</span>
                </p>
                <form action="{{ route('rider.return', $assignment->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl font-bold text-xs shadow-xs transition">
                        ↩️ Confirm Returned to Dark Store
                    </button>
                </form>
            </div>
        @endif
    </div>

    <!-- Customer OTP Modal -->
    <div x-show="showOtpModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
        <div class="bg-white rounded-3xl p-5 w-full max-w-sm shadow-2xl space-y-4" @click.away="showOtpModal = false">
            <div class="text-center">
                <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-1 text-2xl font-bold">
                    🔑
                </div>
                <h3 class="font-extrabold text-base text-slate-900">Verify Customer OTP</h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Ask the customer for the confirmation code displayed on their live order tracking screen.
                </p>
            </div>

            <form action="{{ route('rider.deliver', $assignment->id) }}" method="POST" enctype="multipart/form-data" class="space-y-3.5">
                @csrf
                <div>
                    <input 
                        type="text" 
                        name="otp" 
                        required 
                        maxlength="6" 
                        pattern="[0-9]{4,6}" 
                        placeholder="••••••" 
                        autofocus
                        class="w-full text-center text-3xl font-mono tracking-widest font-black py-2.5 rounded-2xl border-2 border-slate-300 focus:border-emerald-500 focus:ring-0"
                    />
                </div>

                @if($isCod)
                    <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-center text-xs text-amber-900 font-bold">
                        💵 Confirm Cash Collected: ₹{{ number_format($codAmount, 2) }}
                    </div>
                @endif

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">
                        Proof of Delivery Photo (Optional)
                    </label>
                    <input 
                        type="file" 
                        name="proof_image" 
                        accept="image/*" 
                        capture="environment"
                        class="w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200"
                    />
                </div>

                <div class="grid grid-cols-2 gap-2 pt-1">
                    <button type="button" @click="showOtpModal = false" class="py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl text-xs shadow-xs">
                        Verify &amp; Finish
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Failed Delivery Modal -->
    <div x-show="showFailModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
        <div class="bg-white rounded-3xl p-5 w-full max-w-sm shadow-xl space-y-3" @click.away="showFailModal = false">
            <div class="text-center">
                <span class="text-rose-500 text-2xl">⚠️</span>
                <h3 class="font-extrabold text-base text-slate-900 mt-1">Report Failed Delivery</h3>
                <p class="text-xs text-slate-500">
                    If customer cannot be reached or delivery cannot be fulfilled, report here.
                </p>
            </div>

            <form action="{{ route('rider.fail', $assignment->id) }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Reason <span class="text-rose-500">*</span></label>
                    <select name="reason" required class="w-full text-xs p-2.5 rounded-xl border border-slate-200 bg-slate-50">
                        <option value="customer_unreachable">Customer unreachable by phone / call</option>
                        <option value="door_locked_absent">Premises locked / Customer absent</option>
                        <option value="customer_refused">Customer refused to accept order</option>
                        <option value="incorrect_address">Incorrect or incomplete address</option>
                        <option value="cod_cash_shortage">Customer refused COD payment</option>
                        <option value="other">Other issue</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Additional Notes</label>
                    <textarea 
                        name="notes" 
                        rows="2" 
                        placeholder="E.g. Called customer 3 times, phone switched off."
                        class="w-full text-xs p-2.5 rounded-xl border border-slate-200 bg-slate-50"
                    ></textarea>
                </div>

                <div class="grid grid-cols-2 gap-2 pt-1">
                    <button type="button" @click="showFailModal = false" class="py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold text-xs shadow-xs">
                        Confirm Failure
                    </button>
                </div>
            </form>
        </div>
    </div>

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

            <form action="{{ route('rider.reject', $assignment->id) }}" method="POST" class="space-y-3">
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
    function assignmentManager() {
        return {
            showOtpModal: false,
            showFailModal: false,
            showRejectModal: false
        };
    }
</script>
@endsection
