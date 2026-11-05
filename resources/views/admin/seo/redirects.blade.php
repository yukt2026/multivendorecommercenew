@extends('layouts.dashboard', ['portal' => 'admin', 'title' => '301 / 302 Redirect Manager & URL Routing'])

@section('content')
<div class="space-y-6" x-data="{ 
    createModal: false, 
    importModal: false, 
    editingRule: null 
}">

    <!-- Page Header -->
    <x-page-header 
        title="301 / 302 Redirect Manager" 
        subtitle="Manage permanent canonical URL migrations, legacy link routing, and avoid 404 crawl errors."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'SEO Redirects' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                {{ number_format($stats['total_rules']) }} Rules
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <button 
                    @click="importModal = true" 
                    type="button" 
                    class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 text-xs font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition"
                >
                    Import CSV
                </button>
                <button 
                    @click="createModal = true" 
                    type="button" 
                    class="px-4 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-sm flex items-center space-x-1.5 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>New Redirect</span>
                </button>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Configured Rules</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($stats['total_rules']) }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
            <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Active Live Redirects</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($stats['active_rules']) }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
            <div class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">Total Redirect Hits</div>
            <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ number_format($stats['total_hits']) }}</div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.redirects.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Search Source or Target</label>
                <input 
                    type="text" 
                    name="search" 
                    value="{{ $search }}" 
                    placeholder="e.g. /old-product or https://..."
                    class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-indigo-500"
                >
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">HTTP Status</label>
                <select name="code" class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-indigo-500">
                    <option value="all" {{ $selectedCode === 'all' ? 'selected' : '' }}>All Codes</option>
                    <option value="301" {{ $selectedCode === '301' ? 'selected' : '' }}>301 Permanent</option>
                    <option value="302" {{ $selectedCode === '302' ? 'selected' : '' }}>302 Temporary</option>
                </select>
            </div>

            <div class="flex items-center space-x-2">
                <button type="submit" class="w-full px-4 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition">
                    Filter
                </button>
                <a href="{{ route('admin.redirects.index') }}" class="px-3 py-2 text-xs font-semibold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 font-semibold uppercase tracking-wider bg-slate-50/50 dark:bg-slate-950/40">
                        <th class="py-3.5 px-4">Source Path (Old URL)</th>
                        <th class="py-3.5 px-4">Target Destination</th>
                        <th class="py-3.5 px-4">HTTP Status</th>
                        <th class="py-3.5 px-4">State</th>
                        <th class="py-3.5 px-4 text-center">Hits</th>
                        <th class="py-3.5 px-4">Last Accessed</th>
                        <th class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($rules as $rule)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-slate-900 dark:text-white">
                                {{ $rule->source_path }}
                            </td>
                            <td class="py-3.5 px-4 text-indigo-600 dark:text-indigo-400 max-w-xs truncate" title="{{ $rule->target_path }}">
                                {{ $rule->target_path }}
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold {{ $rule->status_code === 301 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400' }}">
                                    {{ $rule->status_code }} {{ $rule->status_code === 301 ? 'Permanent' : 'Temporary' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <form method="POST" action="{{ route('admin.redirects.toggle', $rule) }}">
                                    @csrf
                                    <button type="submit" class="px-2 py-0.5 rounded-full text-[10px] font-bold cursor-pointer transition {{ $rule->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                                        {{ $rule->is_active ? 'Active' : 'Disabled' }}
                                    </button>
                                </form>
                            </td>
                            <td class="py-3.5 px-4 font-mono font-bold text-center text-slate-700 dark:text-slate-300">
                                {{ number_format($rule->hit_count) }}
                            </td>
                            <td class="py-3.5 px-4 text-slate-400 text-[11px]">
                                {{ $rule->last_accessed_at ? $rule->last_accessed_at->diffForHumans() : 'Never' }}
                            </td>
                            <td class="py-3.5 px-4 text-right space-x-2">
                                <button 
                                    type="button" 
                                    @click="editingRule = {{ json_encode($rule) }}"
                                    class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold"
                                >
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('admin.redirects.destroy', $rule) }}" class="inline" onsubmit="return confirm('Delete this redirect rule?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:underline font-semibold">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                No redirect rules configured. Create one above to preserve SEO equity.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-800">
            {{ $rules->links() }}
        </div>
    </div>

    <!-- Create Modal -->
    <div x-show="createModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="createModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-800 p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Create Redirect Rule</h3>
                    <button @click="createModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.redirects.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Source Path (Old URL)</label>
                        <input type="text" name="source_path" placeholder="/old-category/old-item" required class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-indigo-500">
                        <p class="text-[11px] text-slate-400 mt-0.5">Incoming relative path to intercept.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Target Destination (New URL)</label>
                        <input type="text" name="target_path" placeholder="/p/new-canonical-slug or https://..." required class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-indigo-500">
                        <p class="text-[11px] text-slate-400 mt-0.5">Destination relative path or absolute URL.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">HTTP Status Code</label>
                        <select name="status_code" class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-indigo-500">
                            <option value="301">301 - Moved Permanently (Passes full PageRank / Link Equity)</option>
                            <option value="302">302 - Found / Temporary Redirect</option>
                        </select>
                    </div>

                    <div class="flex items-center space-x-2">
                        <input type="checkbox" id="create_active" name="is_active" value="1" checked class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700">
                        <label for="create_active" class="text-xs font-semibold text-slate-700 dark:text-slate-300">Active Immediately</label>
                    </div>

                    <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="createModal = false" class="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition">Create Rule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div x-show="editingRule !== null" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="editingRule = null"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-800 p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Edit Redirect Rule</h3>
                    <button @click="editingRule = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form :action="`/admin/redirects/${editingRule?.id}`" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Source Path (Old URL)</label>
                        <input type="text" name="source_path" :value="editingRule?.source_path" required class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Target Destination (New URL)</label>
                        <input type="text" name="target_path" :value="editingRule?.target_path" required class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">HTTP Status Code</label>
                        <select name="status_code" class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-indigo-500">
                            <option value="301" :selected="editingRule?.status_code == 301">301 - Moved Permanently</option>
                            <option value="302" :selected="editingRule?.status_code == 302">302 - Temporary Redirect</option>
                        </select>
                    </div>

                    <div class="flex items-center space-x-2">
                        <input type="checkbox" id="edit_active" name="is_active" value="1" :checked="editingRule?.is_active" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700">
                        <label for="edit_active" class="text-xs font-semibold text-slate-700 dark:text-slate-300">Active</label>
                    </div>

                    <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="editingRule = null" class="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import CSV Modal -->
    <div x-show="importModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="importModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-800 p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Bulk Import Redirects (CSV)</h3>
                    <button @click="importModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.redirects.import') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Upload CSV File</label>
                        <input type="file" name="csv_file" accept=".csv,text/csv" required class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-indigo-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 dark:file:bg-indigo-950 dark:file:text-indigo-300">
                        <p class="text-[11px] text-slate-400 mt-1">Columns format: <code>source_path, target_path, status_code</code> (e.g. <code>/old-url,/new-url,301</code>).</p>
                    </div>

                    <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="importModal = false" class="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition">Start Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
