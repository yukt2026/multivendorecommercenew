@extends('layouts.dashboard', ['portal' => 'admin', 'title' => $seller->business_name . ' - Merchant Control'])

@section('content')
<div class="space-y-6" x-data="sellerDetail('{{ $activeTab }}')">
    <!-- Top Header Breadcrumb & Profile Bar -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-2">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 dark:text-slate-400 mb-1.5">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-indigo-600 transition">Command Central</a>
                <span>&bull;</span>
                <a href="{{ route('admin.sellers.index') }}" class="hover:text-indigo-600 transition">Sellers</a>
                <span>&bull;</span>
                <span class="font-bold text-slate-700 dark:text-slate-300">{{ $seller->business_name }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    {{ $seller->business_name }}
                </h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $seller->status->badgeClass() }}">
                    {{ $seller->status->label() }}
                </span>
                <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                    {{ $seller->seller_type->label() }}
                </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Legal Name: <strong class="text-slate-700 dark:text-slate-200">{{ $seller->legal_name }}</strong> &bull; Merchant ID: <span class="font-mono font-bold">#{{ $seller->id }}</span>
            </p>
        </div>

        <!-- Quick Top Action Buttons based on status -->
        <div class="flex flex-wrap items-center gap-2">
            @can('approve', $seller)
                @if($seller->status === \App\Enums\SellerStatus::PENDING || $seller->status === \App\Enums\SellerStatus::KYC_SUBMITTED || $seller->status === \App\Enums\SellerStatus::REJECTED)
                    <form method="POST" action="{{ route('admin.sellers.approve', $seller) }}">
                        @csrf
                        <button 
                            type="submit" 
                            onclick="return confirm('Approve this merchant account? This will initialize their default storefront, assign seller_owner role, and send an approval email.')"
                            class="px-4 py-2 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition-all flex items-center"
                        >
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            Approve Merchant KYC
                        </button>
                    </form>

                    <button 
                        type="button" 
                        @click="$dispatch('open-modal', 'reject-seller-modal')"
                        class="px-3.5 py-2 text-xs font-bold rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 hover:bg-rose-100 border border-rose-200 dark:border-rose-800 transition-all flex items-center"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Reject Application
                    </button>
                @endif
            @endcan

            @can('suspend', $seller)
                @if($seller->status === \App\Enums\SellerStatus::APPROVED)
                    <button 
                        type="button" 
                        @click="$dispatch('open-modal', 'suspend-seller-modal')"
                        class="px-3.5 py-2 text-xs font-bold rounded-xl bg-rose-600 hover:bg-rose-700 text-white shadow-xs transition-all flex items-center"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        Suspend Merchant
                    </button>
                @elseif($seller->status === \App\Enums\SellerStatus::SUSPENDED)
                    <form method="POST" action="{{ route('admin.sellers.reactivate', $seller) }}">
                        @csrf
                        <button 
                            type="submit" 
                            onclick="return confirm('Reactivate this merchant? Their public storefronts and catalogue will be restored.')"
                            class="px-4 py-2 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition-all flex items-center"
                        >
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Reactivate Account
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    <!-- Rejection or Suspension Warning Callouts -->
    @if($seller->status === \App\Enums\SellerStatus::REJECTED && $seller->rejection_reason)
        <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-xs text-rose-900 dark:text-rose-200 flex items-start space-x-3">
            <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <strong class="font-bold">Application Rejected:</strong> {{ $seller->rejection_reason }}
            </div>
        </div>
    @elseif($seller->status === \App\Enums\SellerStatus::SUSPENDED && $seller->suspension_reason)
        <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 text-xs text-amber-900 dark:text-amber-200 flex items-start space-x-3">
            <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <strong class="font-bold">Account Suspended:</strong> {{ $seller->suspension_reason }} (Storefronts and product listings are hidden from marketplace buyers).
            </div>
        </div>
    @endif

    <!-- 9 Navigation Tabs Bar -->
    <div class="border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 rounded-2xl p-1.5 shadow-xs">
        <div class="overflow-x-auto scrollbar-thin scrollbar-thumb-slate-200 dark:scrollbar-thumb-slate-700">
            <nav class="flex space-x-1 sm:space-x-1.5 min-w-max p-1" aria-label="Seller detail navigation tabs">
                @php
                    $tabList = [
                        'overview' => [
                            'label' => 'Overview', 
                            'badge' => null,
                            'svg' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>'
                        ],
                        'kyc' => [
                            'label' => 'Business & KYC', 
                            'badge' => null,
                            'svg' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>'
                        ],
                        'documents' => [
                            'label' => 'Documents', 
                            'badge' => $seller->documents->count(),
                            'svg' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
                        ],
                        'bank' => [
                            'label' => 'Bank Details', 
                            'badge' => null,
                            'svg' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>'
                        ],
                        'stores' => [
                            'label' => 'Stores', 
                            'badge' => $seller->stores->count(),
                            'svg' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>'
                        ],
                        'products' => [
                            'label' => 'Products', 
                            'badge' => $seller->products()->count(),
                            'svg' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>'
                        ],
                        'orders' => [
                            'label' => 'Orders', 
                            'badge' => $seller->fulfilmentOrders()->count(),
                            'svg' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>'
                        ],
                        'commission' => [
                            'label' => 'Commission Override', 
                            'badge' => null,
                            'svg' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>'
                        ],
                        'activity' => [
                            'label' => 'Activity Log', 
                            'badge' => $activityLogs->count(),
                            'svg' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
                        ],
                    ];
                @endphp

                @foreach($tabList as $key => $tab)
                    <button 
                        type="button" 
                        id="tab-btn-{{ $key }}"
                        @click="setTab('{{ $key }}')"
                        :class="activeTab === '{{ $key }}' 
                            ? 'bg-indigo-600 text-white shadow-xs font-bold' 
                            : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 font-medium'"
                        class="px-3.5 py-2 rounded-xl text-xs transition-all flex items-center space-x-1.5 shrink-0 focus:outline-hidden"
                    >
                        <span class="shrink-0" :class="activeTab === '{{ $key }}' ? 'text-white' : 'text-slate-400 dark:text-slate-500'">
                            {!! $tab['svg'] !!}
                        </span>
                        <span>{{ $tab['label'] }}</span>
                        @if($tab['badge'] !== null)
                            <span 
                                :class="activeTab === '{{ $key }}' 
                                    ? 'bg-indigo-700/80 text-white border-transparent' 
                                    : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                                class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-bold border transition-colors ml-1"
                            >
                                {{ $tab['badge'] }}
                            </span>
                        @endif
                    </button>
                @endforeach
            </nav>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB 1: OVERVIEW -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'overview'" class="space-y-6">
        <!-- 5 Key Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <x-stat-card 
                label="Total GMV" 
                :value="'₹' . number_format($stats['total_gmv'], 2)" 
                change="Fulfilment Sales" 
                changeType="neutral" 
                color="primary"
            />
            <x-stat-card 
                label="Total Orders" 
                :value="number_format($stats['total_orders'])" 
                change="Dispatches" 
                changeType="up" 
                color="emerald"
            />
            <x-stat-card 
                label="Connected Stores" 
                :value="number_format($stats['stores_count'])" 
                change="Outlets" 
                changeType="neutral" 
                color="amber"
            />
            <x-stat-card 
                label="Catalogue Items" 
                :value="number_format($stats['products_count'])" 
                change="Active SKUs" 
                changeType="neutral" 
                color="sky"
            />
            <x-stat-card 
                label="Pending Payout" 
                :value="'₹' . number_format($stats['pending_payout'], 2)" 
                change="Settlement Due" 
                changeType="neutral" 
                color="rose"
            />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Merchant Profile Summary -->
            <div class="lg:col-span-2">
                <x-card title="Merchant Summary & Owner Details" subtitle="Primary account holder credentials and contact information">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700">
                            <span class="text-slate-400 font-medium block">Owner Account</span>
                            <span class="font-bold text-slate-900 dark:text-white text-sm mt-0.5 block">{{ $seller->user?->name ?? 'N/A' }}</span>
                            <span class="text-slate-500 font-mono mt-0.5 block">{{ $seller->user?->email }}</span>
                            <span class="text-slate-500 font-mono mt-0.5 block">{{ $seller->user?->phone ?? 'No phone' }}</span>
                        </div>

                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700">
                            <span class="text-slate-400 font-medium block">Commission Rate</span>
                            <span class="font-bold text-slate-900 dark:text-white text-sm mt-0.5 block">
                                {{ $seller->commission_override !== null ? $seller->commission_override . '% (Custom Override)' : setting('default_commission_pct', 10) . '% (Platform Default)' }}
                            </span>
                            <span class="text-slate-500 mt-1 block">Effective platform take on fulfilled merchandise.</span>
                        </div>

                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700">
                            <span class="text-slate-400 font-medium block">Taxation & Legal Identity</span>
                            <span class="font-mono font-bold text-slate-900 dark:text-white mt-1 block">PAN: {{ $seller->pan ?? 'Not provided' }}</span>
                            <span class="font-mono font-bold text-slate-900 dark:text-white mt-0.5 block">GSTIN: {{ $seller->gstin ?? 'Not provided' }}</span>
                        </div>

                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700">
                            <span class="text-slate-400 font-medium block">Audit & Timestamps</span>
                            <span class="text-slate-700 dark:text-slate-300 mt-1 block">Registered: {{ $seller->created_at->format('M d, Y h:i A') }}</span>
                            <span class="text-slate-700 dark:text-slate-300 mt-0.5 block">
                                Approved: {{ $seller->approved_at ? $seller->approved_at->format('M d, Y h:i A') : 'Pending verification' }}
                            </span>
                        </div>
                    </div>
                </x-card>
            </div>

            <!-- Stores Mini Card -->
            <div>
                <x-card title="Digital Storefronts" subtitle="Stores owned by this merchant">
                    <div class="space-y-3">
                        @forelse($seller->stores as $store)
                            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 text-xs space-y-2">
                                <div class="flex items-center justify-between">
                                    <a href="{{ route('admin.stores.show', $store) }}" class="font-bold text-slate-900 dark:text-white hover:text-indigo-600 transition">
                                        {{ $store->name }}
                                    </a>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300">
                                        {{ $store->status->label() }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-[11px]">
                                    <a href="{{ route('store.show', $store->slug) }}" target="_blank" class="text-indigo-600 dark:text-indigo-400 font-mono hover:underline">
                                        /store/{{ $store->slug }} ↗
                                    </a>
                                    <a href="{{ route('admin.stores.show', $store) }}" class="font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700">
                                        Manage &rarr;
                                    </a>
                                </div>
                                <div class="flex items-center space-x-2 text-[10px] pt-1">
                                    @if($store->accepts_quick_commerce)
                                        <span class="px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 font-bold">10-30m Quick</span>
                                    @endif
                                    @if($store->accepts_pickup)
                                        <span class="px-1.5 py-0.5 rounded bg-sky-100 dark:bg-sky-950/60 text-sky-800 dark:text-sky-300 font-bold">Store Pickup</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-xs text-slate-400 text-center py-4">No storefronts initialized yet. Approving will create a default store.</div>
                        @endforelse
                    </div>
                </x-card>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB 2: BUSINESS & KYC -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'kyc'" class="space-y-6">
        <x-card title="Business Registration & Compliance Verification" subtitle="Statutory tax credentials, legal entity details, and audit history">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500 font-medium">Commercial Trade Name</span>
                        <strong class="text-slate-900 dark:text-white">{{ $seller->business_name }}</strong>
                    </div>
                    <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500 font-medium">Registered Legal Entity Name</span>
                        <strong class="text-slate-900 dark:text-white">{{ $seller->legal_name }}</strong>
                    </div>
                    <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500 font-medium">Operating Model</span>
                        <strong class="text-slate-900 dark:text-white">{{ $seller->seller_type->label() }}</strong>
                    </div>
                    <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500 font-medium">Permanent Account Number (PAN)</span>
                        <strong class="font-mono text-slate-900 dark:text-white">{{ $seller->pan ?? 'N/A' }}</strong>
                    </div>
                    <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500 font-medium">Goods & Services Tax ID (GSTIN)</span>
                        <strong class="font-mono text-slate-900 dark:text-white">{{ $seller->gstin ?? 'N/A' }}</strong>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500 font-medium">KYC Verification Status</span>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $seller->status->badgeClass() }}">
                            {{ $seller->status->label() }}
                        </span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500 font-medium">Registered / Applied Date</span>
                        <strong class="text-slate-900 dark:text-white">{{ $seller->created_at->format('Y-m-d H:i') }}</strong>
                    </div>
                    <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500 font-medium">Approved Date</span>
                        <strong class="text-slate-900 dark:text-white">{{ $seller->approved_at ? $seller->approved_at->format('Y-m-d H:i') : 'Pending Review' }}</strong>
                    </div>
                    <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500 font-medium">Approved By Administrator</span>
                        <strong class="text-slate-900 dark:text-white">{{ $seller->approver?->name ?? 'N/A' }}</strong>
                    </div>
                </div>
            </div>
        </x-card>
    </div>

    <!-- ========================================== -->
    <!-- TAB 3: DOCUMENTS -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'documents'" class="space-y-6">
        <x-card title="Uploaded KYC Verification Proofs" subtitle="Review statutory identity, PAN, GST, FSSAI licenses, and cancelled cheques">
            <div class="overflow-x-auto -mx-6">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 uppercase font-semibold">
                            <th class="py-2.5 px-6">Document Type</th>
                            <th class="py-2.5 px-4">File Name / Path</th>
                            <th class="py-2.5 px-4 text-center">Status</th>
                            <th class="py-2.5 px-4">Review Remarks</th>
                            <th class="py-2.5 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($seller->documents as $doc)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-850/50">
                                <td class="py-3 px-6 font-bold text-slate-900 dark:text-white">
                                    {{ $doc->type->label() }}
                                    <div class="text-[10px] font-normal text-slate-400">Uploaded {{ $doc->created_at->format('M d, Y') }}</div>
                                </td>
                                <td class="py-3 px-4 font-mono text-[11px] text-slate-500">
                                    {{ basename($doc->file_path) }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $doc->status->badgeClass() }}">
                                        {{ $doc->status->label() }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-600 dark:text-slate-400 text-xs">
                                    {{ $doc->remarks ?? 'None recorded' }}
                                </td>
                                <td class="py-3 px-6 text-right">
                                    <div class="inline-flex items-center space-x-2">
                                        <!-- View in modal trigger -->
                                        <button 
                                            type="button" 
                                            @click="openDocViewer('{{ $doc->type->label() }}', '{{ route('admin.sellers.documents.download', [$seller, $doc]) }}')"
                                            class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 transition"
                                        >
                                            View
                                        </button>
                                        <a 
                                            href="{{ route('admin.sellers.documents.download', [$seller, $doc]) }}" 
                                            target="_blank"
                                            class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 border border-indigo-200 dark:border-indigo-800 transition"
                                        >
                                            Download
                                        </a>

                                        @can('approve', $seller)
                                            @if($doc->status !== \App\Enums\SellerDocumentStatus::APPROVED)
                                                <form method="POST" action="{{ route('admin.sellers.documents.review', [$seller, $doc]) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="status" value="approved">
                                                    <input type="hidden" name="remarks" value="Verified and approved by admin.">
                                                    <button type="submit" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition">
                                                        Approve
                                                    </button>
                                                </form>
                                            @endif

                                            @if($doc->status !== \App\Enums\SellerDocumentStatus::REJECTED)
                                                <button 
                                                    type="button" 
                                                    @click="openDocRejectModal({{ $doc->id }}, '{{ $doc->type->label() }}')"
                                                    class="px-2.5 py-1 text-xs font-bold rounded-lg bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 transition"
                                                >
                                                    Reject
                                                </button>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400">
                                    No KYC documents uploaded by this merchant yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    <!-- ========================================== -->
    <!-- TAB 4: BANK DETAILS -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'bank'" class="space-y-6">
        <x-card title="Disbursement Bank Accounts" subtitle="Direct bank payout accounts for automated weekly settlements">
            <div class="space-y-4">
                @forelse($seller->bankAccounts as $bank)
                    <div class="p-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="space-y-1.5 text-xs">
                            <div class="flex items-center space-x-2">
                                <span class="text-base font-bold text-slate-900 dark:text-white">{{ $bank->bank_name }}</span>
                                @if($bank->is_primary)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        Primary Account
                                    </span>
                                @endif
                                @if($bank->verified_at)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Verified for Payouts
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        Pending Verification
                                    </span>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 pt-1 text-slate-600 dark:text-slate-400">
                                <div>Account Holder: <strong class="text-slate-900 dark:text-white">{{ $bank->account_holder }}</strong></div>
                                <div>IFSC Code: <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $bank->ifsc }}</span></div>
                                @php
                                    $rawAcc = (string) $bank->account_no;
                                    $masked = strlen($rawAcc) > 4 ? str_repeat('•', strlen($rawAcc) - 4) . ' ' . substr($rawAcc, -4) : $rawAcc;
                                @endphp
                                <div>Masked Account No: <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $masked }}</span></div>
                                <div>Branch: {{ $bank->branch_name ?? 'Main Branch' }}</div>
                            </div>
                        </div>

                        <!-- Verification Trigger Button -->
                        @can('verifyBank', $seller)
                            <div class="shrink-0">
                                <form method="POST" action="{{ route('admin.sellers.bank.verify', [$seller, $bank]) }}">
                                    @csrf
                                    @if($bank->verified_at)
                                        <input type="hidden" name="verified" value="0">
                                        <button type="submit" class="px-3.5 py-1.5 text-xs font-semibold rounded-xl bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-700 dark:text-slate-200 transition">
                                            Revoke Verification
                                        </button>
                                    @else
                                        <input type="hidden" name="verified" value="1">
                                        <button type="submit" class="px-3.5 py-1.5 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition">
                                            Verify Bank Account
                                        </button>
                                    @endif
                                </form>
                            </div>
                        @endcan
                    </div>
                @empty
                    <div class="text-xs text-slate-400 text-center py-6">
                        No bank account details provided yet.
                    </div>
                @endforelse
            </div>
        </x-card>
    </div>

    <!-- ========================================== -->
    <!-- TAB 5: STORES -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'stores'" class="space-y-6">
        <x-card title="Digital Storefronts & Physical Outlets" subtitle="Store profiles, locations, delivery radius, and timings">
            <div class="space-y-4">
                @forelse($seller->stores as $store)
                    <div class="p-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div>
                                <div class="flex items-center space-x-2">
                                    <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $store->name }}</h3>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300">
                                        {{ $store->status->label() }}
                                    </span>
                                    @if($store->is_onestall_owned)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300">
                                            OneStall Direct
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-indigo-600 dark:text-indigo-400 font-mono mt-0.5">/store/{{ $store->slug }}</div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <a href="{{ route('admin.stores.show', $store) }}" class="px-3 py-1.5 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition flex items-center">
                                    <span>Store Console</span>
                                    <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                                <a href="{{ route('store.show', $store->slug) }}" target="_blank" class="px-3 py-1.5 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 transition flex items-center">
                                    <span>Storefront</span>
                                    <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            </div>
                        </div>

                        <!-- Locations -->
                        <div class="text-xs">
                            <span class="font-bold text-slate-700 dark:text-slate-300 block mb-1">Locations & Dispatch Coordinates:</span>
                            @foreach($store->locations as $loc)
                                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 flex justify-between items-center text-[11px]">
                                    <div>
                                        {{ $loc->address }}, {{ $loc->city }}, {{ $loc->state }} - {{ $loc->pincode }}
                                    </div>
                                    <div class="font-mono text-slate-400">
                                        GPS: {{ number_format($loc->lat, 4) }}, {{ number_format($loc->lng, 4) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="text-xs text-slate-400 text-center py-6">
                        No stores registered for this merchant.
                    </div>
                @endforelse
            </div>
        </x-card>
    </div>

    <!-- ========================================== -->
    <!-- TAB 6: PRODUCTS -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'products'" class="space-y-6">
        <x-card title="Product Catalogue Listings" subtitle="Products and SKU variants owned by this merchant">
            <div class="overflow-x-auto -mx-6">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 uppercase font-semibold">
                            <th class="py-2.5 px-6">Product Title</th>
                            <th class="py-2.5 px-4">Category</th>
                            <th class="py-2.5 px-4 text-center">Type</th>
                            <th class="py-2.5 px-4 text-center">Variants</th>
                            <th class="py-2.5 px-4 text-center">Status</th>
                            <th class="py-2.5 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($products as $product)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-850/50">
                                <td class="py-3 px-6 font-bold text-slate-900 dark:text-white">
                                    {{ $product->title }}
                                    <div class="text-[10px] font-normal text-slate-400">HSN: {{ $product->hsn_code ?? 'N/A' }}</div>
                                </td>
                                <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                                    {{ $product->category?->name ?? 'Uncategorized' }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-mono bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                        {{ ucfirst($product->product_type instanceof \BackedEnum ? $product->product_type->value : (string)($product->product_type ?? 'simple')) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-bold text-slate-700 dark:text-slate-300">
                                    {{ $product->variants->count() }} SKU(s)
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $product->status->badgeClass() }}">
                                        {{ $product->status->label() }}
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-right">
                                    <div class="inline-flex items-center space-x-2">
                                        <a href="{{ route('admin.catalogue.moderation.show', $product) }}" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 border border-indigo-200 dark:border-indigo-800 transition">
                                            Moderate
                                        </a>
                                        @if($product->slug)
                                            <a href="{{ route('product.show', $product->slug) }}" target="_blank" class="px-2 py-1 text-xs text-slate-500 hover:text-indigo-600 transition" title="Open Public Page">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400">
                                    No products found under this merchant account.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-100 dark:border-slate-800 pt-3 mt-4">
                <x-pagination :paginator="$products" />
            </div>
        </x-card>
    </div>

    <!-- ========================================== -->
    <!-- TAB 7: ORDERS -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'orders'" class="space-y-6">
        <x-card title="Fulfilment Orders Dispatches" subtitle="Orders routed to this merchant's inventory locations">
            <div class="overflow-x-auto -mx-6">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 uppercase font-semibold">
                            <th class="py-2.5 px-6">Fulfilment Order #</th>
                            <th class="py-2.5 px-4">Channel / Mode</th>
                            <th class="py-2.5 px-4 text-right">Subtotal</th>
                            <th class="py-2.5 px-4 text-right">Platform Commission</th>
                            <th class="py-2.5 px-4 text-right">Seller Payable</th>
                            <th class="py-2.5 px-4 text-center">Status</th>
                            <th class="py-2.5 px-4 text-right">Date</th>
                            <th class="py-2.5 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($orders as $order)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-850/50">
                                <td class="py-3 px-6 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                    <a href="{{ $order->order_id ? route('admin.orders.show', $order->order_id) : '#' }}" class="hover:underline">
                                        #{{ $order->id }}
                                    </a>
                                    <div class="text-[10px] text-slate-400 font-sans">
                                        @if($order->order_id)
                                            <a href="{{ route('admin.orders.show', $order->order_id) }}" class="hover:underline hover:text-indigo-600">
                                                Parent: {{ $order->order?->order_number ?? 'N/A' }}
                                            </a>
                                        @else
                                            Parent: {{ $order->order?->order_number ?? 'N/A' }}
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    @php
                                        $fType = $order->fulfilment_type instanceof \BackedEnum ? $order->fulfilment_type->value : (string) $order->fulfilment_type;
                                        $fLabel = $order->fulfilment_type instanceof \App\Enums\FulfilmentType ? $order->fulfilment_type->label() : ($fType === 'quick' ? 'Quick Commerce' : ($fType === 'pickup' ? 'Store Pickup' : 'Standard Delivery'));
                                    @endphp
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold {{ $fType === 'quick' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' : 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300' }}">
                                        {{ $fLabel }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 dark:text-white">
                                    ₹{{ number_format((float) $order->subtotal, 2) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-indigo-600 dark:text-indigo-400">
                                    ₹{{ number_format((float) $order->commission_amount, 2) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-extrabold text-emerald-600 dark:text-emerald-400">
                                    ₹{{ number_format((float) $order->seller_payable, 2) }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    @if(method_exists($order, 'statusBadgeClass'))
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $order->statusBadgeClass() }}">
                                            {{ $order->statusLabel() }}
                                        </span>
                                    @elseif(is_object($order->status) && method_exists($order->status, 'badgeClass'))
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $order->status->badgeClass() }}">
                                            {{ $order->status->label() }}
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700">
                                            {{ ucwords(str_replace('_', ' ', (string) $order->status)) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right text-slate-400 text-[11px]">
                                    {{ $order->created_at->format('M d, Y') }}
                                </td>
                                <td class="py-3 px-6 text-right">
                                    @if($order->order_id)
                                        <a href="{{ route('admin.orders.show', $order->order_id) }}" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 border border-indigo-200 dark:border-indigo-800 transition">
                                            View Order
                                        </a>
                                    @else
                                        <span class="text-slate-400 text-xs">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-slate-400">
                                    No fulfilment orders found for this merchant.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-100 dark:border-slate-800 pt-3 mt-4">
                <x-pagination :paginator="$orders" />
            </div>
        </x-card>
    </div>

    <!-- ========================================== -->
    <!-- TAB 8: COMMISSION OVERRIDE -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'commission'" class="space-y-6">
        <x-card title="Merchant Commission Agreement" subtitle="Configure custom platform rake override percentage for this seller">
            <div class="max-w-xl space-y-4">
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 text-xs text-slate-600 dark:text-slate-300">
                    <p class="font-bold text-slate-900 dark:text-white mb-1">Rule 2 Compliance Note:</p>
                    <p>Platform default commission is dynamically retrieved from platform settings (<strong class="text-indigo-600">{{ setting('default_commission_pct', 10) }}%</strong>). Setting an override here takes absolute precedence for all standard and quick commerce fulfilments for this merchant.</p>
                </div>

                <form method="POST" action="{{ route('admin.sellers.commission.update', $seller) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Commission Override Percentage (%)
                        </label>
                        <div class="relative max-w-xs">
                            <input 
                                type="number" 
                                step="0.01" 
                                min="0" 
                                max="100" 
                                name="commission_override" 
                                value="{{ old('commission_override', $seller->commission_override) }}"
                                placeholder="e.g. 8.50 (leave empty for platform default)"
                                class="w-full py-2.5 px-3.5 pr-8 text-xs font-mono font-bold rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                            />
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400 font-bold text-xs">%</div>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Leave empty or clear to revert to platform default.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Internal Reason / Remarks
                        </label>
                        <textarea 
                            name="remarks" 
                            rows="2" 
                            placeholder="e.g. Special brand agreement signed for FY2026..." 
                            class="w-full py-2 px-3 text-xs rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        ></textarea>
                    </div>

                    <div class="flex items-center space-x-3 pt-2">
                        <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition">
                            Save Commission Override
                        </button>
                    </div>
                </form>
            </div>
        </x-card>
    </div>

    <!-- ========================================== -->
    <!-- TAB 9: ACTIVITY AUDIT LOG -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'activity'" class="space-y-6">
        <x-card title="Merchant Audit Trail & Governance Log" subtitle="Comprehensive activity history with before/after state snapshots">
            <div class="space-y-4">
                @forelse($activityLogs as $activity)
                    <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 text-xs space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-900 dark:text-white">{{ $activity->description }}</span>
                            <span class="text-slate-400 text-[11px]">{{ $activity->created_at->diffForHumans() }} ({{ $activity->created_at->format('Y-m-d H:i') }})</span>
                        </div>
                        <div class="text-slate-500 text-[11px]">
                            Initiated by: <strong class="text-slate-700 dark:text-slate-300">{{ $activity->causer?->name ?? 'System Automated' }}</strong>
                        </div>

                        @if(!empty($activity->properties['old']) || !empty($activity->properties['new']))
                            <div class="p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 font-mono text-[10px] space-y-1">
                                @if(!empty($activity->properties['old']))
                                    <div class="text-rose-600 dark:text-rose-400">
                                        - OLD: {{ json_encode($activity->properties['old']) }}
                                    </div>
                                @endif
                                @if(!empty($activity->properties['new']))
                                    <div class="text-emerald-600 dark:text-emerald-400">
                                        + NEW: {{ json_encode($activity->properties['new']) }}
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-xs text-slate-400 text-center py-6">
                        No audit events logged for this merchant yet.
                    </div>
                @endforelse
            </div>
        </x-card>
    </div>

    <!-- ========================================== -->
    <!-- MODALS -->
    <!-- ========================================== -->

    <!-- Reject Seller Modal -->
    <x-modal name="reject-seller-modal" title="Reject Merchant Application" size="md">
        <form method="POST" action="{{ route('admin.sellers.reject', $seller) }}" class="space-y-4">
            @csrf
            <div>
                <p class="text-xs text-slate-500 mb-3 leading-relaxed">
                    Rejecting will mark this merchant as <strong>Rejected</strong> and notify them with your remarks. They can review the notes and re-upload proofs.
                </p>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Mandatory Rejection Remarks <span class="text-rose-500">*</span>
                </label>
                <textarea 
                    name="reason" 
                    rows="3" 
                    required 
                    minlength="5"
                    placeholder="Provide specific details (e.g. GST certificate expired, PAN name mismatch)..." 
                    class="w-full p-2.5 text-xs rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                ></textarea>
            </div>

            <div class="flex items-center justify-end space-x-2 pt-2">
                <button type="button" @click="$dispatch('close-modal', 'reject-seller-modal')" class="px-3 py-1.5 text-xs text-slate-600 hover:text-slate-900">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-rose-600 hover:bg-rose-700 text-white shadow-xs transition">
                    Confirm Rejection
                </button>
            </div>
        </form>
    </x-modal>

    <!-- Suspend Seller Modal -->
    <x-modal name="suspend-seller-modal" title="Suspend Merchant Account" size="md">
        <form method="POST" action="{{ route('admin.sellers.suspend', $seller) }}" class="space-y-4">
            @csrf
            <div>
                <p class="text-xs text-rose-600 dark:text-rose-400 mb-3 leading-relaxed font-semibold">
                    Warning: Suspending this merchant will automatically deactivate their public storefronts and hide all catalogue products from marketplace buyers.
                </p>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Mandatory Suspension Reason <span class="text-rose-500">*</span>
                </label>
                <textarea 
                    name="reason" 
                    rows="3" 
                    required 
                    minlength="5"
                    placeholder="Provide detailed compliance violation or operational reason..." 
                    class="w-full p-2.5 text-xs rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                ></textarea>
            </div>

            <div class="flex items-center justify-end space-x-2 pt-2">
                <button type="button" @click="$dispatch('close-modal', 'suspend-seller-modal')" class="px-3 py-1.5 text-xs text-slate-600 hover:text-slate-900">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-rose-600 hover:bg-rose-700 text-white shadow-xs transition">
                    Confirm Suspension
                </button>
            </div>
        </form>
    </x-modal>

    <!-- Document Viewer Modal -->
    <x-modal name="doc-viewer-modal" title="KYC Proof Viewer" size="lg">
        <div class="space-y-4">
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 flex items-center justify-between text-xs">
                <div>
                    <span class="font-bold text-slate-900 dark:text-white block" x-text="docViewerTitle"></span>
                    <span class="text-slate-400 font-mono text-[11px]" x-text="docViewerPath"></span>
                </div>
                <a :href="docViewerPath" target="_blank" class="px-3 py-1 text-xs font-bold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                    Open File
                </a>
            </div>

            <div class="w-full h-80 rounded-xl bg-slate-100 dark:bg-slate-950 flex flex-col items-center justify-center p-6 text-center text-slate-400 text-xs border border-dashed border-slate-300 dark:border-slate-800">
                <svg class="w-12 h-12 text-slate-300 dark:text-slate-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <p class="font-bold text-slate-700 dark:text-slate-300">Document Security Enclave</p>
                <p class="mt-1 max-w-sm">Stored on encrypted S3 / private local storage. Click "Open File" to inspect the high-resolution original scan.</p>
            </div>
        </div>
    </x-modal>

    <!-- Document Reject Modal -->
    <x-modal name="doc-reject-modal" title="Reject KYC Document" size="md">
        <form :action="'{{ url('/admin/sellers/' . $seller->id . '/documents') }}/' + docRejectId + '/review'" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="status" value="rejected">
            <div>
                <p class="text-xs text-slate-500 mb-3 leading-relaxed">
                    Rejecting document: <strong class="text-slate-800 dark:text-slate-200" x-text="docRejectTitle"></strong>
                </p>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Mandatory Document Rejection Remarks <span class="text-rose-500">*</span>
                </label>
                <textarea 
                    name="remarks" 
                    rows="3" 
                    required 
                    minlength="5"
                    placeholder="e.g. Scanned copy is illegible, please upload high-res color photo..." 
                    class="w-full p-2.5 text-xs rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                ></textarea>
            </div>

            <div class="flex items-center justify-end space-x-2 pt-2">
                <button type="button" @click="$dispatch('close-modal', 'doc-reject-modal')" class="px-3 py-1.5 text-xs text-slate-600 hover:text-slate-900">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-rose-600 hover:bg-rose-700 text-white shadow-xs transition">
                    Reject Document
                </button>
            </div>
        </form>
    </x-modal>
</div>

<script>
    function sellerDetail(initialTab) {
        return {
            activeTab: initialTab || 'overview',
            docViewerTitle: '',
            docViewerPath: '',
            docRejectId: null,
            docRejectTitle: '',

            init() {
                this.$nextTick(() => {
                    const el = document.getElementById('tab-btn-' + this.activeTab);
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', inline: 'nearest', block: 'nearest' });
                    }
                });

                window.addEventListener('popstate', () => {
                    const url = new URL(window.location);
                    const tab = url.searchParams.get('tab');
                    if (tab && tab !== this.activeTab) {
                        this.activeTab = tab;
                        this.$nextTick(() => {
                            document.getElementById('tab-btn-' + tab)?.scrollIntoView({ behavior: 'smooth', inline: 'nearest', block: 'nearest' });
                        });
                    }
                });
            },

            setTab(tab) {
                this.activeTab = tab;
                const url = new URL(window.location);
                url.searchParams.set('tab', tab);
                window.history.replaceState({}, '', url);
                this.$nextTick(() => {
                    const el = document.getElementById('tab-btn-' + tab);
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', inline: 'nearest', block: 'nearest' });
                    }
                });
            },

            openDocViewer(title, path) {
                this.docViewerTitle = title;
                this.docViewerPath = path;
                this.$dispatch('open-modal', 'doc-viewer-modal');
            },

            openDocRejectModal(id, title) {
                this.docRejectId = id;
                this.docRejectTitle = title;
                this.$dispatch('open-modal', 'doc-reject-modal');
            }
        };
    }
</script>
@endsection
