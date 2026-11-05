@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Banners & Visual Merchandising'])

@section('content')
<div class="space-y-6" x-data="bannerManager()">

    <!-- Page Header -->
    <x-page-header 
        title="Homepage &amp; Promotional Banners" 
        subtitle="Manage storefront promotional carousels, category strips, deal highlights, and scheduling."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Banners' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                {{ $metrics['total_banners'] }} Banners
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <button 
                    @click="openModal()" 
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm flex items-center space-x-1.5 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Create Banner</span>
                </button>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Metrics Strip -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Banners</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $metrics['total_banners'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Active Live</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $metrics['active_banners'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Hero Carousel</div>
            <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ $metrics['hero_banners'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-amber-600 dark:text-amber-400">Deal Banners</div>
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $metrics['deal_banners'] }}</div>
        </div>
    </div>

    <!-- Filters Strip -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row gap-4 justify-between items-center">
        <div class="flex flex-wrap items-center gap-2">
            @php $currentPos = request('position', 'all'); @endphp
            @foreach(['all' => 'All Positions', 'hero' => 'Hero Carousel', 'deal_banner' => 'Deal Banners', 'category_strip' => 'Category Strips', 'sidebar' => 'Sidebar'] as $posKey => $posLabel)
                <a href="{{ request()->fullUrlWithQuery(['position' => $posKey, 'page' => 1]) }}" 
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $currentPos === $posKey ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                    {{ $posLabel }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.banners.index') }}" class="flex items-center space-x-2 w-full md:w-auto">
            @if(request('position'))
                <input type="hidden" name="position" value="{{ request('position') }}">
            @endif
            <div class="relative w-full md:w-64">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ request('search') }}" 
                    placeholder="Search banner title..." 
                    class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white pl-8 pr-3 py-2 focus:ring-1 focus:ring-indigo-500"
                >
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <button type="submit" class="px-3 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold transition">
                Filter
            </button>
        </form>
    </div>

    <!-- Banners Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Preview &amp; Title</th>
                        <th class="px-6 py-3.5">Position</th>
                        <th class="px-6 py-3.5">Destination Link</th>
                        <th class="px-6 py-3.5">Sort Order</th>
                        <th class="px-6 py-3.5">Schedule</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($banners as $b)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-20 h-10 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden flex items-center justify-center shrink-0">
                                    @if($b->image_url)
                                        <img src="{{ $b->image_url }}" alt="{{ $b->title }}" class="w-full h-full object-cover">
                                    @else
                                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">{{ $b->title }}</div>
                                    <div class="text-[10px] text-slate-400">ID: #{{ $b->id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider
                                {{ $b->position === 'hero' ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800' : '' }}
                                {{ $b->position === 'deal_banner' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800' : '' }}
                                {{ $b->position === 'category_strip' ? 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-400 border border-purple-200 dark:border-purple-800' : '' }}
                                {{ $b->position === 'sidebar' ? 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300' : '' }}
                            ">
                                {{ str_replace('_', ' ', $b->position) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($b->link)
                                <a href="{{ $b->link }}" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center space-x-1 max-w-xs truncate">
                                    <span>{{ $b->link }}</span>
                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 font-mono font-semibold text-slate-700 dark:text-slate-300">
                            {{ $b->sort ?? 0 }}
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400">
                            @if($b->starts_at || $b->ends_at)
                                <div>From: {{ $b->starts_at ? $b->starts_at->format('d M Y') : 'Immediate' }}</div>
                                <div>To: {{ $b->ends_at ? $b->ends_at->format('d M Y') : 'Indefinite' }}</div>
                            @else
                                <span class="text-slate-400">Always active</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('admin.banners.toggle', $b) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold border transition
                                    {{ $b->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800' : 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700' }}">
                                    <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $b->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                    {{ $b->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end space-x-2">
                                <button 
                                    @click="editBanner(@js($b))" 
                                    class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition" 
                                    title="Edit Banner"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <form method="POST" action="{{ route('admin.banners.destroy', $b) }}" onsubmit="return confirm('Delete this banner?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            No banners found matching criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            {{ $banners->links() }}
        </div>
    </div>

    <!-- Create / Edit Banner Modal -->
    <div 
        x-show="isOpen" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto" 
        aria-labelledby="modal-title" 
        role="dialog" 
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="isOpen" @click="closeModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div 
                x-show="isOpen" 
                class="inline-block align-bottom bg-white dark:bg-slate-900 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full border border-slate-200 dark:border-slate-800"
            >
                <form :action="formUrl" method="POST" enctype="multipart/form-data">
                    @csrf
                    <template x-if="isEditing">
                        <input type="hidden" name="_method" value="PUT">
                    </template>

                    <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white" x-text="isEditing ? 'Edit Banner' : 'Create Promotional Banner'"></h3>
                        <button type="button" @click="closeModal()" class="text-slate-400 hover:text-slate-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Banner Title *</label>
                            <input type="text" name="title" x-model="form.title" required placeholder="e.g. Summer Mega Sale 50% Off" class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Position *</label>
                                <select name="position" x-model="form.position" required class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5">
                                    <option value="hero">Hero Carousel</option>
                                    <option value="deal_banner">Deal Banner</option>
                                    <option value="category_strip">Category Strip</option>
                                    <option value="sidebar">Sidebar</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Sort Priority</label>
                                <input type="number" name="sort" x-model="form.sort" min="0" placeholder="0" class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Target Destination Link (URL / Route)</label>
                            <input type="text" name="link" x-model="form.link" placeholder="e.g. /c/electronics or https://example.com" class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Banner Image File</label>
                            <input type="file" name="image" accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-950/60 dark:file:text-indigo-400">
                            <div class="text-[10px] text-slate-400">Recommended size: 1920x600 for Hero, 800x400 for Deals. Or provide URL below:</div>
                            <input type="text" name="image_url" x-model="form.image_url" placeholder="Or external Image URL..." class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2 focus:ring-1 focus:ring-indigo-500">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Starts At (Schedule)</label>
                                <input type="date" name="starts_at" x-model="form.starts_at" class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ends At (Schedule)</label>
                                <input type="date" name="ends_at" x-model="form.ends_at" class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2">
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 pt-2">
                            <input type="checkbox" name="is_active" id="banner_is_active" value="1" x-model="form.is_active" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="banner_is_active" class="text-xs font-medium text-slate-700 dark:text-slate-300">Set active immediately</label>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800 flex justify-end space-x-2">
                        <button type="button" @click="closeModal()" class="px-4 py-2 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition" x-text="isEditing ? 'Update Banner' : 'Create Banner'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
function bannerManager() {
    return {
        isOpen: false,
        isEditing: false,
        formUrl: '{{ route("admin.banners.store") }}',
        form: {
            title: '',
            position: 'hero',
            sort: 0,
            link: '',
            image_url: '',
            starts_at: '',
            ends_at: '',
            is_active: true
        },
        openModal() {
            this.isEditing = false;
            this.formUrl = '{{ route("admin.banners.store") }}';
            this.form = {
                title: '',
                position: 'hero',
                sort: 0,
                link: '',
                image_url: '',
                starts_at: '',
                ends_at: '',
                is_active: true
            };
            this.isOpen = true;
        },
        editBanner(banner) {
            this.isEditing = true;
            this.formUrl = `/admin/banners/${banner.id}`;
            this.form = {
                title: banner.title,
                position: banner.position,
                sort: banner.sort || 0,
                link: banner.link || '',
                image_url: banner.image_url || '',
                starts_at: banner.starts_at ? banner.starts_at.substring(0, 10) : '',
                ends_at: banner.ends_at ? banner.ends_at.substring(0, 10) : '',
                is_active: Boolean(banner.is_active)
            };
            this.isOpen = true;
        },
        closeModal() {
            this.isOpen = false;
        }
    }
}
</script>
@endsection
