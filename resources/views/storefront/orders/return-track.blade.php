@extends('layouts.storefront', ['title' => 'Track Return #' . $return->return_number])

@section('content')
<div class="min-h-screen bg-slate-50 py-10">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 space-y-6">

        <!-- Top Breadcrumbs & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <a href="{{ route('orders.show', $order->order_number) }}" class="inline-flex items-center text-xs font-bold text-indigo-600 hover:text-indigo-700 transition mb-2">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to Order #{{ $order->order_number }}
                </a>
                <div class="flex items-center space-x-3">
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">Return #{{ $return->return_number }}</h1>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-black border {{ $return->status->badgeClass() }}">
                        {{ $return->status->label() }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">Submitted on {{ $return->created_at->format('d M Y, h:i A') }}</p>
            </div>

            <div class="text-right">
                <div class="text-xs font-bold text-slate-500 uppercase">Estimated Refund</div>
                <div class="text-xl font-black text-emerald-600 mt-0.5">{{ format_currency((float)($return->refund_amount ?? $return->orderItem->total)) }}</div>
                <div class="text-[10px] text-slate-400 capitalize">Settlement via {{ $return->refund_mode }}</div>
            </div>
        </div>

        @php
            $stages = [
                ['key' => 'requested', 'label' => 'Requested', 'desc' => 'Customer submitted request'],
                ['key' => 'approved', 'label' => 'Approved', 'desc' => 'Merchant approved return'],
                ['key' => 'reverse_pickup_scheduled', 'label' => 'Pickup Scheduled', 'desc' => 'Logistics waybill generated'],
                ['key' => 'picked_up', 'label' => 'Picked Up', 'desc' => 'Carrier collected package'],
                ['key' => 'received', 'label' => 'Received', 'desc' => 'Delivered to inspection hub'],
                ['key' => 'qc_passed', 'label' => 'QC Inspection', 'desc' => 'Quality verification verified'],
                ['key' => 'refunded', 'label' => 'Refunded', 'desc' => 'Settlement processed'],
            ];

            $statusRanks = [
                'requested' => 1,
                'approved' => 2,
                'reverse_pickup_scheduled' => 3,
                'pickup_scheduled' => 3,
                'picked_up' => 4,
                'received' => 5,
                'qc_passed' => 6,
                'qc_failed' => 6,
                'refunded' => 7,
                'rejected' => 6,
                'cancelled' => 0,
            ];

            $currentRank = $statusRanks[$return->status->value] ?? 1;
            $isRejected = in_array($return->status->value, ['rejected', 'qc_failed']);
        @endphp

        <!-- Visual Lifecycle Progress Stepper -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-xs">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-6">Return Lifecycle Stepper</h3>

            <div class="relative">
                <div class="hidden sm:block absolute top-1/2 left-0 right-0 h-1 bg-slate-200 -translate-y-1/2 z-0"></div>
                <div 
                    class="hidden sm:block absolute top-1/2 left-0 h-1 bg-indigo-600 -translate-y-1/2 z-0 transition-all duration-500"
                    style="width: {{ min(100, max(0, (($currentRank - 1) / (count($stages) - 1)) * 100)) }}%;"
                ></div>

                <div class="relative z-10 flex flex-col sm:flex-row justify-between gap-6 sm:gap-2">
                    @foreach($stages as $idx => $st)
                        @php
                            $stepRank = $idx + 1;
                            $isComplete = $currentRank > $stepRank || ($currentRank === count($stages) && $stepRank === count($stages));
                            $isCurrent = $currentRank === $stepRank;
                        @endphp
                        <div class="flex sm:flex-col items-center sm:text-center space-x-3 sm:space-x-0">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs transition shrink-0
                                {{ $isComplete ? 'bg-emerald-600 text-white' : ($isCurrent ? ($isRejected ? 'bg-rose-600 text-white animate-pulse' : 'bg-indigo-600 text-white ring-4 ring-indigo-100') : 'bg-slate-200 text-slate-500') }}
                            ">
                                @if($isComplete)
                                    ✓
                                @else
                                    {{ $stepRank }}
                                @endif
                            </div>
                            <div class="sm:mt-2">
                                <div class="text-xs font-black {{ $isCurrent ? ($isRejected ? 'text-rose-600' : 'text-indigo-600') : ($isComplete ? 'text-slate-900' : 'text-slate-400') }}">
                                    {{ $st['label'] }}
                                </div>
                                <div class="text-[10px] text-slate-400 hidden sm:block max-w-[100px]">{{ $st['desc'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if($return->pickup_waybill)
                <div class="mt-8 p-4 rounded-2xl bg-indigo-50/70 border border-indigo-200/80 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex items-center space-x-2.5">
                        <span class="text-xl">🚚</span>
                        <div>
                            <span class="font-bold text-indigo-950">Reverse Logistics Waybill:</span>
                            <span class="font-mono font-black text-indigo-700 ml-1">{{ $return->pickup_waybill }}</span>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 bg-white text-indigo-700 font-bold rounded-lg border border-indigo-200 text-[11px]">
                        OneStall Cargo Logistics
                    </span>
                </div>
            @endif

            @if($return->rejection_reason)
                <div class="mt-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-xs text-rose-800">
                    <div class="font-bold uppercase text-[10px] tracking-wider text-rose-600">Rejection Note</div>
                    <div class="mt-1 font-medium">{{ $return->rejection_reason }}</div>
                </div>
            @endif
        </div>

        <!-- Details Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- Item & Reason -->
            <div class="md:col-span-2 space-y-6">
                <!-- Item Summary Card -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 space-y-4 shadow-xs">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Returned Item Details</h3>
                    <div class="flex items-start space-x-4">
                        <div class="w-14 h-14 rounded-2xl bg-slate-100 border border-slate-200 overflow-hidden shrink-0 flex items-center justify-center font-bold text-slate-400">
                            📦
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-black text-slate-900">{{ $return->orderItem?->title }}</h4>
                            @if($return->orderItem?->variant_name)
                                <div class="text-xs text-slate-500 font-medium">{{ $return->orderItem->variant_name }}</div>
                            @endif
                            <div class="text-xs text-slate-600 mt-1">
                                Qty: <strong>{{ $return->orderItem?->qty }}</strong> &bull; 
                                Total Refundable: <strong>{{ format_currency((float)($return->refund_amount ?? $return->orderItem->total)) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reason & Photos Card -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 space-y-4 shadow-xs">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Claim Details</h3>
                    <div>
                        <div class="text-[10px] font-bold uppercase text-slate-400">Customer Stated Reason</div>
                        <div class="text-xs font-black text-slate-900 mt-0.5">{{ $return->reason }}</div>
                    </div>

                    @if($return->customer_notes)
                        <div>
                            <div class="text-[10px] font-bold uppercase text-slate-400">Additional Notes</div>
                            <div class="text-xs text-slate-700 bg-slate-50 p-3 rounded-xl border border-slate-100 mt-0.5 leading-relaxed">
                                {{ $return->customer_notes }}
                            </div>
                        </div>
                    @endif

                    @if(!empty($return->photos))
                        <div>
                            <div class="text-[10px] font-bold uppercase text-slate-400 mb-2">Uploaded Verification Photos</div>
                            <div class="grid grid-cols-4 gap-3">
                                @foreach($return->photos as $p)
                                    <a href="{{ $p }}" target="_blank" class="block aspect-square rounded-2xl overflow-hidden border border-slate-200 bg-slate-100 hover:opacity-90 transition">
                                        <img src="{{ $p }}" class="w-full h-full object-cover">
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Sidebar / Financials & Timeline -->
            <div class="space-y-6">

                <!-- Settlement Card -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 space-y-4 shadow-xs">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Refund Settlement</h3>
                    
                    <div class="flex items-center justify-between py-2 border-b border-slate-100">
                        <span class="text-xs text-slate-500">Refund Amount</span>
                        <span class="text-sm font-black text-slate-900">{{ format_currency((float)($return->refund_amount ?? $return->orderItem->total)) }}</span>
                    </div>

                    <div class="flex items-center justify-between py-2 border-b border-slate-100">
                        <span class="text-xs text-slate-500">Method</span>
                        <span class="text-xs font-bold text-slate-900 capitalize">{{ $return->refund_mode }}</span>
                    </div>

                    <div class="flex items-center justify-between py-2">
                        <span class="text-xs text-slate-500">QC Status</span>
                        <span class="text-xs font-bold text-slate-900 uppercase">{{ $return->qc_status->value }}</span>
                    </div>
                </div>

                <!-- Seller Context Card -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 space-y-2 shadow-xs">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Merchant Info</h3>
                    <div class="text-xs font-bold text-slate-900">{{ $return->seller?->business_name ?? 'OneStall Marketplace' }}</div>
                    @if($return->seller_notes)
                        <div class="text-[11px] text-slate-600 bg-slate-50 p-2.5 rounded-xl border border-slate-100 mt-2">
                            <span class="font-bold text-slate-700">Seller Note:</span> {{ $return->seller_notes }}
                        </div>
                    @endif
                </div>

            </div>

        </div>

    </div>
</div>
@endsection
