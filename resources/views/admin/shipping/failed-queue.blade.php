@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Failed Shipment Queue'])

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    <!-- Header -->
    <x-page-header 
        title="Failed Shipments Queue" 
        subtitle="Manage and retry consignments that failed carrier API booking, AWB generation, or pickup requests."
        :breadcrumbs="[
            'Command Console' => route('admin.dashboard'), 
            'Shipping & Logistics' => route('admin.shipping.index'),
            'Failed Queue' => '#'
        ]"
    >
        <x-slot name="actions">
            <x-button variant="outline" size="sm" href="{{ route('admin.shipping.index') }}">
                &larr; Back to Logistics Overview
            </x-button>
        </x-slot>
    </x-page-header>

    <!-- Failed Shipments Table -->
    <x-card padding="p-0" class="overflow-hidden">
        @if($failedShipments->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="py-3 px-4">Shipment #</th>
                            <th class="py-3 px-4">Order / Package</th>
                            <th class="py-3 px-4">Merchant</th>
                            <th class="py-3 px-4">Courier Partner</th>
                            <th class="py-3 px-4">Status &amp; Failure</th>
                            <th class="py-3 px-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($failedShipments as $shp)
                            @php
                                $fo = $shp->fulfilmentOrder;
                                $order = $fo?->order;
                            @endphp
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3 px-4 font-mono text-xs">
                                    <div class="font-bold text-slate-900 dark:text-slate-100">{{ $shp->shipment_number }}</div>
                                    <div class="text-[10px] text-slate-400 font-sans mt-0.5">
                                        {{ $shp->created_at->format('d M Y, h:i A') }}
                                    </div>
                                </td>

                                <td class="py-3 px-4 text-xs">
                                    @if($order)
                                        <a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-primary-600 hover:underline">
                                            #{{ $order->order_number }}
                                        </a>
                                        <div class="text-slate-500 font-mono text-[11px]">{{ $fo?->fulfilment_order_number }}</div>
                                    @endif
                                </td>

                                <td class="py-3 px-4 text-xs">
                                    <div class="font-medium text-slate-800 dark:text-slate-200">
                                        {{ $fo?->seller?->company_name ?? 'Merchant' }}
                                    </div>
                                </td>

                                <td class="py-3 px-4 text-xs">
                                    <div class="font-semibold text-slate-800 dark:text-slate-200">{{ $shp->courier }}</div>
                                    <div class="text-[10px] text-slate-400 uppercase">{{ $shp->service ?? 'Surface' }}</div>
                                </td>

                                <td class="py-3 px-4 text-xs">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                        {{ $shp->status->label() }}
                                    </span>
                                    <div class="text-[10px] text-rose-600 font-medium mt-1">
                                        {{ $shp->awb ? 'Pickup dispatch error' : 'Missing AWB / API Timeout' }}
                                    </div>
                                </td>

                                <td class="py-3 px-4 text-right">
                                    <form method="POST" action="{{ route('admin.shipping.retry_shipment', $shp) }}">
                                        @csrf
                                        <x-button variant="primary" size="sm" type="submit" class="text-xs">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            Retry Booking
                                        </x-button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $failedShipments->links() }}
            </div>
        @else
            <x-empty-state 
                title="Failed Consignments Queue Clear" 
                description="All carrier consignments have successfully generated AWBs and are in active transit."
                icon="check-circle"
            />
        @endif
    </x-card>
</div>
@endsection
