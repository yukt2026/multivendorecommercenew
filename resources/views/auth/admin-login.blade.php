@extends('layouts.auth')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div>
        <div class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-full bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs font-semibold mb-2">
            <span>🔒 Restricted Access: Authorized Personnel Only</span>
        </div>
        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
            Administration Portal
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Access OneStall multi-vendor operations, settlement ledgers, and logistics routing.
        </p>
    </div>

    @if (session('status'))
        <x-alert type="success" :message="session('status')" />
    @endif
    @if (session('info'))
        <x-alert type="info" :message="session('info')" />
    @endif

    <!-- High-Security Form -->
    <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-4">
        @csrf

        <x-input 
            type="email" 
            name="email" 
            label="Admin corporate email" 
            :value="old('email')" 
            placeholder="admin@onestall.in" 
            required 
            autofocus 
        />

        <div>
            <div class="flex items-center justify-between mb-1">
                <label for="password" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                    Security Key / Password
                </label>
                <a href="{{ route('password.request') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-900 dark:text-slate-400">
                    Reset
                </a>
            </div>
            <x-input 
                type="password" 
                name="password" 
                placeholder="••••••••••••" 
                required 
            />
        </div>

        <div class="flex items-center justify-between">
            <x-checkbox 
                name="remember" 
                label="Trust this workstation" 
                :checked="false" 
            />
        </div>

        <x-button type="submit" variant="primary" class="w-full py-2.5 bg-slate-900 hover:bg-slate-800 dark:bg-indigo-600 dark:hover:bg-indigo-500 shadow-lg shadow-slate-950/20 text-white font-bold">
            Verify & Access Console
        </x-button>
    </form>

    <!-- Security Warning Card -->
    <div class="p-3.5 rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
        <span class="font-bold text-slate-700 dark:text-slate-200">Notice:</span> All administrative access attempts, IP addresses, and user-agent signatures are cryptographically logged in the tamper-evident audit ledger. Unauthorized attempts are reported immediately.
    </div>
</div>
@endsection
