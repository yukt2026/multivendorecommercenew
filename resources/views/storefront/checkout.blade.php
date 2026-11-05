@extends('layouts.storefront')

@section('title', 'Secure Checkout - OneStall.in')

@section('content')
<div class="min-h-screen bg-slate-50 py-6 sm:py-10"
     x-data="{
        selectedAddressId: {{ $selectedAddress?->id ?? 'null' }},
        paymentMethod: 'online',
        showAddressModal: false,
        submitting: false,
        idempotencyKey: '{{ (string) \Illuminate\Support\Str::uuid() }}',
        newAddress: {
            name: '{{ Auth::user()->name ?? '' }}',
            phone: '{{ Auth::user()->phone ?? '' }}',
            type: 'home',
            line1: '',
            line2: '',
            landmark: '',
            city: 'Bengaluru',
            state: 'Karnataka',
            pincode: '560001',
            lat: 12.9716,
            lng: 77.5946,
            is_default: true
        },
        useCurrentGeo() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition((pos) => {
                    this.newAddress.lat = pos.coords.latitude;
                    this.newAddress.lng = pos.coords.longitude;
                });
            }
        }
     }">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Breadcrumb & Title -->
        <nav class="flex items-center space-x-2 text-xs font-semibold text-slate-500 mb-6">
            <a href="{{ route('home') }}" class="hover:text-indigo-600 transition">Home</a>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <a href="{{ route('cart.index') }}" class="hover:text-indigo-600 transition">Cart</a>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-slate-900 font-bold">Checkout</span>
        </nav>

        <!-- Flash messages -->
        @if (session('error'))
            <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold flex items-center space-x-2 shadow-sm">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold shadow-sm space-y-1">
                @foreach ($errors->all() as $err)
                    <div>• {{ $err }}</div>
                @endforeach
            </div>
        @endif

        <div class="flex items-center justify-between pb-4 mb-6 border-b border-slate-200">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-center space-x-2">
                    <span>Secure Checkout</span>
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-extrabold flex items-center space-x-1">
                        <span>🔒</span>
                        <span>256-Bit SSL Encrypted</span>
                    </span>
                </h1>
                <p class="text-xs text-slate-500 font-medium mt-1">
                    Complete your order in 3 simple steps with automated split fulfilment.
                </p>
            </div>
        </div>

        <!-- Main Checkout Form -->
        <form action="{{ route('checkout.place-order') }}" method="POST" @submit="submitting = true" id="checkout-form">
            @csrf
            <input type="hidden" name="idempotency_key" :value="idempotencyKey">
            <input type="hidden" name="address_id" :value="selectedAddressId">
            <input type="hidden" name="payment_method" :value="paymentMethod">

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Left 8 Columns: Address, Fulfilment Breakdown, Payment Method -->
                <div class="lg:col-span-8 space-y-8">

                    <!-- Section 1: Delivery Address -->
                    <div class="bg-white rounded-3xl p-6 sm:p-7 border border-slate-200 shadow-sm space-y-5">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div class="flex items-center space-x-2.5">
                                <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-extrabold text-xs">
                                    1
                                </div>
                                <h2 class="text-base sm:text-lg font-black text-slate-900">Delivery Address</h2>
                            </div>

                            <button type="button" @click="showAddressModal = true"
                                    class="px-3.5 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-extrabold text-xs transition flex items-center space-x-1">
                                <span>+ Add New Address</span>
                            </button>
                        </div>

                        @if ($addresses->isEmpty())
                            <div class="p-6 rounded-2xl bg-amber-50 border border-amber-200 text-center space-y-3">
                                <p class="text-xs font-bold text-amber-800">You don't have any saved delivery address yet.</p>
                                <button type="button" @click="showAddressModal = true"
                                        class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md transition">
                                    Add Your Address
                                </button>
                            </div>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach ($addresses as $addr)
                                    <label class="relative rounded-2xl border-2 p-4 cursor-pointer transition flex flex-col justify-between"
                                           :class="selectedAddressId === {{ $addr->id }} ? 'border-indigo-600 bg-indigo-50/30 ring-2 ring-indigo-500/20' : 'border-slate-200 hover:border-slate-300 bg-white'">
                                        <div class="flex items-start justify-between">
                                            <div class="flex items-center space-x-2">
                                                <input type="radio" name="_address_selector" value="{{ $addr->id }}"
                                                       @change="selectedAddressId = {{ $addr->id }}; window.location = '{{ route('checkout.index') }}?address_id=' + {{ $addr->id }}"
                                                       :checked="selectedAddressId === {{ $addr->id }}"
                                                       class="w-4 h-4 text-indigo-600 focus:ring-indigo-500">
                                                <span class="text-xs font-black text-slate-900">{{ $addr->name }}</span>
                                            </div>

                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wide bg-slate-100 text-slate-700">
                                                {{ $addr->type?->value ?? 'Home' }}
                                            </span>
                                        </div>

                                        <div class="mt-3 text-xs text-slate-600 space-y-0.5">
                                            <p>{{ $addr->line1 }}{{ $addr->line2 ? ', ' . $addr->line2 : '' }}</p>
                                            @if ($addr->landmark)
                                                <p class="text-slate-400">Landmark: {{ $addr->landmark }}</p>
                                            @endif
                                            <p class="font-bold text-slate-800">{{ $addr->city }}, {{ $addr->state }} - {{ $addr->pincode }}</p>
                                            <p class="text-slate-500">📞 {{ $addr->phone }}</p>
                                        </div>

                                        @if ($addr->is_default)
                                            <div class="mt-3 pt-2 border-t border-slate-100 text-[10px] font-bold text-emerald-600 flex items-center space-x-1">
                                                <span>✓ Default Delivery Address</span>
                                            </div>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Section 2: Delivery Summary Per Fulfilment Group (Automated Order Splitting) -->
                    <div class="bg-white rounded-3xl p-6 sm:p-7 border border-slate-200 shadow-sm space-y-5">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div class="flex items-center space-x-2.5">
                                <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-extrabold text-xs">
                                    2
                                </div>
                                <div>
                                    <h2 class="text-base sm:text-lg font-black text-slate-900">Delivery Packages & Fulfilment Plan</h2>
                                    <p class="text-xs text-slate-500 font-medium">
                                        {{ count($fulfilmentPlan->groups) }} Package{{ count($fulfilmentPlan->groups) > 1 ? 's' : '' }} routed for optimal speed and reliability
                                    </p>
                                </div>
                            </div>

                            @if ($fulfilmentPlan->allQuick)
                                <span class="px-3 py-1 rounded-full text-[11px] font-black bg-amber-100 text-amber-900 border border-amber-300">
                                    ⚡ All Items Quick Delivery (15-30m)
                                </span>
                            @endif
                        </div>

                        <!-- Packages Grid -->
                        <div class="space-y-4">
                            @foreach ($fulfilmentPlan->groups as $index => $group)
                                <div class="rounded-2xl border border-slate-200 overflow-hidden bg-slate-50/50">
                                    <!-- Package Header -->
                                    <div class="p-4 bg-slate-100/70 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center space-x-2">
                                            <span class="w-6 h-6 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-xs font-black">
                                                {{ $index + 1 }}
                                            </span>
                                            <div>
                                                <span class="text-xs font-black text-slate-900">Package {{ $index + 1 }}: {{ $group->sellerName }}</span>
                                                @if ($group->storeName)
                                                    <span class="text-[11px] text-slate-500">({{ $group->storeName }})</span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="flex items-center space-x-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-black {{ $group->fulfilmentType->value === 'quick' ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-slate-200 text-slate-800' }}">
                                                {{ $group->deliveryBadge }}
                                            </span>
                                            <span class="text-xs font-bold text-indigo-700">
                                                ETA: {{ $group->estimatedMinutes <= 60 ? $group->estimatedMinutes . ' mins' : '2-4 Days' }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Items in Package -->
                                    <div class="p-4 divide-y divide-slate-100">
                                        @foreach ($group->items as $item)
                                            <div class="py-2.5 first:pt-0 last:pb-0 flex items-center justify-between gap-4 text-xs">
                                                <div class="flex items-center space-x-3">
                                                    <span class="w-5 h-5 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-[10px] font-black">
                                                        {{ $item['quantity'] }}×
                                                    </span>
                                                    <div>
                                                        <span class="font-extrabold text-slate-900">{{ $item['title'] }}</span>
                                                        <span class="text-[11px] text-slate-400">({{ $item['variant_name'] }})</span>
                                                    </div>
                                                </div>

                                                <span class="font-bold text-slate-900">{{ format_currency($item['subtotal']) }}</span>
                                            </div>
                                        @endforeach
                                    </div>

                                    <!-- Package Delivery Subtotal & Fee -->
                                    <div class="p-3 bg-white border-t border-slate-200/80 flex items-center justify-between text-[11px] text-slate-500">
                                        <span>Package items subtotal: <strong class="text-slate-800">{{ format_currency($group->subtotal) }}</strong></span>
                                        <span>Delivery Fee: <strong class="{{ $group->deliveryFee == 0 ? 'text-emerald-600 font-extrabold' : 'text-slate-800' }}">{{ $group->deliveryFee == 0 ? 'FREE' : format_currency($group->deliveryFee) }}</strong></span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Section 3: Payment Method -->
                    <div class="bg-white rounded-3xl p-6 sm:p-7 border border-slate-200 shadow-sm space-y-5">
                        <div class="flex items-center space-x-2.5 pb-3 border-b border-slate-100">
                            <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-extrabold text-xs">
                                3
                            </div>
                            <h2 class="text-base sm:text-lg font-black text-slate-900">Select Payment Method</h2>
                        </div>

                        <div class="space-y-3">
                            <!-- Option 1: Online Payment (UPI / Cards / Netbanking) -->
                            <label class="rounded-2xl border-2 p-4 cursor-pointer transition flex items-center justify-between"
                                   :class="paymentMethod === 'online' ? 'border-emerald-600 bg-emerald-50/30 ring-2 ring-emerald-500/20' : 'border-slate-200 hover:border-slate-300 bg-white'">
                                <div class="flex items-center space-x-3">
                                    <input type="radio" name="_payment_selector" value="online"
                                           @change="paymentMethod = 'online'"
                                           :checked="paymentMethod === 'online'"
                                           class="w-4 h-4 text-emerald-600 focus:ring-emerald-500">
                                    <div>
                                        <div class="text-xs sm:text-sm font-extrabold text-slate-900 flex items-center space-x-2">
                                            <span>UPI / Credit & Debit Cards / NetBanking / Wallets</span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-600 text-white">Recommended</span>
                                        </div>
                                        <div class="text-[11px] text-slate-500 mt-0.5">Instant confirmation & priority dispatch via Razorpay gateway</div>
                                    </div>
                                </div>

                                <div class="hidden sm:flex items-center space-x-1.5 text-lg">
                                    <span>⚡</span>
                                    <span>💳</span>
                                </div>
                            </label>

                            <!-- Option 2: Cash on Delivery (COD) -->
                            <label class="rounded-2xl border-2 p-4 transition flex items-center justify-between {{ $codEligible ? 'cursor-pointer hover:border-slate-300' : 'opacity-60 cursor-not-allowed bg-slate-50' }}"
                                   :class="paymentMethod === 'cod' ? 'border-indigo-600 bg-indigo-50/30 ring-2 ring-indigo-500/20' : 'border-slate-200'">
                                <div class="flex items-center space-x-3">
                                    <input type="radio" name="_payment_selector" value="cod"
                                           @change="paymentMethod = 'cod'"
                                           :checked="paymentMethod === 'cod'"
                                           {{ !$codEligible ? 'disabled' : '' }}
                                           class="w-4 h-4 text-indigo-600 focus:ring-indigo-500">
                                    <div>
                                        <div class="text-xs sm:text-sm font-extrabold text-slate-900 flex items-center space-x-2">
                                            <span>Cash on Delivery (COD)</span>
                                            @if (!$codEligible)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">Not Eligible</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-slate-500 mt-0.5">
                                            @if ($codEligible)
                                                Pay cash or UPI to delivery executive at your doorstep (limit: {{ format_currency($maxCodAmount) }})
                                            @else
                                                <span class="text-rose-600 font-semibold">{{ $codReason }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <span class="text-xl">💵</span>
                            </label>
                        </div>
                    </div>

                    <!-- Section 4: Delivery Instructions Notes (Optional) -->
                    <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm space-y-3">
                        <label for="order-notes" class="text-xs font-black text-slate-900 block">
                            Delivery Instructions or Order Notes (Optional)
                        </label>
                        <textarea id="order-notes" name="notes" rows="2"
                                  placeholder="e.g. Ring the doorbell twice, leave package at reception or security gate..."
                                  class="w-full text-xs p-3 rounded-2xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>

                </div>

                <!-- Right 4 Columns: Order Summary Card & Place Order CTA -->
                <div class="lg:col-span-4 space-y-6 lg:sticky lg:top-24">
                    
                    <!-- Bill Summary Card -->
                    <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm space-y-5">
                        <h3 class="text-base font-extrabold text-slate-900 border-b border-slate-100 pb-3">Payment Summary</h3>

                        <div class="space-y-3 text-xs text-slate-600">
                            <div class="flex items-center justify-between">
                                <span>Items Subtotal</span>
                                <span class="font-bold text-slate-800">{{ $summary['formatted_subtotal'] }}</span>
                            </div>

                            @if ($summary['coupon_discount'] > 0)
                                <div class="flex items-center justify-between text-emerald-600">
                                    <span>Coupon Discount ({{ $summary['coupon_code'] }})</span>
                                    <span class="font-bold">- {{ $summary['formatted_coupon_discount'] }}</span>
                                </div>
                            @endif

                            <div class="flex items-center justify-between">
                                <span>Delivery Charges</span>
                                <span class="font-bold {{ $summary['delivery_fee'] == 0 ? 'text-emerald-600 font-extrabold' : 'text-slate-800' }}">
                                    {{ $summary['formatted_delivery_fee'] }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1 border-t border-slate-100">
                                <span>Taxes (Estimated GST Included)</span>
                                <span class="font-semibold">{{ $summary['formatted_estimated_tax'] }}</span>
                            </div>

                            <div class="flex items-center justify-between text-base font-black text-slate-900 pt-3 border-t border-slate-200">
                                <span>Total Payable</span>
                                <span class="text-indigo-600 text-lg">{{ $summary['formatted_total_payable'] }}</span>
                            </div>
                        </div>

                        @if ($summary['total_savings'] > 0)
                            <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-xs font-bold text-center">
                                🎉 You are saving {{ $summary['formatted_total_savings'] }} on this purchase!
                            </div>
                        @endif

                        <!-- Submit Button -->
                        <button type="submit"
                                :disabled="submitting || !selectedAddressId"
                                class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-emerald-600 via-teal-600 to-indigo-600 hover:from-emerald-700 hover:to-indigo-700 text-white font-extrabold text-sm flex items-center justify-between shadow-lg shadow-emerald-500/25 active:scale-95 transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="!submitting">Place Order Now</span>
                            <span x-show="submitting" class="animate-pulse">Processing Order...</span>
                            <span class="flex items-center space-x-1">
                                <span>{{ $summary['formatted_total_payable'] }}</span>
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </span>
                        </button>

                        <div class="pt-2 text-center">
                            <a href="{{ route('cart.index') }}" class="text-xs text-indigo-600 hover:underline font-semibold">
                                ← Return to Cart
                            </a>
                        </div>
                    </div>

                    <!-- Trust & Guarantee -->
                    <div class="bg-white rounded-3xl p-5 border border-slate-200 text-xs text-slate-500 space-y-2.5">
                        <div class="flex items-center space-x-2">
                            <span class="text-emerald-600 font-bold">✓</span>
                            <span>Direct Hyperlocal Fulfillment Guarantee</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-emerald-600 font-bold">✓</span>
                            <span>Live GPS Delivery Rider Tracking</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-emerald-600 font-bold">✓</span>
                            <span>Automated GST Invoice on Dispatch</span>
                        </div>
                    </div>

                </div>

            </div>
        </form>

    </div>

    <!-- Add New Address Modal -->
    <div x-show="showAddressModal" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity"
             @click="showAddressModal = false"></div>

        <!-- Dialog -->
        <div class="relative bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 z-10 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base sm:text-lg font-black text-slate-900">Add Delivery Address</h3>
                <button type="button" @click="showAddressModal = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form action="{{ route('checkout.address.add') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Full Name</label>
                        <input type="text" name="name" x-model="newAddress.name" required
                               class="w-full text-xs p-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Phone Number</label>
                        <input type="text" name="phone" x-model="newAddress.phone" required
                               class="w-full text-xs p-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="flex items-center space-x-4 text-xs">
                    <span class="font-bold text-slate-700">Address Type:</span>
                    <label class="flex items-center space-x-1 cursor-pointer">
                        <input type="radio" name="type" value="home" x-model="newAddress.type" class="text-indigo-600">
                        <span>Home</span>
                    </label>
                    <label class="flex items-center space-x-1 cursor-pointer">
                        <input type="radio" name="type" value="work" x-model="newAddress.type" class="text-indigo-600">
                        <span>Work</span>
                    </label>
                    <label class="flex items-center space-x-1 cursor-pointer">
                        <input type="radio" name="type" value="other" x-model="newAddress.type" class="text-indigo-600">
                        <span>Other</span>
                    </label>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Flat, House no., Building, Apartment</label>
                    <input type="text" name="line1" x-model="newAddress.line1" required
                           class="w-full text-xs p-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Area, Street, Sector, Village (Line 2)</label>
                    <input type="text" name="line2" x-model="newAddress.line2"
                           class="w-full text-xs p-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Landmark</label>
                        <input type="text" name="landmark" x-model="newAddress.landmark"
                               class="w-full text-xs p-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Pincode</label>
                        <input type="text" name="pincode" x-model="newAddress.pincode" required pattern="^[1-9][0-9]{5}$"
                               class="w-full text-xs p-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">City</label>
                        <input type="text" name="city" x-model="newAddress.city" required
                               class="w-full text-xs p-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">State</label>
                        <input type="text" name="state" x-model="newAddress.state" required
                               class="w-full text-xs p-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- GPS Map Pin coordinates -->
                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200 space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-800">📍 Map Pin Location</span>
                        <button type="button" @click="useCurrentGeo()" class="text-indigo-600 font-extrabold hover:underline">
                            Detect GPS Pin
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-[11px] text-slate-500">
                        <div>Lat: <input type="number" step="any" name="lat" x-model="newAddress.lat" class="w-full px-2 py-1 rounded border border-slate-200"></div>
                        <div>Lng: <input type="number" step="any" name="lng" x-model="newAddress.lng" class="w-full px-2 py-1 rounded border border-slate-200"></div>
                    </div>
                </div>

                <label class="flex items-center space-x-2 text-xs cursor-pointer">
                    <input type="checkbox" name="is_default" value="1" x-model="newAddress.is_default" class="rounded text-indigo-600">
                    <span class="text-slate-700 font-semibold">Make this my default delivery address</span>
                </label>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-100">
                    <button type="button" @click="showAddressModal = false"
                            class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md">
                        Save Address
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
