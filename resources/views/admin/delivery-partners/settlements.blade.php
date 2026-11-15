@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Rider Wallets & Settlements'])

@section('content')
<div class="space-y-6" x-data="riderSettlementsManager()">

    <!-- Page Header & Action Bar -->
    <x-page-header 
        title="Rider Wallets & Settlements" 
        subtitle="Manage delivery fleet earnings, wallet balances, cash-in-hand collections, and bank payout disbursements."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Delivery Fleet' => route('admin.delivery_partners.index'), 'Wallets & Settlements' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                💰 ₹{{ number_format($metrics['total_balance'], 2) }} Pending Fleet Payouts
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <a 
                    href="{{ route('admin.settings.index', ['tab' => 'rider_payout']) }}" 
                    class="px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 text-xs font-bold rounded-xl transition flex items-center space-x-1.5 border border-indigo-200 dark:border-indigo-800"
                    title="Configure Rider Base Pay, KM Rates &amp; Cash Limits"
                >
                    <span>⚙️</span>
                    <span>Configure Payout Rates</span>
                </a>
                <a 
                    href="{{ route('admin.delivery_partners.index') }}" 
                    class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl transition flex items-center space-x-1"
                >
                    <span>&larr; Fleet Directory</span>
                </a>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Metrics Cards Strip -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-5 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Active Wallets</div>
            <div class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">
                {{ number_format($metrics['total_wallets']) }}
            </div>
            <div class="text-[10px] text-slate-400">Riders with digital wallets</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-5 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Payable Balance</div>
            <div class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400">
                ₹{{ number_format($metrics['total_balance'], 2) }}
            </div>
            <div class="text-[10px] text-slate-400">Pending disbursement to riders</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-5 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Cash in Hand (COD)</div>
            <div class="text-xl sm:text-2xl font-black text-amber-500">
                ₹{{ number_format($metrics['total_cash_in_hand'], 2) }}
            </div>
            <div class="text-[10px] text-slate-400">Collected from COD deliveries</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-5 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Lifetime Payouts</div>
            <div class="text-xl sm:text-2xl font-black text-indigo-600 dark:text-indigo-400">
                ₹{{ number_format($metrics['total_lifetime_earned'], 2) }}
            </div>
            <div class="text-[10px] text-slate-400">Total earned by all riders</div>
        </div>
    </div>

    <!-- Wallets Table Card -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
        @if($wallets->isEmpty())
            <div class="p-12 text-center space-y-3">
                <div class="w-16 h-16 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center text-3xl">
                    👛
                </div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">No rider wallets created yet</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">Wallets are automatically provisioned when delivery partners join and complete trips.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-[11px] font-bold uppercase text-slate-400 tracking-wider">
                        <tr>
                            <th class="p-4">Rider Details</th>
                            <th class="p-4">Current Balance</th>
                            <th class="p-4">Lifetime Earned</th>
                            <th class="p-4">Cash In Hand (COD)</th>
                            <th class="p-4">Last Transaction</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        @foreach($wallets as $wallet)
                            @php $partner = $wallet->deliveryPartner; @endphp
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                                <td class="p-4">
                                    <div class="font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                                        <div class="w-7 h-7 rounded-full bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-400 font-black text-xs flex items-center justify-center shrink-0">
                                            {{ strtoupper(substr($partner?->name ?? 'R', 0, 1)) }}
                                        </div>
                                        <div>
                                            <span>{{ $partner?->name ?? 'Unknown Rider' }}</span>
                                            <div class="text-[10px] text-slate-400 font-mono">{{ $partner?->phone ?? 'No Phone' }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="p-4">
                                    <div class="font-bold text-sm text-emerald-600 dark:text-emerald-400 font-mono">
                                        ₹{{ number_format($wallet->balance, 2) }}
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-sans">Available for payout</span>
                                </td>

                                <td class="p-4 font-mono font-bold text-slate-800 dark:text-slate-200">
                                    ₹{{ number_format($wallet->total_earned, 2) }}
                                </td>

                                <td class="p-4 font-mono font-bold text-amber-600 dark:text-amber-400">
                                    ₹{{ number_format($partner?->current_cash_in_hand ?? 0, 2) }}
                                    @if(($partner?->current_cash_in_hand ?? 0) >= ($partner?->cash_in_hand_limit ?? 5000))
                                        <span class="block text-[9px] text-rose-500 font-sans font-semibold">Limit Reached</span>
                                    @endif
                                </td>

                                <td class="p-4 text-slate-500 font-mono text-[11px]">
                                    {{ $wallet->updated_at?->diffForHumans() ?? 'Never' }}
                                </td>

                                <td class="p-4 text-right">
                                    @if($partner)
                                        <button 
                                            type="button" 
                                            @click="openPayoutModal({{ json_encode([
                                                'id' => $partner->id,
                                                'name' => $partner->name,
                                                'balance' => (float) $wallet->balance,
                                                'cash_in_hand' => (float) $partner->current_cash_in_hand,
                                            ]) }})"
                                            class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-xs cursor-pointer inline-flex items-center space-x-1"
                                            @if($wallet->balance <= 0) disabled class="opacity-50 cursor-not-allowed px-3 py-1.5 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-400 text-xs font-bold" @endif
                                        >
                                            <span>💸 Process Payout</span>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $wallets->links() }}
            </div>
        @endif
    </div>

    <!-- Process Payout Modal -->
    <x-modal name="payout-modal" size="md">
        <x-slot name="header">
            <div class="flex items-center space-x-2">
                <span class="text-emerald-600 dark:text-emerald-400 text-lg">💸</span>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Process Rider Payout</h3>
            </div>
        </x-slot>

        <form id="payoutForm" :action="payoutAction" method="POST" class="space-y-4">
            @csrf

            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-1 text-xs">
                <div class="flex justify-between">
                    <span class="text-slate-500">Rider:</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200" x-text="activeRider.name"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Available Wallet Balance:</span>
                    <span class="font-bold text-emerald-600 dark:text-emerald-400" x-text="'₹' + Number(activeRider.balance).toFixed(2)"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Cash in Hand:</span>
                    <span class="font-bold text-amber-600" x-text="'₹' + Number(activeRider.cash_in_hand).toFixed(2)"></span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Payout Amount (₹) <span class="text-rose-500">*</span>
                </label>
                <input 
                    type="number" 
                    step="0.01" 
                    min="1" 
                    :max="activeRider.balance" 
                    name="amount" 
                    x-model="payoutAmount" 
                    required 
                    placeholder="Enter amount to disburse"
                    class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-indigo-500"
                />
                <div class="flex justify-between mt-1 text-[11px] text-slate-400">
                    <span>Min: ₹1.00</span>
                    <button type="button" @click="payoutAmount = activeRider.balance" class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">
                        Disburse Full Balance
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Payment Reference / UTR
                </label>
                <input 
                    type="text" 
                    name="reference" 
                    placeholder="e.g. UTR-20261116-8921"
                    class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono uppercase"
                />
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Admin Notes
                </label>
                <textarea 
                    name="notes" 
                    rows="2" 
                    placeholder="Optional notes for payout settlement record..."
                    class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                ></textarea>
            </div>
        </form>

        <x-slot name="footer">
            <button 
                type="button" 
                @click="$dispatch('close-modal', 'payout-modal')" 
                class="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 transition"
            >
                Cancel
            </button>
            <button 
                form="payoutForm"
                type="submit" 
                class="px-5 py-2 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition flex items-center space-x-1.5 cursor-pointer"
            >
                <span>Confirm Disbursement</span>
            </button>
        </x-slot>
    </x-modal>

</div>

<script>
    function riderSettlementsManager() {
        return {
            payoutAction: '',
            payoutAmount: '',
            activeRider: {
                id: null,
                name: '',
                balance: 0,
                cash_in_hand: 0
            },

            openPayoutModal(rider) {
                this.activeRider = rider;
                this.payoutAmount = rider.balance > 0 ? rider.balance : '';
                this.payoutAction = '{{ url("/admin/delivery-partners") }}/' + rider.id + '/payout';
                this.$dispatch('open-modal', 'payout-modal');
            }
        };
    }
</script>
@endsection
