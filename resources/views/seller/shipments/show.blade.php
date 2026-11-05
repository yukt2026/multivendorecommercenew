@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Shipment ' . ($shipment->awb ?? $shipment->shipment_number)])

@section('content')
<div class="space-y-6" x-data="{
    pickupModalOpen: false,
    ndrModalOpen: false,
    ndrAction: 'reattempt'
}">
    <!-- Header -->
    <x-page-header 
        title="Consignment: {{ $shipment->awb ?? $shipment->shipment_number }}" 
        subtitle="OneStall Cargo real-time tracking, courier waybill status, and exception handling."
        :breadcrumbs="[
            'Seller Studio' => route('seller.dashboard'), 
            'Shipments' => route('seller.shipments.index'), 
            $shipment->awb ?? $shipment->shipment_number => '#'
        ]"
    >
        <x-slot name="badge">
            <div class="flex items-center space-x-2">
                @php
                    $badgeColor = match($shipment->status->value) {
                        'manifest_created' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                        'picked_up' => 'bg-purple-50 text-purple-700 border-purple-200',
                        'in_transit' => 'bg-sky-50 text-sky-700 border-sky-200',
                        'out_for_delivery' => 'bg-blue-50 text-blue-700 border-blue-200',
                        'delivered' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        'ndr_raised' => 'bg-rose-50 text-rose-700 border-rose-200 font-bold animate-pulse',
                        'rto_initiated', 'rto_delivered' => 'bg-amber-50 text-amber-700 border-amber-200',
                        default => 'bg-slate-100 text-slate-700 border-slate-200',
                    };
                @endphp
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border {{ $badgeColor }}">
                    {{ $shipment->status->label() }}
                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-mono border bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    {{ $shipment->courier }}
                </span>
            </div>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center gap-2">
                <x-button variant="outline" size="sm" href="{{ route('seller.shipments.index') }}">
                    &larr; All Shipments
                </x-button>

                <form method="POST" action="{{ route('seller.shipments.sync_tracking', $shipment) }}">
                    @csrf
                    <x-button variant="outline" size="sm" type="submit">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Sync Live Tracking
                    </x-button>
                </form>

                <x-button variant="outline" size="sm" href="{{ route('seller.shipments.label', $shipment) }}" target="_blank">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print Label
                </x-button>

                @if($shipment->pickup_status !== 'completed' && $shipment->status === \App\Enums\ShipmentStatus::MANIFEST_CREATED)
                    <x-button variant="primary" size="sm" @click="pickupModalOpen = true">
                        Request Carrier Pickup
                    </x-button>
                @endif
            </div>
        </x-slot>
    </x-page-header>

    @php
        $fo = $shipment->fulfilmentOrder;
        $order = $fo?->order;
        $customer = $order?->user ?? $order?->customer?->user;
        $activeNdr = $shipment->ndrCases->where('status', '!=', 'closed')->first();
        $rto = $shipment->rtoCases->first();
    @endphp

    <!-- NDR Alert Box & Action Trigger -->
    @if($shipment->status === \App\Enums\ShipmentStatus::NDR_RAISED || $activeNdr)
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-900 dark:text-rose-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2 font-black text-sm text-rose-700 dark:text-rose-400">
                    <svg class="w-5 h-5 text-rose-600 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>Delivery Attempt Failed (Non-Delivery Report #{{ $activeNdr?->id ?? 'NDR' }})</span>
                </div>
                <div class="text-xs text-rose-800 dark:text-rose-300 mt-1">
                    <strong>Carrier Reason:</strong> {{ $activeNdr?->reason ?? 'Customer contact failed / Delivery address incomplete' }}
                    &bull; Attempts Made: <span class="font-bold">{{ $activeNdr?->attempts ?? 1 }}</span>
                </div>
                @if($activeNdr?->customer_action)
                    <div class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                        Latest Instruction: <span class="font-semibold text-rose-700">{{ strtoupper($activeNdr->customer_action) }}</span> &bull; {{ $activeNdr->action_notes }}
                    </div>
                @endif
            </div>

            <x-button variant="primary" size="sm" class="bg-rose-600 hover:bg-rose-700 flex-shrink-0" @click="ndrModalOpen = true">
                Resolve NDR Exception
            </x-button>
        </div>
    @endif

    <!-- RTO Alert Box -->
    @if($shipment->status === \App\Enums\ShipmentStatus::RTO_INITIATED || $shipment->status === \App\Enums\ShipmentStatus::RTO_DELIVERED || $rto)
        <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-100 flex items-center justify-between">
            <div class="space-y-0.5">
                <div class="font-bold text-sm text-amber-800 dark:text-amber-300 flex items-center space-x-2">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"/></svg>
                    <span>Consignment In Return to Origin (RTO)</span>
                </div>
                <div class="text-xs text-amber-700 dark:text-amber-300">
                    Reason: {{ $rto?->reason ?? 'Maximum delivery attempts exhausted' }} &bull; Status: {{ ucwords(str_replace('_', ' ', $rto?->status ?? 'in_transit')) }}
                </div>
            </div>
            @if($shipment->status === \App\Enums\ShipmentStatus::RTO_DELIVERED)
                <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded">Stock Restocked at Origin</span>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Column: Tracking Events & Package Contents (2 cols) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Consignment Details -->
            <x-card>
                <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100">
                        Consignment Logistics Data
                    </h3>
                    <span class="font-mono text-xs font-black text-primary-600">
                        AWB: {{ $shipment->awb ?? 'Pending Assignment' }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                    <div>
                        <span class="text-slate-400">Carrier Partner:</span>
                        <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ $shipment->courier }}</div>
                    </div>
                    <div>
                        <span class="text-slate-400">Transit Mode:</span>
                        <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5 uppercase">{{ $shipment->service ?? 'Surface' }}</div>
                    </div>
                    <div>
                        <span class="text-slate-400">Declared Weight:</span>
                        <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ $shipment->weight }} kg</div>
                    </div>
                    <div>
                        <span class="text-slate-400">Pickup Status:</span>
                        <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5 capitalize">{{ $shipment->pickup_status ?? 'Pending' }}</div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-between items-center text-xs">
                    <div>
                        <span class="text-slate-400">OneStall Cargo Shipment Ref:</span>
                        <span class="font-mono font-semibold text-slate-800 dark:text-slate-200 ml-1">{{ $shipment->shipment_number }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400">Freight Charges:</span>
                        <span class="font-black text-slate-900 dark:text-slate-100 ml-1">{{ format_currency($shipment->charges) }}</span>
                    </div>
                </div>
            </x-card>

            <!-- Real-time Tracking Timeline -->
            <x-card>
                <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 flex items-center space-x-2">
                        <span>OneStall Cargo Live Tracking Timeline</span>
                    </h3>
                    <span class="text-xs text-slate-400">
                        {{ $shipment->tracking->count() }} event(s) recorded
                    </span>
                </div>

                @if($shipment->tracking->count() > 0)
                    <div class="mt-4 relative pl-6 border-l-2 border-primary-200 dark:border-primary-900 space-y-6">
                        @foreach($shipment->tracking as $tr)
                            <div class="relative">
                                <div class="absolute -left-[31px] top-1 w-3.5 h-3.5 rounded-full bg-primary-600 border-2 border-white dark:border-slate-900"></div>
                                <div class="text-xs font-bold text-slate-900 dark:text-slate-100">
                                    {{ ucwords(str_replace(['.', '_'], ' ', $tr->status)) }}
                                </div>
                                <div class="text-xs text-slate-600 dark:text-slate-300 mt-0.5">
                                    {{ $tr->location ?? 'Hub' }}
                                    @if(!empty($tr->raw['activity']))
                                        &bull; {{ $tr->raw['activity'] }}
                                    @endif
                                </div>
                                <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                    {{ $tr->event_time->format('d M Y, h:i A') }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-6 text-center text-xs text-slate-400">
                        Consignment registered. Real-time courier milestone events will appear here once carrier scans the package.
                    </div>
                @endif
            </x-card>

            <!-- Package Items Snapshot -->
            <x-card>
                <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 pb-3 border-b border-slate-200 dark:border-slate-800">
                    Consignment Manifested Items
                </h3>

                <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($fo->items as $item)
                        <div class="py-2.5 flex items-center justify-between text-xs">
                            <div>
                                <div class="font-semibold text-slate-900 dark:text-slate-100">{{ $item->title }}</div>
                                <div class="text-slate-500 font-mono text-[11px]">
                                    SKU: {{ $item->sku }} &bull; Qty: {{ $item->qty }}
                                </div>
                            </div>
                            <div class="font-bold text-slate-800 dark:text-slate-200">
                                {{ format_currency($item->total) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>

        <!-- Sidebar Column (1 col) -->
        <div class="space-y-6">
            <!-- Order & Delivery Destination -->
            <x-card>
                <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 pb-3 border-b border-slate-200 dark:border-slate-800">
                    Destination &amp; Customer
                </h3>

                <div class="mt-3 space-y-3 text-xs">
                    <div>
                        <span class="text-slate-400">Order Number:</span>
                        <div class="font-bold text-slate-900 dark:text-slate-100">
                            @if($order)
                                <a href="{{ route('seller.orders.show', $fo) }}" class="text-primary-600 hover:underline">
                                    #{{ $order->order_number }}
                                </a>
                            @endif
                        </div>
                    </div>

                    <div>
                        <span class="text-slate-400">Recipient Name:</span>
                        <div class="font-semibold text-slate-900 dark:text-slate-100">
                            {{ $customer?->name ?? 'Customer' }}
                        </div>
                        <div class="text-slate-500 font-mono text-[11px]">{{ $customer?->phone ?? '9876543210' }}</div>
                    </div>

                    <div>
                        <span class="text-slate-400">Delivery Address:</span>
                        <div class="text-slate-700 dark:text-slate-300 mt-0.5">
                            {{ $order?->customerAddress?->line1 ?? 'Address' }}, {{ $order?->customerAddress?->city ?? 'Bengaluru' }}, {{ $order?->customerAddress?->state ?? 'Karnataka' }} - <span class="font-bold">{{ $order?->customerAddress?->pincode ?? '560001' }}</span>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                        <span class="text-slate-400">Payment Type:</span>
                        <div class="font-bold text-slate-900 dark:text-slate-100 mt-0.5">
                            @if($shipment->cod_amount > 0)
                                <span class="text-amber-600">Cash on Delivery (Collect: {{ format_currency($shipment->cod_amount) }})</span>
                            @else
                                <span class="text-emerald-600">Prepaid Consignment</span>
                            @endif
                        </div>
                    </div>
                </div>
            </x-card>

            <!-- COD Remittance Ledger Status -->
            @if($shipment->codRemittance || $shipment->cod_amount > 0)
                <x-card>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 pb-3 border-b border-slate-200 dark:border-slate-800">
                        COD Courier Remittance
                    </h3>

                    @php $remit = $shipment->codRemittance; @endphp
                    <div class="mt-3 space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Collectable Amount:</span>
                            <span class="font-black text-slate-900 dark:text-slate-100">{{ format_currency($shipment->cod_amount) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Remittance Status:</span>
                            <span class="font-bold capitalize {{ $remit?->status->value === 'remitted' ? 'text-emerald-600' : 'text-amber-600' }}">
                                {{ $remit?->status?->label() ?? 'Pending Delivery' }}
                            </span>
                        </div>
                        @if($remit?->reference)
                            <div class="flex justify-between">
                                <span class="text-slate-500">Bank UTR Ref:</span>
                                <span class="font-mono text-slate-700 dark:text-slate-300">{{ $remit->reference }}</span>
                            </div>
                        @endif
                        @if($remit?->remitted_at)
                            <div class="text-[11px] text-slate-400">
                                Remitted on: {{ $remit->remitted_at->format('d M Y, h:i A') }}
                            </div>
                        @endif
                    </div>
                </x-card>
            @endif
        </div>
    </div>

    <!-- Modal: Request Carrier Pickup -->
    <div x-show="pickupModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div @click.away="pickupModalOpen = false" class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 dark:border-slate-800">
            <h3 class="font-bold text-base text-slate-900 dark:text-slate-100">Schedule Carrier Warehouse Pickup</h3>
            <p class="text-xs text-slate-500 mt-1">
                Carrier vehicle dispatch will be booked with OneStall Cargo for your origin warehouse.
            </p>

            <form method="POST" action="{{ route('seller.shipments.request_pickup', $shipment) }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Pickup Date *</label>
                    <input type="date" name="pickup_date" value="{{ now()->format('Y-m-d') }}" min="{{ now()->format('Y-m-d') }}" required class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Preferred Time Window</label>
                    <select name="time_slot" class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5">
                        <option value="10:00 - 13:00">Morning (10:00 AM - 01:00 PM)</option>
                        <option value="14:00 - 18:00" selected>Afternoon (02:00 PM - 06:00 PM)</option>
                        <option value="18:00 - 21:00">Evening (06:00 PM - 09:00 PM)</option>
                    </select>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <x-button variant="outline" size="sm" type="button" @click="pickupModalOpen = false">Cancel</x-button>
                    <x-button variant="primary" size="sm" type="submit">Confirm Pickup Dispatch</x-button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Resolve NDR Exception -->
    <div x-show="ndrModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div @click.away="ndrModalOpen = false" class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 dark:border-slate-800">
            <h3 class="font-bold text-base text-rose-600">Resolve Non-Delivery Exception (NDR)</h3>
            <p class="text-xs text-slate-500 mt-1">
                Instruct the courier on what action to take for failed delivery attempts.
            </p>

            <form method="POST" action="{{ route('seller.shipments.ndr_action', $shipment) }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Resolution Action *</label>
                    <select name="action" x-model="ndrAction" required class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5">
                        <option value="reattempt">Schedule Re-attempt (Contacted customer, ready to receive)</option>
                        <option value="change_address">Update Address / Contact Phone</option>
                        <option value="rto">Return to Origin (RTO - Cancel delivery &amp; return)</option>
                    </select>
                </div>

                <div x-show="ndrAction === 'change_address'" class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Updated Phone Number</label>
                        <input type="text" name="new_phone" placeholder="New customer phone number" class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Corrected Address Notes</label>
                        <input type="text" name="new_address" placeholder="Landmark or updated house/flat info" class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Remarks for Courier Partner</label>
                    <textarea name="remarks" rows="2" placeholder="e.g. Spoke to buyer, customer requested delivery tomorrow before 2 PM." class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5"></textarea>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <x-button variant="outline" size="sm" type="button" @click="ndrModalOpen = false">Cancel</x-button>
                    <x-button variant="primary" size="sm" type="submit" class="bg-rose-600 hover:bg-rose-700">Submit Instruction</x-button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
