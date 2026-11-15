@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Admin Order Console #' . $order->order_number])

@section('content')
<div class="space-y-6" x-data="{ assignModalFoId: null }">

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
                    class="px-3.5 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:text-indigo-600 text-xs font-bold transition shadow-xs flex items-center space-x-1.5"
                >
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Download Invoice</span>
                </a>

                <button 
                    type="button" 
                    @click="$dispatch('open-modal', 'note-modal')"
                    class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition flex items-center space-x-1.5 cursor-pointer"
                >
                    <span>+ Internal Note</span>
                </button>

                <button 
                    type="button" 
                    @click="$dispatch('open-modal', 'override-status-modal')"
                    class="px-3.5 py-2 rounded-xl bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800 hover:bg-rose-100 text-xs font-bold transition cursor-pointer"
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
            <div class="text-xs font-black uppercase tracking-wider text-slate-400">Customer &amp; Shipping Profile</div>
            <div class="text-sm font-bold text-slate-900 dark:text-white">
                {{ $order->customer?->user?->name ?? $order->address_snapshot['name'] ?? 'Guest Customer' }}
            </div>
            <div class="text-xs text-slate-500 space-y-1">
                @if($email = $order->customer?->user?->email)
                    <div>Email: <a href="mailto:{{ $email }}" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">{{ $email }}</a></div>
                @endif
                @if($phone = ($order->address_snapshot['phone'] ?? $order->customer?->user?->phone))
                    <div>Phone: <a href="tel:{{ $phone }}" class="text-slate-800 dark:text-slate-200 font-bold hover:underline font-mono">{{ $phone }}</a></div>
                @endif
                <div>Customer ID: <span class="font-mono font-bold text-slate-700 dark:text-slate-300">#{{ $order->customer_id }}</span></div>
            </div>
            <div class="pt-2 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-500 leading-relaxed">
                <strong class="text-slate-700 dark:text-slate-300">Delivery Address:</strong><br>
                {{ $order->address_snapshot['line1'] ?? '' }}{{ !empty($order->address_snapshot['line2']) ? ', ' . $order->address_snapshot['line2'] : '' }}<br>
                {{ $order->address_snapshot['city'] ?? '' }}{{ !empty($order->address_snapshot['state']) ? ', ' . $order->address_snapshot['state'] : '' }} - <span class="font-mono font-bold">{{ $order->address_snapshot['pincode'] ?? '' }}</span>
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
        <div class="flex items-center justify-between">
            <h3 class="text-base font-black text-slate-900 dark:text-white tracking-tight">
                Split Fulfilment Packages ({{ $order->fulfilmentOrders->count() }})
            </h3>
            <span class="text-xs text-slate-400">Independent merchant shipments &amp; rider routing</span>
        </div>

        <div class="space-y-4">
            @foreach($order->fulfilmentOrders as $fo)
                @php
                    $isQuick = $fo->fulfilment_type === \App\Enums\FulfilmentType::QUICK;
                    $rider = $fo->deliveryAssignment?->deliveryPartner;
                    $shipment = $fo->shipment;
                @endphp
                <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
                    <!-- Package Header Bar -->
                    <div class="bg-slate-50 dark:bg-slate-800/60 px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
                        <div class="flex items-center space-x-3">
                            <span class="font-bold text-slate-900 dark:text-white text-sm">
                                Package #{{ $fo->fulfilment_order_number }}
                            </span>
                            @if($isQuick)
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                                    ⚡ 10-30m Quick Commerce
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                    📦 Standard Logistics
                                </span>
                            @endif
                            <span class="text-slate-400">
                                Seller: <strong class="text-slate-700 dark:text-slate-300">{{ $fo->seller?->business_name }}</strong> &bull; Store: <strong class="text-slate-700 dark:text-slate-300">{{ $fo->store?->name ?? 'Main Hub' }}</strong>
                            </span>
                        </div>

                        <div class="flex items-center space-x-3">
                            <span class="text-slate-400 font-mono text-[11px]">Package Status:</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-400 capitalize">
                                {{ str_replace('_', ' ', $fo->status) }}
                            </span>
                        </div>
                    </div>

                    <!-- Logistics / Rider / Courier Ribbon -->
                    @if($isQuick)
                        <div class="px-6 py-2.5 bg-amber-50/50 dark:bg-amber-950/20 border-b border-amber-100 dark:border-amber-900/30 flex flex-wrap items-center justify-between gap-2 text-xs">
                            <div class="flex items-center space-x-2">
                                @if($rider)
                                    <span class="font-bold text-slate-800 dark:text-slate-200">🚴 Assigned Rider:</span>
                                    <span class="font-bold text-amber-800 dark:text-amber-300">{{ $rider->name }}</span>
                                    <a href="tel:{{ $rider->phone }}" class="text-indigo-600 font-mono hover:underline font-bold">({{ $rider->phone }})</a>
                                    @if($rider->vehicle_number)
                                        <span class="text-slate-400 font-mono text-[10px]">&bull; {{ $rider->vehicle_number }}</span>
                                    @endif
                                @else
                                    <span class="font-bold text-amber-800 dark:text-amber-400">⚡ Hyperlocal Dispatch:</span>
                                    <span class="text-slate-500">No rider assigned</span>
                                @endif
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="text-[11px] text-slate-500 font-mono">
                                    SLA: {{ $fo->minutes_since_placed }}m elapsed
                                </div>
                                @if(!in_array($fo->status, ['delivered', 'cancelled']))
                                    <button 
                                        type="button" 
                                        @click="assignModalFoId = {{ $fo->id }}; $dispatch('open-modal', 'assign-rider-modal')"
                                        class="px-2.5 py-1 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-[11px] font-bold shadow-xs transition cursor-pointer"
                                    >
                                        {{ $rider ? 'Reassign Rider' : '+ Assign Rider' }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    @elseif($shipment && $shipment->awb)
                        <div class="px-6 py-2.5 bg-slate-50/80 dark:bg-slate-850 border-b border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2 text-xs" x-data="{ copiedAwb: false }">
                            <div class="flex items-center space-x-2">
                                <span class="text-slate-500 font-semibold">🚚 Courier Partner:</span>
                                <strong class="text-slate-800 dark:text-slate-200">{{ $shipment->courier ?? 'Express Cargo' }}</strong>
                                <span class="text-slate-400">&bull;</span>
                                <span class="text-slate-500 font-semibold">AWB Tracking:</span>
                                <span class="font-mono font-bold text-indigo-600">{{ $shipment->awb }}</span>
                                <button 
                                    type="button" 
                                    @click="navigator.clipboard.writeText('{{ $shipment->awb }}'); copiedAwb = true; setTimeout(() => copiedAwb = false, 2000)"
                                    class="text-[10px] text-slate-400 hover:text-indigo-600 underline cursor-pointer"
                                    x-text="copiedAwb ? 'Copied!' : 'Copy'"
                                ></button>
                            </div>
                            <div class="text-[11px] text-slate-500">
                                Shipment Status: <strong class="text-slate-700 dark:text-slate-300 uppercase">{{ $shipment->status?->value ?? 'Dispatched' }}</strong>
                            </div>
                        </div>
                    @endif

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
        <div class="text-xs font-black uppercase tracking-wider text-slate-400">Order Audit History &amp; State Transitions</div>

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
    <x-modal name="override-status-modal" size="md">
        <x-slot name="header">
            <div class="flex items-center space-x-2">
                <span class="text-rose-500 font-bold">⚠️</span>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Override Order Status</h3>
            </div>
        </x-slot>

        <form id="overrideStatusForm" method="POST" action="{{ route('admin.orders.override_status', $order) }}" class="space-y-4">
            @csrf
            <p class="text-xs text-slate-500">Super Admin intervention. A mandatory audit reason will be permanently recorded in the order history.</p>

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
        </form>

        <x-slot name="footer">
            <button 
                type="button" 
                @click="$dispatch('close-modal', 'override-status-modal')" 
                class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold transition"
            >
                Cancel
            </button>
            <button 
                form="overrideStatusForm"
                type="submit" 
                class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-xs transition"
            >
                Execute Override
            </button>
        </x-slot>
    </x-modal>

    <!-- Admin Internal Note Modal -->
    <x-modal name="note-modal" size="md">
        <x-slot name="header">
            <div class="flex items-center space-x-2">
                <span class="text-indigo-500">📝</span>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Add Internal Administrative Note</h3>
            </div>
        </x-slot>

        <form id="addNoteForm" method="POST" action="{{ route('admin.orders.add_note', $order) }}" class="space-y-4">
            @csrf
            <p class="text-xs text-slate-500">Internal notes are visible exclusively to OneStall admins and support staff.</p>

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
        </form>

        <x-slot name="footer">
            <button 
                type="button" 
                @click="$dispatch('close-modal', 'note-modal')" 
                class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold transition"
            >
                Cancel
            </button>
            <button 
                form="addNoteForm"
                type="submit" 
                class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition"
            >
                Save Note
            </button>
        </x-slot>
    </x-modal>

    <!-- Assign / Reassign Rider Modal -->
    <x-modal name="assign-rider-modal" size="md">
        <x-slot name="header">
            <div class="flex items-center space-x-2">
                <span class="text-amber-500 text-lg">🚴</span>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Assign Delivery Partner</h3>
            </div>
        </x-slot>

        <form id="assignRiderForm" method="POST" :action="'{{ url('/admin/delivery-partners/orders') }}/' + assignModalFoId + '/assign'" class="space-y-4">
            @csrf
            <p class="text-xs text-slate-500">Manually select a verified delivery rider to fulfill this quick-commerce package.</p>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Select Delivery Rider <span class="text-rose-500">*</span></label>
                <select 
                    name="delivery_partner_id" 
                    required 
                    class="w-full text-xs font-medium p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">-- Choose Rider --</option>
                    @if(isset($availableRiders))
                        @foreach($availableRiders as $avRider)
                            <option value="{{ $avRider->id }}">
                                {{ $avRider->name }} ({{ $avRider->phone }}) &bull; {{ $avRider->vehicle }} &bull; [{{ ucfirst($avRider->status) }}]
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
        </form>

        <x-slot name="footer">
            <button 
                type="button" 
                @click="$dispatch('close-modal', 'assign-rider-modal')" 
                class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold transition"
            >
                Cancel
            </button>
            <button 
                form="assignRiderForm"
                type="submit" 
                class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-xs transition cursor-pointer"
            >
                Confirm Assignment
            </button>
        </x-slot>
    </x-modal>

</div>
@endsection
