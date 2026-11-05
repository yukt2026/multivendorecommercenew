@extends('layouts.storefront')

@section('title', 'Track Package #' . $fo->fulfilment_order_number . ' - ' . setting('name', 'OneStall.in'))

@section('content')
<div class="min-h-screen bg-slate-50 py-8 sm:py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 space-y-8">

        <!-- Breadcrumbs & Heading -->
        <div>
            <nav class="flex items-center space-x-2 text-xs font-semibold text-slate-400 mb-2">
                <a href="{{ route('home') }}" class="hover:text-slate-700">Home</a>
                <span>&rsaquo;</span>
                <a href="{{ route('orders.index') }}" class="hover:text-slate-700">My Orders</a>
                <span>&rsaquo;</span>
                <a href="{{ route('orders.show', $order->order_number) }}" class="hover:text-slate-700">#{{ $order->order_number }}</a>
                <span>&rsaquo;</span>
                <span class="text-slate-800">Track Package</span>
            </nav>
            <div class="flex items-center space-x-3">
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Track Package #{{ $fo->fulfilment_order_number }}</h1>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800">
                    {{ ucwords(str_replace('_', ' ', $fo->status)) }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Dispatched by <strong>{{ $fo->store?->name ?? $fo->seller?->business_name }}</strong> via <strong>{{ $fo->fulfilment_type->label() }}</strong>
            </p>
        </div>

        <!-- Tracking Milestone Card -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div>
                    <div class="text-xs text-slate-400 font-semibold uppercase">Destination</div>
                    <div class="text-sm font-bold text-slate-900 mt-0.5">
                        {{ $order->address_snapshot['name'] ?? 'Customer' }} &bull; {{ $order->address_snapshot['city'] ?? '' }} ({{ $order->address_snapshot['pincode'] ?? '' }})
                    </div>
                </div>

                @if($fo->shipment?->awb)
                    <div class="text-right">
                        <div class="text-xs text-slate-400 font-semibold uppercase">AWB / Courier Ref</div>
                        <div class="text-sm font-mono font-bold text-indigo-600 mt-0.5">
                            {{ $fo->shipment->awb }}
                        </div>
                    </div>
                @endif
            </div>

            @php
                $activeNdr = $fo->shipment?->ndrCases?->where('status', '!=', 'closed')?->first();
            @endphp

            @if($activeNdr)
                <!-- NDR Customer Resolution Box -->
                <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 space-y-3" x-data="{ ndrChoice: 'reattempt' }">
                    <div class="flex items-center space-x-2 font-bold text-sm text-rose-700">
                        <svg class="w-5 h-5 text-rose-600 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>Delivery Attempt Incomplete: {{ $activeNdr->reason }}</span>
                    </div>
                    <p class="text-xs text-rose-700">
                        The courier could not complete delivery. Please tell us how you would like the carrier to proceed:
                    </p>

                    <form method="POST" action="{{ route('orders.ndr.resolve', [$order->order_number, $fo->fulfilment_order_number]) }}" class="space-y-3 pt-1">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                            <label class="p-2.5 rounded-xl border bg-white cursor-pointer flex items-center space-x-2" :class="ndrChoice === 'reattempt' ? 'border-primary-600 ring-2 ring-primary-100' : 'border-slate-200'">
                                <input type="radio" name="action" value="reattempt" x-model="ndrChoice" class="text-primary-600">
                                <span class="font-bold text-slate-800">Re-attempt Tomorrow</span>
                            </label>
                            <label class="p-2.5 rounded-xl border bg-white cursor-pointer flex items-center space-x-2" :class="ndrChoice === 'change_address' ? 'border-primary-600 ring-2 ring-primary-100' : 'border-slate-200'">
                                <input type="radio" name="action" value="change_address" x-model="ndrChoice" class="text-primary-600">
                                <span class="font-bold text-slate-800">Update Phone/Landmark</span>
                            </label>
                            <label class="p-2.5 rounded-xl border bg-white cursor-pointer flex items-center space-x-2" :class="ndrChoice === 'rto' ? 'border-rose-600 ring-2 ring-rose-100' : 'border-slate-200'">
                                <input type="radio" name="action" value="rto" x-model="ndrChoice" class="text-rose-600">
                                <span class="font-bold text-slate-800">Cancel &amp; Return</span>
                            </label>
                        </div>

                        <div x-show="ndrChoice === 'change_address'" class="space-y-2">
                            <input type="text" name="new_phone" placeholder="Correct Phone Number" class="w-full text-xs rounded-lg border border-slate-300 p-2 bg-white">
                            <input type="text" name="new_address" placeholder="Nearby landmark or building instructions" class="w-full text-xs rounded-lg border border-slate-300 p-2 bg-white">
                        </div>

                        <div>
                            <input type="text" name="remarks" placeholder="Optional notes for delivery executive (e.g. Call before arrival, leave at security gate)" class="w-full text-xs rounded-lg border border-slate-300 p-2 bg-white">
                        </div>

                        <button type="submit" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-xs font-bold transition shadow-xs">
                            Submit Delivery Instruction
                        </button>
                    </form>
                </div>
            @endif

            <!-- Activity Logs / Milestones -->
            <div class="space-y-6">
                <div class="text-xs font-black uppercase tracking-wider text-slate-400">Tracking Event Milestones</div>

                @php
                    $trackingEvents = $fo->shipment?->tracking ?? collect();
                    $historyLogs = $fo->statusHistory()->latest()->get();
                @endphp

                @if($trackingEvents->isEmpty() && $historyLogs->isEmpty())
                    <div class="p-6 text-center text-xs text-slate-500 bg-slate-50 rounded-2xl border border-slate-200">
                        Package has been created and is queued for processing. Live scan events will appear shortly.
                    </div>
                @else
                    <div class="relative pl-6 sm:pl-8 space-y-6 before:absolute before:left-2.5 sm:before:left-3.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-indigo-200">
                        @foreach($historyLogs as $idx => $log)
                            <div class="relative">
                                <!-- Dot -->
                                <div class="absolute -left-6 sm:-left-8 top-1 w-3.5 h-3.5 rounded-full {{ $idx === 0 ? 'bg-indigo-600 ring-4 ring-indigo-100' : 'bg-slate-300' }}"></div>
                                
                                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-1">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-bold text-slate-900 capitalize">{{ str_replace('_', ' ', $log->to_status) }}</span>
                                        <span class="text-slate-400 font-mono">{{ $log->created_at->format('d M Y, h:i A') }}</span>
                                    </div>
                                    <p class="text-xs text-slate-600">{{ $log->note }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Back to Order -->
            <div class="pt-4 border-t border-slate-100 flex justify-between items-center text-xs">
                <a href="{{ route('orders.show', $order->order_number) }}" class="font-bold text-indigo-600 hover:text-indigo-800 transition">
                    &larr; Back to Order Details
                </a>
                <a href="{{ route('orders.invoice', $order->order_number) }}" class="font-semibold text-slate-600 hover:text-slate-900 transition">
                    Download Invoice PDF
                </a>
            </div>
        </div>

    </div>
</div>
@endsection
