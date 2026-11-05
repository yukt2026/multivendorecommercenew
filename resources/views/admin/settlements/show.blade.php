@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Settlement #' . $settlement->settlement_number])

@section('content')
<div class="space-y-6" x-data="{ openPayModal: false, openHoldModal: false, openAdjModal: false, utrRef: '' }">

    <!-- Page Header & Action Bar -->
    <x-page-header 
        :title="'Settlement #' . $settlement->settlement_number" 
        :subtitle="'Cycle period ' . $settlement->period_start->format('d M Y') . ' to ' . $settlement->period_end->format('d M Y') . ' for ' . ($settlement->seller?->store_name ?? 'Seller #' . $settlement->seller_id)"
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Settlements' => route('admin.settlements.index'), $settlement->settlement_number => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase
                @if($settlement->status->value === 'paid' || $settlement->status->value === 'settled') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400
                @elseif($settlement->status->value === 'processing') bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400
                @elseif($settlement->status->value === 'on_hold') bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400
                @else bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400
                @endif
            ">
                {{ $settlement->status->label() }}
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                @if($settlement->status->value === 'pending')
                    <form method="POST" action="{{ route('admin.settlements.approve', $settlement) }}" class="inline">
                        @csrf
                        <button type="submit" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                            Approve Batch
                        </button>
                    </form>
                    <button @click="openHoldModal = true" class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                        Place on Hold
                    </button>
                @elseif($settlement->status->value === 'processing')
                    <button @click="openPayModal = true" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                        Mark Paid (UTR)
                    </button>
                    <button @click="openHoldModal = true" class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                        Place on Hold
                    </button>
                @elseif($settlement->status->value === 'on_hold')
                    <form method="POST" action="{{ route('admin.settlements.release', $settlement) }}" class="inline">
                        @csrf
                        <button type="submit" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                            Release Hold
                        </button>
                    </form>
                @endif

                @if($settlement->status->value !== 'paid' && $settlement->status->value !== 'settled')
                    <button @click="openAdjModal = true" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold transition">
                        + Adjustment
                    </button>
                @endif

                <a href="{{ route('admin.settlements.pdf', $settlement) }}" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1 transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>PDF Statement</span>
                </a>

                <a href="{{ route('admin.settlements.csv', $settlement) }}" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1 transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>CSV</span>
                </a>
            </div>
        </x-slot>
    </x-page-header>

    @if($settlement->status->value === 'on_hold')
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span><strong>SETTLEMENT ON HOLD:</strong> {{ $settlement->hold_reason }}</span>
            </div>
        </div>
    @endif

    <!-- Accounting Ledger Breakdown Cards -->
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase">Gross Sales</div>
            <div class="text-xl font-bold font-mono text-slate-900 dark:text-white mt-1">{{ format_currency((float)$settlement->gross) }}</div>
            <div class="text-[10px] text-slate-400 mt-1">Delivered orders</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-rose-500 uppercase">Commission</div>
            <div class="text-xl font-bold font-mono text-rose-600 dark:text-rose-400 mt-1">- {{ format_currency((float)$settlement->commission) }}</div>
            <div class="text-[10px] text-slate-400 mt-1">Platform deduction</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-rose-500 uppercase">GST on Comm</div>
            <div class="text-xl font-bold font-mono text-rose-600 dark:text-rose-400 mt-1">- {{ format_currency((float)$settlement->tax_on_commission) }}</div>
            <div class="text-[10px] text-slate-400 mt-1">{{ setting('commission_gst_pct', 18) }}% GST on fee</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-amber-500 uppercase">COD Deducted</div>
            <div class="text-xl font-bold font-mono text-amber-600 dark:text-amber-400 mt-1">
                @if((float)$settlement->cod_collected > 0)
                    - {{ format_currency((float)$settlement->cod_collected) }}
                @else
                    ₹0.00
                @endif
            </div>
            <div class="text-[10px] text-slate-400 mt-1">Doorstep cash</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-indigo-500 uppercase">Adjustments</div>
            <div class="text-xl font-bold font-mono text-indigo-600 dark:text-indigo-400 mt-1">
                {{ (float)$settlement->adjustments >= 0 ? '+ ' : '' }}{{ format_currency((float)$settlement->adjustments) }}
            </div>
            <div class="text-[10px] text-slate-400 mt-1">Manual / promo</div>
        </div>

        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 shadow-sm">
            <div class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-300 uppercase">Net Payout</div>
            <div class="text-xl font-bold font-mono text-emerald-700 dark:text-emerald-300 mt-1">{{ format_currency((float)$settlement->net) }}</div>
            <div class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-1">
                @if($settlement->reference)
                    UTR: {{ $settlement->reference }}
                @else
                    Payable amount
                @endif
            </div>
        </div>
    </div>

    <!-- Settlement Details & Merchant Bank Information -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
            <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Merchant &amp; Store Entity</h3>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div>
                    <span class="text-slate-400 block">Store Name:</span>
                    <strong class="text-slate-900 dark:text-white">{{ $settlement->seller?->store_name }}</strong>
                </div>
                <div>
                    <span class="text-slate-400 block">Legal Entity:</span>
                    <strong class="text-slate-900 dark:text-white">{{ $settlement->seller?->legal_name ?? $settlement->seller?->company_name ?? 'N/A' }}</strong>
                </div>
                <div>
                    <span class="text-slate-400 block">GSTIN:</span>
                    <span class="font-mono text-slate-700 dark:text-slate-300">{{ $settlement->seller?->gstin ?? 'Unregistered' }}</span>
                </div>
                <div>
                    <span class="text-slate-400 block">PAN:</span>
                    <span class="font-mono text-slate-700 dark:text-slate-300">{{ $settlement->seller?->pan ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
            <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Disbursement Target &amp; Timestamps</h3>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div>
                    <span class="text-slate-400 block">Settlement Period:</span>
                    <strong class="text-slate-900 dark:text-white">{{ $settlement->period_start->format('d M') }} &rarr; {{ $settlement->period_end->format('d M Y') }}</strong>
                </div>
                <div>
                    <span class="text-slate-400 block">Generated At:</span>
                    <span class="text-slate-700 dark:text-slate-300">{{ $settlement->created_at->format('d M Y, h:i A') }}</span>
                </div>
                <div>
                    <span class="text-slate-400 block">Paid Status / Date:</span>
                    <span class="text-slate-700 dark:text-slate-300">
                        {{ $settlement->paid_at ? $settlement->paid_at->format('d M Y, h:i A') : 'Pending Payout' }}
                    </span>
                </div>
                <div>
                    <span class="text-slate-400 block">Bank UTR Reference:</span>
                    <strong class="font-mono text-emerald-600 dark:text-emerald-400">{{ $settlement->reference ?: 'Not disbursed yet' }}</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Settlement Items Ledger -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Itemized Ledger Transactions ({{ $settlement->items->count() }})</h3>
            <span class="text-xs text-slate-400">All mature delivered orders and deductions</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">#</th>
                        <th class="px-6 py-3.5">Transaction Type</th>
                        <th class="px-6 py-3.5">Description</th>
                        <th class="px-6 py-3.5">Package / Ref</th>
                        <th class="px-6 py-3.5 text-right">Order Gross</th>
                        <th class="px-6 py-3.5 text-right">Commission Fee</th>
                        <th class="px-6 py-3.5 text-right">GST (18%)</th>
                        <th class="px-6 py-3.5 text-right">Net Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($settlement->items as $idx => $item)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-3.5 text-slate-400 font-mono">{{ $idx + 1 }}</td>
                        <td class="px-6 py-3.5">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                @if($item->type === 'order_payout') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400
                                @elseif($item->type === 'cod_deduction') bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400
                                @elseif($item->type === 'adjustment') bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400
                                @else bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300
                                @endif
                            ">
                                {{ str_replace('_', ' ', $item->type) }}
                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-slate-900 dark:text-white font-medium">
                            {{ $item->description }}
                        </td>
                        <td class="px-6 py-3.5 font-mono text-slate-600 dark:text-slate-400">
                            @if($item->fulfilmentOrder)
                                <a href="{{ route('admin.orders.show', $item->fulfilmentOrder->order_id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                    #{{ $item->fulfilmentOrder->fulfilment_order_number }}
                                </a>
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-6 py-3.5 text-right font-mono text-slate-900 dark:text-white">
                            {{ $item->commissionEntry ? format_currency((float)$item->commissionEntry->base_amount) : '-' }}
                        </td>
                        <td class="px-6 py-3.5 text-right font-mono text-rose-600 dark:text-rose-400">
                            {{ $item->commissionEntry ? '- ' . format_currency((float)$item->commissionEntry->commission) : '-' }}
                        </td>
                        <td class="px-6 py-3.5 text-right font-mono text-rose-600 dark:text-rose-400">
                            {{ $item->commissionEntry ? '- ' . format_currency((float)$item->commissionEntry->tax_on_commission) : '-' }}
                        </td>
                        <td class="px-6 py-3.5 text-right font-mono font-bold {{ (float)$item->amount < 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            {{ format_currency((float)$item->amount) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-slate-500">No items found in this settlement.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modals -->
    <!-- Pay UTR Modal -->
    <div x-show="openPayModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div @click.away="openPayModal = false" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-2">Disburse &amp; Record Bank UTR</h3>
            <form action="{{ route('admin.settlements.pay', $settlement) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Bank UTR Reference Number</label>
                    <input type="text" name="reference" required x-model="utrRef" placeholder="e.g. UTR829104810294" class="w-full text-xs font-mono rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                </div>
                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="openPayModal = false" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-semibold shadow-sm">Confirm Disbursement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hold Modal -->
    <div x-show="openHoldModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div @click.away="openHoldModal = false" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-2">Place Settlement On Hold</h3>
            <form action="{{ route('admin.settlements.hold', $settlement) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Hold Reason</label>
                    <textarea name="reason" required rows="3" placeholder="Explain why this settlement is being held..." class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500"></textarea>
                </div>
                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="openHoldModal = false" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-xs font-semibold shadow-sm">Hold Settlement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Adjustment Modal -->
    <div x-show="openAdjModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div @click.away="openAdjModal = false" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-2">Add Financial Adjustment</h3>
            <form action="{{ route('admin.settlements.adjustment', $settlement) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Adjustment Amount (₹, use negative for deduction)</label>
                    <input type="number" step="0.01" name="amount" required placeholder="-500.00 or 250.00" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Reason / Memo</label>
                    <input type="text" name="reason" required placeholder="e.g. Return shipping cost waiver / Bonus" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                </div>
                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="openAdjModal = false" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm">Apply Adjustment</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
