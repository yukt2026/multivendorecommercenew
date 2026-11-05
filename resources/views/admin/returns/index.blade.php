@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Returns & RMA Oversight'])

@section('content')
<div class="space-y-6">
    <!-- Page Header -->
    <x-page-header 
        title="Returns &amp; RMA Oversight" 
        subtitle="Global surveillance of customer returns, merchant quality control checks, disputes, and refund disbursements."
        :breadcrumbs="['Command Console' => route('admin.dashboard'), 'Returns & RMA' => '#']"
    >
        <x-slot name="actions">
            <x-button variant="outline" size="sm" href="{{ route('admin.returns.export') }}">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export CSV Report
            </x-button>
        </x-slot>
    </x-page-header>

    <!-- Metrics Summary -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <x-stat-card 
            label="Total Returns" 
            :value="$metrics['total_returns']"
            color="primary"
        />
        <x-stat-card 
            label="Pending Review" 
            :value="$metrics['pending_review']"
            color="amber"
        />
        <x-stat-card 
            label="In Reverse Transit" 
            :value="$metrics['in_transit']"
            color="sky"
        />
        <x-stat-card 
            label="Disputed / QC Failed" 
            :value="$metrics['disputed_qc']"
            color="rose"
        />
        <x-stat-card 
            label="Total Refunded" 
            :value="format_currency($metrics['total_refunded_value'])"
            color="emerald"
        />
    </div>

    <!-- Filters & Query Form -->
    <x-card padding="p-4">
        <form method="GET" action="{{ route('admin.returns.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 items-end">
            <!-- Search -->
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Search RMA / Order / Customer</label>
                <div class="relative">
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ request('search') }}" 
                        placeholder="e.g. RET-202611, ORD-4921, Customer name..." 
                        class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    />
                    <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Return Status</label>
                <select name="status" class="w-full py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200">
                    <option value="all">All Statuses</option>
                    @foreach(\App\Enums\ReturnStatus::cases() as $st)
                        <option value="{{ $st->value }}" {{ request('status') === $st->value ? 'selected' : '' }}>
                            {{ $st->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- QC Filter -->
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">QC Outcome</label>
                <select name="qc_status" class="w-full py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200">
                    <option value="all">All QC States</option>
                    @foreach(\App\Enums\QCStatus::cases() as $qc)
                        <option value="{{ $qc->value }}" {{ request('qc_status') === $qc->value ? 'selected' : '' }}>
                            {{ $qc->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Buttons -->
            <div class="flex items-center gap-2">
                <x-button variant="primary" size="sm" type="submit" class="w-full justify-center">
                    Filter
                </x-button>
                @if(request()->anyFilled(['search', 'status', 'qc_status', 'seller_id']))
                    <x-button variant="outline" size="sm" href="{{ route('admin.returns.index') }}" class="w-auto">
                        Reset
                    </x-button>
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
                            <th class="py-3 px-4">RMA / Return #</th>
                            <th class="py-3 px-4">Order / Customer</th>
                            <th class="py-3 px-4">Seller / Merchant</th>
                            <th class="py-3 px-4">Item &amp; Qty</th>
                            <th class="py-3 px-4">Claim Value</th>
                            <th class="py-3 px-4">Return Status</th>
                            <th class="py-3 px-4">QC Status</th>
                            <th class="py-3 px-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($returns as $ret)
                            @php
                                $item = $ret->orderItem;
                                $fulfilment = $item?->fulfilmentOrder;
                                $order = $fulfilment?->order;
                                $user = $order?->user;
                                $seller = $fulfilment?->seller;
                            @endphp
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3 px-4 font-mono text-xs">
                                    <a href="{{ route('admin.returns.show', $ret) }}" class="font-bold text-primary-600 hover:underline">
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

                                <td class="py-3 px-4 text-xs">
                                    @if($order)
                                        <a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-slate-900 dark:text-slate-100 hover:text-primary-600">
                                            #{{ $order->order_number }}
                                        </a>
                                    @else
                                        <span class="text-slate-400">N/A</span>
                                    @endif
                                    <div class="text-slate-500">{{ $user?->name ?? 'Guest' }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $user?->email }}</div>
                                </td>

                                <td class="py-3 px-4 text-xs">
                                    @if($seller)
                                        <a href="{{ route('admin.sellers.show', $seller) }}" class="font-medium text-slate-800 dark:text-slate-200 hover:underline">
                                            {{ $seller->business_name ?? $seller->company_name }}
                                        </a>
                                    @else
                                        <span class="text-slate-400">Platform Store</span>
                                    @endif
                                </td>

                                <td class="py-3 px-4">
                                    <div class="text-xs font-semibold text-slate-900 dark:text-slate-100 line-clamp-1">
                                        {{ $item?->title ?? 'Item' }}
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        Qty: <span class="font-semibold">{{ $ret->qty ?? $item?->qty ?? 1 }}</span>
                                        @if($item?->sku)
                                            &bull; SKU: {{ $item->sku }}
                                        @endif
                                    </div>
                                    <div class="text-[10px] text-amber-600 dark:text-amber-400 truncate max-w-xs mt-0.5">
                                        {{ $ret->reason }}
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
                                    <x-button variant="outline" size="sm" href="{{ route('admin.returns.show', $ret) }}" class="text-xs">
                                        Inspect
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
                title="No Returns Found" 
                description="No return requests match the selected criteria."
                icon="refresh"
            />
        @endif
    </x-card>
</div>
@endsection
