@extends('layouts.storefront')

@section('title', 'Order #' . $order->order_number . ' - ' . setting('name', 'OneStall.in'))

@section('content')
<div class="min-h-screen bg-slate-50 py-8 sm:py-12" x-data="{ cancelModal: false, returnModal: false, activeFo: null }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

        <!-- Breadcrumbs & Heading -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <nav class="flex items-center space-x-2 text-xs font-semibold text-slate-400 mb-2">
                    <a href="{{ route('home') }}" class="hover:text-slate-700">Home</a>
                    <span>&rsaquo;</span>
                    <a href="{{ route('orders.index') }}" class="hover:text-slate-700">My Orders</a>
                    <span>&rsaquo;</span>
                    <span class="text-slate-800">#{{ $order->order_number }}</span>
                </nav>
                <div class="flex items-center space-x-3">
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Order #{{ $order->order_number }}</h1>
                    <x-order-status-badge :status="$order->status" size="lg" />
                </div>
                <p class="text-xs text-slate-500 mt-1">Placed on {{ $order->created_at->format('l, d F Y \a\t h:i A') }}</p>
            </div>

            <!-- Top Action Buttons -->
            <div class="flex flex-wrap items-center gap-2">
                <a 
                    href="{{ route('orders.invoice', $order->order_number) }}" 
                    class="px-4 py-2.5 rounded-xl bg-white text-slate-700 hover:text-indigo-600 hover:bg-slate-50 border border-slate-200 text-xs font-bold shadow-xs transition flex items-center space-x-1.5"
                >
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Download Tax Invoice</span>
                </a>

                <form method="POST" action="{{ route('orders.reorder', $order->order_number) }}" class="inline">
                    @csrf
                    <button 
                        type="submit" 
                        class="px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 shadow-xs transition flex items-center space-x-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Reorder Items</span>
                    </button>
                </form>

                @if($canCancel)
                    <button 
                        type="button" 
                        @click="cancelModal = true"
                        class="px-4 py-2.5 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 text-xs font-bold transition"
                    >
                        Cancel Order
                    </button>
                @endif
            </div>
        </div>

        <!-- Order Summary & Delivery Address Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Shipping Address -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-400">Delivery Address</span>
                    <span class="text-slate-400">📍</span>
                </div>
                <div class="text-sm font-black text-slate-900">{{ $order->address_snapshot['name'] ?? 'Recipient' }}</div>
                <div class="text-xs text-slate-600 leading-relaxed">
                    {{ $order->address_snapshot['line1'] ?? '' }}<br>
                    {{ $order->address_snapshot['city'] ?? '' }}, {{ $order->address_snapshot['state'] ?? '' }} - <strong>{{ $order->address_snapshot['pincode'] ?? '' }}</strong>
                </div>
                <div class="text-xs text-slate-500 pt-2 border-t border-slate-100 flex items-center space-x-2">
                    <span>📞</span>
                    <span>{{ $order->address_snapshot['phone'] ?? 'N/A' }}</span>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-400">Payment Information</span>
                    <span class="text-slate-400">💳</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-600 font-medium">Payment Mode</span>
                    <span class="font-bold text-slate-900 uppercase">{{ $order->payment_method->value ?? 'Online' }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-600 font-medium">Payment Status</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $order->payment_status === \App\Enums\PaymentStatus::PAID ? 'bg-emerald-100 text-emerald-800' : ($order->payment_status === \App\Enums\PaymentStatus::COD_PENDING ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">
                        {{ strtoupper(str_replace('_', ' ', $order->payment_status->value ?? 'Pending')) }}
                    </span>
                </div>
                <div class="pt-2 border-t border-slate-100 text-xs text-slate-500">
                    Split Fulfilment: <strong class="text-slate-800">{{ $order->fulfilmentOrders->count() }} package(s)</strong>
                </div>
            </div>

            <!-- Cost Breakdown -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-2">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-400">Price Details</span>
                    <span class="text-slate-400">🧾</span>
                </div>
                <div class="flex justify-between text-xs text-slate-600">
                    <span>Items Subtotal</span>
                    <span class="font-semibold text-slate-900">{{ format_currency((float) $order->subtotal) }}</span>
                </div>
                <div class="flex justify-between text-xs text-slate-600">
                    <span>Delivery Fee</span>
                    <span class="font-semibold {{ (float) $order->delivery_fee == 0 ? 'text-emerald-600' : 'text-slate-900' }}">
                        {{ (float) $order->delivery_fee == 0 ? 'FREE' : format_currency((float) $order->delivery_fee) }}
                    </span>
                </div>
                @if((float) $order->discount > 0)
                <div class="flex justify-between text-xs text-emerald-600 font-semibold">
                    <span>Coupon Savings</span>
                    <span>-{{ format_currency((float) $order->discount) }}</span>
                </div>
                @endif
                @if((float) $order->tax > 0)
                <div class="flex justify-between text-xs text-slate-600">
                    <span>GST &amp; Taxes</span>
                    <span class="font-semibold text-slate-900">{{ format_currency((float) $order->tax) }}</span>
                </div>
                @endif
                <div class="pt-2 border-t border-slate-200 flex justify-between text-sm font-black text-slate-900">
                    <span>Total Amount</span>
                    <span class="text-indigo-600">{{ format_currency((float) $order->total) }}</span>
                </div>
            </div>
        </div>

        <!-- Per-Fulfilment-Order Breakdown & Timeline -->
        <div class="space-y-6">
            <h2 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight">
                Fulfilment Packages ({{ $order->fulfilmentOrders->count() }})
            </h2>

            @foreach($order->fulfilmentOrders as $index => $fo)
                @php
                    $isQuick = $fo->fulfilment_type === \App\Enums\FulfilmentType::QUICK;
                    $status = strtolower($fo->status);

                    // Milestones for Quick:
                    // 1: placed, 2: store_accepted, 3: packing, 4: ready, 5: rider_assigned, 6: out_for_delivery, 7: delivered
                    $quickSteps = [
                        ['id' => 'placed', 'label' => 'Order Placed'],
                        ['id' => 'store_accepted', 'label' => 'Store Accepted'],
                        ['id' => 'packing', 'label' => 'Packing Items'],
                        ['id' => 'ready', 'label' => 'Ready for Pickup'],
                        ['id' => 'rider_assigned', 'label' => 'Rider Assigned'],
                        ['id' => 'out_for_delivery', 'label' => 'Out for Delivery'],
                        ['id' => 'delivered', 'label' => 'Delivered'],
                    ];

                    $quickStepRank = match($status) {
                        'placed' => 1,
                        'store_accepted' => 2,
                        'packing' => 3,
                        'ready' => 4,
                        'rider_assigned' => 5,
                        'picked_up', 'out_for_delivery' => 6,
                        'delivered' => 7,
                        default => 0,
                    };

                    // Milestones for Standard:
                    // 1: placed, 2: confirmed, 3: packed, 4: ready_to_ship, 5: in_transit, 6: out_for_delivery, 7: delivered
                    $standardSteps = [
                        ['id' => 'placed', 'label' => 'Order Placed'],
                        ['id' => 'confirmed', 'label' => 'Confirmed'],
                        ['id' => 'packed', 'label' => 'Packed'],
                        ['id' => 'ready_to_ship', 'label' => 'Ready to Ship'],
                        ['id' => 'in_transit', 'label' => 'In Transit'],
                        ['id' => 'out_for_delivery', 'label' => 'Out for Delivery'],
                        ['id' => 'delivered', 'label' => 'Delivered'],
                    ];

                    $standardStepRank = match($status) {
                        'placed' => 1,
                        'confirmed' => 2,
                        'packed' => 3,
                        'ready_to_ship' => 4,
                        'picked_up', 'in_transit' => 5,
                        'out_for_delivery' => 6,
                        'delivered' => 7,
                        default => 0,
                    };

                    $steps = $isQuick ? $quickSteps : $standardSteps;
                    $rank = $isQuick ? $quickStepRank : $standardStepRank;
                    $isCancelled = in_array($status, ['cancelled', 'rejected']);
                @endphp

                <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
                    <!-- Package Header Bar -->
                    <div class="bg-slate-50/80 px-6 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center space-x-3">
                            <span class="w-8 h-8 rounded-xl {{ $isQuick ? 'bg-amber-500 text-white' : 'bg-indigo-600 text-white' }} flex items-center justify-center font-bold text-xs shadow-xs">
                                {{ $index + 1 }}
                            </span>
                            <div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm font-black text-slate-900">Package #{{ $fo->fulfilment_order_number }}</span>
                                    @if($isQuick)
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-900 border border-amber-300">
                                            ⚡ Quick Commerce (10-30 min)
                                        </span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                            📦 Standard Freight
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500 font-medium mt-0.5">
                                    Dispatched by <strong>{{ $fo->store?->name ?? $fo->seller?->business_name ?? 'OneStall Warehouse' }}</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Status Badge & Action -->
                        <div class="flex items-center space-x-3">
                            <a 
                                href="{{ route('orders.track', [$order->order_number, $fo->fulfilment_order_number]) }}"
                                class="px-3.5 py-1.5 rounded-xl bg-white text-indigo-600 hover:bg-indigo-50 border border-indigo-200 text-xs font-bold transition flex items-center space-x-1"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                                <span>Track Package</span>
                            </a>

                            @if($status === 'delivered')
                                <button 
                                    type="button" 
                                    @click="activeFo = '{{ $fo->fulfilment_order_number }}'; returnModal = true"
                                    class="px-3.5 py-1.5 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-semibold transition"
                                >
                                    Request Return
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Package Body: Timeline & Items -->
                    <div class="p-6 space-y-6">

                        <!-- Interactive Lifecycle Timeline -->
                        @if(!$isCancelled)
                            <div class="bg-slate-50 rounded-2xl p-4 sm:p-6 border border-slate-200/60">
                                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">
                                    {{ $isQuick ? 'Hyperlocal Quick Commerce SLA Milestones' : 'Standard Parcel Transit Timeline' }}
                                </div>

                                <div class="relative">
                                    <!-- Progress line behind circles -->
                                    <div class="hidden md:block absolute top-1/2 left-0 right-0 h-1 bg-slate-200 -translate-y-1/2 z-0"></div>
                                    <div 
                                        class="hidden md:block absolute top-1/2 left-0 h-1 bg-indigo-600 -translate-y-1/2 z-0 transition-all duration-500" 
                                        style="width: {{ min(100, max(0, (($rank - 1) / (count($steps) - 1)) * 100)) }}%;"
                                    ></div>

                                    <!-- Milestone Steps -->
                                    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-4 relative z-10">
                                        @foreach($steps as $idx => $st)
                                            @php
                                                $stepNum = $idx + 1;
                                                $isPassed = $stepNum <= $rank;
                                                $isCurrent = $stepNum === $rank;
                                            @endphp
                                            <div class="flex flex-col items-center text-center">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-black transition-all shadow-xs {{ $isCurrent ? 'bg-indigo-600 text-white ring-4 ring-indigo-100 scale-110' : ($isPassed ? 'bg-indigo-600 text-white' : 'bg-white border-2 border-slate-300 text-slate-400') }}">
                                                    @if($isPassed && !$isCurrent)
                                                        ✓
                                                    @else
                                                        {{ $stepNum }}
                                                    @endif
                                                </div>
                                                <span class="text-[11px] font-bold mt-2 leading-tight {{ $isCurrent ? 'text-indigo-600' : ($isPassed ? 'text-slate-800' : 'text-slate-400') }}">
                                                    {{ $st['label'] }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-medium flex items-center space-x-2">
                                <span class="text-base">❌</span>
                                <span>This package was cancelled / rejected.</span>
                            </div>
                        @endif

                        <!-- Items in this package -->
                        <div>
                            <div class="text-xs font-black uppercase tracking-wider text-slate-400 mb-3">Items in this Package</div>
                            <div class="divide-y divide-slate-100 border border-slate-200 rounded-2xl overflow-hidden">
                                @foreach($fo->items as $item)
                                    <div class="p-4 flex items-center justify-between gap-4 bg-white hover:bg-slate-50 transition">
                                        <div class="flex items-center space-x-4 min-w-0">
                                            <div class="w-14 h-14 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center shrink-0">
                                                @if($item->variant?->product?->primary_image_url)
                                                    <img src="{{ $item->variant->product->primary_image_url }}" alt="{{ $item->title }}" class="w-full h-full object-cover">
                                                @else
                                                    <span class="text-2xl">🛍️</span>
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <h4 class="text-sm font-bold text-slate-900 truncate" title="{{ $item->title }}">{{ $item->title }}</h4>
                                                @if($item->variant_name && $item->variant_name !== 'Default')
                                                    <div class="text-xs text-indigo-600 font-semibold">{{ $item->variant_name }}</div>
                                                @endif
                                                <div class="text-xs text-slate-400 font-mono mt-0.5">SKU: {{ $item->sku }}</div>
                                            </div>
                                        </div>

                                        <div class="text-right shrink-0 space-y-1">
                                            <div class="text-sm font-black text-slate-900">{{ format_currency((float) $item->total) }}</div>
                                            <div class="text-xs text-slate-500">Qty: {{ $item->qty }} &times; {{ format_currency((float) $item->price) }}</div>

                                            @php
                                                $activeReturn = $item->returns->whereNotIn('status', [\App\Enums\ReturnStatus::REJECTED, \App\Enums\ReturnStatus::CANCELLED])->first();
                                            @endphp

                                            @if($activeReturn)
                                                <div class="pt-1">
                                                    <a 
                                                        href="{{ route('orders.return.track', [$order->order_number, $activeReturn->return_number]) }}"
                                                        class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold border {{ $activeReturn->status->badgeClass() }}"
                                                    >
                                                        <span>Return #{{ $activeReturn->return_number }} ({{ $activeReturn->status->label() }}) &rarr;</span>
                                                    </a>
                                                </div>
                                            @elseif($status === 'delivered')
                                                <div class="pt-1">
                                                    <a 
                                                        href="{{ route('orders.return.form', [$order->order_number, $item->id]) }}"
                                                        class="inline-block px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-bold transition"
                                                    >
                                                        Return Item
                                                    </a>
                                                </div>
                                            @elseif(!$isCancelled && !in_array($status, ['shipped', 'out_for_delivery']))
                                                <form method="POST" action="{{ route('orders.cancel.item', [$order->order_number, $item->id]) }}" onsubmit="return confirm('Cancel this item?');" class="inline-block pt-1">
                                                    @csrf
                                                    <input type="hidden" name="reason" value="Customer requested item cancellation">
                                                    <button type="submit" class="text-[11px] font-bold text-rose-600 hover:text-rose-800 transition">
                                                        Cancel Item
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                    </div>
                </div>
            @endforeach
        </div>

        <!-- Help & Support Strip -->
        <div class="bg-indigo-50/60 rounded-3xl p-6 border border-indigo-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-xl shadow-xs">
                    🎧
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Need help with your order?</h3>
                    <p class="text-xs text-slate-500">Our 24x7 customer support team is here to assist with any questions or issues.</p>
                </div>
            </div>

            <a 
                href="mailto:support@onestall.in?subject=Help with Order #{{ $order->order_number }}"
                class="px-5 py-2.5 rounded-xl bg-white text-indigo-600 hover:bg-indigo-50 border border-indigo-200 text-xs font-bold shadow-xs transition whitespace-nowrap text-center"
            >
                Contact Support &rarr;
            </a>
        </div>

    </div>

    <!-- Cancel Order Confirmation Modal -->
    <div 
        x-show="cancelModal" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs"
        @keydown.escape.window="cancelModal = false"
    >
        <div 
            @click.away="cancelModal = false" 
            class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 space-y-4 shadow-2xl border border-slate-200"
        >
            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-2xl mx-auto">
                ⚠️
            </div>
            <div class="text-center space-y-1">
                <h3 class="text-lg font-black text-slate-900">Cancel Order #{{ $order->order_number }}?</h3>
                <p class="text-xs text-slate-500">Are you sure you want to cancel this order? Active stock reservations will be released back to merchants.</p>
            </div>

            <form method="POST" action="{{ route('orders.cancel', $order->order_number) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Reason for Cancellation</label>
                    <select name="reason" required class="w-full text-xs font-medium p-2.5 rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-rose-500">
                        <option value="Changed my mind">Changed my mind</option>
                        <option value="Ordered by mistake">Ordered by mistake</option>
                        <option value="Expected delivery time too long">Expected delivery time too long</option>
                        <option value="Incorrect delivery address">Incorrect delivery address</option>
                        <option value="Other reasons">Other reasons</option>
                    </select>
                </div>

                <div class="flex items-center space-x-3 pt-2">
                    <button 
                        type="button" 
                        @click="cancelModal = false" 
                        class="w-1/2 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition"
                    >
                        Keep Order
                    </button>
                    <button 
                        type="submit" 
                        class="w-1/2 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-xs transition"
                    >
                        Confirm Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Return Request Modal -->
    <div 
        x-show="returnModal" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs"
        @keydown.escape.window="returnModal = false"
    >
        <div 
            @click.away="returnModal = false" 
            class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 space-y-4 shadow-2xl border border-slate-200"
        >
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl mx-auto">
                📦
            </div>
            <div class="text-center space-y-1">
                <h3 class="text-lg font-black text-slate-900">Request Return</h3>
                <p class="text-xs text-slate-500">Package: <strong x-text="activeFo"></strong></p>
            </div>

            <form 
                method="POST" 
                :action="'{{ url('/orders/' . $order->order_number . '/return') }}/' + activeFo" 
                class="space-y-4"
            >
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Reason for Return</label>
                    <textarea 
                        name="reason" 
                        required 
                        rows="3" 
                        placeholder="Please describe why you wish to return this item..." 
                        class="w-full text-xs font-medium p-2.5 rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-indigo-500"
                    ></textarea>
                </div>

                <div class="flex items-center space-x-3 pt-2">
                    <button 
                        type="button" 
                        @click="returnModal = false" 
                        class="w-1/2 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit" 
                        class="w-1/2 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition"
                    >
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
