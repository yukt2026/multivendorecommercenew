@extends('layouts.auth')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div>
        <div class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs font-semibold mb-3">
            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
            <span>Account Status: Suspended</span>
        </div>
        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
            Seller Access Blocked
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Access for <strong class="text-slate-900 dark:text-white">{{ $seller->business_name ?? 'Your Business' }}</strong> has been temporarily suspended by OneStall Administration.
        </p>
    </div>

    <!-- Alert Card -->
    <div class="p-6 rounded-2xl bg-rose-50/60 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800/80 shadow-sm space-y-4">
        <div class="flex items-start space-x-3">
            <svg class="w-6 h-6 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div class="text-xs text-rose-900 dark:text-rose-200 leading-relaxed space-y-2">
                <p class="font-bold text-sm">Action Required on your Seller Account</p>
                <p>
                    Your merchant storefront and order fulfillment privileges have been suspended. This may be due to an SLA breach, KYC compliance audit, or pending settlement dispute.
                </p>
                <p>
                    While suspended, your products are hidden from customer search and quick commerce delivery dark stores.
                </p>
            </div>
        </div>

        <div class="pt-4 border-t border-rose-200/80 dark:border-rose-900 flex items-center justify-between">
            <span class="text-xs text-rose-800 dark:text-rose-300 font-semibold">OneStall Grievance Desk</span>
            <a href="mailto:grievance@onestall.in" class="px-3.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition">
                Submit Appeal &rarr;
            </a>
        </div>
    </div>

    <!-- Sign Out Button -->
    <div class="pt-2 text-center">
        <form method="POST" action="{{ route('seller.logout') }}">
            @csrf
            <button type="submit" class="text-xs font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white transition underline">
                Sign out of Seller Hub
            </button>
        </form>
    </div>
</div>
@endsection
