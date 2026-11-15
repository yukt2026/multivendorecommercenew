@extends('layouts.dashboard', ['portal' => 'admin', 'title' => '301 / 302 Redirect Manager & URL Routing'])

@section('content')
<div class="space-y-6" x-data="redirectManager()">

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
                <a 
                    href="{{ route('admin.redirects.export', request()->query()) }}" 
                    class="px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 text-xs font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center space-x-1.5 transition"
                >
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Export CSV</span>
                </a>

                <button 
                    @click="importModal = true" 
                    type="button" 
                    class="px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 text-xs font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center space-x-1.5 transition"
                >
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12"/></svg>
                    <span>Import CSV</span>
                </button>

                <button 
                    @click="openCreateModal()" 
                    type="button" 
                    class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-sm flex items-center space-x-1.5 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>New Redirect</span>
                </button>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Configured Rules</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($stats['total_rules']) }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
            <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Active Live Redirects</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($stats['active_rules']) }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
            <div class="text-xs font-semibold text-blue-600 dark:text-blue-400">Permanent 301 Rules</div>
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ number_format($stats['permanent_rules']) }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
            <div class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">Total Redirect Hits</div>
            <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ number_format($stats['total_hits']) }}</div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.redirects.index') }}" class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[220px]">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ $search }}" 
                    placeholder="Search source path or target URL..." 
                    class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white pl-8 pr-3 py-2 focus:ring-1 focus:ring-indigo-500"
                >
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <select name="code" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3">
                <option value="all" {{ $selectedCode === 'all' ? 'selected' : '' }}>All HTTP Status Codes</option>
                <option value="301" {{ $selectedCode === '301' ? 'selected' : '' }}>301 - Moved Permanently</option>
                <option value="302" {{ $selectedCode === '302' ? 'selected' : '' }}>302 - Temporary Redirect</option>
            </select>

            <select name="status" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3">
                <option value="all" {{ $selectedStatus === 'all' ? 'selected' : '' }}>All States</option>
                <option value="active" {{ $selectedStatus === 'active' ? 'selected' : '' }}>Active Only</option>
                <option value="disabled" {{ $selectedStatus === 'disabled' ? 'selected' : '' }}>Disabled Only</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                Filter
            </button>
            <a href="{{ route('admin.redirects.index') }}" class="px-3 py-2 text-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">
                Reset
            </a>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 uppercase text-[10px] tracking-wider bg-slate-50 dark:bg-slate-800/60 font-semibold">
                        <th class="py-3.5 px-6">Source Path (Old URL)</th>
                        <th class="py-3.5 px-6">Target Destination (New URL)</th>
                        <th class="py-3.5 px-6">HTTP Status</th>
                        <th class="py-3.5 px-6">State</th>
                        <th class="py-3.5 px-6 text-center">Hits</th>
                        <th class="py-3.5 px-6">Last Accessed</th>
                        <th class="py-3.5 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($rules as $rule)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-4 px-6 font-mono font-bold text-slate-900 dark:text-white">
                                {{ $rule->source_path }}
                            </td>
                            <td class="py-4 px-6 text-indigo-600 dark:text-indigo-400 max-w-xs truncate" title="{{ $rule->target_path }}">
                                <div class="flex items-center space-x-1">
                                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    <span class="truncate">{{ $rule->target_path }}</span>
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold border {{ $rule->status_code === 301 ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-400' }}">
                                    {{ $rule->status_code }} {{ $rule->status_code === 301 ? 'Permanent' : 'Temporary' }}
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                <form method="POST" action="{{ route('admin.redirects.toggle', $rule) }}">
                                    @csrf
                                    <button 
                                        type="submit" 
                                        class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border transition cursor-pointer {{ $rule->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400 hover:bg-emerald-100' : 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 hover:bg-slate-200' }}"
                                        title="Click to toggle redirect state"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $rule->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                        {{ $rule->is_active ? 'Active' : 'Disabled' }}
                                    </button>
                                </form>
                            </td>
                            <td class="py-4 px-6 font-mono font-bold text-center text-slate-700 dark:text-slate-300">
                                {{ number_format($rule->hit_count) }}
                            </td>
                            <td class="py-4 px-6 text-slate-500 font-mono text-[11px] whitespace-nowrap">
                                <div>{{ $rule->last_accessed_at ? $rule->last_accessed_at->format('Y-m-d H:i') : 'Never' }}</div>
                                <div class="text-[10px] text-slate-400 font-sans">{{ $rule->last_accessed_at ? $rule->last_accessed_at->diffForHumans() : 'No hits recorded' }}</div>
                            </td>
                            <td class="py-4 px-6 text-right space-x-2">
                                <button 
                                    type="button" 
                                    @click="openEditModal(@js($rule))"
                                    class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition"
                                    title="Edit Rule"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <form method="POST" action="{{ route('admin.redirects.destroy', $rule) }}" class="inline" onsubmit="return confirm('Delete this redirect rule? Legacy incoming traffic to this path will result in 404s.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition" title="Delete Rule">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500 dark:text-slate-400 font-sans">
                                No redirect rules configured. Create a rule above to preserve search engine ranking equity.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-800">
            <x-pagination :paginator="$rules" />
        </div>
    </div>

    <!-- Create / Edit Modal -->
    <div 
        x-show="modalOpen" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title" 
        role="dialog" 
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="modalOpen" @click="closeModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div 
                x-show="modalOpen" 
                class="inline-block align-bottom bg-white dark:bg-slate-900 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-200 dark:border-slate-800"
            >
                <form :action="formUrl" method="POST" class="space-y-0">
                    @csrf
                    <template x-if="isEditing">
                        <input type="hidden" name="_method" value="PUT">
                    </template>

                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white" x-text="isEditing ? 'Edit Redirect Rule' : 'Create Canonical Redirect Rule'"></h3>
                        <button type="button" @click="closeModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Source Path (Old URL) *</label>
                            <input 
                                type="text" 
                                name="source_path" 
                                x-model="form.source_path"
                                placeholder="/old-category/old-item" 
                                required 
                                class="w-full text-xs font-mono rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                            >
                            <p class="text-[11px] text-slate-400 mt-1">Relative path to intercept (e.g. <code>/products/legacy-item</code>).</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Target Destination (New URL) *</label>
                            <input 
                                type="text" 
                                name="target_path" 
                                x-model="form.target_path"
                                placeholder="/p/new-canonical-slug or https://..." 
                                required 
                                class="w-full text-xs font-mono rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                            >
                            <p class="text-[11px] text-slate-400 mt-1">Target relative path or fully-qualified external URL.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">HTTP Status Code *</label>
                            <select 
                                name="status_code" 
                                x-model="form.status_code"
                                class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5"
                            >
                                <option value="301">301 - Moved Permanently (Passes full PageRank / Link Equity)</option>
                                <option value="302">302 - Found / Temporary Redirect (Does not pass link equity)</option>
                            </select>
                        </div>

                        <div class="flex items-center space-x-2 pt-1">
                            <input 
                                type="checkbox" 
                                id="rule_is_active" 
                                name="is_active" 
                                value="1" 
                                x-model="form.is_active"
                                class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700"
                            >
                            <label for="rule_is_active" class="text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                                Active (Disable to stop intercepting this URL)
                            </label>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800 flex justify-end space-x-2">
                        <button type="button" @click="closeModal()" class="px-4 py-2 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition" x-text="isEditing ? 'Save Changes' : 'Create Rule'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import CSV Modal -->
    <div 
        x-show="importModal" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title" 
        role="dialog" 
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="importModal" @click="importModal = false" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div 
                x-show="importModal" 
                class="inline-block align-bottom bg-white dark:bg-slate-900 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-200 dark:border-slate-800"
            >
                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Bulk Import Redirect Rules (CSV)</h3>
                    <button type="button" @click="importModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.redirects.import') }}" enctype="multipart/form-data" class="space-y-4 p-6">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Upload CSV File</label>
                        <input 
                            type="file" 
                            name="csv_file" 
                            accept=".csv,text/csv" 
                            required 
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 dark:file:bg-indigo-950 dark:file:text-indigo-300"
                        >
                        <div class="mt-2 text-[11px] text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-lg border border-slate-200 dark:border-slate-700 font-mono">
                            <div>Expected Headers: <code>source_path, target_path, status_code</code></div>
                            <div class="text-[10px] text-slate-400 mt-1 font-sans">Example row: <code>/old-winter-jacket, /p/winter-jacket-2026, 301</code></div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="importModal = false" class="px-4 py-2 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                            Start Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
function redirectManager() {
    return {
        modalOpen: false,
        importModal: false,
        isEditing: false,
        formUrl: '{{ route("admin.redirects.store") }}',
        form: {
            id: null,
            source_path: '',
            target_path: '',
            status_code: '301',
            is_active: true
        },
        openCreateModal() {
            this.isEditing = false;
            this.formUrl = '{{ route("admin.redirects.store") }}';
            this.form = {
                id: null,
                source_path: '',
                target_path: '',
                status_code: '301',
                is_active: true
            };
            this.modalOpen = true;
        },
        openEditModal(rule) {
            this.isEditing = true;
            this.formUrl = `/admin/redirects/${rule.id}`;
            this.form = {
                id: rule.id,
                source_path: rule.source_path,
                target_path: rule.target_path,
                status_code: String(rule.status_code || '301'),
                is_active: Boolean(rule.is_active)
            };
            this.modalOpen = true;
        },
        closeModal() {
            this.modalOpen = false;
        }
    }
}
</script>
@endsection
