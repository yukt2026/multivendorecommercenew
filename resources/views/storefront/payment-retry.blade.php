@extends('layouts.storefront')

@section('title', 'Payment Failed - OneStall.in')

@section('content')
<div class="min-h-screen bg-slate-50 py-10 sm:py-16">
    <div class="max-w-lg mx-auto px-4">
        
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xl space-y-6 text-center">
            
            <div class="w-16 h-16 mx-auto rounded-3xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center text-3xl shadow-sm">
                ⚠️
            </div>

            <div class="space-y-1">
                <span class="px-3 py-0.5 rounded-full text-[11px] font-black bg-rose-100 text-rose-800 uppercase tracking-wider">
                    Payment Incomplete
                </span>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Payment Was Not Completed</h1>
                <p class="text-xs text-slate-500 font-medium">Order: <strong class="text-slate-800">{{ $order->order_number }}</strong></p>
            </div>

            @if (session('error'))
                <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold text-left">
                    {{ session('error') }}
                </div>
            @endif

            <p class="text-xs text-slate-600">
                Your bank or payment gateway did not complete the transaction. Don't worry, your order items are safe. You can retry paying with UPI, Card, NetBanking or change payment mode.
            </p>

            <!-- Actions -->
            <div class="space-y-3 pt-2">
                <a href="{{ route('payment.pay', $order->order_number) }}"
                   class="w-full py-4 px-6 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-black text-sm flex items-center justify-center space-x-2 shadow-lg shadow-indigo-500/25 active:scale-95 transition">
                    <span>Retry Online Payment</span>
                </a>

                <a href="{{ route('checkout.index') }}"
                   class="w-full py-3 px-6 rounded-2xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs flex items-center justify-center transition">
                    Return to Checkout & Change Payment Method
                </a>
            </div>

            <div class="text-[11px] text-slate-400">
                If money was debited from your bank account, it will be automatically refunded within 3-5 business days.
            </div>

        </div>

    </div>
</div>
@endsection
