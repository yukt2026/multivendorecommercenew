@extends('layouts.storefront')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">

    <!-- Breadcrumbs -->
    <nav class="flex items-center space-x-2 text-xs text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-indigo-600 font-medium">Home</a>
        <span class="text-slate-300">/</span>
        <a href="{{ route('campaigns.index') }}" class="hover:text-indigo-600 font-medium">Campaigns</a>
        <span class="text-slate-300">/</span>
        <span class="font-bold text-slate-800">{{ $campaign->name }}</span>
    </nav>

    <!-- Campaign Festival Hero Header -->
    <div class="p-6 sm:p-10 rounded-3xl bg-gradient-to-r from-indigo-950 via-purple-900 to-slate-900 text-white shadow-xl relative overflow-hidden">
        <div class="max-w-3xl space-y-3 relative z-10">
            <div class="flex items-center space-x-2">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950 shadow-sm">
                    ⚡ {{ $campaign->type_label }}
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-white/10 text-slate-200">
                    Live Deal Festival
                </span>
            </div>

            <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight">{{ $campaign->name }}</h1>
            <p class="text-xs sm:text-sm text-slate-300 max-w-2xl leading-relaxed">
                {{ $campaign->description ?: 'Shop hand-picked festival highlights, exclusive seller deals, and seasonal discounts available for a limited time.' }}
            </p>

            <div class="pt-2 flex items-center space-x-4 text-xs text-amber-300 font-mono">
                <span>⏱ Valid until: {{ $campaign->ends_at ? $campaign->ends_at->format('M d, Y h:i A') : 'Stock Lasts' }}</span>
            </div>
        </div>
    </div>

    <!-- Active Campaign Coupons Strip -->
    @if($coupons->isNotEmpty())
        <div class="p-5 rounded-2xl bg-amber-50 border border-amber-200" x-data="{ copiedCode: null }">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-800">Extra Promo Savings</span>
                    <h3 class="text-sm font-bold text-slate-900">Stack these coupons on top of campaign prices</h3>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                @foreach($coupons as $coupon)
                    <div class="p-3 rounded-xl bg-white border border-amber-200/80 shadow-2xs flex items-center justify-between">
                        <div>
                            <span class="font-mono font-black text-xs text-amber-700 tracking-wider">{{ $coupon->code }}</span>
                            <div class="text-[11px] font-bold text-slate-800">{{ $coupon->formatted_discount }} OFF</div>
                            <div class="text-[9px] text-slate-400">Min. ₹{{ number_format($coupon->min_order, 0) }}</div>
                        </div>
                        <button 
                            type="button" 
                            @click="navigator.clipboard.writeText('{{ $coupon->code }}'); copiedCode = '{{ $coupon->code }}'; setTimeout(() => copiedCode = null, 2000)"
                            class="px-2 py-1 text-[10px] font-bold rounded-lg transition"
                            :class="copiedCode === '{{ $coupon->code }}' ? 'bg-emerald-600 text-white' : 'bg-amber-100 hover:bg-amber-200 text-amber-900'"
                        >
                            <span x-text="copiedCode === '{{ $coupon->code }}' ? 'Copied' : 'Copy'"></span>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Campaign Deals Products -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg sm:text-xl font-bold text-slate-900">Featured Campaign Deals ({{ $products->total() }})</h2>
        </div>

        @if($products->isNotEmpty())
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 gap-3 sm:gap-6">
                @foreach($products as $prod)
                    <x-product-card :product="$prod" />
                @endforeach
            </div>

            <div class="pt-6">
                {{ $products->links() }}
            </div>
        @else
            <div class="p-10 text-center text-slate-500 rounded-2xl border border-slate-200 bg-white">
                No discounted products mapped to this campaign yet. Check out our <a href="{{ route('home') }}" class="text-indigo-600 font-bold hover:underline">homepage</a> for trending products!
            </div>
        @endif
    </div>

</div>
@endsection
