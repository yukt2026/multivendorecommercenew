@extends('layouts.storefront')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">

    <!-- Breadcrumbs -->
    <nav class="flex items-center space-x-2 text-xs text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-indigo-600 font-medium">Home</a>
        <span class="text-slate-300">/</span>
        <span class="font-bold text-slate-800">Festival & Marketing Campaigns</span>
    </nav>

    <!-- Header Hero Banner -->
    <div class="p-6 sm:p-10 rounded-3xl bg-gradient-to-r from-amber-600 via-rose-600 to-indigo-900 text-white shadow-xl relative overflow-hidden">
        <div class="max-w-3xl space-y-3 relative z-10">
            <span class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-white/20 text-white backdrop-blur-md">
                <span>🎉 ONESTALL FESTIVAL DISCOVERIES</span>
            </span>
            <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight">Active Festival & Mega Sale Campaigns</h1>
            <p class="text-xs sm:text-sm text-amber-100 max-w-xl">
                Explore high-value flash sales, seasonal events, and exclusive curated multi-vendor deals across verified local and national stores.
            </p>
        </div>
    </div>

    <!-- Active Campaigns Grid -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg sm:text-xl font-bold text-slate-900">Live Campaigns ({{ $campaigns->total() }})</h2>
        </div>

        @if($campaigns->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($campaigns as $camp)
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md transition flex flex-col justify-between space-y-4">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-100">
                                    {{ $camp->type_label }}
                                </span>
                                <span class="text-[11px] font-semibold text-emerald-600 flex items-center space-x-1">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span>Active Now</span>
                                </span>
                            </div>

                            <h3 class="text-lg font-bold text-slate-900 group-hover:text-indigo-600 transition">
                                {{ $camp->name }}
                            </h3>

                            <p class="text-xs text-slate-500 line-clamp-3 leading-relaxed">
                                {{ $camp->description ?: 'Exclusive seasonal discount festival featuring curated deals from top-rated sellers.' }}
                            </p>
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-[10px] text-slate-400 font-mono">
                                Ends {{ $camp->ends_at ? $camp->ends_at->format('M d, Y') : 'Ongoing' }}
                            </span>
                            <a href="{{ route('campaign.show', $camp->slug) }}" class="inline-flex items-center space-x-1 text-xs font-bold text-indigo-600 hover:text-indigo-700">
                                <span>Shop Campaign Deals</span>
                                <span>&rarr;</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="pt-4">
                {{ $campaigns->links() }}
            </div>
        @else
            <div class="p-8 text-center text-slate-500 rounded-2xl border border-slate-200 bg-white">
                No active campaigns at this moment. Please check back soon!
            </div>
        @endif
    </div>

    <!-- Active Coupons Strip -->
    @if($coupons->isNotEmpty())
        <div class="p-6 rounded-2xl bg-indigo-50/60 border border-indigo-100 space-y-4" x-data="{ copiedCode: null }">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-indigo-700">Platform Savings</span>
                    <h3 class="text-base font-black text-slate-900">Available Coupons to Use on All Campaigns</h3>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                @foreach($coupons as $coupon)
                    <div class="p-3.5 rounded-xl bg-white border border-indigo-200/80 shadow-2xs flex items-center justify-between">
                        <div>
                            <span class="font-mono font-black text-xs text-indigo-600 tracking-wider">{{ $coupon->code }}</span>
                            <div class="text-[11px] font-bold text-slate-800">{{ $coupon->formatted_discount }} OFF</div>
                            <div class="text-[10px] text-slate-400">Min. order ₹{{ number_format($coupon->min_order, 0) }}</div>
                        </div>
                        <button 
                            type="button" 
                            @click="navigator.clipboard.writeText('{{ $coupon->code }}'); copiedCode = '{{ $coupon->code }}'; setTimeout(() => copiedCode = null, 2000)"
                            class="px-2.5 py-1 text-[11px] font-bold rounded-lg transition"
                            :class="copiedCode === '{{ $coupon->code }}' ? 'bg-emerald-600 text-white' : 'bg-indigo-50 hover:bg-indigo-100 text-indigo-700'"
                        >
                            <span x-text="copiedCode === '{{ $coupon->code }}' ? 'Copied!' : 'Copy'"></span>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>
@endsection
