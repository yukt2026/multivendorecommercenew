@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Admin Order Console #' . $order->order_number])

@section('content')
<div class="space-y-6" x-data="{ overrideModal: false, noteModal: false }">

    <!-- Page Header & Action Bar -->
    <x-page-header 
        title="Order #{{ $order->order_number }}" 
        subtitle="Placed on {{ $order->created_at->format('l, d M Y, h:i A') }} &bull; Total: {{ format_currency((float) $order->total) }}"
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Orders' => route('admin.orders.index'), '#' . $order->order_number => '#']"
    >
        <x-slot name="badge">
            <x-order-status-badge :status="$order->status" size="lg" />
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <a 
                    href="{{ route('admin.orders.invoice', $order) }}" 
                    class="px-3.5 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:text-indigo-600 text-xs font-bold transition shadow-xs flex items-center space-x-1"
                >
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Download Invoice</span>
                </a>

                <button 
                    type="button" 
                    @click="noteModal = true"
                    class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition flex items-center space-x-1"
                >
                    <span>+ Internal Note</span>
                </button>

                <button 
                    type="button" 
                    @click="overrideModal = true"
                    class="px-3.5 py-2 rounded-xl bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800 hover:bg-rose-100 text-xs font-bold transition"
                >
                    Override Status
                </button>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Master Order Info Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Customer Profile -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-xs space-y-3">
            <div class="text-xs font-black uppercase tracking-wider text-slate-400">Customer Profile</div>
            <div class="text-sm font-bold text-slate-900 dark:text-white">
                {{ $order->customer?->user?->name ?? $order->address_snapshot['name'] ?? 'Guest Customer' }}
            </div>
            <div class="text-xs text-slate-500 space-y-0.5">
                <div>Email: <strong class="text-slate-800 dark:text-slate-200">{{ $order->customer?->user?->email ?? 'N/A' }}</strong></div>
                <div>Phone: <strong class="text-slate-800 dark:text-slate-200">{{ $order->address_snapshot['phone'] ?? $order->customer?->user?->phone ?? 'N/A' }}</strong></div>
                <div>Customer ID: #{{ $order->customer_id }}</div>
            </div>
            <div class="pt-2 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-400 leading-relaxed">
                <strong>Shipping Address:</strong><br>
                {{ $order->address_snapshot['line1'] ?? '' }}, {{ $order->address_snapshot['city'] ?? '' }} - {{ $order->address_snapshot['pincode'] ?? '' }}
            </div>
        </div>

        <!-- Payment Details -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-xs space-y-3">
            <div class="text-xs font-black uppercase tracking-wider text-slate-400">Payment &amp; Gateway Audit</div>
            <div class="flex items-center justify-between text-xs">
                <span class="text-slate-500">Method</span>
                <span class="font-bold uppercase text-slate-900 dark:text-white">{{ $order->payment_method->value ?? 'Online' }}</span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <span class="text-slate-500">Status</span>
                <span class="px-2 py-0.5 rounded-full font-bold text-[10px] {{ $order->payment_status === \App\Enums\PaymentStatus::PAID ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400' }}">
                    {{ strtoupper(str_replace('_', ' ', $order->payment_status->value ?? 'Pending')) }}
                </span>
            </div>
            @if($payment = $order->payments->first())
                <div class="pt-2 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-400 space-y-0.5 font-mono">
                    <div>Gateway: <strong>{{ strtoupper($payment->gateway) }}</strong></div>
                    <div>Gateway Order ID: {{ $payment->gateway_order_id ?? 'N/A' }}</div>
                    <div>Gateway Payment ID: {{ $payment->gateway_payment_id ?? 'N/A' }}</div>
                </div>
            @endif
        </div>

        <!-- Price Breakdown -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-xs space-y-2">
            <div class="text-xs font-black uppercase tracking-wider text-slate-400 mb-1">Financial Reconciliation</div>
            <div class="flex justify-between text-xs text-slate-600 dark:text-slate-300">
                <span>Subtotal</span>
                <span>{{ format_currency((float) $order->subtotal) }}</span>
            </div>
            <div class="flex justify-between text-xs text-slate-600 dark:text-slate-300">
                <span>Delivery Fee</span>
                <span>{{ (float) $order->delivery_fee == 0 ? 'FREE' : format_currency((float) $order->delivery_fee) }}</span>
            </div>
            @if((float) $order->discount > 0)
                <div class="flex justify-between text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
                    <span>Discount</span>
                    <span>-{{ format_currency((float) $order->discount) }}</span>
                </div>
            @endif
            @if((float) $order->tax > 0)
                <div class="flex justify-between text-xs text-slate-600 dark:text-slate-300">
                    <span>Taxes</span>
                    <span>{{ format_currency((float) $order->tax) }}</span>
                </div>
            @endif
            <div class="pt-2 border-t border-slate-200 dark:border-slate-700 flex justify-between text-sm font-black text-slate-900 dark:text-white">
                <span>Order Total</span>
                <span class="text-indigo-600 dark:text-indigo-400">{{ format_currency((float) $order->total) }}</span>
            </div>
        </div>
    </div>

    <!-- Administrative Notes (if any) -->
    @if($order->notes)
        <div class="bg-amber-500/10 border border-amber-500/20 rounded-3xl p-6 space-y-2">
            <div class="text-xs font-black uppercase tracking-wider text-amber-600 dark:text-amber-400 flex items-center space-x-1.5">
                <span>📌</span>
                <span>Internal Administrative Notes</span>
            </div>
            <div class="text-xs text-slate-700 dark:text-slate-200 whitespace-pre-line leading-relaxed font-mono">
                {{ $order->notes }}
            </div>
        </div>
    @endif

    <!-- Split Fulfilment Packages -->
    <div class="space-y-4">
        <h3 class="text-base font-black text-slate-900 dark:text-white tracking-tight">
            Split Fulfilment Packages ({{ $order->fulfilmentOrders->count() }})
        </h3>

        <div class="space-y-4">
            @foreach($order->fulfilmentOrders as $fo)
                <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
                    <div class="bg-slate-50 dark:bg-slate-800/60 px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
                        <div class="flex items-center space-x-3">
                            <span class="font-bold text-slate-900 dark:text-white">
                                Package #{{ $fo->fulfilment_order_number }}
                            </span>
                            @if($fo->fulfilment_type === \App\Enums\FulfilmentType::QUICK)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500/10 text-amber-500 border border-amber-500/20">
                                    ⚡ Quick Commerce
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                    📦 Standard
                                </span>
                            @endif
                            <span class="text-slate-400">
                                Seller: <strong>{{ $fo->seller?->business_name }}</strong> &bull; Store: <strong>{{ $fo->store?->name ?? 'Main Hub' }}</strong>
                            </span>
                        </div>

                        <div class="flex items-center space-x-3">
                            <span class="text-slate-400 font-mono">Status:</span>
                            <span class="font-bold text-slate-900 dark:text-white capitalize">{{ str_replace('_', ' ', $fo->status) }}</span>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="p-6">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-slate-100 dark:border-slate-800 text-[10px] uppercase font-bold text-slate-400">
                                <tr>
                                    <th class="pb-2">Item</th>
                                    <th class="pb-2">SKU</th>
                                    <th class="pb-2 text-center">Qty</th>
                                    <th class="pb-2 text-right">Price</th>
                                    <th class="pb-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach($fo->items as $item)
                                    <tr>
                                        <td class="py-2.5 font-bold text-slate-900 dark:text-white">
                                            {{ $item->title }}
                                            @if($item->variant_name && $item->variant_name !== 'Default')
                                                <span class="text-[11px] text-slate-400 font-normal">({{ $item->variant_name }})</span>
                                            @endif
                                        </td>
                                        <td class="py-2.5 font-mono text-slate-400">{{ $item->sku }}</td>
                                        <td class="py-2.5 text-center font-bold">{{ $item->qty }}</td>
                                        <td class="py-2.5 text-right">{{ format_currency((float) $item->price) }}</td>
                                        <td class="py-2.5 text-right font-bold text-slate-900 dark:text-white">{{ format_currency((float) $item->total) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Complete Audit History Trail -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-xs space-y-4">
        <div class="text-xs font-black uppercase tracking-wider text-slate-400">Order Audit History &amp; Transitions</div>

        <div class="space-y-3">
            @forelse($order->statusHistory()->latest()->get() as $log)
                <div class="flex items-start space-x-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60 text-xs">
                    <span class="w-2.5 h-2.5 rounded-full {{ str_contains($log->note, '[ADMIN OVERRIDE]') ? 'bg-rose-500' : 'bg-indigo-500' }} mt-1 shrink-0"></span>
                    <div class="flex-1 space-y-0.5">
                        <div class="flex items-center justify-between font-bold text-slate-900 dark:text-white">
                            <span>
                                {{ $log->from_status ? ucwords(str_replace('_', ' ', $log->from_status)) . ' → ' : '' }}
                                <span class="text-indigo-600 dark:text-indigo-400 capitalize">{{ str_replace('_', ' ', $log->to_status) }}</span>
                            </span>
                            <span class="text-[11px] text-slate-400 font-mono font-normal">{{ $log->created_at->format('d M Y, h:i A') }}</span>
                        </div>
                        <p class="text-slate-600 dark:text-slate-300">{{ $log->note }}</p>
                        @if($log->changedByUser)
                            <div class="text-[10px] text-slate-400">Recorded by: {{ $log->changedByUser->name }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-xs text-slate-400 italic">No status history logged yet.</div>
            @endforelse
        </div>
    </div>

    <!-- Admin Status Override Modal -->
    <div 
        x-show="overrideModal" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-xs"
        @keydown.escape.window="overrideModal = false"
    >
        <div 
            @click.away="overrideModal = false" 
            class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 sm:p-8 space-y-4 shadow-2xl border border-slate-200 dark:border-slate-800"
        >
            <div class="w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 flex items-center justify-center text-2xl mx-auto">
                ⚠️
            </div>
            <div class="text-center space-y-1">
                <h3 class="text-lg font-black text-slate-900 dark:text-white">Override Order Status</h3>
                <p class="text-xs text-slate-500">Super Admin intervention. A mandatory audit reason will be permanently recorded in the order history.</p>
            </div>

            <form method="POST" action="{{ route('admin.orders.override_status', $order) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Target Status</label>
                    <select name="status" required class="w-full text-xs font-semibold p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white">
                        @foreach(\App\Enums\OrderStatus::cases() as $st)
                            <option value="{{ $st->value }}" {{ $order->status === $st ? 'selected' : '' }}>
                                {{ $st->label() }} ({{ $st->value }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Mandatory Override Reason</label>
                    <textarea 
                        name="reason" 
                        required 
                        rows="3" 
                        placeholder="State why this manual administrative override is required..." 
                        class="w-full text-xs font-medium p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-rose-500"
                    ></textarea>
                </div>

                <div class="flex items-center space-x-3 pt-2">
                    <button 
                        type="button" 
                        @click="overrideModal = false" 
                        class="w-1/2 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold transition"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit" 
                        class="w-1/2 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-xs transition"
                    >
                        Execute Override
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Admin Internal Note Modal -->
    <div 
        x-show="noteModal" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-xs"
        @keydown.escape.window="noteModal = false"
    >
        <div 
            @click.away="noteModal = false" 
            class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 sm:p-8 space-y-4 shadow-2xl border border-slate-200 dark:border-slate-800"
        >
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 flex items-center justify-center text-2xl mx-auto">
                📝
            </div>
            <div class="text-center space-y-1">
                <h3 class="text-lg font-black text-slate-900 dark:text-white">Add Internal Administrative Note</h3>
                <p class="text-xs text-slate-500">Internal notes are visible exclusively to OneStall admins and support staff.</p>
            </div>

            <form method="POST" action="{{ route('admin.orders.add_note', $order) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Note Details</label>
                    <textarea 
                        name="note" 
                        required 
                        rows="4" 
                        placeholder="E.g., Customer phoned regarding dispatch timeline..." 
                        class="w-full text-xs font-medium p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                    ></textarea>
                </div>

                <div class="flex items-center space-x-3 pt-2">
                    <button 
                        type="button" 
                        @click="noteModal = false" 
                        class="w-1/2 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold transition"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit" 
                        class="w-1/2 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition"
                    >
                        Save Note
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
