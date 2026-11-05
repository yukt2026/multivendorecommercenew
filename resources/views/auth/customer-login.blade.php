@extends('layouts.auth')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div>
        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
            Welcome back
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Sign in to track orders, manage your cart, and access fast checkout.
        </p>
    </div>

    @if (session('status'))
        <x-alert type="success" :message="session('status')" />
    @endif
    @if (session('info'))
        <x-alert type="info" :message="session('info')" />
    @endif

    <!-- Email & Password Form -->
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <x-input 
            type="email" 
            name="email" 
            label="Email address" 
            :value="old('email')" 
            placeholder="you@example.com" 
            required 
            autofocus 
        />

        <div>
            <div class="flex items-center justify-between mb-1">
                <label for="password" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                    Password
                </label>
                <a href="{{ route('password.request') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                    Forgot password?
                </a>
            </div>
            <x-input 
                type="password" 
                name="password" 
                placeholder="••••••••" 
                required 
            />
        </div>

        <div class="flex items-center justify-between">
            <x-checkbox 
                name="remember" 
                label="Remember me on this device" 
                :checked="old('remember', true)" 
            />
        </div>

        <x-button type="submit" variant="primary" class="w-full py-2.5 shadow-md shadow-indigo-600/20">
            Sign In with Password
        </x-button>
    </form>

    <!-- Alternative OTP Divider -->
    <div class="relative flex py-2 items-center">
        <div class="flex-grow border-t border-slate-200 dark:border-slate-800"></div>
        <span class="flex-shrink mx-4 text-xs font-semibold uppercase tracking-wider text-slate-400">Or continue with</span>
        <div class="flex-grow border-t border-slate-200 dark:border-slate-800"></div>
    </div>

    <!-- Phone OTP Login Button -->
    <a href="{{ route('login.otp') }}" class="w-full inline-flex items-center justify-center space-x-2 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 font-semibold text-sm hover:bg-slate-50 dark:hover:bg-slate-800/80 transition shadow-sm">
        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
        </svg>
        <span>Instant Sign In with Phone OTP</span>
    </a>

    <!-- Footer Switcher Links -->
    <div class="pt-2 text-center space-y-2">
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Don't have an account? 
            <a href="{{ route('register') }}" class="font-bold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                Create customer account
            </a>
        </p>
        <p class="text-xs text-slate-400 dark:text-slate-500">
            Selling on OneStall? 
            <a href="{{ route('seller.login') }}" class="font-semibold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400">
                Access Seller Hub &rarr;
            </a>
        </p>
    </div>
</div>
@endsection
