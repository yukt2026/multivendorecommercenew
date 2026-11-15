@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Coupons & Promotional Discounts'])

@section('content')
<div class="space-y-6" x-data="couponManager()">

    <!-- Page Header -->
    <x-page-header 
        title="Platform & Seller Coupons" 
        subtitle="Manage promotional discount codes, minimum basket thresholds, usage caps, and validity windows."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Coupons' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                {{ $metrics['total_coupons'] }} Codes
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <button 
                    type="button"
                    @click="openModal()" 
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm flex items-center space-x-1.5 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Create Coupon</span>
                </button>
                <a href="{{ route('admin.coupons.export') }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Export CSV</span>
                </a>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Metrics Strip -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Promotional Codes</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $metrics['total_coupons'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Active Live Codes</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $metrics['active_coupons'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Platform Wide</div>
            <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ $metrics['platform_coupons'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-purple-600 dark:text-purple-400">Seller Specific</div>
            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">{{ $metrics['seller_coupons'] }}</div>
        </div>
    </div>

    <!-- Filters Strip -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row gap-4 justify-between items-center">
        <div class="flex flex-wrap items-center gap-2">
            @php 
                $currentType = request('type', 'all');
                $currentScope = request('scope', 'all');
                $currentStatus = request('status', 'all');
            @endphp
            
            <a href="{{ request()->fullUrlWithQuery(['type' => 'all', 'scope' => 'all', 'status' => 'all', 'page' => 1]) }}" 
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $currentType === 'all' && $currentScope === 'all' && $currentStatus === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                All Codes
            </a>
            <a href="{{ request()->fullUrlWithQuery(['type' => 'percent', 'page' => 1]) }}" 
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $currentType === 'percent' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                Percentage (%)
            </a>
            <a href="{{ request()->fullUrlWithQuery(['type' => 'flat', 'page' => 1]) }}" 
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $currentType === 'flat' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                Flat Amount (₹)
            </a>
            <a href="{{ request()->fullUrlWithQuery(['scope' => 'platform', 'page' => 1]) }}" 
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $currentScope === 'platform' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                Platform Wide
            </a>
            <a href="{{ request()->fullUrlWithQuery(['scope' => 'seller', 'page' => 1]) }}" 
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $currentScope === 'seller' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                Seller Specific
            </a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'active', 'page' => 1]) }}" 
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $currentStatus === 'active' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                Active Only
            </a>
        </div>

        <form method="GET" action="{{ route('admin.coupons.index') }}" class="flex items-center space-x-2 w-full md:w-auto">
            @if(request('type'))
                <input type="hidden" name="type" value="{{ request('type') }}">
            @endif
            @if(request('scope'))
                <input type="hidden" name="scope" value="{{ request('scope') }}">
            @endif
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <div class="relative w-full md:w-64">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ request('search') }}" 
                    placeholder="Search coupon code..." 
                    class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white pl-8 pr-3 py-2 focus:ring-1 focus:ring-indigo-500"
                >
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <button type="submit" class="px-3 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold transition">
                Filter
            </button>
            @if(request('search') || (request('type') && request('type') !== 'all') || (request('scope') && request('scope') !== 'all') || (request('status') && request('status') !== 'all'))
                <a href="{{ route('admin.coupons.index') }}" class="px-2.5 py-2 text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                    Clear
                </a>
            @endif
        </form>
    </div>

    <!-- Coupons Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Coupon Code</th>
                        <th class="px-6 py-3.5">Discount Value</th>
                        <th class="px-6 py-3.5">Min Basket / Max Disc</th>
                        <th class="px-6 py-3.5">Scope</th>
                        <th class="px-6 py-3.5">Usage &amp; Limits</th>
                        <th class="px-6 py-3.5">Validity Window</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($coupons as $c)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4 font-mono font-bold text-slate-900 dark:text-white">
                            <span class="px-2.5 py-1 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 tracking-wider">
                                {{ $c->code }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-900 dark:text-white text-sm">
                                {{ $c->formatted_discount }}
                            </div>
                            <span class="text-[10px] font-medium text-slate-400 block uppercase tracking-wider">
                                {{ $c->type_label }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                            <div>Min: <span class="font-semibold text-slate-800 dark:text-slate-200">{{ format_currency((float)$c->min_order) }}</span></div>
                            <div class="text-[10px] text-slate-400">
                                Max Cap: {{ $c->max_discount ? format_currency((float)$c->max_discount) : 'Unlimited' }}
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @if($c->seller)
                                <div class="font-medium text-slate-800 dark:text-slate-200">{{ $c->seller->business_name }}</div>
                                <div class="text-[10px] text-slate-400">Seller #{{ $c->seller->id }}</div>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400">
                                    Platform Wide
                                </span>
                            @endif
                            @if(!empty($c->applies_to['categories']))
                                <div class="text-[10px] text-indigo-600 dark:text-indigo-400 mt-0.5">
                                    {{ count($c->applies_to['categories']) }} category restriction(s)
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300 font-mono text-[11px]">
                            <div>Redeemed: <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $c->usages_count ?? 0 }}</span> / {{ $c->usage_limit ?? '∞' }}</div>
                            <div class="text-[10px] text-slate-400">Per User: {{ $c->per_user_limit }}</div>
                        </td>
                        <td class="px-6 py-4 text-slate-500 font-mono text-[11px]">
                            @if($c->starts_at || $c->ends_at)
                                <div class="text-slate-700 dark:text-slate-300 font-medium">
                                    {{ $c->starts_at ? $c->starts_at->format('d M Y') : 'Immediate' }} &rarr; 
                                    {{ $c->ends_at ? $c->ends_at->format('d M Y') : 'Indefinite' }}
                                </div>
                            @else
                                <span class="text-slate-400">Permanent</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('admin.coupons.toggle', $c) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold border transition hover:opacity-80
                                    {{ $c->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800' : 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700' }}">
                                    <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $c->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                    {{ $c->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <button 
                                type="button"
                                @click="openModal(@js($c))" 
                                class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition" 
                                title="Edit Coupon"
                            >
                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <form method="POST" action="{{ route('admin.coupons.destroy', $c) }}" class="inline" onsubmit="return confirm('Delete this coupon code?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition" title="Delete Coupon">
                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            <div class="max-w-sm mx-auto text-center space-y-3">
                                <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                </div>
                                <div class="text-sm font-semibold text-slate-900 dark:text-white">No promotional coupon codes found</div>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Create discount coupons to boost customer orders, set minimum thresholds, or partner with specific verified sellers.</p>
                                <button 
                                    type="button" 
                                    @click="openModal()" 
                                    class="inline-flex items-center px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                                >
                                    Create First Coupon
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($coupons->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
                {{ $coupons->links() }}
            </div>
        @endif
    </div>

    <!-- Create/Edit Modal -->
    <x-modal name="coupon-modal" size="lg">
        <form :action="formUrl" method="POST" class="flex flex-col min-h-0">
            @csrf
            <template x-if="editingCoupon">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between shrink-0 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xs">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white" x-text="editingCoupon ? 'Edit Coupon Promotion' : 'Create New Discount Coupon'"></h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Configure discount rates, order caps, seller restrictions, and schedules.</p>
                </div>
                <button 
                    type="button" 
                    @click="$dispatch('close-modal', 'coupon-modal')" 
                    class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="p-6 space-y-4 overflow-y-auto flex-1 min-h-0 text-left">
                <!-- Code & Type -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Coupon Code *</label>
                        <input 
                            type="text" 
                            name="code" 
                            required 
                            x-model="form.code" 
                            placeholder="e.g. FESTIVE20" 
                            class="w-full text-xs font-mono uppercase rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Discount Type *</label>
                        <select 
                            name="type" 
                            x-model="form.type" 
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                        >
                            <option value="percent">Percentage (%)</option>
                            <option value="flat">Flat Amount (₹)</option>
                        </select>
                    </div>
                </div>

                <!-- Value, Min Order, Max Discount -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            <span x-text="form.type === 'percent' ? 'Discount Rate (%) *' : 'Flat Discount (₹) *'"></span>
                        </label>
                        <input 
                            type="number" 
                            step="0.01" 
                            name="value" 
                            required 
                            x-model="form.value" 
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Min Basket (₹)</label>
                        <input 
                            type="number" 
                            step="1" 
                            name="min_order" 
                            x-model="form.min_order" 
                            placeholder="0"
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Max Cap (₹)</label>
                        <input 
                            type="number" 
                            step="1" 
                            name="max_discount" 
                            x-model="form.max_discount" 
                            :disabled="form.type === 'flat'"
                            :placeholder="form.type === 'flat' ? 'Not applicable' : 'Optional cap'"
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500 disabled:opacity-50"
                        >
                    </div>
                </div>

                <!-- Limits: Usage Limit & Per User Limit -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Total Usage Limit</label>
                        <input 
                            type="number" 
                            name="usage_limit" 
                            x-model="form.usage_limit" 
                            placeholder="Blank for Unlimited" 
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Per User Limit *</label>
                        <input 
                            type="number" 
                            name="per_user_limit" 
                            required 
                            x-model="form.per_user_limit" 
                            min="1"
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                </div>

                <!-- Seller Scope -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Merchant Store Scope</label>
                    <select 
                        name="seller_id" 
                        x-model="form.seller_id" 
                        class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                    >
                        <option value="">Platform Wide (All Merchants)</option>
                        @foreach($sellers as $s)
                            <option value="{{ $s->id }}">{{ $s->business_name }} (#{{ $s->id }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Category Scope (Optional) -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Category Restriction (Optional)</label>
                    <select 
                        name="category_ids[]" 
                        x-model="form.category_ids" 
                        multiple 
                        class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500 h-24"
                    >
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    <span class="text-[10px] text-slate-400 mt-0.5 block">Hold Ctrl (or Cmd) to select multiple categories. Leave empty to apply to all categories.</span>
                </div>

                <!-- Validity Schedule: Starts At & Ends At -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Valid From (Schedule)</label>
                        <input 
                            type="datetime-local" 
                            name="starts_at" 
                            x-model="form.starts_at" 
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Valid Until (Expiry)</label>
                        <input 
                            type="datetime-local" 
                            name="ends_at" 
                            x-model="form.ends_at" 
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                </div>

                <!-- Active Immediately -->
                <div class="flex items-center space-x-2.5 pt-1">
                    <input 
                        type="checkbox" 
                        id="coupon_active_box" 
                        name="is_active" 
                        value="1" 
                        x-model="form.is_active" 
                        class="rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                    >
                    <label for="coupon_active_box" class="text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                        Coupon code is active immediately for customers
                    </label>
                </div>
            </div>

            <!-- Sticky Action Footer -->
            <div class="sticky bottom-0 px-6 py-3.5 bg-white/95 dark:bg-slate-900/95 backdrop-blur-xs border-t border-slate-200 dark:border-slate-800 flex justify-end space-x-2 shrink-0 z-10">
                <button 
                    type="button" 
                    @click="$dispatch('close-modal', 'coupon-modal')" 
                    class="px-4 py-2 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                >
                    Cancel
                </button>
                <button 
                    type="submit" 
                    class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition inline-flex items-center space-x-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span x-text="editingCoupon ? 'Update Coupon' : 'Save Coupon'"></span>
                </button>
            </div>
        </form>
    </x-modal>

</div>

<script>
function couponManager() {
    return {
        editingCoupon: null,
        formUrl: '{{ route("admin.coupons.store") }}',
        form: {
            code: '',
            type: 'percent',
            value: 10,
            min_order: 0,
            max_discount: '',
            usage_limit: '',
            per_user_limit: 1,
            seller_id: '',
            category_ids: [],
            starts_at: '',
            ends_at: '',
            is_active: true
        },
        openModal(coupon = null) {
            this.editingCoupon = coupon;
            if (coupon) {
                this.formUrl = `/admin/coupons/${coupon.id}`;

                let catIds = [];
                if (coupon.applies_to && Array.isArray(coupon.applies_to.categories)) {
                    catIds = coupon.applies_to.categories.map(String);
                }

                // Handle enum type serialization
                let typeVal = typeof coupon.type === 'object' && coupon.type !== null ? coupon.type.value : coupon.type;

                this.form = {
                    code: coupon.code,
                    type: typeVal || 'percent',
                    value: coupon.value,
                    min_order: coupon.min_order,
                    max_discount: coupon.max_discount || '',
                    usage_limit: coupon.usage_limit || '',
                    per_user_limit: coupon.per_user_limit || 1,
                    seller_id: coupon.seller_id ? String(coupon.seller_id) : '',
                    category_ids: catIds,
                    starts_at: coupon.starts_at ? coupon.starts_at.slice(0, 16) : '',
                    ends_at: coupon.ends_at ? coupon.ends_at.slice(0, 16) : '',
                    is_active: Boolean(coupon.is_active)
                };
            } else {
                this.formUrl = '{{ route("admin.coupons.store") }}';
                this.form = {
                    code: '',
                    type: 'percent',
                    value: 10,
                    min_order: 0,
                    max_discount: '',
                    usage_limit: '',
                    per_user_limit: 1,
                    seller_id: '',
                    category_ids: [],
                    starts_at: '',
                    ends_at: '',
                    is_active: true
                };
            }
            this.$dispatch('open-modal', 'coupon-modal');
        },
        closeModal() {
            this.$dispatch('close-modal', 'coupon-modal');
            this.editingCoupon = null;
        }
    };
}
</script>
@endsection
