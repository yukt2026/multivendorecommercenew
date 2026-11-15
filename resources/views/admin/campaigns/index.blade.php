@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Featured Marketing Campaigns'])

@section('content')
<div class="space-y-6" x-data="campaignManager()">

    <!-- Page Header -->
    <x-page-header 
        title="Marketing Campaigns & Featured Events" 
        subtitle="Manage seasonal mega sales, flash deal collections, festival events, and featured store promotions."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Campaigns' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                {{ $metrics['active'] }} Active Campaigns
            </span>
        </x-slot>

        <x-slot name="actions">
            <button 
                type="button"
                @click="openModal()" 
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm flex items-center space-x-1.5 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>New Campaign</span>
            </button>
        </x-slot>
    </x-page-header>

    <!-- Metrics Strip -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Campaigns</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $metrics['total'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Active Live</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $metrics['active'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-amber-600 dark:text-amber-400">Flash Sales</div>
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $metrics['flash_sales'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-rose-600 dark:text-rose-400">Festive Events</div>
            <div class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">{{ $metrics['festive'] }}</div>
        </div>
    </div>

    <!-- Filters Strip -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row gap-4 justify-between items-center">
        <div class="flex flex-wrap items-center gap-2">
            @php 
                $currentType = request('type', 'all');
                $currentStatus = request('status', 'all');
            @endphp
            
            <a href="{{ request()->fullUrlWithQuery(['type' => 'all', 'status' => 'all', 'page' => 1]) }}" 
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $currentType === 'all' && $currentStatus === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                All Campaigns
            </a>
            <a href="{{ request()->fullUrlWithQuery(['type' => 'flash_sale', 'page' => 1]) }}" 
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $currentType === 'flash_sale' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                Flash Sales
            </a>
            <a href="{{ request()->fullUrlWithQuery(['type' => 'festive_season', 'page' => 1]) }}" 
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ in_array($currentType, ['festive_season', 'festive', 'festive_sale']) ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                Festive Events
            </a>
            <a href="{{ request()->fullUrlWithQuery(['type' => 'featured_stores', 'page' => 1]) }}" 
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $currentType === 'featured_stores' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                Featured Stores
            </a>
            <a href="{{ request()->fullUrlWithQuery(['type' => 'clearance', 'page' => 1]) }}" 
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $currentType === 'clearance' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                Clearance
            </a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'active', 'page' => 1]) }}" 
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $currentStatus === 'active' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                Active Live
            </a>
        </div>

        <form method="GET" action="{{ route('admin.campaigns.index') }}" class="flex items-center space-x-2 w-full md:w-auto">
            @if(request('type'))
                <input type="hidden" name="type" value="{{ request('type') }}">
            @endif
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <div class="relative w-full md:w-64">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ request('search') }}" 
                    placeholder="Search campaign name..." 
                    class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white pl-8 pr-3 py-2 focus:ring-1 focus:ring-indigo-500"
                >
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <button type="submit" class="px-3 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold transition">
                Filter
            </button>
            @if(request('search') || (request('type') && request('type') !== 'all') || (request('status') && request('status') !== 'all'))
                <a href="{{ route('admin.campaigns.index') }}" class="px-2.5 py-2 text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                    Clear
                </a>
            @endif
        </form>
    </div>

    <!-- Campaigns Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Campaign Name &amp; Route</th>
                        <th class="px-6 py-3.5">Event Type</th>
                        <th class="px-6 py-3.5">Description</th>
                        <th class="px-6 py-3.5">Active Window</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($campaigns as $camp)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-900 dark:text-white text-sm">
                                {{ $camp->name }}
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono mt-0.5 flex items-center space-x-1">
                                <a href="{{ route('campaign.show', $camp->slug) }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 hover:underline flex items-center space-x-0.5" title="View public storefront campaign page">
                                    <span>/campaign/{{ $camp->slug }}</span>
                                    <svg class="w-2.5 h-2.5 inline ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $typeKey = $camp->type;
                                $isFlash = $typeKey === 'flash_sale';
                                $isFestive = in_array($typeKey, ['festive_season', 'festive_sale', 'festive']);
                                $isFeatured = $typeKey === 'featured_stores';
                                $isClearance = $typeKey === 'clearance';
                            @endphp
                            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider border
                                {{ $isFlash ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-400 dark:border-amber-800' : '' }}
                                {{ $isFestive ? 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-400 dark:border-rose-800' : '' }}
                                {{ $isFeatured ? 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/60 dark:text-purple-400 dark:border-purple-800' : '' }}
                                {{ $isClearance ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800' : '' }}
                                {{ !$isFlash && !$isFestive && !$isFeatured && !$isClearance ? 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/60 dark:text-sky-400 dark:border-sky-800' : '' }}
                            ">
                                {{ $camp->type_label }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300 max-w-xs">
                            <span class="line-clamp-2">{{ $camp->description ?? 'No promotional description provided.' }}</span>
                        </td>
                        <td class="px-6 py-4 text-slate-500 font-mono text-[11px]">
                            @if($camp->starts_at || $camp->ends_at)
                                <div class="text-slate-700 dark:text-slate-300 font-medium">
                                    {{ $camp->starts_at ? $camp->starts_at->format('d M Y') : 'Immediate' }} &rarr; 
                                    {{ $camp->ends_at ? $camp->ends_at->format('d M Y') : 'Indefinite' }}
                                </div>
                            @else
                                <span class="inline-flex items-center text-slate-400">
                                    <svg class="w-3.5 h-3.5 mr-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Always Active
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('admin.campaigns.toggle', $camp) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold border transition hover:opacity-80
                                    {{ $camp->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800' : 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700' }}">
                                    <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $camp->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                    {{ $camp->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <button 
                                type="button"
                                @click="editCampaign(@js($camp))" 
                                class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition" 
                                title="Edit Campaign"
                            >
                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <form method="POST" action="{{ route('admin.campaigns.destroy', $camp) }}" class="inline" onsubmit="return confirm('Delete this marketing campaign?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition" title="Delete Campaign">
                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            <div class="max-w-sm mx-auto text-center space-y-3">
                                <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                                </div>
                                <div class="text-sm font-semibold text-slate-900 dark:text-white">No promotional marketing campaigns found</div>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Create campaigns to group seasonal discounts, highlight festival deals, and boost sales across multiple merchants.</p>
                                <button 
                                    type="button" 
                                    @click="openModal()" 
                                    class="inline-flex items-center px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                                >
                                    Create First Campaign
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($campaigns->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
                {{ $campaigns->links() }}
            </div>
        @endif
    </div>

    <!-- Create/Edit Modal -->
    <x-modal name="campaign-modal" size="md">
        <form :action="formUrl" method="POST" class="flex flex-col min-h-0">
            @csrf
            <template x-if="editingCampaign">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between shrink-0 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xs">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white" x-text="editingCampaign ? 'Edit Marketing Campaign' : 'Create New Campaign'"></h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Configure campaign title, placement type, and promotional active dates.</p>
                </div>
                <button 
                    type="button" 
                    @click="$dispatch('close-modal', 'campaign-modal')" 
                    class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="p-6 space-y-4 overflow-y-auto flex-1 min-h-0 text-left">
                <!-- Name -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Campaign Title *</label>
                    <input 
                        type="text" 
                        name="name" 
                        required 
                        x-model="form.name" 
                        placeholder="e.g. Great Indian Festive Sale" 
                        class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                    >
                </div>

                <!-- Type -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Event Type *</label>
                    <select 
                        name="type" 
                        x-model="form.type" 
                        required
                        class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                    >
                        <option value="flash_sale">Flash Sale</option>
                        <option value="festive_season">Festive Season</option>
                        <option value="featured_stores">Featured Stores Spotlight</option>
                        <option value="clearance">Clearance</option>
                        <option value="seasonal">Seasonal Mega Sale</option>
                    </select>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Promotional Description</label>
                    <textarea 
                        name="description" 
                        rows="3" 
                        x-model="form.description" 
                        placeholder="Short summary of promotional highlights, target audiences, and discounts..."
                        class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                    ></textarea>
                </div>

                <!-- Validity Schedule: Starts At & Ends At -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Starts At (Schedule)</label>
                        <input 
                            type="datetime-local" 
                            name="starts_at" 
                            x-model="form.starts_at" 
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ends At (Schedule)</label>
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
                        id="camp_active" 
                        name="is_active" 
                        value="1" 
                        x-model="form.is_active" 
                        class="rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                    >
                    <label for="camp_active" class="text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                        Campaign is active immediately
                    </label>
                </div>
            </div>

            <!-- Sticky Action Footer -->
            <div class="sticky bottom-0 px-6 py-3.5 bg-white/95 dark:bg-slate-900/95 backdrop-blur-xs border-t border-slate-200 dark:border-slate-800 flex justify-end space-x-2 shrink-0 z-10">
                <button 
                    type="button" 
                    @click="$dispatch('close-modal', 'campaign-modal')" 
                    class="px-4 py-2 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                >
                    Cancel
                </button>
                <button 
                    type="submit" 
                    class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition inline-flex items-center space-x-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span x-text="editingCampaign ? 'Update Campaign' : 'Save Campaign'"></span>
                </button>
            </div>
        </form>
    </x-modal>

</div>

<script>
function campaignManager() {
    return {
        editingCampaign: null,
        formUrl: '{{ route("admin.campaigns.store") }}',
        form: {
            name: '',
            type: 'flash_sale',
            description: '',
            starts_at: '',
            ends_at: '',
            is_active: true
        },
        openModal() {
            this.editingCampaign = null;
            this.formUrl = '{{ route("admin.campaigns.store") }}';
            this.form = {
                name: '',
                type: 'flash_sale',
                description: '',
                starts_at: '',
                ends_at: '',
                is_active: true
            };
            this.$dispatch('open-modal', 'campaign-modal');
        },
        editCampaign(campaign) {
            this.editingCampaign = campaign;
            this.formUrl = `/admin/campaigns/${campaign.id}`;

            let t = campaign.type;
            if (['festive_sale', 'festive'].includes(t)) {
                t = 'festive_season';
            }

            this.form = {
                name: campaign.name || '',
                type: t || 'flash_sale',
                description: campaign.description || '',
                starts_at: campaign.starts_at ? campaign.starts_at.slice(0, 16) : '',
                ends_at: campaign.ends_at ? campaign.ends_at.slice(0, 16) : '',
                is_active: Boolean(campaign.is_active)
            };
            this.$dispatch('open-modal', 'campaign-modal');
        },
        closeModal() {
            this.$dispatch('close-modal', 'campaign-modal');
            this.editingCampaign = null;
        }
    };
}
</script>
@endsection
