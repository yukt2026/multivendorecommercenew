@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Payment #' . $payment->id])

@section('content')
<div class="space-y-6" x-data="{ openRefundModal: false, refundAmount: '{{ $refundableBalance }}', refundReason: '' }">

    <!-- Page Header -->
    <x-page-header 
        :title="'Payment Transaction #' . $payment->id" 
        :subtitle="'Captured via ' . strtoupper($payment->gateway) . ' on ' . $payment->created_at->format('d M Y, h:i A')"
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Payments' => route('admin.payments.index'), '#' . $payment->id => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase
                @if($payment->status->value === 'paid') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400
                @elseif($payment->status->value === 'pending') bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400
                @elseif($payment->status->value === 'refunded') bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-400
                @else bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400
                @endif
            ">
                {{ $payment->status->label() }}
            </span>
        </x-slot>

        <x-slot name="actions">
            @if($payment->status->value === 'paid' && $refundableBalance > 0)
                <button @click="openRefundModal = true" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-xs font-semibold shadow-sm flex items-center space-x-1.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    <span>Process Refund</span>
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <!-- Financial Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-slate-500 mb-1">Original Paid Amount</div>
            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ format_currency((float)$payment->amount) }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-rose-500 mb-1 font-semibold">Total Refunded</div>
            <div class="text-2xl font-black font-mono text-rose-600 dark:text-rose-400">- {{ format_currency($totalRefunded) }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-emerald-600 dark:text-emerald-400 mb-1 font-semibold">Refundable Balance</div>
            <div class="text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">{{ format_currency($refundableBalance) }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs text-indigo-600 dark:text-indigo-400 mb-1 font-semibold">Payment Gateway</div>
            <div class="text-2xl font-black uppercase text-indigo-600 dark:text-indigo-400">{{ $payment->gateway }}</div>
        </div>
    </div>

    <!-- Metadata Details -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white">Transaction Reference</h3>
            <div class="text-xs space-y-2 text-slate-600 dark:text-slate-300">
                <div><span class="text-slate-400 w-36 inline-block">Gateway Payment ID:</span> <span class="font-mono text-slate-900 dark:text-white">{{ $payment->gateway_payment_id ?? 'N/A' }}</span></div>
                <div><span class="text-slate-400 w-36 inline-block">Gateway Order ID:</span> <span class="font-mono text-slate-900 dark:text-white">{{ $payment->gateway_order_id ?? 'N/A' }}</span></div>
                <div><span class="text-slate-400 w-36 inline-block">Payment Method:</span> <span class="font-semibold uppercase">{{ $payment->method?->value ?? (string) $payment->method }}</span></div>
                <div><span class="text-slate-400 w-36 inline-block">Currency:</span> <span class="font-mono">{{ $payment->currency }}</span></div>
                <div><span class="text-slate-400 w-36 inline-block">Verified At:</span> <span>{{ $payment->verified_at ? $payment->verified_at->format('d M Y, h:i A') : 'N/A' }}</span></div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white">Linked Parent Order</h3>
            <div class="text-xs space-y-2 text-slate-600 dark:text-slate-300">
                @if($payment->order)
                    <div><span class="text-slate-400 w-28 inline-block">Order Number:</span> <strong>#{{ $payment->order->order_number }}</strong></div>
                    <div><span class="text-slate-400 w-28 inline-block">Customer:</span> {{ $payment->order->customer?->user?->name ?? 'Guest' }}</div>
                    <div><span class="text-slate-400 w-28 inline-block">Order Status:</span> <x-order-status-badge :status="$payment->order->status" /></div>
                    <div class="pt-2">
                        <a href="{{ route('admin.orders.show', $payment->order) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">
                            Open Order Console &rarr;
                        </a>
                    </div>
                @else
                    <div class="text-slate-400">No parent order associated.</div>
                @endif
            </div>
        </div>
    </div>

    <!-- Refund History -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Refund History ({{ $payment->refunds->count() }})</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Refund ID</th>
                        <th class="px-6 py-3.5">Gateway Refund ID</th>
                        <th class="px-6 py-3.5">Reason</th>
                        <th class="px-6 py-3.5 text-right">Amount</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($payment->refunds as $rf)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-3.5 font-mono font-bold text-slate-900 dark:text-white">#{{ $rf->id }}</td>
                        <td class="px-6 py-3.5 font-mono text-slate-500">{{ $rf->gateway_refund_id ?? 'N/A' }}</td>
                        <td class="px-6 py-3.5 text-slate-700 dark:text-slate-300">{{ $rf->reason }}</td>
                        <td class="px-6 py-3.5 text-right font-mono font-bold text-rose-600 dark:text-rose-400">- {{ format_currency((float)$rf->amount) }}</td>
                        <td class="px-6 py-3.5">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400">{{ $rf->status->value }}</span>
                        </td>
                        <td class="px-6 py-3.5 text-slate-500 font-mono text-[11px]">{{ $rf->created_at->format('d M Y, h:i A') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-6 text-center text-slate-400">No refunds processed on this payment.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Raw Gateway Audit Log -->
    @if(!empty($payment->raw_response))
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white mb-2">Raw Gateway Response Payload (Sanitized Audit)</h3>
        <pre class="p-4 rounded-xl bg-slate-950 text-emerald-400 font-mono text-[11px] overflow-x-auto">{{ json_encode($payment->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
    @endif

    <!-- Refund Modal -->
    <div x-show="openRefundModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div @click.away="openRefundModal = false" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-2">Initiate Gateway Refund</h3>
            <p class="text-xs text-slate-500 mb-4">Refund will be processed via {{ strtoupper($payment->gateway) }} API directly back to the customer's source account.</p>

            <form action="{{ route('admin.payments.refund', $payment) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Refund Amount (Max {{ format_currency($refundableBalance) }})</label>
                    <input type="number" step="0.01" name="amount" required max="{{ $refundableBalance }}" x-model="refundAmount" class="w-full text-xs font-mono rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-rose-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Reason for Refund</label>
                    <input type="text" name="reason" required x-model="refundReason" placeholder="e.g. Customer cancelled item / defective product" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-rose-500">
                </div>
                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="openRefundModal = false" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-xs font-semibold shadow-sm">Execute Refund</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
