@extends('layouts.auth')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div>
        <div class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-full bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300 text-xs font-semibold mb-2">
            <span>🛡️ Partner Portal</span>
        </div>
        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
            Seller Hub Sign In
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Manage your stores, catalogue, live inventory, and fulfillment orders.
        </p>
    </div>

    @if (session('status'))
        <x-alert type="success" :message="session('status')" />
    @endif
    @if (session('info'))
        <x-alert type="info" :message="session('info')" />
    @endif

    <!-- Form -->
    <form method="POST" action="{{ route('seller.login') }}" class="space-y-4">
        @csrf

        <x-input 
            type="email" 
            name="email" 
            label="Merchant email address" 
            :value="old('email')" 
            placeholder="seller@business.in" 
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
                label="Keep me signed in to Seller Hub" 
                :checked="old('remember', true)" 
            />
        </div>

        <x-button type="submit" variant="primary" class="w-full py-2.5 shadow-md shadow-indigo-600/20">
            Sign In to Seller Panel
        </x-button>
    </form>

    <!-- Footer Links -->
    <div class="pt-4 text-center border-t border-slate-200 dark:border-slate-800 space-y-2">
        <p class="text-xs text-slate-500 dark:text-slate-400">
            New to OneStall Merchant network? 
            <a href="{{ route('seller.register') }}" class="font-bold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                Register as a Seller
            </a>
        </p>
        <p class="text-xs text-slate-400 dark:text-slate-500">
            Shopper account? 
            <a href="{{ route('login') }}" class="font-semibold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400">
                Go to customer login &rarr;
            </a>
        </p>
    </div>
</div>
@endsection
