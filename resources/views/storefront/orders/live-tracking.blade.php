@extends('layouts.storefront')

@section('title', 'Live Order Tracking - ' . $order->order_number . ' | OneStall')

@section('content')
<div class="bg-slate-50 min-h-screen py-8" x-data="{
    orderId: {{ $order->id }},
    status: '{{ $fulfilmentOrder?->status ?? 'placed' }}',
    otp: '{{ $fulfilmentOrder?->delivery_otp ?? '482910' }}',
    rider: null,
    store: null,
    customer: null,
    estimatedDelivery: '{{ $fulfilmentOrder?->estimated_delivery_at?->diffForHumans() ?? '20 mins' }}',
    pollInterval: null,
    fetchLive() {
        fetch('{{ route('orders.live_location', $order->id) }}')
            .then(res => res.json())
            .then(data => {
                this.status = data.package_status;
                this.otp = data.delivery_otp || this.otp;
                this.rider = data.rider;
                this.store = data.store;
                this.customer = data.customer;
            })
            .catch(err => console.error(err));
    },
    init() {
        this.fetchLive();
        this.pollInterval = setInterval(() => this.fetchLive(), 5000);
    }
}">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header breadcrumb / back link -->
        <div class="flex items-center justify-between mb-6">
            <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-orange-600 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                <span>Back to My Orders</span>
            </a>
            <span class="text-xs font-mono font-bold bg-slate-200/80 text-slate-700 px-2.5 py-1 rounded-lg">
                #{{ $order->order_number }}
            </span>
        </div>

        <!-- Main Live Tracking Card -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-xl overflow-hidden mb-6">
            
            <!-- Live Status Banner -->
            <div class="bg-gradient-to-r from-orange-500 to-amber-600 text-white p-6 sm:p-8">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur-md px-3 py-1 rounded-full text-xs font-bold mb-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                            <span>⚡ Quick Commerce Live Tracking</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                            <span x-show="status === 'placed'">Order Received by Store</span>
                            <span x-show="status === 'store_accepted'">Store Preparing Items</span>
                            <span x-show="status === 'packing'">Items Being Packed</span>
                            <span x-show="status === 'ready'">Ready for Rider Pickup</span>
                            <span x-show="status === 'rider_assigned'">Rider Heading to Store</span>
                            <span x-show="status === 'picked_up' || status === 'out_for_delivery'">Rider Out for Delivery!</span>
                            <span x-show="status === 'delivered'">Delivered Successfully!</span>
                            <span x-show="status === 'cancelled'">Order Cancelled</span>
                        </h1>
                        <p class="text-orange-100 text-xs sm:text-sm mt-1">
                            Dispatched from <strong class="text-white">{{ $fulfilmentOrder?->store?->name ?? 'OneStall Dark Store' }}</strong>
                        </p>
                    </div>

                    <!-- Prominent Delivery OTP Card -->
                    <div class="bg-white/10 backdrop-blur-md border border-white/30 rounded-2xl p-4 text-center min-w-[160px] shadow-lg">
                        <span class="text-[11px] uppercase tracking-wider text-orange-200 font-bold block mb-1">Delivery OTP</span>
                        <div class="text-3xl font-mono font-black text-white tracking-widest" x-text="otp">
                            {{ $fulfilmentOrder?->delivery_otp ?? '482910' }}
                        </div>
                        <span class="text-[10px] text-orange-100/90 block mt-1">Share with rider upon arrival</span>
                    </div>
                </div>
            </div>

            <!-- Stepper Progress Bar -->
            <div class="p-6 sm:p-8 border-b border-slate-100">
                <div class="relative">
                    <div class="overflow-hidden h-2 mb-4 text-xs flex rounded-full bg-slate-100">
                        <div class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-gradient-to-r from-orange-500 to-emerald-500 transition-all duration-500"
                             :style="{
                                 width: status === 'placed' ? '15%' :
                                        (status === 'store_accepted' ? '30%' :
                                        (status === 'packing' ? '50%' :
                                        (status === 'ready' || status === 'rider_assigned' ? '70%' :
                                        (status === 'picked_up' || status === 'out_for_delivery' ? '85%' :
                                        (status === 'delivered' ? '100%' : '5%')))))
                             }">
                        </div>
                    </div>

                    <div class="grid grid-cols-4 sm:grid-cols-5 text-center text-xs">
                        <div class="flex flex-col items-center">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs mb-1"
                                 :class="['placed', 'store_accepted', 'packing', 'ready', 'rider_assigned', 'picked_up', 'out_for_delivery', 'delivered'].includes(status) ? 'bg-orange-500 text-white' : 'bg-slate-200 text-slate-500'">
                                ✓
                            </div>
                            <span class="font-bold text-slate-900 text-[11px]">Placed</span>
                        </div>

                        <div class="flex flex-col items-center">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs mb-1"
                                 :class="['store_accepted', 'packing', 'ready', 'rider_assigned', 'picked_up', 'out_for_delivery', 'delivered'].includes(status) ? 'bg-orange-500 text-white' : 'bg-slate-200 text-slate-500'">
                                2
                            </div>
                            <span class="font-bold text-slate-900 text-[11px]">Accepted</span>
                        </div>

                        <div class="flex flex-col items-center">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs mb-1"
                                 :class="['packing', 'ready', 'rider_assigned', 'picked_up', 'out_for_delivery', 'delivered'].includes(status) ? 'bg-orange-500 text-white' : 'bg-slate-200 text-slate-500'">
                                3
                            </div>
                            <span class="font-bold text-slate-900 text-[11px]">Packing</span>
                        </div>

                        <div class="flex flex-col items-center">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs mb-1"
                                 :class="['picked_up', 'out_for_delivery', 'delivered'].includes(status) ? 'bg-orange-500 text-white' : 'bg-slate-200 text-slate-500'">
                                4
                            </div>
                            <span class="font-bold text-slate-900 text-[11px]">On the Way</span>
                        </div>

                        <div class="hidden sm:flex flex-col items-center">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs mb-1"
                                 :class="status === 'delivered' ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-500'">
                                5
                            </div>
                            <span class="font-bold text-slate-900 text-[11px]">Delivered</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Simulated Live GPS Map View -->
            <div class="p-6 sm:p-8 bg-slate-50 border-b border-slate-200">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                        <span>Live Route & Telemetry</span>
                    </h3>
                    <span class="inline-flex items-center gap-1.5 text-xs text-emerald-600 font-semibold bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                        GPS Live Tracking
                    </span>
                </div>

                <!-- Map Canvas Container -->
                <div class="relative w-full h-64 sm:h-80 bg-slate-900 rounded-2xl overflow-hidden shadow-inner flex items-center justify-center border border-slate-800">
                    <!-- Stylized SVG Map Grid -->
                    <svg class="absolute inset-0 w-full h-full opacity-20" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                                <path d="M 40 0 L 0 0 0 40" fill="none" stroke="#60a5fa" stroke-width="1"/>
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#grid)" />
                    </svg>

                    <!-- Route Connection Path -->
                    <svg class="absolute inset-0 w-full h-full pointer-events-none">
                        <line x1="20%" y1="50%" x2="80%" y2="50%" stroke="#f97316" stroke-width="4" stroke-dasharray="6,6" class="animate-pulse" />
                    </svg>

                    <!-- Store Pin -->
                    <div class="absolute left-[20%] top-[50%] -translate-x-1/2 -translate-y-1/2 flex flex-col items-center">
                        <div class="w-10 h-10 bg-orange-600 text-white rounded-full flex items-center justify-center shadow-lg border-2 border-white">
                            🏬
                        </div>
                        <span class="text-[10px] bg-slate-900/90 text-white font-bold px-2 py-0.5 rounded mt-1 whitespace-nowrap">
                            Store Outlet
                        </span>
                    </div>

                    <!-- Rider Moving Pin -->
                    <div class="absolute top-[50%] -translate-x-1/2 -translate-y-1/2 flex flex-col items-center transition-all duration-1000"
                         :style="{
                             left: status === 'placed' || status === 'store_accepted' || status === 'packing' ? '25%' :
                                   (status === 'ready' || status === 'rider_assigned' ? '35%' :
                                   (status === 'picked_up' || status === 'out_for_delivery' ? '60%' : '80%'))
                         }">
                        <div class="w-12 h-12 bg-amber-400 text-slate-900 rounded-full flex items-center justify-center shadow-2xl border-4 border-white animate-bounce text-xl">
                            🛵
                        </div>
                        <span class="text-[10px] bg-amber-400 text-slate-950 font-black px-2 py-0.5 rounded shadow mt-1 whitespace-nowrap">
                            Rider on the move
                        </span>
                    </div>

                    <!-- Customer Home Pin -->
                    <div class="absolute left-[80%] top-[50%] -translate-x-1/2 -translate-y-1/2 flex flex-col items-center">
                        <div class="w-10 h-10 bg-emerald-600 text-white rounded-full flex items-center justify-center shadow-lg border-2 border-white">
                            🏠
                        </div>
                        <span class="text-[10px] bg-slate-900/90 text-white font-bold px-2 py-0.5 rounded mt-1 whitespace-nowrap">
                            Your Address
                        </span>
                    </div>
                </div>
            </div>

            <!-- Rider & Delivery Details Row -->
            <div class="p-6 sm:p-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Rider Profile Card -->
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Delivery Partner</h4>
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-orange-100 text-orange-600 flex items-center justify-center font-extrabold text-xl shadow-inner">
                            🛵
                        </div>
                        <div>
                            <h5 class="font-bold text-slate-900 text-sm" x-text="rider ? rider.name : 'Rider Assigning Soon...'">
                                {{ $fulfilmentOrder?->deliveryAssignment?->deliveryPartner?->name ?? 'Rider Assigning...' }}
                            </h5>
                            <p class="text-xs text-slate-500 mt-0.5" x-text="rider ? (rider.vehicle + ' • ' + rider.vehicle_number) : 'OneStall Fleet'">
                                {{ $fulfilmentOrder?->deliveryAssignment?->deliveryPartner?->vehicle ?? 'Motorcycle' }}
                            </p>
                            <div class="flex items-center gap-3 mt-2">
                                <a :href="'tel:' + (rider ? rider.phone : '#')" class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-bold text-xs rounded-lg transition border border-emerald-200">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                    <span>Call Rider</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Destination Address Card -->
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Delivery Destination</h4>
                    <h5 class="font-bold text-slate-900 text-sm mb-1">
                        {{ $order->customerAddress?->name ?? 'Delivery Address' }}
                    </h5>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        {{ $order->customerAddress?->line1 }}, {{ $order->customerAddress?->city }} {{ $order->customerAddress?->pincode }}
                    </p>
                    <p class="text-xs text-slate-400 mt-2">
                        Phone: {{ $order->customerAddress?->phone ?? 'N/A' }}
                    </p>
                </div>
            </div>

            <!-- Items in this package -->
            <div class="p-6 sm:p-8 bg-slate-50/50 border-t border-slate-100">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Items in Package ({{ $fulfilmentOrder?->items?->count() ?? 0 }})</h4>
                <div class="divide-y divide-slate-100">
                    @foreach($fulfilmentOrder?->items ?? [] as $item)
                        <div class="py-2.5 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-3">
                                <span class="font-bold text-slate-900">{{ $item->qty }}x</span>
                                <div>
                                    <p class="font-medium text-slate-900">{{ $item->title }}</p>
                                    @if($item->variant_name)
                                        <p class="text-[11px] text-slate-400">{{ $item->variant_name }}</p>
                                    @endif
                                </div>
                            </div>
                            <span class="font-bold text-slate-900">₹{{ number_format($item->total, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
