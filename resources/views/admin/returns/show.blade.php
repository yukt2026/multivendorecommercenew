@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'RMA Claim #' . $return->return_number])

@section('content')
<div class="space-y-6" x-data="{
    overrideModalOpen: false,
    overrideAction: 'approve',
    forceRefundModalOpen: false,
    selectedPhoto: null
}">
    <!-- Header -->
    <x-page-header 
        title="Return #{{ $return->return_number }}" 
        subtitle="Dispute resolution console, reverse shipment tracking, and administrative force-actions."
        :breadcrumbs="[
            'Command Console' => route('admin.dashboard'), 
            'Returns & RMA' => route('admin.returns.index'), 
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
                <x-button variant="outline" size="sm" href="{{ route('admin.returns.index') }}">
                    &larr; Back to Registry
                </x-button>

                @if($return->status !== \App\Enums\ReturnStatus::REFUNDED)
                    <x-button variant="outline" size="sm" class="border-amber-300 text-amber-700 hover:bg-amber-50" @click="overrideAction = 'approve'; overrideModalOpen = true">
                        Override &amp; Approve
                    </x-button>

                    <x-button variant="outline" size="sm" class="border-rose-300 text-rose-700 hover:bg-rose-50" @click="overrideAction = 'reject'; overrideModalOpen = true">
                        Override &amp; Reject
                    </x-button>

                    <x-button variant="primary" size="sm" class="bg-emerald-600 hover:bg-emerald-700" @click="forceRefundModalOpen = true">
                        ⚡ Force Refund
                    </x-button>
                @endif
            </div>
        </x-slot>
    </x-page-header>

    @php
        $item = $return->orderItem;
        $fulfilment = $item?->fulfilmentOrder;
        $order = $fulfilment?->order;
        $customer = $order?->user;
        $seller = $fulfilment?->seller;
    @endphp

    <!-- Administrative Notes Banner -->
    @if($return->admin_notes)
        <div class="p-4 rounded-xl bg-purple-50 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-800 text-purple-900 dark:text-purple-200">
            <div class="flex items-center space-x-2 font-bold text-sm">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Administrative Intervention Logged</span>
            </div>
            <p class="mt-1 text-xs text-purple-700 dark:text-purple-300">
                {{ $return->admin_notes }}
            </p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Column (2 cols) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Item Details & Claim -->
            <x-card>
                <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100">
                        Item Claim Information
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

                    @if($return->seller_notes)
                        <div>
                            <span class="text-xs text-slate-400 uppercase font-semibold">Seller Feedback / Remarks:</span>
                            <div class="mt-1 text-xs text-slate-600 dark:text-slate-300 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                                "{{ $return->seller_notes }}"
                            </div>
                        </div>
                    @endif

                    <!-- Uploaded Photos -->
                    @if(!empty($return->photos) && is_array($return->photos))
                        <div>
                            <span class="text-xs text-slate-400 uppercase font-semibold block mb-2">Customer Photos ({{ count($return->photos) }}):</span>
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

            <!-- Reverse Logistics Hub & Waybill -->
            <x-card>
                <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100">
                        Reverse Logistics Consignment
                    </h3>
                    @if($return->pickup_waybill)
                        <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/50 dark:text-indigo-400">
                            AWB: {{ $return->pickup_waybill }}
                        </span>
                    @endif
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-slate-400">Logistics Carrier:</span>
                        <div class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">OneStall Cargo Freight</div>
                    </div>
                    <div>
                        <span class="text-slate-400">Pickup Waybill:</span>
                        <div class="font-mono font-bold text-slate-900 dark:text-slate-100 mt-0.5">
                            {{ $return->pickup_waybill ?? 'Not Booked' }}
                        </div>
                    </div>
                    <div>
                        <span class="text-slate-400">Hub Received At:</span>
                        <div class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">
                            {{ $return->received_at ? $return->received_at->format('d M Y, h:i A') : 'Pending Arrival' }}
                        </div>
                    </div>
                </div>
            </x-card>

            <!-- QC Inspection Results -->
            <x-card>
                <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100">
                        Quality Control (QC) Findings
                    </h3>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded border {{ $return->qc_status === \App\Enums\QCStatus::PASSED ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($return->qc_status === \App\Enums\QCStatus::FAILED ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-slate-100 text-slate-700 border-slate-200') }}">
                        {{ $return->qc_status->label() }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-slate-400">Restock Eligibility:</span>
                        <div class="font-bold mt-0.5 {{ $return->is_resellable ? 'text-emerald-600' : 'text-slate-500' }}">
                            {{ $return->is_resellable ? 'Eligible (Resellable)' : 'Ineligible (Damaged/Scrapped)' }}
                        </div>
                    </div>
                    <div>
                        <span class="text-slate-400">QC Inspection Date:</span>
                        <div class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">
                            {{ $return->qc_completed_at ? $return->qc_completed_at->format('d M Y, h:i A') : 'Not Yet Inspected' }}
                        </div>
                    </div>
                    <div>
                        <span class="text-slate-400">Inspector Notes:</span>
                        <div class="text-slate-700 dark:text-slate-300 mt-0.5 italic">
                            {{ $return->qc_notes ?? 'No findings noted.' }}
                        </div>
                    </div>
                </div>
            </x-card>
        </div>

        <!-- Sidebar (1 col) -->
        <div class="space-y-6">
            <!-- Merchant & Order Snapshot -->
            <x-card>
                <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 pb-3 border-b border-slate-200 dark:border-slate-800">
                    Order &amp; Merchant
                </h3>

                <div class="mt-3 space-y-3 text-xs">
                    <div>
                        <span class="text-slate-400">Order:</span>
                        <div class="font-bold text-slate-900 dark:text-slate-100">
                            @if($order)
                                <a href="{{ route('admin.orders.show', $order) }}" class="text-primary-600 hover:underline">
                                    #{{ $order->order_number }}
                                </a>
                            @else
                                N/A
                            @endif
                        </div>
                    </div>

                    <div>
                        <span class="text-slate-400">Customer:</span>
                        <div class="font-semibold text-slate-900 dark:text-slate-100">
                            {{ $customer?->name ?? 'Customer' }}
                        </div>
                        <div class="text-slate-500 font-mono text-[11px]">{{ $customer?->email }}</div>
                    </div>

                    <div>
                        <span class="text-slate-400">Seller / Vendor:</span>
                        <div class="font-semibold text-slate-900 dark:text-slate-100">
                            @if($seller)
                                <a href="{{ route('admin.sellers.show', $seller) }}" class="text-primary-600 hover:underline">
                                    {{ $seller->company_name }}
                                </a>
                            @else
                                Platform Direct Store
                            @endif
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                        <span class="text-slate-400">Refund Destination:</span>
                        <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">
                            {{ strtoupper($return->refund_mode ?? 'ORIGINAL') }}
                        </div>
                    </div>
                </div>
            </x-card>

            <!-- Financial Settlement -->
            <x-card>
                <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 pb-3 border-b border-slate-200 dark:border-slate-800">
                    Financial Impact
                </h3>

                <div class="mt-3 space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Refund Amount:</span>
                        <span class="font-black text-slate-900 dark:text-slate-100">
                            {{ format_currency($return->refund_amount ?? $item?->total ?? 0) }}
                        </span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-slate-500">Commission Reversal:</span>
                        <span class="font-semibold text-amber-600">
                            Automatic on Refund
                        </span>
                    </div>

                    @if($return->refunded_at)
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                            <span class="text-slate-400">Refunded On:</span>
                            <div class="font-bold text-emerald-600">
                                {{ $return->refunded_at->format('d M Y, h:i A') }}
                            </div>
                        </div>
                    @endif
                </div>
            </x-card>
        </div>
    </div>

    <!-- Modal: Override Action (Approve / Reject) -->
    <div 
        x-show="overrideModalOpen" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
    >
        <div @click.away="overrideModalOpen = false" class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 dark:border-slate-800">
            <h3 class="font-bold text-base text-slate-900 dark:text-slate-100" x-text="overrideAction === 'approve' ? 'Admin Override: Approve Return' : 'Admin Override: Reject Return'"></h3>
            <p class="text-xs text-slate-500 mt-1">
                This administrative intervention overrides merchant decisions and customer claims.
            </p>

            <form method="POST" action="{{ route('admin.returns.override', $return) }}" class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="action" :value="overrideAction">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Administrative Reason / Justification *</label>
                    <textarea name="notes" required rows="3" placeholder="Provide reason for administrative override..." class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5"></textarea>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <x-button variant="outline" size="sm" type="button" @click="overrideModalOpen = false">
                        Cancel
                    </x-button>
                    <x-button variant="primary" size="sm" type="submit">
                        Confirm Override
                    </x-button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Force Refund -->
    <div 
        x-show="forceRefundModalOpen" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
    >
        <div @click.away="forceRefundModalOpen = false" class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 dark:border-slate-800">
            <h3 class="font-bold text-base text-emerald-600">Administrative Force Refund</h3>
            <p class="text-xs text-slate-500 mt-1">
                Immediately trigger financial refund to customer via payment gateway or wallet credit, bypassing seller approval bottlenecks.
            </p>

            <form method="POST" action="{{ route('admin.returns.force_refund', $return) }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Refund Amount (Leave blank for full amount: {{ format_currency($return->refund_amount ?? $item?->total ?? 0) }})</label>
                    <input type="number" step="0.01" name="amount" placeholder="{{ $return->refund_amount ?? $item?->total ?? 0 }}" class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5">
                </div>

                <div class="flex items-center space-x-2">
                    <input type="checkbox" name="is_resellable" id="admin_resellable" value="1" class="rounded border-slate-300 text-emerald-600">
                    <label for="admin_resellable" class="text-xs text-slate-700 dark:text-slate-300 font-medium">
                        Item physically restockable (Restores inventory balance)
                    </label>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Administrative Reason *</label>
                    <textarea name="notes" required rows="3" placeholder="e.g. Approved in customer's favor due to delayed seller QC inspection." class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5"></textarea>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <x-button variant="outline" size="sm" type="button" @click="forceRefundModalOpen = false">
                        Cancel
                    </x-button>
                    <x-button variant="primary" size="sm" type="submit" class="bg-emerald-600 hover:bg-emerald-700">
                        Execute Force Refund
                    </x-button>
                </div>
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
