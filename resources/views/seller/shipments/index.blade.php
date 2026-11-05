@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Shipments & Logistics'])

@section('content')
<div class="space-y-6" x-data="{
    selectedShipments: [],
    selectAll: false,
    toggleAll() {
        if (this.selectAll) {
            this.selectedShipments = Array.from(document.querySelectorAll('.shipment-checkbox')).map(el => el.value);
        } else {
            this.selectedShipments = [];
        }
    }
}">
    <!-- Header -->
    <x-page-header 
        title="Shipments &amp; Consignments" 
        subtitle="Manage parcel dispatch, Air Waybills (AWBs), carrier pickups, and Non-Delivery Report (NDR) exceptions."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Shipments' => '#']"
    >
        <x-slot name="badge">
            @if($metrics['ndr_action_required'] > 0)
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black bg-rose-500/10 text-rose-500 border border-rose-500/20">
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse mr-1.5"></span>
                    {{ $metrics['ndr_action_required'] }} NDR Exception{{ $metrics['ndr_action_required'] > 1 ? 's' : '' }}
                </span>
            @endif
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center gap-2">
                <x-button variant="outline" size="sm" href="{{ route('seller.shipments.export') }}">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export CSV
                </x-button>

                <form method="POST" action="{{ route('seller.shipments.bulk_labels') }}" target="_blank" x-show="selectedShipments.length > 0">
                    @csrf
                    <template x-for="id in selectedShipments" :key="id">
                        <input type="hidden" name="shipment_ids[]" :value="id">
                    </template>
                    <x-button variant="primary" size="sm" type="submit">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Print Selected Labels (<span x-text="selectedShipments.length"></span>)
                    </x-button>
                </form>
            </div>
        </x-slot>
    </x-page-header>

    <!-- KPI Summary -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <x-stat-card 
            label="Total Consignments" 
            :value="$metrics['total_shipments']"
            color="primary"
        />
        <x-stat-card 
            label="In Transit" 
            :value="$metrics['in_transit']"
            color="sky"
        />
        <x-stat-card 
            label="NDR Exceptions" 
            :value="$metrics['ndr_action_required']"
            color="rose"
        />
        <x-stat-card 
            label="Delivered" 
            :value="$metrics['delivered']"
            color="emerald"
        />
        <x-stat-card 
            label="RTO In Progress" 
            :value="$metrics['rto']"
            color="amber"
        />
    </div>

    <!-- Toolbar Filters -->
    <x-card padding="p-4">
        <form method="GET" action="{{ route('seller.shipments.index') }}" class="flex flex-col sm:flex-row gap-3 items-center justify-between">
            <div class="flex items-center gap-2 overflow-x-auto w-full sm:w-auto pb-1 sm:pb-0">
                @php
                    $activeStatus = request('status', 'all');
                    $statuses = [
                        'all' => 'All Shipments',
                        'manifest_created' => 'Manifested',
                        'in_transit' => 'In Transit',
                        'out_for_delivery' => 'Out for Delivery',
                        'ndr_raised' => 'NDR Raised',
                        'delivered' => 'Delivered',
                        'rto_initiated' => 'RTO',
                    ];
                @endphp

                @foreach($statuses as $key => $label)
                    <a 
                        href="{{ route('seller.shipments.index', array_merge(request()->query(), ['status' => $key, 'page' => 1])) }}"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition {{ $activeStatus === $key ? 'bg-primary-600 text-white shadow-sm' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="flex items-center gap-2 w-full sm:w-72">
                <div class="relative w-full">
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ request('search') }}" 
                        placeholder="Search AWB, shipment #, order #..." 
                        class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    />
                    <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                @if(request('search') || request('status'))
                    <a href="{{ route('seller.shipments.index') }}" class="text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 underline">Reset</a>
                @endif
            </div>
        </form>
    </x-card>

    <!-- Table -->
    <x-card padding="p-0" class="overflow-hidden">
        @if($shipments->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="py-3 px-4 w-10">
                                <input type="checkbox" x-model="selectAll" @change="toggleAll()" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                            </th>
                            <th class="py-3 px-4">Consignment / AWB</th>
                            <th class="py-3 px-4">Order / Customer</th>
                            <th class="py-3 px-4">Carrier &amp; Service</th>
                            <th class="py-3 px-4">Weight &amp; Type</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($shipments as $shp)
                            @php
                                $fo = $shp->fulfilmentOrder;
                                $order = $fo?->order;
                                $ndr = $shp->ndrCases->first();
                            @endphp
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3 px-4">
                                    <input type="checkbox" value="{{ $shp->id }}" x-model="selectedShipments" class="shipment-checkbox rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                                </td>

                                <td class="py-3 px-4 font-mono text-xs">
                                    <a href="{{ route('seller.shipments.show', $shp) }}" class="font-bold text-primary-600 hover:underline">
                                        {{ $shp->awb ?? $shp->shipment_number }}
                                    </a>
                                    <div class="text-[11px] text-slate-400 font-sans mt-0.5">
                                        Ref: {{ $shp->shipment_number }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-sans">
                                        {{ $shp->created_at->format('d M Y, h:i A') }}
                                    </div>
                                </td>

                                <td class="py-3 px-4 text-xs">
                                    @if($order)
                                        <a href="{{ route('seller.orders.show', $fo) }}" class="font-semibold text-slate-900 dark:text-slate-100 hover:text-primary-600">
                                            #{{ $order->order_number }}
                                        </a>
                                    @endif
                                    <div class="text-slate-500">{{ $order?->customer?->user?->name ?? 'Customer' }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $order?->customerAddress?->city ?? 'Bengaluru' }} ({{ $order?->customerAddress?->pincode ?? '560001' }})</div>
                                </td>

                                <td class="py-3 px-4 text-xs">
                                    <div class="font-semibold text-slate-800 dark:text-slate-200">
                                        {{ $shp->courier }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 uppercase tracking-wide">
                                        {{ $shp->service ?? 'Surface' }}
                                    </div>
                                </td>

                                <td class="py-3 px-4 text-xs">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ $shp->weight }} kg
                                    </div>
                                    @if($shp->cod_amount > 0)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                            COD {{ format_currency($shp->cod_amount) }}
                                        </span>
                                    @else
                                        <span class="text-[11px] text-slate-400">Prepaid</span>
                                    @endif
                                </td>

                                <td class="py-3 px-4">
                                    @php
                                        $badgeColor = match($shp->status->value) {
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
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold border {{ $badgeColor }}">
                                        {{ $shp->status->label() }}
                                    </span>

                                    @if($shp->status === \App\Enums\ShipmentStatus::NDR_RAISED && $ndr)
                                        <div class="text-[10px] text-rose-600 font-medium mt-1">
                                            Reason: {{ Str::limit($ndr->reason, 25) }}
                                        </div>
                                    @endif
                                </td>

                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end space-x-1.5">
                                        <a href="{{ route('seller.shipments.label', $shp) }}" target="_blank" class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 hover:text-slate-900 dark:hover:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 transition" title="Print Shipping Label">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        </a>

                                        <x-button variant="outline" size="sm" href="{{ route('seller.shipments.show', $shp) }}" class="text-xs">
                                            Track
                                        </x-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $shipments->links() }}
            </div>
        @else
            <x-empty-state 
                title="No Cargo Shipments Found" 
                description="When orders are prepared and set to 'Ready to Ship', standard delivery consignments are automatically generated here with AWB tracking."
                icon="truck"
            />
        @endif
    </x-card>
</div>
@endsection
