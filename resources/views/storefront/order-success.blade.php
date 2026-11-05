@extends('layouts.storefront')

@section('title', 'Order Confirmed - OneStall.in')

@section('content')
<div class="min-h-screen bg-slate-50 py-8 sm:py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Celebration Banner Card -->
        <div class="bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 text-center shadow-sm space-y-4">
            <div class="w-20 h-20 mx-auto rounded-3xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-4xl shadow-inner">
                ✓
            </div>

            <div class="space-y-1">
                <span class="px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-800">
                    Order Confirmed & Placed
                </span>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Thank You For Your Order!</h1>
                <p class="text-xs sm:text-sm text-slate-500 font-medium">
                    Order Reference: <strong class="text-indigo-600 font-black">{{ $order->order_number }}</strong>
                </p>
            </div>

            <p class="text-xs text-slate-600 max-w-lg mx-auto">
                We've received your order and notified the sellers. Your items have been automatically routed into {{ count($order->fulfilmentOrders) }} package{{ count($order->fulfilmentOrders) > 1 ? 's' : '' }} for quickest delivery.
            </p>

            <!-- Order Timeline -->
            <div class="pt-6 border-t border-slate-100">
                <div class="flex items-center justify-between max-w-md mx-auto text-xs font-bold text-slate-800">
                    <div class="flex flex-col items-center">
                        <div class="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs">✓</div>
                        <span class="mt-1.5 text-[11px] text-emerald-700">Placed</span>
                    </div>
                    <div class="flex-1 h-0.5 bg-emerald-500 mx-2"></div>
                    <div class="flex flex-col items-center">
                        <div class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs animate-pulse">●</div>
                        <span class="mt-1.5 text-[11px] text-indigo-700">Processing</span>
                    </div>
                    <div class="flex-1 h-0.5 bg-slate-200 mx-2"></div>
                    <div class="flex flex-col items-center opacity-40">
                        <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-xs">📦</div>
                        <span class="mt-1.5 text-[11px] text-slate-600">Dispatched</span>
                    </div>
                    <div class="flex-1 h-0.5 bg-slate-200 mx-2"></div>
                    <div class="flex flex-col items-center opacity-40">
                        <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-xs">🏠</div>
                        <span class="mt-1.5 text-[11px] text-slate-600">Delivered</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Split Fulfilment Packages -->
        <div class="mt-8 space-y-5">
            <h2 class="text-lg font-black text-slate-900 tracking-tight flex items-center justify-between">
                <span>Fulfilment Packages ({{ count($order->fulfilmentOrders) }})</span>
                <span class="text-xs text-slate-500 font-semibold">Automated multi-store split</span>
            </h2>

            @foreach ($order->fulfilmentOrders as $index => $fo)
                <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
                    <!-- Package Header -->
                    <div class="p-4 sm:p-5 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center space-x-3">
                            <span class="w-7 h-7 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-xs font-black">
                                {{ $index + 1 }}
                            </span>
                            <div>
                                <div class="text-xs font-black text-slate-900">
                                    Package: {{ $fo->fulfilment_order_number }}
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    Seller: <strong>{{ $fo->seller?->business_name ?? 'OneStall Seller' }}</strong>
                                    @if ($fo->store)
                                        • Store: {{ $fo->store->name }}
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2">
                            <span class="px-3 py-1 rounded-full text-[11px] font-black {{ $fo->fulfilment_type->value === 'quick' ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-slate-100 text-slate-800 border border-slate-200' }}">
                                {{ $fo->fulfilment_type->value === 'quick' ? '⚡ Quick Delivery' : '📦 Standard Delivery' }}
                            </span>
                            <span class="text-xs font-bold text-indigo-700">
                                ETA: {{ $fo->estimated_delivery_at ? $fo->estimated_delivery_at->diffForHumans() : 'Soon' }}
                            </span>
                        </div>
                    </div>

                    <!-- Items in Package -->
                    <div class="p-4 sm:p-5 divide-y divide-slate-100">
                        @foreach ($fo->items as $item)
                            <div class="py-3 first:pt-0 last:pb-0 flex items-center justify-between gap-4 text-xs">
                                <div class="flex items-center space-x-3">
                                    <span class="w-6 h-6 rounded-lg bg-slate-100 font-black text-slate-700 flex items-center justify-center text-xs">
                                        {{ $item->qty }}×
                                    </span>
                                    <div>
                                        <div class="font-extrabold text-slate-900">{{ $item->title }}</div>
                                        <div class="text-[11px] text-slate-400">SKU: {{ $item->sku }} • {{ $item->variant_name }}</div>
                                    </div>
                                </div>

                                <div class="text-right">
                                    <div class="font-black text-slate-900">{{ format_currency($item->total) }}</div>
                                    <div class="text-[10px] text-slate-400">({{ format_currency($item->price) }} each)</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Package Subtotal -->
                    <div class="p-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600 font-semibold">
                        <span>Package Subtotal:</span>
                        <span class="text-slate-900 font-black">{{ format_currency($fo->subtotal) }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Order Snapshot Details: Address & Payment Summary -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- Shipping Address Snapshot -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm space-y-3">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-400">Delivery Address</h3>
                @php $addr = $order->address_snapshot; @endphp
                <div class="text-xs text-slate-800 space-y-1">
                    <p class="font-black text-slate-900 text-sm">{{ $addr['name'] ?? 'Customer' }}</p>
                    <p>{{ $addr['line1'] ?? '' }}{{ !empty($addr['line2']) ? ', ' . $addr['line2'] : '' }}</p>
                    @if (!empty($addr['landmark']))
                        <p class="text-slate-500">Landmark: {{ $addr['landmark'] }}</p>
                    @endif
                    <p class="font-bold">{{ $addr['city'] ?? '' }}, {{ $addr['state'] ?? '' }} - {{ $addr['pincode'] ?? '' }}</p>
                    <p class="text-slate-500 pt-1">📞 {{ $addr['phone'] ?? '' }}</p>
                </div>
            </div>

            <!-- Payment Breakdown -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm space-y-3">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-400">Order Totals</h3>
                <div class="text-xs text-slate-600 space-y-2">
                    <div class="flex items-center justify-between">
                        <span>Payment Method:</span>
                        <span class="font-extrabold text-slate-900 uppercase">{{ $order->payment_method?->value ?? 'Online' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Items Subtotal:</span>
                        <span class="font-bold text-slate-900">{{ format_currency($order->subtotal) }}</span>
                    </div>
                    @if ($order->discount > 0)
                        <div class="flex items-center justify-between text-emerald-600">
                            <span>Discount / Coupon:</span>
                            <span class="font-bold">- {{ format_currency($order->discount) }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span>Delivery Fee:</span>
                        <span class="font-bold text-slate-900">{{ $order->delivery_fee == 0 ? 'FREE' : format_currency($order->delivery_fee) }}</span>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-slate-200 text-sm font-black text-slate-900">
                        <span>Total Paid:</span>
                        <span class="text-indigo-600 text-base">{{ format_currency($order->total) }}</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Back to Shopping CTA -->
        <div class="mt-8 text-center space-x-3">
            <a href="{{ route('home') }}"
               class="px-8 py-3.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md shadow-indigo-500/20 active:scale-95 transition inline-block">
                Continue Shopping
            </a>
        </div>

    </div>
</div>
@endsection
