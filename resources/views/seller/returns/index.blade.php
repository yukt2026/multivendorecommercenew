@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Returns & Replacements'])

@section('content')
<div class="space-y-6">
    <!-- Page Header -->
    <x-page-header 
        title="Returns &amp; Cancellations" 
        subtitle="Manage customer return requests, reverse logistics pickups, quality checks (QC), and refund settlements."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Returns' => '#']"
    >
        <x-slot name="badge">
            @if($metrics['pending_approval'] > 0)
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-500 border border-amber-500/20">
                    <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse mr-1.5"></span>
                    {{ $metrics['pending_approval'] }} Need Approval
                </span>
            @endif
        </x-slot>

        <x-slot name="actions">
            <x-button variant="outline" size="sm" href="{{ route('seller.returns.export') }}">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export CSV
            </x-button>
        </x-slot>
    </x-page-header>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <x-stat-card 
            label="Total Returns" 
            :value="$metrics['total_returns']"
            color="primary"
        />
        <x-stat-card 
            label="Action Needed" 
            :value="$metrics['pending_approval']"
            color="amber"
        />
        <x-stat-card 
            label="In Reverse Transit" 
            :value="$metrics['in_transit']"
            color="sky"
        />
        <x-stat-card 
            label="QC Pending" 
            :value="$metrics['qc_pending']"
            color="primary"
        />
        <x-stat-card 
            label="Refunded" 
            :value="$metrics['refunded']"
            color="emerald"
        />
    </div>

    <!-- Filters & Search Toolbar -->
    <x-card padding="p-4">
        <form method="GET" action="{{ route('seller.returns.index') }}" class="flex flex-col sm:flex-row gap-3 items-center justify-between">
            <div class="flex items-center gap-2 overflow-x-auto w-full sm:w-auto pb-1 sm:pb-0">
                @php
                    $activeStatus = request('status', 'all');
                    $statuses = [
                        'all' => 'All Returns',
                        'requested' => 'Pending Approval',
                        'approved' => 'Approved',
                        'reverse_pickup_scheduled' => 'Pickup Scheduled',
                        'picked_up' => 'In Transit',
                        'received' => 'QC Inspection',
                        'refunded' => 'Refunded',
                        'rejected' => 'Rejected',
                    ];
                @endphp

                @foreach($statuses as $key => $label)
                    <a 
                        href="{{ route('seller.returns.index', array_merge(request()->query(), ['status' => $key, 'page' => 1])) }}"
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
                        placeholder="Search RMA #, order, item..." 
                        class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    />
                    <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                @if(request('search') || request('status'))
                    <a href="{{ route('seller.returns.index') }}" class="text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 underline">Reset</a>
                @endif
            </div>
        </form>
    </x-card>

    <!-- Returns Table -->
    <x-card padding="p-0" class="overflow-hidden">
        @if($returns->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="py-3 px-4">Return / RMA</th>
                            <th class="py-3 px-4">Order / Customer</th>
                            <th class="py-3 px-4">Item Details</th>
                            <th class="py-3 px-4">Amount</th>
                            <th class="py-3 px-4">Return Status</th>
                            <th class="py-3 px-4">QC Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($returns as $ret)
                            @php
                                $item = $ret->orderItem;
                                $order = $item?->fulfilmentOrder?->order;
                                $user = $order?->user;
                            @endphp
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3 px-4 font-mono text-xs">
                                    <a href="{{ route('seller.returns.show', $ret) }}" class="font-bold text-primary-600 hover:underline">
                                        {{ $ret->return_number }}
                                    </a>
                                    <div class="text-[11px] text-slate-400 font-sans mt-0.5">
                                        {{ $ret->created_at->format('d M Y, h:i A') }}
                                    </div>
                                    @if($ret->pickup_waybill)
                                        <div class="mt-1 flex items-center text-[10px] text-indigo-600 font-mono">
                                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mr-1"></span>
                                            {{ $ret->pickup_waybill }}
                                        </div>
                                    @endif
                                </td>

                                <td class="py-3 px-4">
                                    @if($order)
                                        <a href="{{ route('seller.orders.show', $item->fulfilmentOrder) }}" class="text-xs font-semibold text-slate-900 dark:text-slate-100 hover:text-primary-600">
                                            #{{ $order->order_number }}
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-400">N/A</span>
                                    @endif
                                    <div class="text-xs text-slate-500">
                                        {{ $user?->name ?? 'Guest / Customer' }}
                                    </div>
                                </td>

                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-3">
                                        @if($item?->variant?->image_url || $item?->product?->image_url)
                                            <img src="{{ $item->variant?->image_url ?? $item->product?->image_url }}" alt="" class="w-9 h-9 object-cover rounded border border-slate-200 dark:border-slate-700 flex-shrink-0">
                                        @else
                                            <div class="w-9 h-9 rounded bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 flex-shrink-0">
                                                📦
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-medium text-xs text-slate-900 dark:text-slate-100 line-clamp-1">
                                                {{ $item?->title ?? 'Product Item' }}
                                            </div>
                                            <div class="text-[11px] text-slate-500">
                                                Qty: <span class="font-semibold">{{ $ret->qty ?? $item?->qty ?? 1 }}</span>
                                                @if($item?->variant_title)
                                                    &bull; {{ $item->variant_title }}
                                                @endif
                                            </div>
                                            <div class="text-[10px] text-amber-600 dark:text-amber-400 truncate max-w-xs mt-0.5">
                                                Reason: {{ $ret->reason }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3 px-4 font-semibold text-xs text-slate-900 dark:text-slate-100">
                                    {{ format_currency($ret->refund_amount ?? $item?->total ?? 0) }}
                                    <div class="text-[10px] text-slate-400 font-normal">
                                        via {{ ucfirst($ret->refund_mode ?? 'original') }}
                                    </div>
                                </td>

                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold border {{ $ret->status->badgeClass() }}">
                                        {{ $ret->status->label() }}
                                    </span>
                                </td>

                                <td class="py-3 px-4">
                                    @php
                                        $qcClass = match($ret->qc_status->value ?? 'pending') {
                                            'passed' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-400',
                                            'failed' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-400',
                                            'waived' => 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-300',
                                            default => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-400',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold border {{ $qcClass }}">
                                        {{ $ret->qc_status->label() }}
                                    </span>
                                </td>

                                <td class="py-3 px-4 text-right">
                                    <x-button variant="outline" size="sm" href="{{ route('seller.returns.show', $ret) }}" class="text-xs">
                                        Manage
                                        <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $returns->links() }}
            </div>
        @else
            <x-empty-state 
                title="No Return Requests Found" 
                description="When customers initiate item returns or cancellations within their eligible policy window, they will appear here for verification and reverse dispatch."
                icon="refresh"
            />
        @endif
    </x-card>
</div>
@endsection
