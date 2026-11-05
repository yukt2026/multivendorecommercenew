@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Refunds Audit Ledger'])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        title="Refunds Audit &amp; Reconciliations" 
        subtitle="Gateway reversed transactions, customer refund history, and ledger reversals."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Payments' => route('admin.payments.index'), 'Refunds' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                {{ format_currency($metrics['total_refunded']) }} Refunded
            </span>
        </x-slot>

        <x-slot name="actions">
            <a href="{{ route('admin.payments.index') }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                <span>&larr; Back to Payments</span>
            </a>
        </x-slot>
    </x-page-header>

    <!-- Refunds Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Refund ID</th>
                        <th class="px-6 py-3.5">Gateway Reference</th>
                        <th class="px-6 py-3.5">Parent Order #</th>
                        <th class="px-6 py-3.5">Customer</th>
                        <th class="px-6 py-3.5">Reason</th>
                        <th class="px-6 py-3.5 text-right">Amount</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5">Processed At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($refunds as $r)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4 font-mono font-bold text-slate-900 dark:text-white">#{{ $r->id }}</td>
                        <td class="px-6 py-4 font-mono text-slate-500">{{ $r->gateway_refund_id ?? 'N/A' }}</td>
                        <td class="px-6 py-4 font-mono">
                            @if($r->payment?->order)
                                <a href="{{ route('admin.orders.show', $r->payment->order) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {{ $r->payment->order->order_number }}
                                </a>
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-6 py-4">{{ $r->payment?->order?->customer?->user?->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ $r->reason }}</td>
                        <td class="px-6 py-4 text-right font-mono font-bold text-rose-600 dark:text-rose-400">
                            - {{ format_currency((float)$r->amount) }}
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400">{{ $r->status->value }}</span>
                        </td>
                        <td class="px-6 py-4 text-slate-500 font-mono text-[11px]">{{ $r->created_at->format('d M Y, h:i A') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-slate-400">No refunds recorded.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
            {{ $refunds->links() }}
        </div>
    </div>

</div>
@endsection
