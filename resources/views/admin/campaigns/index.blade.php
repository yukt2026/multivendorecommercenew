@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Promotional Campaigns'])

@section('content')
<div class="space-y-6" x-data="{ openModal: false, editingCampaign: null, form: { name: '', type: 'flash_sale', description: '', starts_at: '', ends_at: '', is_active: true } }">

    <!-- Page Header -->
    <x-page-header 
        title="Marketing Campaigns &amp; Featured Placements" 
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
                @click="editingCampaign = null; form = { name: '', type: 'flash_sale', description: '', starts_at: '', ends_at: '', is_active: true }; openModal = true" 
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm flex items-center space-x-1.5 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>New Campaign</span>
            </button>
        </x-slot>
    </x-page-header>

    <!-- Campaigns Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Campaign Name</th>
                        <th class="px-6 py-3.5">Type</th>
                        <th class="px-6 py-3.5">Description</th>
                        <th class="px-6 py-3.5">Active Window</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($campaigns as $camp)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">
                            {{ $camp->name }}
                            <div class="text-[10px] text-slate-400 font-mono">/campaign/{{ $camp->slug }}</div>
                        </td>
                        <td class="px-6 py-4 uppercase font-semibold text-slate-700 dark:text-slate-300">
                            {{ $camp->type }}
                        </td>
                        <td class="px-6 py-4 text-slate-500 max-w-xs truncate">
                            {{ $camp->description ?? 'No description' }}
                        </td>
                        <td class="px-6 py-4 text-slate-500 font-mono text-[11px]">
                            @if($camp->starts_at || $camp->ends_at)
                                <div>{{ $camp->starts_at ? $camp->starts_at->format('d M') : 'Start' }} &rarr; {{ $camp->ends_at ? $camp->ends_at->format('d M Y') : 'Indefinite' }}</div>
                            @else
                                <span class="text-slate-400">Always Active</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $camp->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                                {{ $camp->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <button 
                                @click="editingCampaign = {{ json_encode($camp) }}; form = { name: '{{ $camp->name }}', type: '{{ $camp->type }}', description: '{{ $camp->description }}', starts_at: '{{ $camp->starts_at ? $camp->starts_at->format('Y-m-d\TH:i') : '' }}', ends_at: '{{ $camp->ends_at ? $camp->ends_at->format('Y-m-d\TH:i') : '' }}', is_active: {{ $camp->is_active ? 'true' : 'false' }} }; openModal = true" 
                                class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition"
                            >
                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <form method="POST" action="{{ route('admin.campaigns.destroy', $camp) }}" class="inline" onsubmit="return confirm('Delete campaign?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 transition">
                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400">No promotional campaigns created yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
            {{ $campaigns->links() }}
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div x-show="openModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div @click.away="openModal = false" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-3" x-text="editingCampaign ? 'Edit Marketing Campaign' : 'Create New Campaign'"></h3>

            <form :action="editingCampaign ? ('/admin/campaigns/' + editingCampaign.id) : '{{ route('admin.campaigns.store') }}'" method="POST" class="space-y-4">
                @csrf
                <template x-if="editingCampaign">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Campaign Title</label>
                    <input type="text" name="name" required x-model="form.name" placeholder="e.g. Great Indian Festive Sale" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Campaign Type</label>
                    <select name="type" x-model="form.type" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                        <option value="flash_sale">Flash Sale</option>
                        <option value="festive_season">Festive Season</option>
                        <option value="featured_stores">Featured Stores Spotlight</option>
                        <option value="clearance">Clearance</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Description</label>
                    <textarea name="description" rows="2" x-model="form.description" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Starts At</label>
                        <input type="datetime-local" name="starts_at" x-model="form.starts_at" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ends At</label>
                        <input type="datetime-local" name="ends_at" x-model="form.ends_at" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                    </div>
                </div>

                <div class="flex items-center space-x-2 pt-1">
                    <input type="checkbox" id="camp_active" name="is_active" value="1" x-model="form.is_active" class="rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500">
                    <label for="camp_active" class="text-xs font-medium text-slate-700 dark:text-slate-300">Campaign is active</label>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="openModal = false" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm">Save Campaign</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
