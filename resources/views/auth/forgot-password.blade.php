@extends('layouts.auth')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div>
        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
            Reset Password
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Enter your account email and we'll send you an authenticated password reset link.
        </p>
    </div>

    @if (session('status'))
        <x-alert type="success" :message="session('status')" />
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-input 
            type="email" 
            name="email" 
            label="Account email address" 
            :value="old('email')" 
            placeholder="you@example.com" 
            required 
            autofocus 
        />

        <x-button type="submit" variant="primary" class="w-full py-2.5 shadow-md shadow-indigo-600/20">
            Email Password Reset Link
        </x-button>
    </form>

    <div class="pt-2 text-center">
        <a href="{{ route('login') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
            &larr; Return to Sign In
        </a>
    </div>
</div>
@endsection
