@extends('layouts.rider', ['title' => 'Delivery History - OneStall Rider'])

@section('content')
<div class="space-y-4">

    <!-- Header & Summary Metrics -->
    <div class="space-y-1">
        <h1 class="text-base font-black text-slate-900">Delivery History</h1>
        <p class="text-xs text-slate-500">Your completed and archived delivery dispatches.</p>
    </div>

    <!-- Quick Metrics Strip -->
    <div class="grid grid-cols-3 gap-2 text-center">
        <div class="bg-white rounded-2xl p-3 border border-slate-200 shadow-xs">
            <div class="text-[10px] font-bold text-slate-400 uppercase">Trips</div>
            <div class="text-lg font-black text-slate-900 mt-0.5">
                {{ number_format($metrics['total_completed']) }}
            </div>
        </div>

        <div class="bg-white rounded-2xl p-3 border border-slate-200 shadow-xs">
            <div class="text-[10px] font-bold text-slate-400 uppercase">Distance</div>
            <div class="text-lg font-black text-indigo-600 mt-0.5">
                {{ number_format($metrics['total_distance'], 1) }} <span class="text-[10px] text-slate-400 font-normal">km</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-3 border border-slate-200 shadow-xs">
            <div class="text-[10px] font-bold text-slate-400 uppercase">Earned</div>
            <div class="text-lg font-black text-emerald-600 font-mono mt-0.5">
                ₹{{ number_format($metrics['total_earned'], 0) }}
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="flex items-center space-x-1.5 p-1 bg-slate-200 rounded-xl text-xs font-bold">
        <a 
            href="{{ route('rider.history', ['status' => 'all']) }}" 
            class="flex-1 py-1.5 text-center rounded-lg transition {{ $filter === 'all' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
        >
            All
        </a>
        <a 
            href="{{ route('rider.history', ['status' => 'delivered']) }}" 
            class="flex-1 py-1.5 text-center rounded-lg transition {{ $filter === 'delivered' ? 'bg-white text-emerald-700 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
        >
            Delivered
        </a>
        <a 
            href="{{ route('rider.history', ['status' => 'failed']) }}" 
            class="flex-1 py-1.5 text-center rounded-lg transition {{ $filter === 'failed' ? 'bg-white text-rose-700 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
        >
            Failed
        </a>
    </div>

    <!-- History List -->
    @if($assignments->isEmpty())
        <div class="bg-white rounded-3xl p-8 text-center border border-dashed border-slate-300 space-y-2">
            <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto text-2xl">
                📜
            </div>
            <h3 class="font-bold text-slate-800 text-xs">No delivery history records found</h3>
            <p class="text-[11px] text-slate-500">Your completed delivery jobs will appear here.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($assignments as $assignment)
                @php
                    $fo = $assignment->fulfilmentOrder;
                    $earning = $assignment->earnings;
                    $isDelivered = $assignment->status->value === 'delivered';
                @endphp
                <div class="bg-white rounded-2xl p-4 shadow-xs border border-slate-200 space-y-2.5">
                    <div class="flex items-center justify-between text-xs pb-2 border-b border-slate-100">
                        <div>
                            <span class="font-mono font-bold text-slate-900">#{{ $fo?->fulfilment_order_number }}</span>
                            <div class="text-[10px] text-slate-400">
                                {{ $assignment->delivered_at?->format('d M Y, h:i A') ?? ($assignment->created_at?->format('d M Y, h:i A')) }}
                            </div>
                        </div>

                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase
                            {{ $isDelivered ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                            {{ $assignment->status->label() }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center text-xs">
                        <div>
                            <p class="font-bold text-slate-800">{{ $fo?->store?->name ?? 'Store Outlet' }}</p>
                            <p class="text-[11px] text-slate-500">&rarr; {{ $fo?->order?->customerAddress?->name ?? (data_get($fo?->order?->address_snapshot, 'name') ?? 'Customer') }}</p>
                        </div>

                        <div class="text-right">
                            @if($earning)
                                <div class="font-mono font-black text-emerald-600 text-sm">
                                    +₹{{ number_format($earning->total_payout, 2) }}
                                </div>
                                <div class="text-[9px] text-slate-400 font-mono">{{ number_format($earning->distance_km, 1) }} km</div>
                            @elseif($isDelivered)
                                <div class="font-mono font-bold text-slate-600 text-xs">Delivered</div>
                            @else
                                <div class="text-[10px] font-semibold text-rose-500 capitalize">
                                    {{ str_replace('_', ' ', $assignment->failure_reason ?? 'Failed') }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="pt-2">
            {{ $assignments->links() }}
        </div>
    @endif

</div>
@endsection
