@extends('layouts.auth')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div>
        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
            Set New Password
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Choose a strong password with letters, numbers, and symbols.
        </p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <x-input 
            type="email" 
            name="email" 
            label="Email address" 
            :value="old('email', $email)" 
            required 
            autofocus 
        />

        <x-input 
            type="password" 
            name="password" 
            label="New password" 
            placeholder="Min. 8 characters" 
            required 
        />

        <x-input 
            type="password" 
            name="password_confirmation" 
            label="Confirm new password" 
            placeholder="Repeat password" 
            required 
        />

        <x-button type="submit" variant="primary" class="w-full py-2.5 shadow-md shadow-indigo-600/20">
            Reset & Update Password
        </x-button>
    </form>
</div>
@endsection
