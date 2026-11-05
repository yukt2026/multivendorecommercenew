@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Return #' . $return->return_number])

@section('content')
<div class="space-y-6" x-data="{
    approveModalOpen: false,
    rejectModalOpen: false,
    qcModalOpen: false,
    refundModalOpen: false,
    selectedPhoto: null
}">
    <!-- Header -->
    <x-page-header 
        title="Return #{{ $return->return_number }}" 
        subtitle="Manage inspection, RMA resolution, and financial refund settlement."
        :breadcrumbs="[
            'Seller Studio' => route('seller.dashboard'), 
            'Returns' => route('seller.returns.index'), 
            $return->return_number => '#'
        ]"
    >
        <x-slot name="badge">
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border {{ $return->status->badgeClass() }}">
                    {{ $return->status->label() }}
                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    QC: {{ $return->qc_status->label() }}
                </span>
            </div>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center gap-2">
                <x-button variant="outline" size="sm" href="{{ route('seller.returns.index') }}">
                    &larr; Back to Returns
                </x-button>

                @if($return->status === \App\Enums\ReturnStatus::REQUESTED)
                    <x-button variant="outline" size="sm" class="border-rose-300 text-rose-600 hover:bg-rose-50" @click="rejectModalOpen = true">
                        Reject Claim
                    </x-button>
                    <x-button variant="primary" size="sm" @click="approveModalOpen = true">
                        Approve Return
                    </x-button>
                @endif

                @if($return->status === \App\Enums\ReturnStatus::APPROVED && !$return->pickup_waybill)
                    <form method="POST" action="{{ route('seller.returns.schedule_pickup', $return) }}">
                        @csrf
                        <x-button variant="primary" size="sm" type="submit">
                            Schedule Reverse Pickup
                        </x-button>
                    </form>
                @endif

                @if(in_array($return->status, [\App\Enums\ReturnStatus::APPROVED, \App\Enums\ReturnStatus::REVERSE_PICKUP_SCHEDULED, \App\Enums\ReturnStatus::PICKED_UP]))
                    <form method="POST" action="{{ route('seller.returns.receive', $return) }}">
                        @csrf
                        <x-button variant="primary" size="sm" type="submit">
                            Mark Package Received
                        </x-button>
                    </form>
                @endif

                @if($return->status === \App\Enums\ReturnStatus::RECEIVED)
                    <x-button variant="primary" size="sm" @click="qcModalOpen = true">
                        Record QC Inspection
                    </x-button>
                @endif

                @if($return->status === \App\Enums\ReturnStatus::QC_PASSED)
                    <x-button variant="primary" size="sm" class="bg-emerald-600 hover:bg-emerald-700" @click="refundModalOpen = true">
                        Issue Refund ({{ format_currency($return->refund_amount ?? $return->orderItem?->total ?? 0) }})
                    </x-button>
                @endif
            </div>
        </x-slot>
    </x-page-header>

    @php
        $item = $return->orderItem;
        $order = $item?->fulfilmentOrder?->order;
        $customer = $order?->user;
        $fulfilment = $item?->fulfilmentOrder;
    @endphp

    <!-- Alerts if rejected / refunded -->
    @if($return->status === \App\Enums\ReturnStatus::REJECTED)
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200">
            <div class="flex items-center space-x-2 font-bold text-sm">
                <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Return Request Rejected</span>
            </div>
            <p class="mt-1 text-xs text-rose-700 dark:text-rose-300">
                <strong>Reason:</strong> {{ $return->seller_notes ?? $return->admin_notes ?? 'Does not meet merchant return policy requirements.' }}
            </p>
        </div>
    @elseif($return->status === \App\Enums\ReturnStatus::REFUNDED)
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200">
            <div class="flex items-center space-x-2 font-bold text-sm">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Refund Settled &bull; {{ format_currency($return->refund_amount) }}</span>
            </div>
            <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">
                Refund disbursed via <strong>{{ strtoupper($return->refund_mode ?? 'ORIGINAL') }}</strong> on {{ $return->refunded_at?->format('d M Y, h:i A') ?? 'recently' }}. Platform commission reversed &amp; inventory updated.
            </p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Column (2 cols) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Product & Claim Details -->
            <x-card>
                <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 flex items-center space-x-2">
                        <span>Returning Item Details</span>
                    </h3>
                    <span class="text-xs uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                        Type: {{ strtoupper($return->type ?? 'RETURN') }}
                    </span>
                </div>

                <div class="mt-4 flex flex-col sm:flex-row gap-4 items-start">
                    @if($item?->variant?->image_url || $item?->product?->image_url)
                        <img src="{{ $item->variant?->image_url ?? $item->product?->image_url }}" alt="" class="w-20 h-20 object-cover rounded-lg border border-slate-200 dark:border-slate-700">
                    @else
                        <div class="w-20 h-20 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-2xl">
                            📦
                        </div>
                    @endif

                    <div class="flex-1 space-y-1">
                        <h4 class="font-bold text-sm text-slate-900 dark:text-slate-100">
                            {{ $item?->title ?? 'Product Item' }}
                        </h4>
                        <div class="text-xs text-slate-500">
                            SKU: <span class="font-mono">{{ $item?->sku ?? 'N/A' }}</span>
                            @if($item?->variant_title)
                                &bull; Variant: <span class="font-semibold">{{ $item->variant_title }}</span>
                            @endif
                        </div>
                        <div class="text-xs text-slate-700 dark:text-slate-300 pt-1">
                            Unit Price: <span class="font-semibold">{{ format_currency($item?->unit_price ?? 0) }}</span> 
                            &times; Returning Qty: <span class="font-bold text-slate-900 dark:text-slate-100">{{ $return->qty ?? $item?->qty ?? 1 }}</span>
                        </div>
                        <div class="text-sm font-black text-primary-600 dark:text-primary-400">
                            Claim Total: {{ format_currency($return->refund_amount ?? $item?->total ?? 0) }}
                        </div>
                    </div>
                </div>

                <!-- Customer Reason & Notes -->
                <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 space-y-3">
                    <div>
                        <span class="text-xs text-slate-400 uppercase font-semibold">Customer Stated Reason:</span>
                        <div class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                            {{ $return->reason }}
                        </div>
                    </div>

                    @if($return->customer_notes)
                        <div>
                            <span class="text-xs text-slate-400 uppercase font-semibold">Customer Comments:</span>
                            <div class="mt-1 text-xs text-slate-600 dark:text-slate-300 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                                "{{ $return->customer_notes }}"
                            </div>
                        </div>
                    @endif

                    <!-- Uploaded Photos -->
                    @if(!empty($return->photos) && is_array($return->photos))
                        <div>
                            <span class="text-xs text-slate-400 uppercase font-semibold block mb-2">Customer Verification Photos ({{ count($return->photos) }}):</span>
                            <div class="flex flex-wrap gap-2">
                                @foreach($return->photos as $photo)
                                    @php
                                        $photoUrl = str_starts_with($photo, 'http') ? $photo : Storage::url($photo);
                                    @endphp
                                    <button 
                                        type="button" 
                                        @click="selectedPhoto = '{{ $photoUrl }}'" 
                                        class="group relative w-16 h-16 rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    >
                                        <img src="{{ $photoUrl }}" alt="Return evidence" class="w-full h-full object-cover group-hover:scale-110 transition">
                                        <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-xs">
                                            🔍
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </x-card>

            <!-- Reverse Logistics & Cargo Consignment -->
            <x-card>
                <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 flex items-center space-x-2">
                        <span>Reverse Logistics &amp; Cargo Hub</span>
                    </h3>
                    @if($return->pickup_waybill)
                        <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/50 dark:text-indigo-400">
                            AWB: {{ $return->pickup_waybill }}
                        </span>
                    @else
                        <span class="text-xs text-slate-400">Pickup Not Scheduled</span>
                    @endif
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-slate-400">Cargo Partner:</span>
                        <div class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">OneStall Logistics Cargo</div>
                    </div>
                    <div>
                        <span class="text-slate-400">Pickup Waybill:</span>
                        <div class="font-mono font-bold text-slate-900 dark:text-slate-100 mt-0.5">
                            {{ $return->pickup_waybill ?? 'Pending Dispatch' }}
                        </div>
                    </div>
                    <div>
                        <span class="text-slate-400">Pickup Scheduled At:</span>
                        <div class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">
                            {{ $return->picked_up_at ? $return->picked_up_at->format('d M Y, h:i A') : ($return->pickup_waybill ? 'Carrier Assigned' : 'Awaiting Approval') }}
                        </div>
                    </div>
                </div>

                @if($return->received_at)
                    <div class="mt-4 p-3 rounded-lg bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 flex items-center justify-between text-xs text-blue-900 dark:text-blue-200">
                        <span class="flex items-center space-x-1.5 font-semibold">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Package physically received at merchant inspection hub</span>
                        </span>
                        <span class="font-mono text-[11px]">{{ $return->received_at->format('d M Y, h:i A') }}</span>
                    </div>
                @endif
            </x-card>

            <!-- Quality Control (QC) Inspection -->
            <x-card>
                <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 flex items-center space-x-2">
                        <span>Quality Control (QC) Inspection</span>
                    </h3>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded border {{ $return->qc_status === \App\Enums\QCStatus::PASSED ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($return->qc_status === \App\Enums\QCStatus::FAILED ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-slate-100 text-slate-700 border-slate-200') }}">
                        {{ $return->qc_status->label() }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-slate-400">Inventory Resellable:</span>
                        <div class="font-bold mt-0.5 {{ $return->is_resellable ? 'text-emerald-600' : 'text-slate-500' }}">
                            {{ $return->is_resellable ? 'Yes (Restocked on Refund)' : 'No (Damaged / Discard)' }}
                        </div>
                    </div>
                    <div>
                        <span class="text-slate-400">QC Completed At:</span>
                        <div class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">
                            {{ $return->qc_completed_at ? $return->qc_completed_at->format('d M Y, h:i A') : 'Pending Physical Review' }}
                        </div>
                    </div>
                    <div>
                        <span class="text-slate-400">Inspector Remarks:</span>
                        <div class="text-slate-700 dark:text-slate-300 mt-0.5 italic">
                            {{ $return->qc_notes ?? 'No QC notes recorded yet.' }}
                        </div>
                    </div>
                </div>
            </x-card>
        </div>

        <!-- Sidebar Column (1 col) -->
        <div class="space-y-6">
            <!-- Order & Customer Snapshot -->
            <x-card>
                <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 pb-3 border-b border-slate-200 dark:border-slate-800">
                    Original Order Details
                </h3>

                <div class="mt-3 space-y-3 text-xs">
                    <div>
                        <span class="text-slate-400">Order Number:</span>
                        <div class="font-bold text-slate-900 dark:text-slate-100">
                            @if($order)
                                <a href="{{ route('seller.orders.show', $fulfilment) }}" class="text-primary-600 hover:underline">
                                    #{{ $order->order_number }}
                                </a>
                            @else
                                N/A
                            @endif
                        </div>
                    </div>

                    <div>
                        <span class="text-slate-400">Customer Name:</span>
                        <div class="font-semibold text-slate-900 dark:text-slate-100">
                            {{ $customer?->name ?? 'Guest User' }}
                        </div>
                        <div class="text-slate-500 font-mono text-[11px]">{{ $customer?->email }}</div>
                        <div class="text-slate-500 font-mono text-[11px]">{{ $customer?->phone ?? 'No phone' }}</div>
                    </div>

                    <div>
                        <span class="text-slate-400">Delivered On:</span>
                        <div class="font-semibold text-slate-800 dark:text-slate-200">
                            {{ $fulfilment?->delivered_at ? \Carbon\Carbon::parse($fulfilment->delivered_at)->format('d M Y, h:i A') : 'Delivered' }}
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                        <span class="text-slate-400">Return Window:</span>
                        <div class="font-semibold text-emerald-600">
                            Verified Eligible (Within policy window)
                        </div>
                    </div>
                </div>
            </x-card>

            <!-- Refund Settlement Summary -->
            <x-card>
                <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 pb-3 border-b border-slate-200 dark:border-slate-800">
                    Refund &amp; Settlement
                </h3>

                <div class="mt-3 space-y-2.5 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Refund Destination:</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">
                            {{ strtoupper($return->refund_mode ?? 'ORIGINAL') }}
                        </span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-slate-500">Refund Amount:</span>
                        <span class="font-black text-slate-900 dark:text-slate-100 text-sm">
                            {{ format_currency($return->refund_amount ?? $item?->total ?? 0) }}
                        </span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-slate-500">Platform Commission:</span>
                        <span class="font-semibold text-amber-600">
                            Auto-Reversed on Refund
                        </span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-slate-500">Inventory Policy:</span>
                        <span class="font-semibold {{ $return->is_resellable ? 'text-emerald-600' : 'text-slate-400' }}">
                            {{ $return->is_resellable ? 'Restock Enabled' : 'Write-off / Scrapped' }}
                        </span>
                    </div>
                </div>
            </x-card>

            <!-- RMA Progress Timeline -->
            <x-card>
                <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 pb-3 border-b border-slate-200 dark:border-slate-800">
                    Lifecycle Timeline
                </h3>

                @php
                    $steps = [
                        ['title' => 'Requested', 'time' => $return->created_at, 'done' => true],
                        ['title' => 'Approved', 'time' => $return->approved_at, 'done' => (bool)$return->approved_at],
                        ['title' => 'Reverse Pickup', 'time' => $return->picked_up_at, 'done' => (bool)$return->picked_up_at || (bool)$return->received_at],
                        ['title' => 'Received at Hub', 'time' => $return->received_at, 'done' => (bool)$return->received_at],
                        ['title' => 'QC Inspection', 'time' => $return->qc_completed_at, 'done' => (bool)$return->qc_completed_at],
                        ['title' => 'Refund Settled', 'time' => $return->refunded_at, 'done' => (bool)$return->refunded_at],
                    ];
                @endphp

                <div class="mt-4 relative pl-5 border-l-2 border-slate-200 dark:border-slate-700 space-y-4">
                    @foreach($steps as $step)
                        <div class="relative">
                            <div class="absolute -left-[27px] top-0.5 w-3.5 h-3.5 rounded-full border-2 {{ $step['done'] ? 'bg-primary-600 border-primary-200 dark:border-primary-900' : 'bg-slate-200 dark:bg-slate-700 border-white dark:border-slate-900' }}"></div>
                            <div class="text-xs font-bold {{ $step['done'] ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400' }}">
                                {{ $step['title'] }}
                            </div>
                            @if($step['time'])
                                <div class="text-[10px] text-slate-400 font-mono">
                                    {{ \Carbon\Carbon::parse($step['time'])->format('d M, h:i A') }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>
    </div>

    <!-- Modal: Approve Return -->
    <div 
        x-show="approveModalOpen" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
    >
        <div @click.away="approveModalOpen = false" class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 dark:border-slate-800">
            <h3 class="font-bold text-base text-slate-900 dark:text-slate-100">Approve Return Request</h3>
            <p class="text-xs text-slate-500 mt-1">
                Approving this claim initiates reverse pickup booking via OneStall Cargo logistics.
            </p>

            <form method="POST" action="{{ route('seller.returns.approve', $return) }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Remarks for Customer (Optional)</label>
                    <textarea name="seller_notes" rows="3" placeholder="e.g. Please ensure item is kept in original packaging with tags intact." class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-primary-500"></textarea>
                </div>

                <div class="flex items-center space-x-2">
                    <input type="checkbox" name="auto_schedule_pickup" id="auto_pickup" value="1" checked class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                    <label for="auto_pickup" class="text-xs text-slate-700 dark:text-slate-300 font-medium">
                        Automatically book OneStall Cargo reverse pickup now
                    </label>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <x-button variant="outline" size="sm" type="button" @click="approveModalOpen = false">
                        Cancel
                    </x-button>
                    <x-button variant="primary" size="sm" type="submit">
                        Confirm Approval
                    </x-button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Reject Return -->
    <div 
        x-show="rejectModalOpen" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
    >
        <div @click.away="rejectModalOpen = false" class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 dark:border-slate-800">
            <h3 class="font-bold text-base text-rose-600">Reject Return Request</h3>
            <p class="text-xs text-slate-500 mt-1">
                Please provide clear reasons why this claim does not qualify for return.
            </p>

            <form method="POST" action="{{ route('seller.returns.reject', $return) }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Rejection Reason *</label>
                    <textarea name="reason" required rows="3" placeholder="e.g. Item condition violates hygiene policy or return window breached." class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-rose-500"></textarea>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <x-button variant="outline" size="sm" type="button" @click="rejectModalOpen = false">
                        Cancel
                    </x-button>
                    <x-button variant="primary" size="sm" type="submit" class="bg-rose-600 hover:bg-rose-700">
                        Confirm Rejection
                    </x-button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Record QC Inspection -->
    <div 
        x-show="qcModalOpen" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
    >
        <div @click.away="qcModalOpen = false" class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 dark:border-slate-800">
            <h3 class="font-bold text-base text-slate-900 dark:text-slate-100">Quality Inspection (QC) Outcome</h3>
            <p class="text-xs text-slate-500 mt-1">
                Record physical condition inspection findings before issuing refund.
            </p>

            <form method="POST" action="{{ route('seller.returns.record_qc', $return) }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">QC Decision *</label>
                    <select name="outcome" required class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5">
                        <option value="passed">QC Passed (Product meets return criteria)</option>
                        <option value="failed">QC Failed (Product damaged / wrong item returned)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Inspector Notes</label>
                    <textarea name="qc_notes" rows="3" placeholder="Condition details, seal verification, barcode match..." class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5"></textarea>
                </div>

                <div class="flex items-center space-x-2">
                    <input type="checkbox" name="is_resellable" id="resellable_toggle" value="1" checked class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <label for="resellable_toggle" class="text-xs text-slate-700 dark:text-slate-300 font-medium">
                        Item is intact and resellable (Restore inventory stock upon refund)
                    </label>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <x-button variant="outline" size="sm" type="button" @click="qcModalOpen = false">
                        Cancel
                    </x-button>
                    <x-button variant="primary" size="sm" type="submit">
                        Save QC Outcome
                    </x-button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Issue Refund Confirmation -->
    <div 
        x-show="refundModalOpen" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
    >
        <div @click.away="refundModalOpen = false" class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 dark:border-slate-800">
            <h3 class="font-bold text-base text-slate-900 dark:text-slate-100">Disburse Refund</h3>
            <p class="text-xs text-slate-500 mt-1">
                You are about to issue a refund of <strong class="text-slate-900 dark:text-slate-100">{{ format_currency($return->refund_amount ?? $item?->total ?? 0) }}</strong> to the customer via {{ strtoupper($return->refund_mode ?? 'original') }}.
            </p>

            <div class="mt-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 text-xs">
                ⚡ <strong>Automated Actions on Disbursement:</strong>
                <ul class="list-disc list-inside mt-1 space-y-0.5 text-[11px]">
                    <li>Dispatches gateway refund or credits customer store wallet balance</li>
                    <li>Reverses platform commission for this item</li>
                    @if($return->is_resellable)
                        <li>Automatically restocks <strong>{{ $return->qty ?? $item?->qty ?? 1 }}</strong> unit(s) into inventory</li>
                    @endif
                </ul>
            </div>

            <form method="POST" action="{{ route('seller.returns.refund', $return) }}" class="mt-5 flex justify-end space-x-2">
                @csrf
                <x-button variant="outline" size="sm" type="button" @click="refundModalOpen = false">
                    Cancel
                </x-button>
                <x-button variant="primary" size="sm" type="submit" class="bg-emerald-600 hover:bg-emerald-700">
                    Confirm &amp; Disburse
                </x-button>
            </form>
        </div>
    </div>

    <!-- Photo Zoom Modal -->
    <div 
        x-show="selectedPhoto" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
        <div @click.away="selectedPhoto = null" class="relative max-w-2xl max-h-[85vh] bg-transparent">
            <button @click="selectedPhoto = null" class="absolute -top-10 right-0 text-white hover:text-slate-300 font-bold text-xl">
                &times; Close
            </button>
            <img :src="selectedPhoto" alt="Zoomed view" class="rounded-xl shadow-2xl max-w-full max-h-[80vh] object-contain">
        </div>
    </div>
</div>
@endsection
