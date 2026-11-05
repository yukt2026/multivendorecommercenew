@extends('layouts.auth')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div>
        <div class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 text-xs font-semibold mb-2">
            <span>🏪 OneStall Merchant Program</span>
        </div>
        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
            Register as a Seller
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Grow your business with physical store connect, quick commerce dispatch, and national logistics.
        </p>
    </div>

    <!-- Seller Register Form -->
    <form method="POST" action="{{ route('seller.register') }}" class="space-y-4">
        @csrf

        <x-input 
            type="text" 
            name="business_name" 
            label="Business / Trade name" 
            :value="old('business_name')" 
            placeholder="e.g. Bangalore Organic Supermart" 
            required 
            autofocus 
        />

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-input 
                type="text" 
                name="owner_name" 
                label="Owner / Contact name" 
                :value="old('owner_name')" 
                placeholder="Ramesh Sharma" 
                required 
            />

            <x-select 
                name="seller_type" 
                label="Seller / Store model" 
                :options="[
                    'local_store' => 'Local Retail Store (Quick Commerce)',
                    'brand' => 'Direct-to-Consumer Brand',
                    'marketplace' => 'Marketplace Vendor',
                    'multi_store' => 'Multi-Outlet Chain Store'
                ]" 
                :selected="old('seller_type', 'local_store')" 
                required 
            />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-input 
                type="email" 
                name="email" 
                label="Official business email" 
                :value="old('email')" 
                placeholder="seller@business.in" 
                required 
            />

            <x-input 
                type="tel" 
                name="phone" 
                label="Registered mobile phone" 
                :value="old('phone')" 
                placeholder="+919876543210" 
                required 
            />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-input 
                type="password" 
                name="password" 
                label="Account password" 
                placeholder="Min. 8 characters" 
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

        <div class="p-3.5 rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400">
            <span class="font-semibold text-slate-800 dark:text-slate-200">ℹ️ Note:</span> After submitting, your seller account will be placed under pending KYC onboarding review. You will be able to upload business documents, GSTIN, PAN, and bank details.
        </div>

        <x-button type="submit" variant="primary" class="w-full py-2.5 shadow-md shadow-indigo-600/20">
            Submit Seller Registration
        </x-button>
    </form>

    <!-- Footer Links -->
    <div class="pt-2 text-center space-y-2">
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Already registered as a seller? 
            <a href="{{ route('seller.login') }}" class="font-bold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                Log in to Seller Hub
            </a>
        </p>
        <p class="text-xs text-slate-400 dark:text-slate-500">
            Looking to buy instead? 
            <a href="{{ route('login') }}" class="font-semibold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400">
                Customer sign in &rarr;
            </a>
        </p>
    </div>
</div>
@endsection
