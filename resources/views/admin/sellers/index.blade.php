@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Sellers & Vendors Directory'])

@section('content')
<div class="space-y-6" x-data="sellersIndex()">
    <!-- Page Header Component -->
    <x-page-header 
        title="Sellers & Vendors Governance" 
        subtitle="Manage onboarding, KYC verification pipelines, commission agreements, and merchant storefront compliance."
        :breadcrumbs="['Command Central' => route('admin.dashboard'), 'Sellers & Vendors' => '#']"
    >
        <x-slot name="badge">
            <x-badge variant="primary" size="sm">
                {{ $sellers->total() }} Registered Merchants
            </x-badge>
        </x-slot>

        <x-slot name="actions">
            <a 
                href="{{ route('admin.sellers.export', request()->query()) }}"
                class="inline-flex items-center px-3.5 py-2 text-xs font-semibold rounded-xl text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200/90 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-750 shadow-xs transition-all"
            >
                <svg class="w-4 h-4 mr-1.5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Export Directory (CSV)
            </a>
        </x-slot>
    </x-page-header>

    <!-- Filters Bar Card -->
    <x-card padding="p-5">
        <form method="GET" action="{{ route('admin.sellers.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                <!-- Search input -->
                <div class="lg:col-span-2">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                        Search Merchant / Credentials
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <input 
                            type="text" 
                            name="search" 
                            value="{{ $filters['search'] ?? '' }}" 
                            placeholder="Business name, legal, email, PAN, GSTIN..." 
                            class="w-full pl-9 pr-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                        />
                    </div>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                        KYC Status
                    </label>
                    <select 
                        name="status" 
                        class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                    >
                        <option value="">All Statuses</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}" {{ ($filters['status'] ?? '') === $status->value ? 'selected' : '' }}>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Type Filter -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                        Seller Type
                    </label>
                    <select 
                        name="type" 
                        class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                    >
                        <option value="">All Store Models</option>
                        @foreach($types as $type)
                            <option value="{{ $type->value }}" {{ ($filters['type'] ?? '') === $type->value ? 'selected' : '' }}>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- City Filter -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                        Store City
                    </label>
                    <input 
                        type="text" 
                        name="city" 
                        value="{{ $filters['city'] ?? '' }}" 
                        placeholder="e.g. Bengaluru, Mumbai" 
                        class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                    />
                </div>

                <!-- Action buttons -->
                <div class="flex items-end space-x-2">
                    <button 
                        type="submit" 
                        class="flex-1 py-2 px-3 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition-all"
                    >
                        Filter
                    </button>
                    @if(array_filter($filters))
                        <a 
                            href="{{ route('admin.sellers.index') }}" 
                            class="py-2 px-3 text-xs font-semibold rounded-xl bg-slate-200 dark:bg-slate-750 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-all"
                            title="Reset all filters"
                        >
                            Reset
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </x-card>

    <!-- Bulk Action Floating Banner (Alpine Reactivity) -->
    <div 
        x-show="selectedIds.length > 0" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-cloak
        class="p-4 rounded-2xl bg-indigo-50 dark:bg-indigo-950/70 border border-indigo-200 dark:border-indigo-800 shadow-lg flex flex-col sm:flex-row items-center justify-between gap-4"
    >
        <div class="flex items-center space-x-3 text-xs font-semibold text-indigo-900 dark:text-indigo-200">
            <span class="w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[11px] font-bold" x-text="selectedIds.length"></span>
            <span>merchant(s) selected for bulk operation</span>
        </div>

        <form method="POST" action="{{ route('admin.sellers.bulk_action') }}" class="flex flex-wrap items-center gap-2">
            @csrf
            <template x-for="id in selectedIds" :key="id">
                <input type="hidden" name="seller_ids[]" :value="id">
            </template>

            <select 
                name="action" 
                x-model="bulkActionType"
                required
                class="py-1.5 px-3 text-xs rounded-xl bg-white dark:bg-slate-900 border border-indigo-200 dark:border-indigo-800 text-slate-800 dark:text-slate-200 font-medium"
            >
                <option value="">Select Bulk Action</option>
                <option value="approve">Bulk Approve KYC & Initialize Stores</option>
                <option value="reject">Bulk Reject with Reason</option>
                <option value="suspend">Bulk Suspend Accounts</option>
                <option value="reactivate">Bulk Reactivate Accounts</option>
            </select>

            <input 
                x-show="bulkActionType === 'reject' || bulkActionType === 'suspend'"
                type="text" 
                name="reason" 
                placeholder="Mandatory reason for bulk action..." 
                class="py-1.5 px-3 text-xs rounded-xl bg-white dark:bg-slate-900 border border-indigo-200 dark:border-indigo-800 text-slate-800 dark:text-slate-200"
            />

            <button 
                type="submit" 
                onclick="return confirm('Are you sure you want to execute this bulk action on selected merchants?')"
                class="px-3.5 py-1.5 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition"
            >
                Execute Action
            </button>

            <button 
                type="button" 
                @click="selectedIds = []" 
                class="px-2.5 py-1.5 text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300"
            >
                Cancel
            </button>
        </form>
    </div>

    <!-- Sellers Directory Table Card -->
    <x-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40 text-slate-400 dark:text-slate-500 font-semibold uppercase tracking-wider">
                        <th class="py-3 px-4 w-10 text-center">
                            <input 
                                type="checkbox" 
                                @change="toggleAll($event)"
                                class="rounded-sm border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500"
                            />
                        </th>
                        <th class="py-3 px-4">Merchant Business</th>
                        <th class="py-3 px-4">Owner & Contact</th>
                        <th class="py-3 px-4">Store Model</th>
                        <th class="py-3 px-4 text-center">KYC Status</th>
                        <th class="py-3 px-4 text-center">Commercials</th>
                        <th class="py-3 px-4 text-center">Commission</th>
                        <th class="py-3 px-4">Primary City</th>
                        <th class="py-3 px-4 text-right">Registered</th>
                        <th class="py-3 px-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @forelse($sellers as $seller)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-850/50 transition-colors">
                            <td class="py-3.5 px-4 text-center">
                                <input 
                                    type="checkbox" 
                                    value="{{ $seller->id }}" 
                                    x-model="selectedIds"
                                    class="rounded-sm border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500"
                                />
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-800/40 flex items-center justify-center font-bold text-xs text-indigo-600 dark:text-indigo-400 shrink-0">
                                        {{ substr($seller->business_name, 0, 2) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.sellers.show', $seller) }}" class="font-bold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                            {{ $seller->business_name }}
                                        </a>
                                        <div class="text-[11px] text-slate-400 dark:text-slate-500">
                                            {{ $seller->legal_name }} &bull; <span class="font-mono">#{{ $seller->id }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-medium text-slate-800 dark:text-slate-200">
                                    {{ $seller->user?->name ?? 'N/A' }}
                                </div>
                                <div class="text-[11px] text-slate-400">
                                    {{ $seller->user?->email ?? 'No email' }}
                                </div>
                                @if($seller->user?->phone)
                                    <div class="text-[10px] font-mono text-slate-400">
                                        {{ $seller->user->phone }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                    {{ $seller->seller_type->label() }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $seller->status->badgeClass() }}">
                                    {{ $seller->status->label() }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="inline-flex items-center space-x-1.5 text-[11px]">
                                    <span title="Stores" class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-mono">
                                        {{ $seller->stores_count }} stores
                                    </span>
                                    <span title="Products" class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-mono">
                                        {{ $seller->products_count }} items
                                    </span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-center font-mono text-xs">
                                @if($seller->commission_override !== null)
                                    <span class="font-bold text-amber-600 dark:text-amber-400">
                                        {{ $seller->commission_override }}%
                                    </span>
                                    <div class="text-[9px] text-amber-500 font-sans">Custom</div>
                                @else
                                    <span class="text-slate-400">Default ({{ setting('default_commission_pct', 10) }}%)</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-slate-600 dark:text-slate-400">
                                {{ $seller->stores->first()?->locations->first()?->city ?? 'N/A' }}
                            </td>
                            <td class="py-3.5 px-4 text-right text-slate-400 text-[11px]">
                                {{ $seller->created_at->format('M d, Y') }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <a 
                                    href="{{ route('admin.sellers.show', $seller) }}" 
                                    class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 border border-indigo-200 dark:border-indigo-800/60 transition"
                                >
                                    Manage &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-12 text-center">
                                <x-empty-state 
                                    title="No merchants match criteria" 
                                    description="Try clearing search filters or adjust keywords to discover merchant accounts."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div class="border-t border-slate-100 dark:border-slate-800/80 px-4 py-3">
            <x-pagination :paginator="$sellers" />
        </div>
    </x-card>
</div>

<script>
    function sellersIndex() {
        return {
            selectedIds: [],
            bulkActionType: '',
            allIds: @js($sellers->pluck('id')->toArray()),

            toggleAll(event) {
                if (event.target.checked) {
                    this.selectedIds = [...this.allIds];
                } else {
                    this.selectedIds = [];
                }
            }
        };
    }
</script>
@endsection
