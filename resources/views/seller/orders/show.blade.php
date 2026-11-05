@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Process Order #' . $fo->fulfilment_order_number])

@section('content')
<div class="space-y-6" x-data="{ assignStaffModal: false, rejectModal: false }">

    <!-- Page Header & Action Bar -->
    <x-page-header 
        title="Package #{{ $fo->fulfilment_order_number }}" 
        subtitle="Parent Order: #{{ $fo->order?->order_number }} &bull; Channel: {{ $fo->fulfilment_type->label() }} &bull; Placed {{ $fo->created_at->format('d M Y, h:i A') }}"
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Orders' => route('seller.orders.index'), '#' . $fo->fulfilment_order_number => '#']"
    >
        <x-slot name="badge">
            @php
                $isQuick = $fo->fulfilment_type === \App\Enums\FulfilmentType::QUICK;
                $badgeBg = match(strtolower($fo->status)) {
                    'placed' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 border-amber-300',
                    'store_accepted', 'confirmed' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-400 border-sky-300',
                    'packing', 'packed' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400 border-indigo-300',
                    'ready', 'ready_to_ship' => 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-400 border-purple-300',
                    'rider_assigned', 'in_transit', 'out_for_delivery' => 'bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-400 border-orange-300',
                    'delivered' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 border-emerald-300',
                    default => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300',
                };
            @endphp
            <span class="px-3 py-1 rounded-full text-xs font-bold border {{ $badgeBg }}">
                {{ ucwords(str_replace('_', ' ', $fo->status)) }}
            </span>
        </x-slot>

        <x-slot name="actions">
            <!-- Print Documents Buttons -->
            <div class="flex items-center space-x-2">
                <a 
                    href="{{ route('seller.orders.packing_slip', $fo) }}" 
                    class="px-3.5 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:text-emerald-600 text-xs font-bold transition shadow-xs flex items-center space-x-1"
                >
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Packing Slip</span>
                </a>

                <a 
                    href="{{ route('seller.orders.shipping_label', $fo) }}" 
                    class="px-3.5 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:text-emerald-600 text-xs font-bold transition shadow-xs flex items-center space-x-1"
                >
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    <span>Shipping Label</span>
                </a>

                <a 
                    href="{{ route('seller.orders.invoice', $fo) }}" 
                    class="px-3.5 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:text-emerald-600 text-xs font-bold transition shadow-xs flex items-center space-x-1"
                >
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Tax Invoice</span>
                </a>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Status Action Execution Strip -->
    <div class="bg-gradient-to-r from-emerald-500/10 via-indigo-500/10 to-transparent p-5 rounded-3xl border border-emerald-500/20 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Next Allowed Status Actions</div>
            <div class="text-sm font-black text-slate-900 dark:text-white mt-0.5">
                Current Package Status: <span class="text-emerald-600 dark:text-emerald-400 capitalize">{{ str_replace('_', ' ', $fo->status) }}</span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(empty($allowedNext))
                <span class="text-xs text-slate-400 italic">No further transitions permitted for this order.</span>
            @endif

            @foreach($allowedNext as $target)
                @if($target === 'rejected')
                    <button 
                        type="button" 
                        @click="rejectModal = true" 
                        class="px-4 py-2 rounded-xl bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 text-xs font-bold transition"
                    >
                        Reject Order
                    </button>
                @elseif($target === 'rider_assigned')
                    <button 
                        type="button" 
                        @click="assignStaffModal = true" 
                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition"
                    >
                        Assign Rider / Staff &rarr;
                    </button>
                @else
                    <form method="POST" action="{{ route('seller.orders.transition', $fo) }}" class="inline">
                        @csrf
                        <input type="hidden" name="target_status" value="{{ $target }}">
                        <button 
                            type="submit" 
                            class="px-4 py-2 rounded-xl text-xs font-bold shadow-xs transition {{ in_array($target, ['store_accepted', 'confirmed', 'delivered']) ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-slate-900 hover:bg-slate-800 dark:bg-white dark:text-slate-900 text-white' }}"
                        >
                            Mark as {{ ucwords(str_replace('_', ' ', $target)) }} &rarr;
                        </button>
                    </form>
                @endif
            @endforeach
        </div>
    </div>

    <!-- Grid: Customer Info & Financial Settlement -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Customer & Delivery Address -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-xs space-y-3">
            <div class="text-xs font-black uppercase tracking-wider text-slate-400">Shipping Destination</div>
            <div class="text-sm font-bold text-slate-900 dark:text-white">
                {{ $fo->order?->address_snapshot['name'] ?? 'Recipient' }}
            </div>
            <div class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                {{ $fo->order?->address_snapshot['line1'] ?? '' }}<br>
                {{ $fo->order?->address_snapshot['city'] ?? '' }}, {{ $fo->order?->address_snapshot['state'] ?? '' }} - {{ $fo->order?->address_snapshot['pincode'] ?? '' }}
            </div>
            <div class="pt-2 border-t border-slate-100 dark:border-slate-800 text-xs text-slate-500">
                Phone: <strong class="text-slate-800 dark:text-slate-200">{{ $fo->order?->address_snapshot['phone'] ?? 'N/A' }}</strong>
            </div>
        </div>

        <!-- Outlet & Source Location -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-xs space-y-3">
            <div class="text-xs font-black uppercase tracking-wider text-slate-400">Dispatch Outlet</div>
            <div class="text-sm font-bold text-slate-900 dark:text-white">
                {{ $fo->store?->name ?? 'Default Store Shelf' }}
            </div>
            <div class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                Inventory Location: <strong>{{ $fo->inventoryLocation?->name ?? 'Primary Store Room' }}</strong> ({{ $fo->inventoryLocation?->code ?? 'LOC-01' }})
            </div>
            <div class="pt-2 border-t border-slate-100 dark:border-slate-800 text-xs text-slate-500">
                Rider Assigned: <strong class="text-slate-800 dark:text-slate-200">{{ $fo->deliveryAssignment?->deliveryPartner?->name ?? 'Unassigned' }}</strong>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-xs space-y-2">
            <div class="text-xs font-black uppercase tracking-wider text-slate-400 mb-1">Financial Settlement</div>
            <div class="flex justify-between text-xs text-slate-600 dark:text-slate-300">
                <span>Package Total</span>
                <span class="font-bold text-slate-900 dark:text-white">{{ format_currency((float) $fo->total) }}</span>
            </div>
            <div class="flex justify-between text-xs text-rose-600 dark:text-rose-400 font-semibold">
                <span>Marketplace Commission</span>
                <span>-{{ format_currency((float) $fo->commission_amount) }}</span>
            </div>
            <div class="pt-2 border-t border-slate-200 dark:border-slate-700 flex justify-between text-sm font-black">
                <span class="text-slate-900 dark:text-white">Seller Net Payable</span>
                <span class="text-emerald-600 dark:text-emerald-400">{{ format_currency((float) $fo->seller_payable) }}</span>
            </div>
        </div>
    </div>

    <!-- Items in this package -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/80 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-xs font-black uppercase tracking-wider text-slate-400">
            Items to Pick &amp; Pack ({{ $fo->items->count() }})
        </div>

        <div class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach($fo->items as $item)
                <div class="p-6 flex items-center justify-between gap-4">
                    <div class="flex items-center space-x-4 min-w-0">
                        <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden flex items-center justify-center shrink-0">
                            @if($item->variant?->product?->primary_image_url)
                                <img src="{{ $item->variant->product->primary_image_url }}" alt="{{ $item->title }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-2xl">🛍️</span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate" title="{{ $item->title }}">{{ $item->title }}</h4>
                            @if($item->variant_name && $item->variant_name !== 'Default')
                                <div class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold">{{ $item->variant_name }}</div>
                            @endif
                            <div class="text-xs text-slate-400 font-mono mt-0.5">SKU: {{ $item->sku }}</div>
                        </div>
                    </div>

                    <div class="text-right shrink-0">
                        <div class="text-base font-black text-slate-900 dark:text-white">{{ format_currency((float) $item->total) }}</div>
                        <div class="text-xs text-slate-400">Qty: <strong>{{ $item->qty }}</strong> &times; {{ format_currency((float) $item->price) }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Audit History Trail -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-xs space-y-4">
        <div class="text-xs font-black uppercase tracking-wider text-slate-400">Package Status History &amp; Audit Trail</div>

        <div class="space-y-3">
            @forelse($fo->statusHistory()->latest()->get() as $log)
                <div class="flex items-start space-x-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60 text-xs">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 mt-1 shrink-0"></span>
                    <div class="flex-1 space-y-0.5">
                        <div class="flex items-center justify-between font-bold text-slate-900 dark:text-white">
                            <span>Status changed to <span class="capitalize text-emerald-600 dark:text-emerald-400">{{ str_replace('_', ' ', $log->to_status) }}</span></span>
                            <span class="text-[11px] text-slate-400 font-mono font-normal">{{ $log->created_at->format('d M Y, h:i A') }}</span>
                        </div>
                        <p class="text-slate-600 dark:text-slate-300">{{ $log->note }}</p>
                        @if($log->changedByUser)
                            <div class="text-[10px] text-slate-400">By user: {{ $log->changedByUser->name }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-xs text-slate-400 italic">No transition history logged yet.</div>
            @endforelse
        </div>
    </div>

    <!-- Reject Modal -->
    <div 
        x-show="rejectModal" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-xs"
        @keydown.escape.window="rejectModal = false"
    >
        <div 
            @click.away="rejectModal = false" 
            class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 sm:p-8 space-y-4 shadow-2xl border border-slate-200 dark:border-slate-800"
        >
            <div class="w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 flex items-center justify-center text-2xl mx-auto">
                ❌
            </div>
            <div class="text-center space-y-1">
                <h3 class="text-lg font-black text-slate-900 dark:text-white">Reject Package #{{ $fo->fulfilment_order_number }}?</h3>
                <p class="text-xs text-slate-500">Rejecting will immediately invoke the auto-reroute engine to find an alternative eligible merchant store.</p>
            </div>

            <form method="POST" action="{{ route('seller.orders.transition', $fo) }}" class="space-y-4">
                @csrf
                <input type="hidden" name="target_status" value="rejected">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Reason for Rejection</label>
                    <select name="note" required class="w-full text-xs font-medium p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white">
                        <option value="Items currently out of stock at store">Items currently out of stock at store</option>
                        <option value="Store closing / staff unavailable">Store closing / staff unavailable</option>
                        <option value="Delivery radius out of reach">Delivery radius out of reach</option>
                        <option value="Technical order discrepancy">Technical order discrepancy</option>
                    </select>
                </div>

                <div class="flex items-center space-x-3 pt-2">
                    <button 
                        type="button" 
                        @click="rejectModal = false" 
                        class="w-1/2 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold transition"
                    >
                        Back
                    </button>
                    <button 
                        type="submit" 
                        class="w-1/2 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-xs transition"
                    >
                        Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign Staff Modal -->
    <div 
        x-show="assignStaffModal" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-xs"
        @keydown.escape.window="assignStaffModal = false"
    >
        <div 
            @click.away="assignStaffModal = false" 
            class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 sm:p-8 space-y-4 shadow-2xl border border-slate-200 dark:border-slate-800"
        >
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 flex items-center justify-center text-2xl mx-auto">
                🛵
            </div>
            <div class="text-center space-y-1">
                <h3 class="text-lg font-black text-slate-900 dark:text-white">Assign Rider / Staff</h3>
                <p class="text-xs text-slate-500">Select an in-store runner or authorized delivery partner rider for package #{{ $fo->fulfilment_order_number }}.</p>
            </div>

            <form method="POST" action="{{ route('seller.orders.assign_staff', $fo) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Select Delivery Partner / Courier</label>
                    <select name="delivery_partner_id" class="w-full text-xs font-medium p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white">
                        <option value="">-- Choose Courier / Partner --</option>
                        @foreach($deliveryPartners as $partner)
                            <option value="{{ $partner->id }}">{{ $partner->name }} ({{ strtoupper($partner->code) }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center space-x-3 pt-2">
                    <button 
                        type="button" 
                        @click="assignStaffModal = false" 
                        class="w-1/2 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold transition"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit" 
                        class="w-1/2 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition"
                    >
                        Assign &amp; Dispatch
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
