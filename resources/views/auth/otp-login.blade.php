@extends('layouts.auth')

@section('content')
<div class="space-y-6" x-data="{
    otpSent: {{ session('otp_sent') ? 'true' : 'false' }},
    countdown: 60,
    timer: null,
    startTimer() {
        this.countdown = 60;
        clearInterval(this.timer);
        this.timer = setInterval(() => {
            if (this.countdown > 0) {
                this.countdown--;
            } else {
                clearInterval(this.timer);
            }
        }, 1000);
    },
    init() {
        if (this.otpSent) {
            this.startTimer();
        }
    }
}">
    <!-- Header -->
    <div>
        <div class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 text-xs font-semibold mb-2">
            <span>⚡ Instant One-Time Passcode</span>
        </div>
        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
            Phone OTP Sign In
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Sign in without a password using a secure 6-digit verification code.
        </p>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif
    @if ($errors->any())
        <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Step 1: Send OTP -->
    <template x-if="!otpSent">
        <form method="POST" action="{{ route('login.otp.send') }}" class="space-y-4">
            @csrf

            <x-input 
                type="tel" 
                name="phone" 
                label="Mobile phone number" 
                :value="old('phone', session('phone', ''))" 
                placeholder="+919876543210" 
                required 
                autofocus 
            />

            <x-button type="submit" variant="primary" class="w-full py-2.5 shadow-md shadow-indigo-600/20">
                Send 6-Digit Code
            </x-button>
        </form>
    </template>

    <!-- Step 2: Verify OTP -->
    <template x-if="otpSent">
        <form method="POST" action="{{ route('login.otp.verify') }}" class="space-y-4">
            @csrf

            <input type="hidden" name="phone" value="{{ session('phone', old('phone', '')) }}">

            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs">
                <div>
                    <span class="text-slate-500 dark:text-slate-400">Code sent to:</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200 ml-1">{{ session('phone') }}</span>
                </div>
                <button type="button" @click="otpSent = false" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                    Change
                </button>
            </div>

            <x-input 
                type="text" 
                name="otp" 
                label="Enter 6-digit OTP" 
                placeholder="123456" 
                maxlength="6" 
                required 
                autofocus 
            />

            <div class="flex items-center justify-between text-xs">
                <span class="text-slate-500 dark:text-slate-400">Valid for 5 minutes</span>
                <span x-show="countdown > 0" class="text-slate-400 font-mono">
                    Resend in <span x-text="countdown"></span>s
                </span>
                <button 
                    type="button" 
                    x-show="countdown === 0" 
                    @click="otpSent = false" 
                    class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline"
                >
                    Resend Code
                </button>
            </div>

            <x-button type="submit" variant="primary" class="w-full py-2.5 shadow-md shadow-indigo-600/20">
                Verify & Sign In
            </x-button>
        </form>
    </template>

    <!-- Footer Switcher Links -->
    <div class="pt-4 text-center border-t border-slate-200 dark:border-slate-800 space-y-2">
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Prefer using a password? 
            <a href="{{ route('login') }}" class="font-bold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                Sign in with Password
            </a>
        </p>
        <p class="text-xs text-slate-400 dark:text-slate-500">
            New customer? 
            <a href="{{ route('register') }}" class="font-semibold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400">
                Create new account &rarr;
            </a>
        </p>
    </div>
</div>
@endsection
