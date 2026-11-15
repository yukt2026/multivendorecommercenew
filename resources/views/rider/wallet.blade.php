@extends('layouts.rider', ['title' => 'Rider Wallet & Earnings - OneStall'])

@section('content')
<div class="space-y-4">

    <!-- Header -->
    <div class="space-y-1">
        <h1 class="text-base font-black text-slate-900">Rider Wallet &amp; Earnings</h1>
        <p class="text-xs text-slate-500">Track delivery trip earnings, digital balance, and cash in hand.</p>
    </div>

    <!-- Balance Hero Cards -->
    <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-3xl p-5 shadow-lg space-y-4">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Available Wallet Balance</span>
            <span class="text-[10px] bg-emerald-500/20 text-emerald-300 font-bold px-2 py-0.5 rounded-full border border-emerald-500/30">
                Direct Bank Payout
            </span>
        </div>

        <div class="space-y-0.5">
            <div class="text-3xl font-black font-mono tracking-tight text-emerald-400">
                ₹{{ number_format($wallet->balance, 2) }}
            </div>
            <p class="text-[11px] text-slate-400 text-white">
                Disbursements are processed weekly directly to your registered bank account.
            </p>
        </div>

        <div class="grid grid-cols-2 gap-3 pt-3 border-t border-slate-700/60 text-xs">
            <div>
                <span class="text-[10px] text-slate-400 uppercase font-bold block text-white">Lifetime Earned</span>
                <span class="font-mono font-bold text-white text-sm">₹{{ number_format($wallet->total_earned, 2) }}</span>
            </div>
            <div class="text-right">
                <span class="text-[10px] text-slate-400 uppercase font-bold block text-white">Total Transactions</span>
                <span class="font-mono font-bold text-white text-sm">{{ $transactions->total() }}</span>
            </div>
        </div>
    </div>

    <!-- Cash In Hand (COD Collection) Card -->
    @php
        $cashHeld = (float) $rider->current_cash_in_hand;
        $limit = (float) ($rider->cash_in_hand_limit ?? 5000);
        $percentHeld = min(100, round(($cashHeld / max(1, $limit)) * 100));
        $isOverLimit = $cashHeld >= $limit;
    @endphp
    <div class="bg-white rounded-3xl p-4 sm:p-5 border border-slate-200 shadow-xs space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-black text-slate-800 uppercase tracking-wider">
                💵 Cash In Hand (COD Collected)
            </span>
            <span class="text-xs font-mono font-black {{ $isOverLimit ? 'text-rose-600' : 'text-amber-600' }}">
                ₹{{ number_format($cashHeld, 2) }} / ₹{{ number_format($limit, 0) }}
            </span>
        </div>

        <!-- Progress Bar -->
        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
            <div 
                class="h-full rounded-full transition-all duration-300 {{ $isOverLimit ? 'bg-rose-500' : ($percentHeld > 75 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                style="width: {{ $percentHeld }}%"
            ></div>
        </div>

        @if($isOverLimit)
            <div class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-[11px] font-semibold flex items-center space-x-1.5">
                <span>⚠️</span>
                <span>Limit reached! Please remit cash collected at your assigned dark store hub to receive new orders.</span>
            </div>
        @else
            <p class="text-[11px] text-slate-400">
                Cash collected from COD customers must be deposited at your local dark store hub before exceeding your limit.
            </p>
        @endif
    </div>

    <!-- Wallet Transaction Ledger -->
    <div class="space-y-2.5 pt-1">
        <div class="flex items-center justify-between text-xs">
            <h2 class="font-black text-slate-900 uppercase tracking-wider text-[11px]">Recent Transactions</h2>
            <span class="text-slate-400 font-mono text-[10px]">{{ $transactions->total() }} recorded</span>
        </div>

        @if($transactions->isEmpty())
            <div class="bg-white rounded-2xl p-6 text-center border border-dashed border-slate-200 text-xs text-slate-400">
                No wallet transactions recorded yet. Earnings will appear here when you complete deliveries.
            </div>
        @else
            <div class="bg-white rounded-3xl shadow-xs border border-slate-200 divide-y divide-slate-100 overflow-hidden">
                @foreach($transactions as $tx)
                    @php $isCredit = $tx->type === 'credit'; @endphp
                    <div class="p-3.5 flex items-center justify-between text-xs">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs shrink-0
                                {{ $isCredit ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">
                                {{ $isCredit ? '↓' : '↑' }}
                            </div>
                            <div>
                                <p class="font-bold text-slate-900 line-clamp-1">
                                    {{ $tx->description ?: ($isCredit ? 'Delivery Compensation' : 'Disbursement / Payout') }}
                                </p>
                                <span class="text-[10px] text-slate-400 font-mono">
                                    {{ $tx->created_at?->format('d M, h:i A') }} &bull; Balance: ₹{{ number_format($tx->balance_after, 2) }}
                                </span>
                            </div>
                        </div>

                        <div class="text-right font-mono font-black text-sm shrink-0 {{ $isCredit ? 'text-emerald-600' : 'text-slate-900' }}">
                            {{ $isCredit ? '+' : '-' }}₹{{ number_format($tx->amount, 2) }}
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="pt-2">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
