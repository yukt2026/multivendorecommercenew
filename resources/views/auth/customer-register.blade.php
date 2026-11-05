@extends('layouts.auth')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div>
        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
            Create an account
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Join OneStall for hyper-local quick deliveries and marketplace shopping.
        </p>
    </div>

    <!-- Registration Form -->
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <x-input 
            type="text" 
            name="name" 
            label="Full name" 
            :value="old('name')" 
            placeholder="John Doe" 
            required 
            autofocus 
        />

        <x-input 
            type="email" 
            name="email" 
            label="Email address" 
            :value="old('email')" 
            placeholder="you@example.com" 
            required 
        />

        <x-input 
            type="tel" 
            name="phone" 
            label="Mobile number (for delivery SMS/OTP)" 
            :value="old('phone')" 
            placeholder="+919876543210" 
            required 
        />

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-input 
                type="password" 
                name="password" 
                label="Password" 
                placeholder="At least 8 chars" 
                required 
            />

            <x-input 
                type="password" 
                name="password_confirmation" 
                label="Confirm password" 
                placeholder="Repeat password" 
                required 
            />
        </div>

        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-normal">
            By continuing, you agree to OneStall's Terms of Service and Privacy Policy.
        </p>

        <x-button type="submit" variant="primary" class="w-full py-2.5 shadow-md shadow-indigo-600/20">
            Create Account
        </x-button>
    </form>

    <!-- Footer Links -->
    <div class="pt-2 text-center">
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Already have an account? 
            <a href="{{ route('login') }}" class="font-bold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                Sign in here
            </a>
        </p>
    </div>
</div>
@endsection
