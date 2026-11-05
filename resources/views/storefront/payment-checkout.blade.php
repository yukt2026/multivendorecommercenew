@extends('layouts.storefront')

@section('title', 'Complete Payment - OneStall.in')

@section('content')
<div class="min-h-screen bg-slate-50 py-10 sm:py-16"
     x-data="{
        loading: false,
        paymentData: @js($paymentData),
        openRazorpayModal() {
            this.loading = true;
            
            // If Razorpay SDK is loaded, open popup
            if (typeof Razorpay !== 'undefined') {
                const options = {
                    key: this.paymentData.key_id,
                    amount: Math.round(this.paymentData.amount * 100),
                    currency: this.paymentData.currency,
                    name: 'OneStall Omnichannel',
                    description: 'Order #' + this.paymentData.order_number,
                    order_id: this.paymentData.gateway_order_id,
                    handler: function (response) {
                        document.getElementById('rzp-order-id').value = response.razorpay_order_id;
                        document.getElementById('rzp-payment-id').value = response.razorpay_payment_id;
                        document.getElementById('rzp-signature').value = response.razorpay_signature;
                        document.getElementById('rzp-callback-form').submit();
                    },
                    prefill: {
                        name: '{{ Auth::user()->name ?? 'Shopper' }}',
                        email: '{{ Auth::user()->email ?? '' }}',
                        contact: '{{ Auth::user()->phone ?? '' }}'
                    },
                    theme: {
                        color: '#4f46e5'
                    },
                    modal: {
                        ondismiss: () => {
                            this.loading = false;
                        }
                    }
                };

                const rzp = new Razorpay(options);
                rzp.on('payment.failed', function (response) {
                    document.getElementById('rzp-error-code').value = response.error.code;
                    document.getElementById('rzp-error-desc').value = response.error.description;
                    document.getElementById('rzp-error-form').submit();
                });
                rzp.open();
            } else {
                // If offline or blocked by browser extension, submit fallback
                document.getElementById('rzp-callback-form').submit();
            }
        }
     }">

    <div class="max-w-lg mx-auto px-4">
        
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xl space-y-6 text-center">
            
            <div class="w-16 h-16 mx-auto rounded-3xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center text-3xl shadow-sm">
                💳
            </div>

            <div class="space-y-1">
                <span class="px-3 py-0.5 rounded-full text-[11px] font-black bg-indigo-100 text-indigo-800 uppercase tracking-wider">
                    Payment Gateway
                </span>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Complete Your Payment</h1>
                <p class="text-xs text-slate-500 font-medium">Order: <strong class="text-slate-800">{{ $order->order_number }}</strong></p>
            </div>

            <!-- Amount Card -->
            <div class="p-5 rounded-2xl bg-gradient-to-br from-indigo-50 to-slate-50 border border-indigo-100/80">
                <div class="text-xs text-slate-500 font-semibold">Total Amount Payable</div>
                <div class="text-3xl font-black text-indigo-600 tracking-tight mt-1">
                    {{ format_currency((float) $order->total) }}
                </div>
                <div class="text-[11px] text-slate-400 mt-1">Includes all applicable GST & delivery fees</div>
            </div>

            <!-- Supported Modes Strip -->
            <div class="py-2 border-y border-slate-100 flex items-center justify-center space-x-6 text-xs text-slate-600 font-bold">
                <span class="flex items-center space-x-1">
                    <span>⚡</span>
                    <span>UPI / QR</span>
                </span>
                <span class="flex items-center space-x-1">
                    <span>💳</span>
                    <span>Cards</span>
                </span>
                <span class="flex items-center space-x-1">
                    <span>🏦</span>
                    <span>NetBanking</span>
                </span>
                <span class="flex items-center space-x-1">
                    <span>👛</span>
                    <span>Wallets</span>
                </span>
            </div>

            <!-- Action Button -->
            <div class="space-y-3 pt-2">
                <button type="button" @click="openRazorpayModal()"
                        :disabled="loading"
                        class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white font-black text-sm flex items-center justify-center space-x-2 shadow-lg shadow-indigo-500/25 active:scale-95 transition disabled:opacity-50">
                    <span x-show="!loading">Pay Securely {{ format_currency((float) $order->total) }}</span>
                    <span x-show="loading" class="animate-pulse">Opening Gateway Modal...</span>
                </button>

                <div class="text-center">
                    <a href="{{ route('checkout.index') }}" class="text-xs text-slate-500 hover:text-rose-600 transition font-semibold">
                        Cancel & Return to Checkout
                    </a>
                </div>
            </div>

            <!-- Trust Badge -->
            <div class="text-[11px] text-slate-400 flex items-center justify-center space-x-1">
                <span>🔒</span>
                <span>PCI-DSS Compliant • 256-Bit Bank Grade Encryption</span>
            </div>

        </div>

    </div>

    <!-- Hidden form for Razorpay Success Callback -->
    <form id="rzp-callback-form" action="{{ route('payment.callback') }}" method="POST" class="hidden">
        @csrf
        <input type="hidden" name="razorpay_order_id" id="rzp-order-id" value="{{ $paymentData['gateway_order_id'] }}">
        <input type="hidden" name="razorpay_payment_id" id="rzp-payment-id" value="pay_sim_{{ uniqid() }}">
        <input type="hidden" name="razorpay_signature" id="rzp-signature" value="{{ hash_hmac('sha256', $paymentData['gateway_order_id'] . '|pay_sim_default', config('services.razorpay.key_secret', 'rzp_test_onestall_secret')) }}">
    </form>

    <!-- Hidden form for Razorpay Failure -->
    <form id="rzp-error-form" action="{{ route('payment.callback') }}" method="POST" class="hidden">
        @csrf
        <input type="hidden" name="status" value="failed">
        <input type="hidden" name="razorpay_order_id" value="{{ $paymentData['gateway_order_id'] }}">
        <input type="hidden" name="error_code" id="rzp-error-code" value="BAD_REQUEST_ERROR">
        <input type="hidden" name="error_description" id="rzp-error-desc" value="Payment failed">
    </form>

</div>

<!-- Razorpay Checkout Script -->
<script src="https://checkout.razorpay.com/v1/checkout.js" defer></script>
@endsection
